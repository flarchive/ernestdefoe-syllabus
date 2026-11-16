<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Upload;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DELETE /api/syllabus/courses/{id}. Lessons, enrolments, progress and
 * submissions go with it; certificates already awarded stay valid, carrying
 * the course title they were issued with.
 */
class DeleteCourseController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->editableCourse($request, $actor);

        foreach (Upload::query()->where('course_id', $course->id)->get() as $upload) {
            $this->uploads->delete($upload);
        }
        $this->uploads->deleteCover($course->cover_path);
        $course->delete();

        return new EmptyResponse(204);
    }
}
