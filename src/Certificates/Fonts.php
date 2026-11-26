<?php

namespace Ernestdefoe\Syllabus\Certificates;

/**
 * The certificate fonts, all bundled (OFL, except DejaVu's own free licence):
 * TrueType files in resources/fonts for the server drawing, and Latin and
 * Arabic woff2 subsets in resources/fonts/web for the browser, which shapes
 * every other script with the visitor's own fonts.
 */
class Fonts
{
    /** Template font => [SVG family, weight, style, woff2 file]. */
    public const WEB = [
        'CrimsonText-Regular' => ['SylCrimson', 400, 'normal', 'CrimsonText-Regular'],
        'CrimsonText-SemiBold' => ['SylCrimson', 600, 'normal', 'CrimsonText-SemiBold'],
        'CrimsonText-Italic' => ['SylCrimson', 400, 'italic', 'CrimsonText-Italic'],
        'Lato-Regular' => ['SylLato', 400, 'normal', 'Lato-Regular'],
        'Lato-Bold' => ['SylLato', 700, 'normal', 'Lato-Bold'],
        'GreatVibes-Regular' => ['SylGreatVibes', 400, 'normal', 'GreatVibes-Regular'],
    ];

    /** Arabic, Persian and Urdu in the browser: one variable font, loaded only when the text needs it. */
    public const ARABIC = ['SylVazirmatn', 'Vazirmatn', 'U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF, U+200C-200F'];

    /** Every web font file the route may serve. */
    public const FILES = ['CrimsonText-Regular', 'CrimsonText-SemiBold', 'CrimsonText-Italic', 'Lato-Regular', 'Lato-Bold', 'GreatVibes-Regular', 'Vazirmatn'];

    private const LATIN = '\x{0000}-\x{024F}\x{1E00}-\x{1EFF}\x{2000}-\x{206F}\x{20A0}-\x{20CF}\x{2100}-\x{214F}';

    /**
     * The TrueType file GD draws (and measures) this text with: the template's
     * font when it covers the text; Vazirmatn for Arabic script (it has the
     * presentation forms Shaper produces); DejaVu Sans for anything else.
     */
    public static function file(string $text, string $name): string
    {
        $covered = match (true) {
            str_starts_with($name, 'Lato') => self::LATIN.'\x{0370}-\x{03FF}\x{0400}-\x{04FF}',
            $name === 'GreatVibes-Regular' => '\x{0000}-\x{017F}\x{2000}-\x{206F}',
            default => self::LATIN,
        };
        if (preg_match('/[^'.$covered.']/u', $text)) {
            $bold = in_array($name, ['Lato-Bold', 'CrimsonText-SemiBold', 'GreatVibes-Regular'], true);
            $name = match (true) {
                (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) => $bold ? 'Vazirmatn-Bold' : 'Vazirmatn-Regular',
                $name === 'GreatVibes-Regular' && ! preg_match('/[^'.self::LATIN.']/u', $text) => 'CrimsonText-Italic',
                default => 'DejaVuSans-Bold',
            };
        }

        return dirname(__DIR__, 2).'/resources/fonts/'.$name.'.ttf';
    }

    /** The woff2 file for an allow-listed name, on the syllabus-fonts disk, or null. */
    public static function webFile(string $name): ?string
    {
        return in_array($name, self::FILES, true) ? $name.'.woff2' : null;
    }
}
