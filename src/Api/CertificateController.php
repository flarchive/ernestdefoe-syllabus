<?php

namespace Ernestdefoe\Syllabus\Api;

use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Course;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/syllabus/certificates/{code} — a certificate by its verification
 * code, for anyone holding the link: who earned it, for which course, when
 * and from which forum. Nothing else about the member.
 */
class CertificateController implements RequestHandlerInterface
{
    public function __construct(private SettingsRepositoryInterface $settings, private UrlGenerator $url, private Factory $filesystems)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $code = strtolower((string) Arr::get($request->getAttributes(), 'routeParameters.code'));

        $certificate = preg_match('/^[0-9a-f]{32}$/', $code) ? Certificate::query()->where('code', $code)->first() : null;
        if (! $certificate) {
            return new JsonResponse(['errors' => [['status' => '404', 'code' => 'not_found']]], 404);
        }

        $course = $certificate->course_id ? Course::query()->visibleTo($actor)->find($certificate->course_id) : null;

        return new JsonResponse(['data' => [
            'code' => $certificate->code,
            'userName' => $certificate->user_name,
            'courseTitle' => $certificate->course_title,
            'issuedAt' => $certificate->issued_at->toIso8601String(),
            'course' => $course ? ['id' => $course->id, 'slug' => $course->slug] : null,
            'isOwner' => ! $actor->isGuest() && $actor->id === $certificate->user_id,
            'text' => (string) $this->settings->get('ernestdefoe-syllabus.certificate_text'),
            'signature' => (string) $this->settings->get('ernestdefoe-syllabus.certificate_signature'),
            'logoUrl' => $this->logo(),
            'verifyUrl' => $this->url->to('forum')->route('syllabus.certificate', ['code' => $certificate->code]),
        ]]);
    }

    private function logo(): ?string
    {
        $custom = (string) $this->settings->get('ernestdefoe-syllabus.certificate_logo');
        if (preg_match('~^https://[^\s"\'<>]+$~i', $custom)) {
            return $custom;
        }

        $path = $this->settings->get('logo_path');

        return $path ? $this->filesystems->disk('flarum-assets')->url($path) : null;
    }
}
