<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Brainstorm;
use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/courses/{id}/students — a course's students and how far
 * each has got, for its instructors and managers. With Brainstorm, each quiz
 * lesson's pass count among them. A fixed number of queries per page.
 */
class StudentsController implements RequestHandlerInterface
{
    use Concerns;

    public const PAGE = 50;

    public function __construct(private Presenter $presenter, private Outline $outline, private Brainstorm $brainstorm, private ConnectionInterface $db)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->editableCourse($request, $actor);
        $offset = max(0, (int) Arr::get($request->getQueryParams(), 'offset', 0));

        $enrolments = Enrolment::query()->where('course_id', $course->id)->with('user')
            ->orderByDesc('last_seen_at')->orderByDesc('id')->skip($offset)->take(self::PAGE + 1)->get();
        $more = $enrolments->count() > self::PAGE;
        $enrolments = $enrolments->take(self::PAGE);

        $total = $this->outline->totals([$course->id])[$course->id] ?? 0;
        $countable = $this->outline->countable()->where('course_id', $course->id)->select('id');
        $done = $enrolments->isEmpty() ? [] : $this->db->table('syllabus_progress')
            ->where('course_id', $course->id)
            ->whereIn('user_id', $enrolments->pluck('user_id')->all())
            ->whereIn('lesson_id', $countable->getQuery())
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as aggregate')->pluck('aggregate', 'user_id')->all();

        $quizzes = [];
        if ($this->brainstorm->enabled()) {
            $lessons = $this->outline->countable()->where('course_id', $course->id)->where('type', 'quiz')->whereNotNull('quiz_id')->orderBy('position')->get(['id', 'title', 'quiz_id']);
            $info = $this->brainstorm->quizzes($lessons->pluck('quiz_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
            $passes = $lessons->isEmpty() ? [] : $this->db->table('quiz_bests')
                ->whereIn('quiz_id', $lessons->pluck('quiz_id')->all())
                ->where('passed', true)
                ->whereIn('user_id', fn ($q) => $q->select('user_id')->from('syllabus_enrolments')->where('course_id', $course->id))
                ->groupBy('quiz_id')->selectRaw('quiz_id, COUNT(*) as aggregate')->pluck('aggregate', 'quiz_id')->all();

            foreach ($lessons as $lesson) {
                $quiz = $info[$lesson->quiz_id] ?? null;
                $quizzes[] = [
                    'lessonId' => $lesson->id,
                    'lessonTitle' => $lesson->title,
                    'quizId' => $lesson->quiz_id,
                    'quizSlug' => $quiz['slug'] ?? '',
                    'quizTitle' => $quiz['title'] ?? null,
                    'passed' => (int) ($passes[$lesson->quiz_id] ?? 0),
                ];
            }
        }

        return new JsonResponse([
            'data' => $enrolments->map(fn (Enrolment $e) => [
                'user' => $this->presenter->person($e->user),
                'enrolledAt' => $e->created_at->toIso8601String(),
                'lastSeenAt' => $e->last_seen_at?->toIso8601String(),
                'completedAt' => $e->completed_at?->toIso8601String(),
                'done' => (int) ($done[$e->user_id] ?? 0),
                'percent' => $total > 0 ? (int) min(100, floor(((int) ($done[$e->user_id] ?? 0)) * 100 / $total)) : 0,
            ])->values()->all(),
            'meta' => [
                'total' => $total,
                'enrolled' => $course->enrolment_count,
                'next' => $more ? $offset + self::PAGE : null,
                'quizzes' => $quizzes,
            ],
        ]);
    }
}
