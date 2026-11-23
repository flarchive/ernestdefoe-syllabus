<?php

namespace Ernestdefoe\Syllabus\Certificates;

/**
 * A certificate, A4 landscape, from one Layout and two painters: a JPEG
 * (GdPainter) for the share image, and SVG (SvgPainter) for the certificate
 * page, its printout and its download, where the browser shapes every
 * script. The templates' colors and fonts are their own (bundled OFL fonts),
 * never the forum theme's.
 */
class Renderer
{
    /** Bump when a template changes, so cached drawings are redrawn. */
    public const VERSION = 3;
    public const WIDTH = Layout::WIDTH;
    public const HEIGHT = Layout::HEIGHT;

    /**
     * @param array{template: string, signerName: string, signerTitle: string} $design
     * @param array<string, ?string> $images raw bytes for logo, background, signature
     * @param array<string, string> $words
     */
    public function render(array $design, array $images, array $words, int $width = self::WIDTH): string
    {
        return (new GdPainter())->paint((new Layout())->build($design, $images, $words, true), $images, $width);
    }

    /**
     * @param array{template: string, signerName: string, signerTitle: string} $design
     * @param array<string, ?string> $images
     * @param array<string, string> $words
     * @param callable(string): string $fontUrl
     */
    public function svg(array $design, array $images, array $words, callable $fontUrl, string $label): string
    {
        return (new SvgPainter())->paint((new Layout())->build($design, $images, $words, false), $images, $fontUrl, $label);
    }
}
