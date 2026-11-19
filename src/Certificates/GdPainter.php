<?php

namespace Ernestdefoe\Syllabus\Certificates;

use GdImage;

/**
 * Paints a Layout as a JPEG with GD: the share image (og:image). GD has no
 * text shaping, so Shaper joins Arabic-script letters and puts right-to-left
 * text in visual order first; Layout has already replaced text in scripts GD
 * can't draw.
 */
class GdPainter
{
    private GdImage $im;
    private float $s = 1.0;

    /**
     * @param list<array<int, mixed>> $ops
     * @param array<string, ?string> $images raw bytes by key
     */
    public function paint(array $ops, array $images, int $width): string
    {
        $width = max(400, min(Layout::WIDTH, $width));
        $this->s = $width / Layout::WIDTH;
        $this->im = imagecreatetruecolor($width, (int) round(Layout::HEIGHT * $this->s));
        imagealphablending($this->im, true);
        imageantialias($this->im, true);

        foreach ($ops as $op) {
            match ($op[0]) {
                'rect' => imagefilledrectangle($this->im, $this->px($op[1]), $this->px($op[2]), $this->px($op[1] + $op[3]) - 1, $this->px($op[2] + $op[4]) - 1, $this->color($op[5])),
                'poly' => imagefilledpolygon($this->im, array_map(fn ($v) => $this->px($v), $op[1]), $this->color($op[2])),
                'disc' => $this->disc($op[1], $op[2], $op[3], $op[4]),
                'background' => $this->background($images['background'] ?? null),
                'image' => $this->image($images[$op[1]] ?? null, $op[2], $op[3], $op[4], $op[5]),
                'text' => $this->text(...array_slice($op, 1)),
                default => null,
            };
        }

        ob_start();
        imageinterlace($this->im, true);
        imagejpeg($this->im, null, 90);

        return (string) ob_get_clean();
    }

    private function text(string $text, string $font, float $size, float $x, float $y, string $anchor, string $color, int $rotate): void
    {
        $file = Fonts::file($text, $font);
        $pt = $size * $this->s * 0.75;
        $visual = Shaper::visual($text);
        $width = Layout::width($text, $font, $size) * $this->s;
        $left = $this->px($x) - match ($anchor) {
            'middle' => $width / 2,
            'end' => $width,
            default => 0,
        };
        imagettftext($this->im, $pt, $rotate, (int) round($left), $this->px($y), $this->color($color), $file, $visual);
    }

    /** The uploaded background, cover-fit. */
    private function background(?string $bytes): void
    {
        $bg = $this->load($bytes);
        if (! $bg) {
            return;
        }
        [$w, $h] = [imagesx($this->im), imagesy($this->im)];
        [$bw, $bh] = [imagesx($bg), imagesy($bg)];
        $scale = max($w / $bw, $h / $bh);
        [$sw, $sh] = [(int) round($w / $scale), (int) round($h / $scale)];
        imagecopyresampled($this->im, $bg, 0, 0, intdiv($bw - $sw, 2), intdiv($bh - $sh, 2), $w, $h, $sw, $sh);
    }

    private function image(?string $bytes, float $x, float $y, float $w, float $h): void
    {
        $src = $this->load($bytes);
        if ($src) {
            imagecopyresampled($this->im, $src, $this->px($x), $this->px($y), 0, 0, max(1, $this->px($w)), max(1, $this->px($h)), imagesx($src), imagesy($src));
        }
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
