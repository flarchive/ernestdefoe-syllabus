<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Uploads;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/syllabus/courses/{id}/cover (multipart `cover`) and DELETE to remove it. */
class CoverController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Uploads $uploads)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = $this->editableCourse($request, $actor);
        $old = $course->cover_path;

        if ($request->getMethod() === 'DELETE') {
            $course->cover_path = null;
            $course->cover_url = null;
        } else {
            $file = $request->getUploadedFiles()['cover'] ?? null;
            if (! $file instanceof \Psr\Http\Message\UploadedFileInterface) {
                $this->invalid('cover', 'upload_failed');
            }
            $course->cover_path = $this->uploads->storeCover($file);
            $course->cover_url = null;
        }

        $course->save();
        if ($old && $old !== $course->cover_path) {
            $this->uploads->deleteCover($old);
        }

        return new JsonResponse(['data' => ['coverUrl' => $this->uploads->coverUrl($course)]]);
    }
}
