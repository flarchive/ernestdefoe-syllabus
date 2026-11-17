<?php

namespace Ernestdefoe\Syllabus\Api;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\CourseSaver;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Presenter;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/syllabus/courses (create) and PATCH /api/syllabus/courses/{id} (update). */
class SaveCourseController implements RequestHandlerInterface
{
    use Concerns;

    /** New courses one instructor may start in an hour. */
    public const HOURLY = 10;

    public function __construct(private CourseSaver $saver, private Presenter $presenter, private Outline $outline)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $creating = $request->getMethod() === 'POST';

        if ($creating) {
            if (! Access::canCreate($actor)) {
                throw new PermissionDeniedException();
            }
            if (! Access::isManager($actor)
                && Course::query()->where('user_id', $actor->id)->where('created_at', '>', Carbon::now()->subHour())->count() >= self::HOURLY) {
                $this->invalid('title', 'slow_down');
            }
            $course = null;
        } else {
            $course = $this->editableCourse($request, $actor);
        }

        $course = $this->saver->save($course, $this->body($request), $actor);
        $course->load(['user', 'category', 'instructors', 'groups']);

        $state = $this->outline->build($course, $actor);
        $data = $this->presenter->course($state, $actor);
        $data['edit'] = $this->presenter->editable($course);

        return new JsonResponse(['data' => $data], $creating ? 201 : 200);
    }
}
