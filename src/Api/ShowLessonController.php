<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Brainstorm;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Ernestdefoe\Syllabus\Progress;
use Ernestdefoe\Syllabus\Submission;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/lessons/{id} — one lesson. A locked lesson comes back
 * with its title, why it is locked and the outline, and nothing of its
 * content. `?edit=1` adds the editor's copy for those who may edit it.
 */
class ShowLessonController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(
        private Outline $outline,
        private Presenter $presenter,
        private Progress $progress,
        private Brainstorm $brainstorm
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $lesson = $this->lessonOf($request, $actor);
        $course = $lesson->course;
        $state = $this->outline->build($course, $actor);

        // A draft, or a quiz lesson while Brainstorm is off: editors only.
        $lesson = $state->lesson($lesson->id) ?? $this->notFound();
        $lesson->setRelation('course', $course);
        $unlocked = $state->unlocked($lesson);
        $extra = [];

        if ($unlocked && $state->enrolment) {
            // A quiz passed before (or outside) the course counts.
            if ($lesson->type === 'quiz' && $lesson->quiz_id && ! isset($state->completed[$lesson->id])
                && $this->brainstorm->passed($actor->id, [$lesson->quiz_id])) {
                $this->progress->complete($lesson, $actor);
                $state = $this->outline->build($course, $actor);
                $lesson = $state->lesson($lesson->id) ?? $lesson;
                $lesson->setRelation('course', $course);
            }

            $this->progress->touch($state->enrolment, $lesson);
        }

        if ($unlocked) {
            $lesson->load('videoFile');
            $lesson->setRelation('discussion', $lesson->discussion_id ? Discussion::query()->whereVisibleTo($actor)->find($lesson->discussion_id) : null);

            if ($lesson->type === 'assignment' && ! $actor->isGuest()) {
                $mine = Submission::query()->where('lesson_id', $lesson->id)->where('user_id', $actor->id)
                    ->with(['user', 'reviewer', 'files'])->orderByDesc('id')->limit(20)->get();
                $waiting = $mine->contains(fn (Submission $s) => $s->status === 'pending');
                $passed = $mine->contains(fn (Submission $s) => $s->status === 'passed');

                $extra['submissions'] = $mine->map(fn (Submission $s) => $this->presenter->submission($s))->values()->all();
                $extra['canSubmit'] = $state->enrolled() && $lesson->isPublished() && ! $waiting && ! $passed;
            }
        }

        $data = $this->presenter->lesson($lesson, $state, $actor, $extra);
        if (! empty($request->getQueryParams()['edit']) && $state->editor) {
            $lesson->loadMissing('videoFile');
            $data['edit'] = $this->presenter->lessonEditable($lesson);
        }

        return new JsonResponse(['data' => $data]);
    }
}
