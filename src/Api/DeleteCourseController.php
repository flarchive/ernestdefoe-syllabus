<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificates\Design;
use Ernestdefoe\Syllabus\Certificates\Images;
use Ernestdefoe\Syllabus\Upload;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DELETE /api/syllabus/courses/{id}. Lessons, enrolments, progress and
 * submissions go with it, and so do its certificate images; certificates
 * already awarded stay valid, carrying the course title they were issued
 * with, and are drawn with the forum's design from then on.
 */
class DeleteCourseController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads, private Design $design, private Images $images)
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
        foreach (Design::IMAGES as $kind) {
            $this->images->delete($this->design->course($course)[$kind] ?? null);
        }
        $course->delete();

        return new EmptyResponse(204);
    }
}
