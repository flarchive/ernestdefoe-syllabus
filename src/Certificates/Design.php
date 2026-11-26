<?php

namespace Ernestdefoe\Syllabus\Certificates;

use Ernestdefoe\Syllabus\Course;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * What a certificate looks like: a template, a logo, a background, and the
 * signer's signature, name and title.
 *
 * The forum keeps a default design (one JSON setting) and a course may
 * override any part of it (syllabus_courses.certificate_design). A part the
 * course leaves empty falls back to the forum's, and a part the forum leaves
 * empty falls back to the forum itself: its logo, and its name as the issuer.
 *
 * Stored designs hold only allow-listed keys: the template's name, two short
 * strings, and the paths of images Images re-encoded on upload.
 */
class Design
{
    public const SETTING = 'ernestdefoe-syllabus.certificate_design';
    public const TEMPLATES = ['classic', 'modern', 'minimal', 'academic'];
    public const DEFAULT_TEMPLATE = 'classic';
    public const IMAGES = ['logo', 'background', 'signature'];
    public const TEXT_MAX = 100;

    public function __construct(private SettingsRepositoryInterface $settings, private Images $images)
    {
    }

    /** @return array<string, string> The forum's default design, as stored. */
    public function forum(): array
    {
        $design = $this->clean((string) $this->settings->get(self::SETTING));
        // Before designs existed, one setting held the line printed above "Issued by".
        $legacy = trim((string) $this->settings->get('ernestdefoe-syllabus.certificate_signature'));
        if (! isset($design['signerName']) && $legacy !== '') {
            $design['signerName'] = mb_substr($legacy, 0, self::TEXT_MAX);
        }

        return $design;
    }

    /** @return array<string, string> The course's own overrides, as stored. */
    public function course(Course $course): array
    {
        return $this->clean((string) $course->certificate_design);
    }

    public function saveForum(array $design): void
    {
        $this->settings->set(self::SETTING, $design ? json_encode($design, JSON_UNESCAPED_UNICODE) : '');
    }

    public function saveCourse(Course $course, array $design): void
    {
        $course->certificate_design = $design ? json_encode($design, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * The design a certificate is drawn with: the course's choices, then the
     * forum's, then the forum itself. `$draft` (the editor's unsaved fields)
     * wins over both, for the live preview.
     *
     * @param array<string, string> $draft
     * @param 'course'|'forum' $scope
     * @return array{template: string, signerName: string, signerTitle: string, logo: ?array{disk: string, path: string}, background: ?array{disk: string, path: string}, signature: ?array{disk: string, path: string}}
     */
    public function resolve(?Course $course, array $draft = [], string $scope = 'course'): array
    {
        $forum = $this->forum();
        $own = $course && $scope === 'course' ? $this->course($course) : [];
        if ($scope === 'forum') {
            $forum = array_merge($forum, $this->text($draft));
        } else {
            $own = array_merge($own, $this->text($draft));
        }
        $pick = fn (string $key) => ($own[$key] ?? '') !== '' ? $own[$key] : ($forum[$key] ?? '');

        $image = function (string $key) use ($pick): ?array {
            $path = $pick($key);

            return $path !== '' ? ['disk' => Images::DISK, 'path' => $path] : null;
        };

        $logo = $image('logo');
        if (! $logo && ($path = (string) $this->settings->get('logo_path')) !== '' && $this->images->isRaster($path)) {
            $logo = ['disk' => 'flarum-assets', 'path' => $path];
        }

        return [
            'template' => $pick('template') ?: self::DEFAULT_TEMPLATE,
            'signerName' => $pick('signerName') ?: (string) $this->settings->get('forum_title'),
            'signerTitle' => $pick('signerTitle'),
            'logo' => $logo,
            'background' => $image('background'),
            'signature' => $image('signature'),
        ];
    }

    /**
     * The text parts of an editor's input, validated. An empty string means
     * "use the default"; anything else invalid is dropped.
     *
     * @return array<string, string>
     */
    public function text(array $input): array
    {
        $out = [];
        if (array_key_exists('template', $input)) {
            $template = is_string($input['template']) ? $input['template'] : '';
            $out['template'] = in_array($template, self::TEMPLATES, true) ? $template : '';
        }
        foreach (['signerName', 'signerTitle'] as $key) {
            if (array_key_exists($key, $input)) {
                $value = is_string($input[$key]) ? $input[$key] : '';
                $out[$key] = trim(mb_substr((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value), 0, self::TEXT_MAX));
            }
        }

        return $out;
    }

    /**
     * What the editor shows: the stored text and which images are set.
     *
     * @param array<string, string> $stored
     * @return array<string, mixed>
     */
    public function editable(array $stored): array
    {
        return [
            'template' => $stored['template'] ?? '',
            'signerName' => $stored['signerName'] ?? '',
            'signerTitle' => $stored['signerTitle'] ?? '',
            'hasLogo' => ($stored['logo'] ?? '') !== '',
            'hasBackground' => ($stored['background'] ?? '') !== '',
            'hasSignature' => ($stored['signature'] ?? '') !== '',
        ];
    }

    /**
     * A version of everything that changes how a certificate looks, so a
     * drawing can be cached until one of them changes.
     */
    public function fingerprint(array $resolved, array $extra): string
    {
        return substr(hash('sha256', json_encode([$resolved, $extra, Renderer::VERSION, $this->settings->get('logo_path')]) ?: ''), 0, 16);
    }

    /** @return array<string, string> */
    private function clean(string $json): array
    {
        $data = $json !== '' ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return [];
        }

        $out = array_filter($this->text($data), fn ($v) => $v !== '');
        foreach (self::IMAGES as $key) {
            $path = $data[$key] ?? null;
            if (is_string($path) && Images::validPath($path, $key)) {
                $out[$key] = $path;
            }
        }

        return $out;
    }
}
