<?php

namespace Ernestdefoe\Syllabus\Api;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Notification\AssignmentReviewedBlueprint;
use Ernestdefoe\Syllabus\Presenter;
use Ernestdefoe\Syllabus\Progress;
use Ernestdefoe\Syllabus\Submission;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/submissions — assignments to review, from the courses the
 * actor teaches (all courses for a manager). `filter[status]` (default
 * pending), `filter[course]`.
 *
 * PATCH /api/syllabus/submissions/{id} — the review: `status` passed or
 * needs_work, and `feedback`. Passing completes the lesson for the student.
 *
 * 🚨 Both answer only for courses the actor may edit: a student never sees
 * another student's work, and an instructor never sees another course's.
 */
class SubmissionsController implements RequestHandlerInterface
{
    use Concerns;

    public const PAGE = 20;

    public function __construct(private Presenter $presenter, private Progress $progress, private NotificationSyncer $notifications)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        if (! Access::canCreate($actor)) {
            throw new PermissionDeniedException();
        }

        return $request->getMethod() === 'PATCH' ? $this->review($request) : $this->list($request);
    }

    private function list(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $filter = (array) Arr::get($params, 'filter', []);
        $status = in_array($filter['status'] ?? null, ['pending', 'passed', 'needs_work', 'all'], true) ? $filter['status'] : 'pending';

        $query = Submission::query()->with(['user', 'reviewer', 'files', 'lesson', 'course']);
        if (! Access::isManager($actor)) {
            $query->whereIn('course_id', Course::query()->taughtBy($actor)->select('id'));
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (is_numeric($filter['course'] ?? null)) {
            $query->where('course_id', (int) $filter['course']);
        }
        $status === 'pending' ? $query->orderBy('created_at')->orderBy('id') : $query->orderByDesc('id');

        $offset = max(0, (int) Arr::get($params, 'page.offset', 0));
        $rows = $query->skip($offset)->take(self::PAGE + 1)->get();

        return new JsonResponse([
            'data' => $rows->take(self::PAGE)->map(fn (Submission $s) => $this->presenter->submission($s, true))->values()->all(),
            'meta' => ['next' => $rows->count() > self::PAGE ? $offset + self::PAGE : null],
        ]);
    }

    private function review(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $submission = Submission::query()->with(['user', 'files', 'lesson', 'course.instructors'])->find($this->routeId($request));

        // The same 404 whether it is missing or someone else's course.
        if (! $submission || ! Access::canEdit($actor, $submission->course)) {
            $this->notFound();
        }

        $data = $this->body($request);
        $status = $data['status'] ?? null;
        if (! in_array($status, ['passed', 'needs_work'], true)) {
            $this->invalid('status', 'invalid');
        }
        $feedback = trim(mb_substr((string) (is_string($data['feedback'] ?? null) ? $data['feedback'] : ''), 0, 5000));

        $was = $submission->status;
        $submission->status = $status;
        $submission->feedback = $feedback === '' ? null : $feedback;
        $submission->reviewer_id = $actor->id;
        $submission->reviewed_at = Carbon::now();
        $submission->save();
        $submission->setRelation('reviewer', $actor);

        if ($submission->user) {
            $submission->lesson->setRelation('course', $submission->course);
            if ($status === 'passed') {
                $this->progress->complete($submission->lesson, $submission->user);
            } elseif ($was === 'passed') {
                $this->progress->uncomplete($submission->lesson, $submission->user);
            }

            if ($submission->user->id !== $actor->id) {
                $this->notifications->sync(new AssignmentReviewedBlueprint($submission, $actor), [$submission->user]);
            }
        }

        return new JsonResponse(['data' => $this->presenter->submission($submission, true)]);
    }
}
