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
 * GET /api/syllabus/certificates/{code}/svg — the certificate as SVG, for
 * anyone holding its link: the page shows, prints and downloads it, and the
 * browser shapes every script in it. It says no more than the verification
 * page does. Sent as an attachment with a locked-down CSP, so opening the
 * address directly never runs anything.
 */
class CertificateSvgController implements RequestHandlerInterface
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

        [$svg, $version] = $this->drawing->certificateSvg($certificate);
        $etag = '"svg-'.$version.'"';
        $headers = ['Cache-Control' => 'public, max-age=300', 'ETag' => $etag, 'X-Content-Type-Options' => 'nosniff'];
        if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return new EmptyResponse(304, $headers);
        }

        return self::svg($svg, $headers);
    }

    /** @param array<string, string> $headers */
    public static function svg(string $svg, array $headers): ResponseInterface
    {
        $body = new Stream('php://temp', 'wb+');
        $body->write($svg);
        $body->rewind();

        return new Response($body, 200, $headers + [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="certificate.svg"',
            'Content-Security-Policy' => "default-src 'none'; img-src data:; font-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
