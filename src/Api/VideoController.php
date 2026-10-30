<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Upload;
use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/syllabus/lessons/{id}/video (multipart `video`) uploads a lesson's video file; DELETE removes it. */
class VideoController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $lesson = $this->editableLesson($request, $actor);
        $old = $lesson->video_file_id ? Upload::query()->find($lesson->video_file_id) : null;

        if ($request->getMethod() === 'DELETE') {
            $lesson->video_file_id = null;
            $upload = null;
        } else {
            $file = $request->getUploadedFiles()['video'] ?? null;
            if (! $file instanceof UploadedFileInterface) {
                $this->invalid('video', 'upload_failed');
            }
            $upload = $this->uploads->storeVideo($file, $lesson, $actor->id);
            $lesson->video_file_id = $upload->id;
        }

        $lesson->save();
        if ($old) {
            $this->uploads->delete($old);
        }

        return new JsonResponse(['data' => ['videoFile' => $upload ? ['id' => $upload->id, 'name' => $upload->name, 'size' => $upload->size] : null]]);
    }
}
