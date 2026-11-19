<?php

namespace Ernestdefoe\Syllabus\Certificates;

/**
 * Paints a Layout as SVG: what the certificate page shows, prints and
 * downloads. Text stays text, in its logical order, so the browser shapes and
 * orders every script itself (Arabic, Hebrew, Chinese, Thai, Indic…), with
 * the template's webfonts, Vazirmatn for Arabic script when the text needs
 * it, and the visitor's own fonts for the rest. Images are embedded as data
 * URIs (the bytes Images re-encoded), so the SVG stands alone.
 *
 * Every piece of text is XML-escaped; nothing else in it comes from members.
 */
class SvgPainter
{
    /**
     * @param list<array<int, mixed>> $ops
     * @param array<string, ?string> $images raw bytes by key
     * @param callable(string): string $fontUrl a web font file name to its address
     */
    public function paint(array $ops, array $images, callable $fontUrl, string $label): string
    {
        $body = '';
        $faces = [];
        $arabic = false;
        foreach ($ops as $op) {
            $body .= match ($op[0]) {
                'rect' => sprintf('<rect x="%s" y="%s" width="%s" height="%s" fill="%s"/>', $this->n($op[1]), $this->n($op[2]), $this->n($op[3]), $this->n($op[4]), $op[5]),
                'poly' => sprintf('<polygon points="%s" fill="%s"/>', implode(' ', array_map(fn ($v) => $this->n($v), $op[1])), $op[2]),
                'disc' => sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"/>', $this->n($op[1]), $this->n($op[2]), $this->n($op[3]), $op[4]),
                'background' => $this->image($images['background'] ?? null, 0, 0, Layout::WIDTH, Layout::HEIGHT, 'xMidYMid slice'),
                'image' => $this->image($images[$op[1]] ?? null, $op[2], $op[3], $op[4], $op[5], 'none'),
                'text' => $this->text(...array_slice($op, 1)),
                default => '',
            };
            if ($op[0] === 'text') {
                $faces[$op[2]] = true;
                $arabic = $arabic || preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $op[1]);
            }
        }

        $css = '';
        foreach (array_keys($faces) as $font) {
            [$family, $weight, $style, $file] = Fonts::WEB[$font];
            $css .= "@font-face{font-family:$family;font-weight:$weight;font-style:$style;font-display:block;src:url('".$this->attr($fontUrl($file))."') format('woff2')}";
        }
        if ($arabic) {
            [$family, $file, $range] = Fonts::ARABIC;
            $css .= "@font-face{font-family:$family;font-weight:100 900;font-display:block;src:url('".$this->attr($fontUrl($file))."') format('woff2');unicode-range:$range}";
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.Layout::WIDTH.' '.Layout::HEIGHT.'" width="'.Layout::WIDTH.'" height="'.Layout::HEIGHT.'" role="img" aria-label="'.$this->attr($label).'">'
            .'<style>'.$css.'</style>'.$body.'</svg>';
    }

    private function text(string $text, string $font, float $size, float $x, float $y, string $anchor, string $color, int $rotate): string
    {
        [$family, $weight, $style] = Fonts::WEB[$font];
        $generic = str_starts_with($font, 'Lato') ? 'sans-serif' : 'serif';
        $attrs = sprintf(
            'x="%s" y="%s" font-family="%s, SylVazirmatn, %s" font-weight="%d" font-style="%s" font-size="%s" fill="%s"',
            $this->n($x), $this->n($y), $family, $generic, $weight, $style, $this->n($size), $color
        );
        // Layout's anchor reads left to right; in a right-to-left line the start is on the right.
        if (Shaper::isRtl($text)) {
            $anchor = ['start' => 'end', 'end' => 'start'][$anchor] ?? $anchor;
            $attrs .= ' direction="rtl" unicode-bidi="embed"';
        }
        $attrs .= ' text-anchor="'.$anchor.'"';
        if ($rotate) {
            $attrs .= sprintf(' transform="rotate(%d %s %s)"', -$rotate, $this->n($x), $this->n($y));
        }

        return '<text '.$attrs.' xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text>';
    }

    private function image(?string $bytes, float $x, float $y, float $w, float $h, string $fit): string
    {
        if ($bytes === null || $bytes === '') {
            return '';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return '';
        }

        return sprintf(
            '<image x="%s" y="%s" width="%s" height="%s" preserveAspectRatio="%s" href="data:%s;base64,%s"/>',
            $this->n($x), $this->n($y), $this->n($w), $this->n($h), $fit, $mime, base64_encode($bytes)
        );
    }

    private function n(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    private function attr(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
