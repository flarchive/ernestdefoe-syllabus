<?php

namespace Ernestdefoe\Syllabus\Content;

use Ernestdefoe\Syllabus\Certificate;
use Flarum\Frontend\Document;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A certificate's verification page in plain HTML — so the link works for
 * someone checking it without JavaScript — and kept out of search engines.
 */
class CertificateDocument
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $document->meta['robots'] = 'noindex, nofollow';

        $code = strtolower((string) Arr::get($request->getQueryParams(), 'code'));
        $certificate = preg_match('/^[0-9a-f]{32}$/', $code) ? Certificate::query()->where('code', $code)->first() : null;
        if (! $certificate) {
            return $document;
        }

        $document->title = $this->translator->trans('ernestdefoe-syllabus.forum.certificate.title');
        $document->content = '<h1>'.e($this->translator->trans('ernestdefoe-syllabus.forum.certificate.verified')).'</h1><p>'
            .e($this->translator->trans('ernestdefoe-syllabus.forum.certificate.verified_text', [
                '{name}' => $certificate->user_name,
                '{course}' => $certificate->course_title,
                '{date}' => $certificate->issued_at->toDateString(),
            ])).'</p>';

        return $document;
    }
}
