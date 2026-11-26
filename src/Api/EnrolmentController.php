<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Enrolment;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Ernestdefoe\Syllabus\Progress;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/syllabus/courses/{id}/enrolment to enrol, DELETE to leave.
 * Leaving keeps the lessons already completed: enrolling again picks up
 * where the member left off.
 */
class EnrolmentController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Outline $outline, private Presenter $presenter, private Progress $progress)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->visibleCourse($request, $actor);
        $actor->assertRegistered();

        $existing = Enrolment::query()->where('course_id', $course->id)->where('user_id', $actor->id)->first();

        if ($request->getMethod() === 'DELETE') {
            if ($existing) {
                $existing->delete();
                $course->newQuery()->whereKey($course->id)->where('enrolment_count', '>', 0)->decrement('enrolment_count');
            }
        } elseif (! $existing) {
            $state = $this->outline->build($course, $actor, null);
            if (! $state->canEnrol) {
                throw new PermissionDeniedException();
            }

            $this->progress->enrol($course, $actor);

            // Lessons completed before (an earlier enrolment) may already finish it.
            $this->progress->checkCourse($course, $actor);
        }

        $course->refresh();
        $course->load(['user', 'category', 'instructors', 'groups']);

        return new JsonResponse(['data' => $this->presenter->course($this->outline->build($course, $actor), $actor)]);
    }
}
