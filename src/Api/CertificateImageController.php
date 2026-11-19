<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Certificates\Drawing;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/certificates/{code}/image — the certificate as a JPEG,
 * for anyone holding its link: the page shows it, prints it, and offers it as
 * the share image. It says no more than the verification page does.
 */
class CertificateImageController implements RequestHandlerInterface
{
    public function __construct(private Drawing $drawing)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $code = strtolower((string) Arr::get($request->getAttributes(), 'routeParameters.code'));
        $certificate = preg_match('/^[0-9a-f]{32}$/', $code) ? Certificate::query()->with('course')->where('code', $code)->first() : null;
        if (! $certificate) {
            return new EmptyResponse(404);
        }

        [$jpeg, $version] = $this->drawing->certificate($certificate);
        $etag = '"'.$version.'"';
        $headers = [
            // The page asks for ?v=<version>, which changes with the drawing.
            'Cache-Control' => ($request->getQueryParams()['v'] ?? null) === $version ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return new EmptyResponse(304, $headers);
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($jpeg);
        $body->rewind();

        $download = isset($request->getQueryParams()['download']);

        return new Response($body, 200, $headers + [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) strlen($jpeg),
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="certificate-'.$certificate->code.'.jpg"',
        ]);
    }
}
