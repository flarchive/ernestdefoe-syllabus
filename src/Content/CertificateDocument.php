<?php

namespace Ernestdefoe\Syllabus\Content;

use Ernestdefoe\Syllabus\Certificate;
use Flarum\Frontend\Document;
use Ernestdefoe\Syllabus\Certificates\Drawing;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A certificate's verification page in plain HTML — so the link works for
 * someone checking it without JavaScript — and kept out of search engines.
 * Its drawing is the share image, so a shared link previews the certificate.
 */
class CertificateDocument
{
    public function __construct(private TranslatorInterface $translator, private Drawing $drawing)
    {
    }

    public function __invoke(Document $document, Request $request): Document
    {
        $document->meta['robots'] = 'noindex, nofollow';

        $code = strtolower((string) Arr::get($request->getQueryParams(), 'code'));
        $certificate = preg_match('/^[0-9a-f]{32}$/', $code) ? Certificate::query()->with('course')->where('code', $code)->first() : null;
        if (! $certificate) {
            return $document;
        }

        $title = $this->translator->trans('ernestdefoe-syllabus.forum.certificate.share_title', ['{name}' => $certificate->user_name, '{course}' => $certificate->course_title]);
        $image = $this->drawing->url($certificate);
        $document->title = $this->translator->trans('ernestdefoe-syllabus.forum.certificate.title');
        foreach (['og:title' => $title, 'og:type' => 'website', 'og:image' => $image, 'og:image:width' => '2400', 'og:image:height' => '1697', 'og:image:alt' => $title, 'twitter:card' => 'summary_large_image', 'twitter:image' => $image] as $property => $value) {
            $attribute = str_starts_with($property, 'twitter:') ? 'name' : 'property';
            $document->head[] = '<meta '.$attribute.'="'.$property.'" content="'.e($value).'">';
        }
        $document->content = '<h1>'.e($this->translator->trans('ernestdefoe-syllabus.forum.certificate.verified')).'</h1><p>'
            .e($this->translator->trans('ernestdefoe-syllabus.forum.certificate.verified_text', [
                '{name}' => $certificate->user_name,
                '{course}' => $certificate->course_title,
                '{date}' => $certificate->issued_at->toDateString(),
            ])).'</p><p><img src="'.e($image).'" alt="'.e($title).'" style="max-width:100%;height:auto"></p>';

        return $document;
    }
}
