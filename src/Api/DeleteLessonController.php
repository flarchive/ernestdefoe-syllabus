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
 * DELETE /api/syllabus/lessons/{id}. Its progress rows and submissions go
 * with it; courses already completed stay completed. Its discussion stays:
 * it belongs to the forum.
 */
class DeleteLessonController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $lesson = $this->editableLesson($request, $actor);

        foreach (Upload::query()->where('lesson_id', $lesson->id)->get() as $upload) {
            $this->uploads->delete($upload);
        }
        $lesson->delete();

        return new EmptyResponse(204);
    }
}
