<?php

namespace Ernestdefoe\Syllabus\Certificates;

use GdImage;

/**
 * Draws a certificate as a JPEG, A4 landscape.
 *
 * One drawing serves the certificate page, its print/PDF output and its
 * share image, so they can never disagree. The templates are laid out on a
 * 2400 × 1697 grid and scaled to the size asked for; their colors and fonts
 * are their own (bundled OFL fonts in resources/fonts), never the forum
 * theme's. An uploaded background replaces a template's paper and frame; its
 * text is drawn on top.
 */
class Renderer
{
    /** Bump when a template changes, so cached drawings are redrawn. */
    public const VERSION = 1;
    public const WIDTH = 2400;
    public const HEIGHT = 1697;

    private GdImage $im;
    private float $s = 1.0;

    /**
     * @param array{template: string, signerName: string, signerTitle: string} $design
     * @param array<string, ?string> $images raw bytes for logo, background, signature
     * @param array{kicker: string, presented: string, name: string, completed: string, course: string, text: string, date: string, dateLabel: string, issuedBy: string, verify: string, seal1: string, seal2: string} $words
     */
    public function render(array $design, array $images, array $words, int $width = self::WIDTH): string
    {
        $width = max(400, min(self::WIDTH, $width));
        $this->s = $width / self::WIDTH;
        $this->im = imagecreatetruecolor($width, (int) round(self::HEIGHT * $this->s));
        imagealphablending($this->im, true);
        imageantialias($this->im, true);

        $background = $this->load($images['background'] ?? null);
        match ($design['template']) {
            'modern' => $this->modern($design, $images, $words, $background),
            'minimal' => $this->minimal($design, $images, $words, $background),
            'academic' => $this->academic($design, $images, $words, $background),
            default => $this->classic($design, $images, $words, $background),
        };

        ob_start();
        imageinterlace($this->im, true);
        imagejpeg($this->im, null, 90);
        $jpeg = (string) ob_get_clean();

        return $jpeg;
    }

    // Templates ------------------------------------------------------------

    private function classic(array $d, array $img, array $w, ?GdImage $bg): void
    {
        $gold = '#B08D3C';
        $navy = '#1F2A44';
        $grey = '#5B5B5B';
        if (! $this->paper($bg, '#FBF8F1')) {
            $this->frame(70, 10, $gold);
            $this->frame(102, 3, $gold);
            foreach ([[102, 102], [2298, 102], [102, 1595], [2298, 1595]] as [$x, $y]) {
                $this->diamond($x, $y, 22, $gold);
            }
        }

        $this->picture($img['logo'] ?? null, 900, 165, 600, 150, 'center');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 30, 1200, 410, $gold, 9, 'center');
        $this->text($w['presented'], 'CrimsonText-Italic', 42, 1200, 515, $grey, 'center');
        $y = 700;
        $this->fitted($w['name'], 'GreatVibes-Regular', 150, 70, 1200, $y, 1750, $navy, 'center');
        $this->text($w['completed'], 'CrimsonText-Italic', 42, 1200, 805, $grey, 'center');
        $y = $this->lines($w['course'], 'CrimsonText-SemiBold', 68, 1200, 905, 1750, 3, 84, $navy, 'center');
        $this->lines($w['text'], 'CrimsonText-Regular', 36, 1200, $y + 85, 1500, 3, 50, '#444444', 'center');

        $this->seal(1200, 1395, 92, $gold, '#FBF8F1');
        $this->star(1200, 1398, 46, '#FBF8F1');
        $this->dateBlock($w, 640, 1420, 520, 'CrimsonText-Regular', 'center');
        $this->signerBlock($d, $img, $w, 1760, 1420, 520, 'center');
        $this->text($w['verify'], 'Lato-Regular', 22, 1200, 1560, '#8A8A8A', 'center');
    }

    private function modern(array $d, array $img, array $w, ?GdImage $bg): void
    {
        $teal = '#0F766E';
        if (! $this->paper($bg, '#FFFFFF')) {
            $this->rect(0, 0, 470, self::HEIGHT, $teal);
            $this->rect(470, 0, 12, self::HEIGHT, '#14B8A6');
            $this->rotated(mb_strtoupper($w['kicker']), 'Lato-Bold', 64, 290, 1560, '#FFFFFF', 12, 1420);
        }

        $x = 600;
        $this->picture($img['logo'] ?? null, $x, 140, 520, 130, 'left');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 30, $x, 460, $teal, 6, 'left');
        $this->fitted($w['name'], 'Lato-Bold', 112, 56, $x, 630, 1650, '#111111', 'left');
        $this->text($w['completed'], 'Lato-Regular', 38, $x, 720, '#666666', 'left');
        $y = $this->lines($w['course'], 'Lato-Bold', 64, $x, 830, 1650, 3, 78, $teal, 'left');
        $this->lines($w['text'], 'Lato-Regular', 34, $x, $y + 80, 1600, 3, 48, '#555555', 'left');

        $this->dateBlock($w, $x, 1420, 460, 'Lato-Regular', 'left');
        $this->signerBlock($d, $img, $w, 1240, 1420, 600, 'left');
        $this->text($w['verify'], 'Lato-Regular', 22, $x, 1590, '#999999', 'left');
    }

    private function minimal(array $d, array $img, array $w, ?GdImage $bg): void
    {
        $ink = '#1A1A1A';
        if (! $this->paper($bg, '#FFFFFF')) {
            $this->frame(80, 3, $ink);
        }

        $this->picture($img['logo'] ?? null, 950, 180, 500, 120, 'center');
        $this->spaced(mb_strtoupper($w['kicker']), 'Lato-Bold', 28, 1200, 420, $ink, 11, 'center');
        $this->text($w['presented'], 'Lato-Regular', 34, 1200, 520, '#666666', 'center');
        $this->fitted($w['name'], 'CrimsonText-SemiBold', 130, 60, 1200, 670, 1750, '#111111', 'center');
        $this->rect(1140, 715, 120, 4, $ink);
        $this->text($w['completed'], 'Lato-Regular', 36, 1200, 810, '#555555', 'center');
        $y = $this->lines($w['course'], 'Lato-Bold', 60, 1200, 905, 1750, 3, 74, '#111111', 'center');
        $this->lines($w['text'], 'Lato-Regular', 32, 1200, $y + 80, 1500, 3, 46, '#555555', 'center');

        $this->dateBlock($w, 680, 1420, 480, 'Lato-Regular', 'center');
        $this->signerBlock($d, $img, $w, 1720, 1420, 480, 'center');
        $this->text($w['verify'], 'Lato-Regular', 22, 1200, 1560, '#9A9A9A', 'center');
    }

    private function academic(array $d, array $img, array $w, ?GdImage $bg): void
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
        $this->picture($img['logo'] ?? null, $x, 140, 520, 130, 'left');
        $this->text($w['date'], 'Lato-Regular', 30, $x, 350, '#555555', 'left');
        $this->fitted($w['name'], 'CrimsonText-SemiBold', 110, 56, $x, 700, 1480, '#111111', 'left');
        $this->text($w['completed'], 'Lato-Regular', 36, $x, 790, '#555555', 'left');
        $y = $this->lines($w['course'], 'Lato-Bold', 64, $x, 900, 1480, 3, 78, $navy, 'left');
        $this->lines($w['text'], 'Lato-Regular', 32, $x, $y + 80, 1450, 3, 46, '#555555', 'left');

        $this->signerBlock($d, $img, $w, $x, 1420, 560, 'left');
        $this->text($w['verify'], 'Lato-Regular', 22, $x, 1590, '#888888', 'left');
    }

    // Blocks ----------------------------------------------------------------

    private function dateBlock(array $w, int $x, int $lineY, int $width, string $font, string $align): void
    {
        $this->text($w['date'], $font, 38, $x, $lineY - 22, '#222222', $align);
        $this->rect($align === 'center' ? $x - intdiv($width, 2) : $x, $lineY, $width, 2, '#9A9A9A');
        $this->text($w['dateLabel'], 'Lato-Regular', 24, $x, $lineY + 45, '#777777', $align);
    }

    private function signerBlock(array $d, array $img, array $w, int $x, int $lineY, int $width, string $align): void
    {
        $left = $align === 'center' ? $x - intdiv($width, 2) : $x;
        $this->picture($img['signature'] ?? null, $left, $lineY - 150, $width, 140, $align === 'center' ? 'center' : 'left', true);
        $this->rect($left, $lineY, $width, 2, '#9A9A9A');
        $this->fitted($d['signerName'], 'Lato-Bold', 28, 18, $x, $lineY + 45, $width, '#222222', $align);
        $this->fitted($d['signerTitle'] !== '' ? $d['signerTitle'] : $w['issuedBy'], 'Lato-Regular', 24, 16, $x, $lineY + 85, $width, '#777777', $align);
    }

    // Drawing ---------------------------------------------------------------

    /** Draws the uploaded background (cover-fit) and says so, or fills the paper color. */
    private function paper(?GdImage $bg, string $color): bool
    {
        [$w, $h] = [imagesx($this->im), imagesy($this->im)];
        if (! $bg) {
            imagefilledrectangle($this->im, 0, 0, $w, $h, $this->color($color));

            return false;
        }

        [$bw, $bh] = [imagesx($bg), imagesy($bg)];
        $scale = max($w / $bw, $h / $bh);
        [$sw, $sh] = [(int) round($w / $scale), (int) round($h / $scale)];
        imagecopyresampled($this->im, $bg, 0, 0, intdiv($bw - $sw, 2), intdiv($bh - $sh, 2), $w, $h, $sw, $sh);

        return true;
    }

    /** An image contained in a box, aligned left or centered; signatures sit on the box's bottom. */
    private function picture(?string $bytes, int $x, int $y, int $bw, int $bh, string $align, bool $bottom = false): void
    {
        $src = $this->load($bytes);
        if (! $src) {
            return;
        }
        [$iw, $ih] = [imagesx($src), imagesy($src)];
        $scale = min($bw / $iw, $bh / $ih);
        [$dw, $dh] = [$iw * $scale, $ih * $scale];
        $dx = $align === 'center' ? $x + ($bw - $dw) / 2 : $x;
        $dy = $bottom ? $y + $bh - $dh : $y + ($bh - $dh) / 2;
        imagecopyresampled($this->im, $src, $this->px($dx), $this->px($dy), 0, 0, max(1, $this->px($dw)), max(1, $this->px($dh)), $iw, $ih);
    }

    private function load(?string $bytes): ?GdImage
    {
        if ($bytes === null || $bytes === '') {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            return null;
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function text(string $text, string $font, float $size, float $x, float $y, string $color, string $align): void
    {
        if ($text === '') {
            return;
        }
        $file = $this->font($text, $font);
        $pt = $this->pt($size);
        $width = $this->width($text, $file, $pt);
        $left = match ($align) {
            'center' => $this->px($x) - $width / 2,
            'right' => $this->px($x) - $width,
            default => $this->px($x),
        };
        imagettftext($this->im, $pt, 0, (int) round($left), $this->px($y), $this->color($color), $file, $text);
    }

    /** One line, shrunk until it fits the width. */
    private function fitted(string $text, string $font, float $size, float $min, float $x, float $y, float $maxWidth, string $color, string $align): void
    {
        $file = $this->font($text, $font);
        while ($size > $min && $this->width($text, $file, $this->pt($size)) > $this->px($maxWidth)) {
            $size -= 2;
        }
        $this->text($this->clip($text, $file, $this->pt($size), $this->px($maxWidth)), $font, $size, $x, $y, $color, $align);
    }

    /** Wrapped text, at most $max lines (the last ends in "…"); returns the last baseline. */
    private function lines(string $text, string $font, float $size, float $x, float $y, float $maxWidth, int $max, float $lineHeight, string $color, string $align): float
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return $y - $lineHeight;
        }
        $file = $this->font($text, $font);
        $pt = $this->pt($size);
        $limit = $this->px($maxWidth);

        $lines = $this->wrap($text, $file, $pt, $limit);
        // Balance the lines: the narrowest width that still needs no more of them.
        if (count($lines) > 1 && count($lines) <= $max) {
            [$lo, $hi] = [$limit / 2, $limit];
            while ($hi - $lo > 4) {
                $mid = ($lo + $hi) / 2;
                count($this->wrap($text, $file, $pt, $mid)) <= count($lines) ? $hi = $mid : $lo = $mid;
            }
            $lines = $this->wrap($text, $file, $pt, $hi);
        }

        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] = $this->clip($lines[$max - 1].'…', $file, $pt, $limit, true);
        }
        foreach ($lines as $i => $one) {
            $this->text($this->clip($one, $file, $pt, $limit), $font, $size, $x, $y + $i * $lineHeight, $color, $align);
        }

        return $y + (count($lines) - 1) * $lineHeight;
    }

    /** @return list<string> */
    private function wrap(string $text, string $file, float $pt, float $limit): array
    {
        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            $try = $line === '' ? $word : "$line $word";
            if ($line !== '' && $this->width($try, $file, $pt) > $limit) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;

        return $lines;
    }

    /** Letter-spaced capitals, for kickers. */
    private function spaced(string $text, string $font, float $size, float $x, float $y, string $color, float $tracking, string $align): void
    {
        $file = $this->font($text, $font);
        $pt = $this->pt($size);
        $gap = $this->px($tracking);
        $chars = mb_str_split($text);
        $widths = array_map(fn ($c) => $this->width($c, $file, $pt), $chars);
        $total = array_sum($widths) + $gap * max(0, count($chars) - 1);
        $left = $align === 'center' ? $this->px($x) - $total / 2 : $this->px($x);
        foreach ($chars as $i => $char) {
            imagettftext($this->im, $pt, 0, (int) round($left), $this->px($y), $this->color($color), $file, $char);
            $left += $widths[$i] + $gap;
        }
    }

    /** Text running up the page, its start at ($x, $y), letter-spaced, shrunk to $maxLength. */
    private function rotated(string $text, string $font, float $size, float $x, float $y, string $color, float $tracking, float $maxLength): void
    {
        $file = $this->font($text, $font);
        $chars = mb_str_split($text);
        do {
            $pt = $this->pt($size);
            $widths = array_map(fn ($c) => $this->width($c, $file, $pt), $chars);
            $total = array_sum($widths) + $this->px($tracking) * max(0, count($chars) - 1);
            $size -= 2;
        } while ($total > $this->px($maxLength) && $size > 20);
        $pos = $this->px($y);
        foreach ($chars as $i => $char) {
            imagettftext($this->im, $pt, 90, $this->px($x), (int) round($pos), $this->color($color), $file, $char);
            $pos -= $widths[$i] + $this->px($tracking);
        }
    }

    private function rect(float $x, float $y, float $w, float $h, string $color): void
    {
        imagefilledrectangle($this->im, $this->px($x), $this->px($y), $this->px($x + $w) - 1, $this->px($y + $h) - 1, $this->color($color));
    }

    private function frame(float $inset, float $stroke, string $color): void
    {
        $this->rect($inset, $inset, self::WIDTH - 2 * $inset, $stroke, $color);
        $this->rect($inset, self::HEIGHT - $inset - $stroke, self::WIDTH - 2 * $inset, $stroke, $color);
        $this->rect($inset, $inset, $stroke, self::HEIGHT - 2 * $inset, $color);
        $this->rect(self::WIDTH - $inset - $stroke, $inset, $stroke, self::HEIGHT - 2 * $inset, $color);
    }

    private function diamond(float $x, float $y, float $r, string $color): void
    {
        imagefilledpolygon($this->im, [$this->px($x), $this->px($y - $r), $this->px($x + $r), $this->px($y), $this->px($x), $this->px($y + $r), $this->px($x - $r), $this->px($y)], $this->color($color));
    }

    /** A round seal: a filled disc with an inner ring. */
    private function seal(float $x, float $y, float $r, string $fill, string $ring): void
    {
        $this->disc($x, $y, $r, $fill);
        $this->disc($x, $y, $r * 0.86, $ring);
        $this->disc($x, $y, $r * 0.82, $fill);
    }

    private function star(float $x, float $y, float $r, string $color): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $radius = $i % 2 ? $r * 0.42 : $r;
            $angle = -M_PI / 2 + $i * M_PI / 5;
            $points[] = $this->px($x + $radius * cos($angle));
            $points[] = $this->px($y + $radius * sin($angle));
        }
        imagefilledpolygon($this->im, $points, $this->color($color));
    }

    private function ribbon(float $x, float $y, string $color): void
    {
        foreach ([-1, 1] as $side) {
            $a = $x + $side * 40;
            imagefilledpolygon($this->im, [
                $this->px($a - 45), $this->px($y),
                $this->px($a + 45), $this->px($y),
                $this->px($a + 45 + $side * 30), $this->px($y + 260),
                $this->px($a + $side * 30), $this->px($y + 215),
                $this->px($a - 45 + $side * 30), $this->px($y + 260),
            ], $this->color($color));
        }
    }

    /** A smooth disc: drawn at 4× and scaled down, since GD does not anti-alias ellipses. */
    private function disc(float $x, float $y, float $r, string $color): void
    {
        $d = max(2, $this->px(2 * $r));
        $big = imagecreatetruecolor($d * 4, $d * 4);
        imagealphablending($big, false);
        imagefill($big, 0, 0, imagecolorallocatealpha($big, 0, 0, 0, 127));
        [$red, $green, $blue] = $this->rgb($color);
        imagefilledellipse($big, $d * 2, $d * 2, $d * 4, $d * 4, imagecolorallocatealpha($big, $red, $green, $blue, 0));
        imagesavealpha($big, true);
        $small = imagecreatetruecolor($d, $d);
        imagealphablending($small, false);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagecopyresampled($small, $big, 0, 0, 0, 0, $d, $d, $d * 4, $d * 4);
        imagealphablending($small, true);
        imagecopy($this->im, $small, $this->px($x) - intdiv($d, 2), $this->px($y) - intdiv($d, 2), 0, 0, $d, $d);
    }

    // Measuring -------------------------------------------------------------

    private function width(string $text, string $file, float $pt): float
    {
        $box = imagettfbbox($pt, 0, $file, $text);

        return $box ? (float) (max($box[2], $box[4]) - min($box[0], $box[6])) : 0.0;
    }

    /** Cuts a line to the width, ending in "…" when it had to. */
    private function clip(string $text, string $file, float $pt, float $limit, bool $ellipsis = false): string
    {
        if ($this->width($text, $file, $pt) <= $limit) {
            return $text;
        }
        $chars = mb_str_split(rtrim($text, '…'));
        while ($chars && $this->width(implode('', $chars).'…', $file, $pt) > $limit) {
            array_pop($chars);
        }

        return rtrim(implode('', $chars)).'…';
    }

    /**
     * The bundled font, or DejaVu Sans for text it cannot show: the template
     * fonts cover Latin scripts (Lato also Greek and Cyrillic); a name in
     * another script would otherwise print as empty boxes.
     */
    private function font(string $text, string $name): string
    {
        $latin = '\x{0000}-\x{024F}\x{1E00}-\x{1EFF}\x{2000}-\x{206F}\x{20A0}-\x{20CF}\x{2100}-\x{214F}';
        $covered = match (true) {
            str_starts_with($name, 'Lato') => $latin.'\x{0370}-\x{03FF}\x{0400}-\x{04FF}',
            $name === 'GreatVibes-Regular' => '\x{0000}-\x{017F}\x{2000}-\x{206F}',
            default => $latin,
        };
        if (preg_match('/[^'.$covered.']/u', $text)) {
            if ($name === 'GreatVibes-Regular' && ! preg_match('/[^'.$latin.']/u', $text)) {
                $name = 'CrimsonText-Italic';
            } else {
                $name = 'DejaVuSans-Bold';
            }
        }

        return dirname(__DIR__, 2).'/resources/fonts/'.$name.'.ttf';
    }

    /** Grid pixels to GD's points at this scale (GD sets type at 96 dpi). */
    private function pt(float $px): float
    {
        return $px * $this->s * 0.75;
    }

    private function px(float $v): int
    {
        return (int) round($v * $this->s);
    }

    private function color(string $hex): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return imagecolorallocate($this->im, $r, $g, $b) ?: 0;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $n = (int) hexdec(ltrim($hex, '#'));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }
}
