<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificates\Drawing;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A sample certificate drawn with the editor's unsaved choices (query
 * template, signerName, signerTitle), for the live preview: SVG with
 * ?format=svg (what the editor shows), otherwise the JPEG share image.
 *
 *   GET /api/syllabus/certificate-design/preview        admins: the forum default
 *   GET /api/syllabus/courses/{id}/certificate/preview  who may edit the course
 */
class CertificatePreviewController implements RequestHandlerInterface
{
    use Concerns;

    public function __construct(private Drawing $drawing)
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

        $draft = array_intersect_key($request->getQueryParams(), array_flip(['template', 'signerName', 'signerTitle']));
        $scope = $course ? 'course' : 'forum';
        if (($request->getQueryParams()['format'] ?? '') === 'svg') {
            return CertificateSvgController::svg($this->drawing->preview($course, $draft, $actor, $scope, true), ['Cache-Control' => 'private, no-store']);
        }
        $jpeg = $this->drawing->preview($course, $draft, $actor, $scope);

        $body = new Stream('php://temp', 'wb+');
        $body->write($jpeg);
        $body->rewind();

        return new Response($body, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($jpeg),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
