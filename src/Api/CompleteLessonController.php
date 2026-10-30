<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Progress;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/syllabus/lessons/{id}/complete — "mark complete" on a text or
 * video lesson; DELETE takes it back. Assignments complete when an
 * instructor passes them and quizzes when Brainstorm marks them passed, so
 * neither can be ticked off here.
 */
class CompleteLessonController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Outline $outline, private Progress $progress)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $lesson = $this->lessonOf($request, $actor);
        $course = $lesson->course;
        $state = $this->outline->build($course, $actor);
        $lesson = $state->lesson($lesson->id) ?? $this->notFound();
        $lesson->setRelation('course', $course);

        if (! $state->enrolled() || ! $state->unlocked($lesson) || ! $this->outline->counts($lesson) || ! in_array($lesson->type, ['text', 'video'], true)) {
            throw new PermissionDeniedException();
        }

        $certificate = null;
        if ($request->getMethod() === 'DELETE') {
            $this->progress->uncomplete($lesson, $actor);
        } else {
            $certificate = $this->progress->complete($lesson, $actor);
        }
        if ($state->enrolment) {
            $this->progress->touch($state->enrolment, $lesson);
        }

        $after = $this->outline->build($course, $actor);
        $next = $after->neighbour($lesson, 1);

        return new JsonResponse(['data' => [
            'completed' => isset($after->completed[$lesson->id]),
            'progress' => ['done' => $after->done, 'total' => $after->total, 'percent' => $after->percent()],
            'courseCompleted' => (bool) $after->enrolment?->completed_at,
            'certificateCode' => $certificate?->code,
            'nextUnlocked' => $next ? $after->unlocked($next) : null,
        ]]);
    }
}
