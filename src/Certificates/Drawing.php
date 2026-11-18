<?php

namespace Ernestdefoe\Syllabus\Certificates;

use Carbon\Carbon;
use Ernestdefoe\Syllabus\Certificate;
use Ernestdefoe\Syllabus\Course;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A certificate's drawing: its design and wording handed to Renderer, and the
 * result kept on the certificates disk until something that shows on it
 * changes (the design, the wording, the forum's logo or name).
 */
class Drawing
{
    public const PREVIEW_WIDTH = 1200;

    public function __construct(
        private Design $design,
        private Images $images,
        private Renderer $renderer,
        private SettingsRepositoryInterface $settings,
        private TranslatorInterface $translator,
        private UrlGenerator $url
    ) {
    }

    /** The drawing's version: it changes whenever anything shown on it does. */
    public function version(Certificate $certificate): string
    {
        return $this->design->fingerprint($this->design->resolve($certificate->course), $this->wordsFor($certificate));
    }

    /** The address of the drawing, versioned so a browser never shows an old one. */
    public function url(Certificate $certificate): string
    {
        return $this->url->to('api')->route('syllabus.certificates.image', ['code' => $certificate->code]).'?v='.$this->version($certificate);
    }

    /** @return array{0: string, 1: string} the JPEG and its version */
    public function certificate(Certificate $certificate): array
    {
        $design = $this->design->resolve($certificate->course);
        $words = $this->wordsFor($certificate);
        $version = $this->design->fingerprint($design, $words);

        $disk = $this->images->disk();
        $dir = 'rendered/'.$certificate->code;
        $path = "$dir/$version.jpg";
        if ($disk->exists($path)) {
            return [(string) $disk->get($path), $version];
        }

        $jpeg = $this->draw($design, $words, Renderer::WIDTH);
        $disk->delete($disk->files($dir));
        $disk->put($path, $jpeg);

        return [$jpeg, $version];
    }

    /**
     * A sample certificate for the editor: the actor's name, the course's
     * title (or a sample), today's date, and the editor's unsaved choices.
     *
     * @param 'course'|'forum' $scope
     */
    public function preview(?Course $course, array $draft, User $actor, string $scope): string
    {
        $design = $this->design->resolve($course, $draft, $scope);
        $title = $course ? $course->title : $this->translator->trans('ernestdefoe-syllabus.forum.certificate.sample_course');
        $words = $this->words((string) $actor->display_name, $title, Carbon::now(), str_repeat('0', 32));

        return $this->draw($design, $words, self::PREVIEW_WIDTH);
    }

    public function forget(string $code): void
    {
        $this->images->disk()->deleteDirectory('rendered/'.$code);
    }

    private function draw(array $design, array $words, int $width): string
    {
        $images = [];
        foreach (Design::IMAGES as $key) {
            $images[$key] = $this->images->read($design[$key]);
        }

        return $this->renderer->render($design, $images, $words, $width);
    }

    /** @return array<string, string> */
    private function wordsFor(Certificate $certificate): array
    {
        return $this->words($certificate->user_name, $certificate->course_title, $certificate->issued_at, $certificate->code);
    }

    /** @return array<string, string> */
    private function words(string $name, string $course, Carbon $issued, string $code): array
    {
        $t = fn (string $key, array $params = []) => $this->translator->trans('ernestdefoe-syllabus.forum.certificate.'.$key, $params);
        $forum = (string) $this->settings->get('forum_title');
        $date = $issued->copy()->locale($this->translator->getLocale())->isoFormat('LL');

        $custom = trim((string) $this->settings->get('ernestdefoe-syllabus.certificate_text'));
        $text = $custom !== ''
            ? strtr($custom, ['{name}' => $name, '{course}' => $course, '{date}' => $date, '{forum}' => $forum])
            : $t('default_text', ['{forum}' => $forum, '{date}' => $date]);

        return [
            'kicker' => $t('heading'),
            'presented' => $t('presented_to'),
            'name' => $name,
            'completed' => $t('for_completing'),
            'course' => $course,
            'text' => $text,
            'date' => $date,
            'dateLabel' => $t('date'),
            'issuedBy' => $t('issued_by'),
            'verify' => $t('verify_at').' '.$this->url->to('forum')->route('syllabus.certificate', ['code' => $code]),
            'seal1' => $t('seal_line1'),
            'seal2' => $t('seal_line2'),
            'unsupported' => $t('unsupported_text'),
        ];
    }
}
