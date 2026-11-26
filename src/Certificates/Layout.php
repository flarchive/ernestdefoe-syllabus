<?php

namespace Ernestdefoe\Syllabus\Certificates;

/**
 * The four certificate templates, laid out once on a 2400 × 1697 grid (A4
 * landscape) as a list of drawing operations. GdPainter turns the list into
 * the JPEG used as the share image; SvgPainter turns the same list into the
 * SVG the certificate page shows, prints and downloads. One layout, two
 * backends, so the two can't drift apart.
 *
 * Text is measured with the TrueType fonts GD draws with. Each text operation
 * carries its anchor (start, middle or end of the line, read left to right),
 * so the browser, which shapes every script itself, lines up on the same
 * points even where its glyph widths differ.
 *
 * Operations:
 *   ['rect', x, y, w, h, color]          ['poly', [x, y, …], color]
 *   ['disc', cx, cy, r, color]            ['background']
 *   ['image', key, x, y, w, h]
 *   ['text', text, font, size, x, y, anchor, color, rotate]
 */
class Layout
{
    public const WIDTH = 2400;
    public const HEIGHT = 1697;

    /** @var list<array<int, mixed>> */
    private array $ops = [];
    /** The text column's width in left-aligned templates: right-to-left lines align to its right edge. */
    private float $col = 0;
    /** @var array<string, array{0: int, 1: int}> image sizes by key */
    private array $sizes = [];

    /**
     * @param array{template: string, signerName: string, signerTitle: string} $design
     * @param array<string, ?string> $images raw bytes for logo, background, signature
     * @param array<string, string> $words
     * @param bool $forImage the server image: text GD can't draw is replaced by $words['unsupported']
     * @return list<array<int, mixed>>
     */
    public function build(array $design, array $images, array $words, bool $forImage): array
    {
        $this->ops = [];
        $this->sizes = [];
        foreach (['logo', 'signature', 'background'] as $key) {
            $size = ($images[$key] ?? '') !== '' ? @getimagesizefromstring((string) $images[$key]) : false;
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $this->sizes[$key] = [$size[0], $size[1]];
            }
        }

        if ($forImage) {
            // A pointer to the verification page, which shows it as text, rather than empty boxes.
            foreach ($words as $key => $value) {
                if (Shaper::unsupported($value)) {
                    $words[$key] = $words['unsupported'] ?? '';
                }
            }
            foreach (['signerName', 'signerTitle'] as $key) {
                if (Shaper::unsupported($design[$key])) {
                    $design[$key] = $key === 'signerName' ? ($words['unsupported'] ?? '') : '';
                }
            }
        }

        $this->col = match ($design['template']) {
            'modern' => 1650,
            'academic' => 1480,
            default => 0,
        };
        $bg = isset($this->sizes['background']);
        match ($design['template']) {
            'modern' => $this->modern($design, $words, $bg),
            'minimal' => $this->minimal($design, $words, $bg),
            'academic' => $this->academic($design, $words, $bg),
            default => $this->classic($design, $words, $bg),
        };

        return $this->ops;
    }

    // Templates ------------------------------------------------------------

    private function classic(array $d, array $w, bool $bg): void
    {
        $gold = '#B08D3C';
        $navy = '#1F2A44';
        $grey = '#5B5B5B';
        if (! $this->paper($bg, '#FBF8F1')) {
            $this->frame(70, 10, $gold);
            $this->frame(102, 3, $gold);
            foreach ([[102, 102], [2298, 102], [102, 1595], [2298, 1595]] as [$x, $y]) {
                $this->poly([$x, $y - 22, $x + 22, $y, $x, $y + 22, $x - 22, $y], $gold);
            }
        }

        $this->picture('logo', 900, 165, 600, 150, 'center');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 30, 1200, 410, $gold, 9, 'center');
        $this->text($w['presented'], 'CrimsonText-Italic', 42, 1200, 515, $grey, 'center');
        $this->fitted($w['name'], 'GreatVibes-Regular', 150, 70, 1200, 700, 1750, $navy, 'center');
        $this->text($w['completed'], 'CrimsonText-Italic', 42, 1200, 805, $grey, 'center');
        $y = $this->lines($w['course'], 'CrimsonText-SemiBold', 68, 1200, 905, 1750, 3, 84, $navy, 'center');
        $this->lines($w['text'], 'CrimsonText-Regular', 36, 1200, $y + 85, 1500, 3, 50, '#444444', 'center');

        $this->seal(1200, 1395, 92, $gold, '#FBF8F1');
        $this->star(1200, 1398, 46, '#FBF8F1');
        $this->dateBlock($w, 640, 1420, 520, 'CrimsonText-Regular', 'center');
        $this->signerBlock($d, $w, 1760, 1420, 520, 'center');
        $this->text($w['verify'], 'Lato-Regular', 22, 1200, 1560, '#8A8A8A', 'center');
    }

    private function modern(array $d, array $w, bool $bg): void
    {
        $teal = '#0F766E';
        if (! $this->paper($bg, '#FFFFFF')) {
            $this->rect(0, 0, 470, self::HEIGHT, $teal);
            $this->rect(470, 0, 12, self::HEIGHT, '#14B8A6');
            $this->rotated(mb_strtoupper($w['kicker']), 'Lato-Bold', 64, 290, 1560, '#FFFFFF', 12, 1420);
        }

        $x = 600;
        $this->picture('logo', $x, 140, 520, 130, 'left');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 30, $x, 460, $teal, 6, 'left');
        $this->fitted($w['name'], 'Lato-Bold', 112, 56, $x, 630, 1650, '#111111', 'left');
        $this->text($w['completed'], 'Lato-Regular', 38, $x, 720, '#666666', 'left');
        $y = $this->lines($w['course'], 'Lato-Bold', 64, $x, 830, 1650, 3, 78, $teal, 'left');
        $this->lines($w['text'], 'Lato-Regular', 34, $x, $y + 80, 1600, 3, 48, '#555555', 'left');

        $this->dateBlock($w, $x, 1420, 460, 'Lato-Regular', 'left');
        $this->signerBlock($d, $w, 1240, 1420, 600, 'left');
        $this->text($w['verify'], 'Lato-Regular', 22, $x, 1590, '#999999', 'left');
    }

    private function minimal(array $d, array $w, bool $bg): void
    {
        $ink = '#1A1A1A';
        if (! $this->paper($bg, '#FFFFFF')) {
            $this->frame(80, 3, $ink);
        }

        $this->picture('logo', 950, 180, 500, 120, 'center');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 28, 1200, 420, $ink, 11, 'center');
        $this->text($w['presented'], 'Lato-Regular', 34, 1200, 520, '#666666', 'center');
        $this->fitted($w['name'], 'CrimsonText-SemiBold', 130, 60, 1200, 670, 1750, '#111111', 'center');
        // Clear of descenders, Arabic script's included.
        $this->rect(1140, 742, 120, 4, $ink);
        $this->text($w['completed'], 'Lato-Regular', 36, 1200, 822, '#555555', 'center');
        $y = $this->lines($w['course'], 'Lato-Bold', 60, 1200, 912, 1750, 3, 74, '#111111', 'center');
        $this->lines($w['text'], 'Lato-Regular', 32, 1200, $y + 80, 1500, 3, 46, '#555555', 'center');

        $this->dateBlock($w, 680, 1420, 480, 'Lato-Regular', 'center');
        $this->signerBlock($d, $w, 1720, 1420, 480, 'center');
        $this->text($w['verify'], 'Lato-Regular', 22, 1200, 1560, '#9A9A9A', 'center');
    }

    private function academic(array $d, array $w, bool $bg): void
    {
        $navy = '#1F3A68';
        if (! $this->paper($bg, '#FFFFFF')) {
            $this->rect(1760, 0, 640, self::HEIGHT, '#F2F4F7');
            $this->rect(0, 0, 1760, 14, $navy);
        }
        // The seal stays on a background too: it carries "Course certificate".
        $this->ribbon(2080, 640, $navy);
        $this->seal(2080, 560, 175, $navy, '#FFFFFF');
        $this->spaced(mb_strtoupper($w['seal1']), 'Lato-Bold', 30, 2080, 545, '#FFFFFF', 4, 'center');
        $this->spaced(mb_strtoupper($w['seal2']), 'Lato-Bold', 30, 2080, 595, '#FFFFFF', 4, 'center');

        $x = 160;
        $this->picture('logo', $x, 140, 520, 130, 'left');
        $this->text($w['date'], 'Lato-Regular', 30, $x, 350, '#555555', 'left');
        $this->fitted($w['name'], 'CrimsonText-SemiBold', 110, 56, $x, 700, 1480, '#111111', 'left');
        $this->text($w['completed'], 'Lato-Regular', 36, $x, 790, '#555555', 'left');
        $y = $this->lines($w['course'], 'Lato-Bold', 64, $x, 900, 1480, 3, 78, $navy, 'left');
        $this->lines($w['text'], 'Lato-Regular', 32, $x, $y + 80, 1450, 3, 46, '#555555', 'left');

        $this->signerBlock($d, $w, $x, 1420, 560, 'left');
        $this->text($w['verify'], 'Lato-Regular', 22, $x, 1590, '#888888', 'left');
    }

    // Blocks ----------------------------------------------------------------

    private function dateBlock(array $w, int $x, int $lineY, int $width, string $font, string $align): void
    {
        $this->text($w['date'], $font, 38, $x, $lineY - 22, '#222222', $align, $width);
        $this->rect($align === 'center' ? $x - intdiv($width, 2) : $x, $lineY, $width, 2, '#9A9A9A');
        $this->text($w['dateLabel'], 'Lato-Regular', 24, $x, $lineY + 45, '#777777', $align, $width);
    }

    private function signerBlock(array $d, array $w, int $x, int $lineY, int $width, string $align): void
    {
        $left = $align === 'center' ? $x - intdiv($width, 2) : $x;
        $this->picture('signature', $left, $lineY - 150, $width, 140, $align === 'center' ? 'center' : 'left', true);
        $this->rect($left, $lineY, $width, 2, '#9A9A9A');
        $this->fitted($d['signerName'], 'Lato-Bold', 28, 18, $x, $lineY + 45, $width, '#222222', $align);
        $this->fitted($d['signerTitle'] !== '' ? $d['signerTitle'] : $w['issuedBy'], 'Lato-Regular', 24, 16, $x, $lineY + 85, $width, '#777777', $align);
    }

    // Shapes ----------------------------------------------------------------

    /** The uploaded background replaces the paper (and the template's frame), or the paper color fills the page. */
    private function paper(bool $bg, string $color): bool
    {
        $this->ops[] = $bg ? ['background'] : ['rect', 0, 0, self::WIDTH, self::HEIGHT, $color];

        return $bg;
    }

    /** An image contained in a box, aligned left or centered; signatures sit on the box's bottom. */
    private function picture(string $key, float $x, float $y, float $bw, float $bh, string $align, bool $bottom = false): void
    {
        if (! isset($this->sizes[$key])) {
            return;
        }
        [$iw, $ih] = $this->sizes[$key];
        $scale = min($bw / $iw, $bh / $ih);
        [$dw, $dh] = [$iw * $scale, $ih * $scale];
        $dx = $align === 'center' ? $x + ($bw - $dw) / 2 : $x;
        $dy = $bottom ? $y + $bh - $dh : $y + ($bh - $dh) / 2;
        $this->ops[] = ['image', $key, $dx, $dy, $dw, $dh];
    }

    private function rect(float $x, float $y, float $w, float $h, string $color): void
    {
        $this->ops[] = ['rect', $x, $y, $w, $h, $color];
    }

    /** @param list<float|int> $points */
    private function poly(array $points, string $color): void
    {
        $this->ops[] = ['poly', $points, $color];
    }

    private function frame(float $inset, float $stroke, string $color): void
    {
        $this->rect($inset, $inset, self::WIDTH - 2 * $inset, $stroke, $color);
        $this->rect($inset, self::HEIGHT - $inset - $stroke, self::WIDTH - 2 * $inset, $stroke, $color);
        $this->rect($inset, $inset, $stroke, self::HEIGHT - 2 * $inset, $color);
        $this->rect(self::WIDTH - $inset - $stroke, $inset, $stroke, self::HEIGHT - 2 * $inset, $color);
    }

    /** A round seal: a filled disc with an inner ring. */
    private function seal(float $x, float $y, float $r, string $fill, string $ring): void
    {
        $this->ops[] = ['disc', $x, $y, $r, $fill];
        $this->ops[] = ['disc', $x, $y, $r * 0.86, $ring];
        $this->ops[] = ['disc', $x, $y, $r * 0.82, $fill];
    }

    private function star(float $x, float $y, float $r, string $color): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $radius = $i % 2 ? $r * 0.42 : $r;
            $angle = -M_PI / 2 + $i * M_PI / 5;
            array_push($points, $x + $radius * cos($angle), $y + $radius * sin($angle));
        }
        $this->poly($points, $color);
    }

    private function ribbon(float $x, float $y, string $color): void
    {
        foreach ([-1, 1] as $side) {
            $a = $x + $side * 40;
            $this->poly([$a - 45, $y, $a + 45, $y, $a + 45 + $side * 30, $y + 260, $a + $side * 30, $y + 215, $a - 45 + $side * 30, $y + 260], $color);
        }
    }

    // Text ------------------------------------------------------------------

    /** One line. A right-to-left line in a left-aligned column starts at the column's right edge. */
    private function text(string $text, string $font, float $size, float $x, float $y, string $color, string $align, ?float $box = null, int $rotate = 0): void
    {
        if ($text === '') {
            return;
        }
        $box ??= $this->col;
        if ($align === 'left' && $box > 0 && Shaper::isRtl($text)) {
            [$align, $x] = ['right', $x + $box];
        }
        $anchor = ['center' => 'middle', 'right' => 'end'][$align] ?? 'start';
        $this->ops[] = ['text', $text, $font, $size, $x, $y, $anchor, $color, $rotate];
    }

    /** One line, shrunk until it fits the width. */
    private function fitted(string $text, string $font, float $size, float $min, float $x, float $y, float $maxWidth, string $color, string $align): void
    {
        while ($size > $min && self::width($text, $font, $size) > $maxWidth) {
            $size -= 2;
        }
        $this->text($this->clip($text, $font, $size, $maxWidth), $font, $size, $x, $y, $color, $align, $maxWidth);
    }

    /** Wrapped text, at most $max lines (the last ends in "…"); returns the last baseline. */
    private function lines(string $text, string $font, float $size, float $x, float $y, float $maxWidth, int $max, float $lineHeight, string $color, string $align): float
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return $y - $lineHeight;
        }

        $lines = $this->wrap($text, $font, $size, $maxWidth);
        // Balance the lines: the narrowest width that still needs no more of them.
        if (count($lines) > 1 && count($lines) <= $max) {
            [$lo, $hi] = [$maxWidth / 2, $maxWidth];
            while ($hi - $lo > 4) {
                $mid = ($lo + $hi) / 2;
                count($this->wrap($text, $font, $size, $mid)) <= count($lines) ? $hi = $mid : $lo = $mid;
            }
            $lines = $this->wrap($text, $font, $size, $hi);
        }

        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = $this->clip($lines[$max - 1].'…', $font, $size, $maxWidth);
        }
        foreach ($lines as $i => $one) {
            $this->text($this->clip($one, $font, $size, $maxWidth), $font, $size, $x, $y + $i * $lineHeight, $color, $align, $maxWidth);
        }

        return $y + (count($lines) - 1) * $lineHeight;
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, float $size, float $limit): array
    {
        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            $try = $line === '' ? $word : "$line $word";
            if ($line !== '' && self::width($try, $font, $size) > $limit) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;

        return $lines;
    }

    /** Letter-spaced capitals, for kickers: one operation per letter, so both backends space them alike. */
    private function spaced(string $text, string $font, float $size, float $x, float $y, string $color, float $tracking, string $align): void
    {
        if (self::joins($text)) {
            // Letter spacing would break joined letters apart: a plain line instead.
            $this->text($text, $font, $size, $x, $y, $color, $align);

            return;
        }
        $chars = mb_str_split($text);
        $widths = array_map(fn ($c) => self::width($c, $font, $size), $chars);
        $total = array_sum($widths) + $tracking * max(0, count($chars) - 1);
        $left = $align === 'center' ? $x - $total / 2 : $x;
        foreach ($chars as $i => $char) {
            if (trim($char) !== '') {
                $this->ops[] = ['text', $char, $font, $size, $left, $y, 'start', $color, 0];
            }
            $left += $widths[$i] + $tracking;
        }
    }

    /** Text running up the page from ($x, $y), letter-spaced, shrunk to $maxLength. */
    private function rotated(string $text, string $font, float $size, float $x, float $y, string $color, float $tracking, float $maxLength): void
    {
        if (self::joins($text)) {
            while ($size > 20 && self::width($text, $font, $size) > $maxLength) {
                $size -= 2;
            }
            $this->text($text, $font, $size, $x, $y, $color, 'left', 0, 90);

            return;
        }
        $chars = mb_str_split($text);
        do {
            $widths = array_map(fn ($c) => self::width($c, $font, $size), $chars);
            $total = array_sum($widths) + $tracking * max(0, count($chars) - 1);
            $size -= 2;
        } while ($total > $maxLength && $size > 20);
        $size += 2;
        $pos = $y;
        foreach ($chars as $i => $char) {
            if (trim($char) !== '') {
                $this->ops[] = ['text', $char, $font, $size, $x, $pos, 'start', $color, 90];
            }
            $pos -= $widths[$i] + $tracking;
        }
    }

    /** Cuts a line to the width, ending in "…" when it had to. */
    private function clip(string $text, string $font, float $size, float $limit): string
    {
        if (self::width($text, $font, $size) <= $limit) {
            return $text;
        }
        $chars = mb_str_split(rtrim($text, '…'));
        while ($chars && self::width(implode('', $chars).'…', $font, $size) > $limit) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)).'…';
    }

    /** Scripts whose letters join or reorder: never split into separate letters. */
    private static function joins(string $text): bool
    {
        return Shaper::visual($text) !== $text || Shaper::unsupported($text);
    }

    /** A line's width in grid pixels, measured with the font GD draws it with. */
    public static function width(string $text, string $font, float $size): float
    {
        $box = imagettfbbox($size * 0.75, 0, Fonts::file($text, $font), Shaper::visual($text));

        return $box ? (float) (max($box[2], $box[4]) - min($box[0], $box[6])) : 0.0;
    }
}
