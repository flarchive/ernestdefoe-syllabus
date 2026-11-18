<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificates\Design;
use Ernestdefoe\Syllabus\Certificates\Images;
use Ernestdefoe\Syllabus\Course;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The forum's default certificate design, and the images of either design.
 *
 *   GET|PATCH /api/syllabus/certificate-design                   admins
 *   POST|DELETE /api/syllabus/certificate-design/{kind}          admins
 *   POST|DELETE /api/syllabus/courses/{id}/certificate/{kind}    who may edit the course
 *
 * {kind} is logo, background or signature; POST takes multipart `image`. A
 * course's template and signer are saved with the rest of the course.
 */
class CertificateDesignController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Design $design, private Images $images)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $course = null;
        if (Arr::get($request->getAttributes(), 'routeParameters.id') !== null) {
            $course = $this->editableCourse($request, $actor);
        } else {
            $actor->assertAdmin();
        }

        $stored = $course ? $this->design->course($course) : $this->design->forum();
        $kind = (string) Arr::get($request->getAttributes(), 'routeParameters.kind', '');

        if ($kind === '') {
            if ($request->getMethod() === 'PATCH') {
                $stored = array_filter(array_merge($stored, $this->design->text($this->body($request))), fn ($v) => $v !== '');
                $this->design->saveForum($stored);
            }

            return $this->answer($stored);
        }

        $old = $stored[$kind] ?? null;
        if ($request->getMethod() === 'DELETE') {
            unset($stored[$kind]);
        } else {
            $file = $request->getUploadedFiles()['image'] ?? null;
            if (! $file instanceof UploadedFileInterface) {
                $this->invalid($kind, 'upload_failed');
            }
            $stored[$kind] = $this->images->store($file, $kind);
        }

        if ($course) {
            $this->design->saveCourse($course, $stored);
            $course->save();
        } else {
            $this->design->saveForum($stored);
        }
        if ($old && $old !== ($stored[$kind] ?? null)) {
            $this->images->delete($old);
        }

        return $this->answer($stored);
    }

    private function answer(array $stored): JsonResponse
    {
        return new JsonResponse(['data' => $this->design->editable($stored)]);
    }
}
