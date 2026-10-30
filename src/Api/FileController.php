<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Access;
use Ernestdefoe\Syllabus\Course;
use Ernestdefoe\Syllabus\Outline;
use Ernestdefoe\Syllabus\Submission;
use Ernestdefoe\Syllabus\Upload;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/files/{id} — an uploaded video or assignment file.
 *
 * 🚨 A video only to someone who may read its lesson right now; an
 * assignment file only to the student who handed it in and the course's
 * instructors. Everyone else gets the same 404 as a file that does not exist.
 */
class FileController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads, private Outline $outline)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $upload = Upload::query()->find($this->routeId($request)) ?? $this->notFound();
        $course = Course::query()->visibleTo($actor)->with(['instructors', 'groups'])->find($upload->course_id) ?? $this->notFound();

        if ($upload->kind === 'video') {
            $state = $this->outline->build($course, $actor);
            $lesson = $upload->lesson_id ? $state->lesson($upload->lesson_id) : null;
            if (! $lesson || $lesson->video_file_id !== $upload->id || ! $state->unlocked($lesson)) {
                $this->notFound();
            }
        } else {
            $owner = Submission::query()->whereKey((int) $upload->submission_id)->value('user_id');
            if (! $owner || ((int) $owner !== $actor->id && ! Access::canEdit($actor, $course))) {
                $this->notFound();
            }
        }

        return $this->uploads->response($upload, $request);
    }
}
