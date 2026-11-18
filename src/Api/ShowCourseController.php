<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/courses/{id} — the overview: description, instructors,
 * the outline with each lesson's lock and completion state, and the actor's
 * progress. `?edit=1` adds the editor's copy for those who may edit it.
 */
class ShowCourseController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Presenter $presenter, private Outline $outline)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->visibleCourse($request, $actor);
        $state = $this->outline->build($course, $actor);

        $data = $this->presenter->course($state, $actor);
        if (! empty($request->getQueryParams()['edit']) && $state->editor) {
            $data['edit'] = $this->presenter->editable($course);
        }

        return new JsonResponse(['data' => $data]);
    }
}
