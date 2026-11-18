<?php

namespace Ernestdefoe\Syllabus\Certificates;

/**
 * Turns a line of text into what GD can draw: GD lays glyphs out one after
 * another, left to right, with no shaping. So Arabic-script letters are
 * replaced by their joined forms (initial, medial, final, isolated, and the
 * lam-alef ligatures) from the Unicode presentation-form blocks, which the
 * bundled DejaVu Sans covers, and right-to-left runs (Arabic, Persian, Urdu,
 * Hebrew) are put in visual order, with Latin words and numbers inside them
 * kept left to right.
 *
 * Scripts that need real shaping or fonts we don't bundle (Chinese, Japanese,
 * Korean, Thai, Indic and others) are reported by unsupported(), so the
 * renderer can say where to read that text instead of drawing empty boxes.
 */
class Shaper
{
    /** Letter => [isolated, final, initial, medial]; two entries for letters that join only on their right. */
    private const FORMS = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82], 0x0623 => [0xFE83, 0xFE84], 0x0624 => [0xFE85, 0xFE86], 0x0625 => [0xFE87, 0xFE88],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x0627 => [0xFE8D, 0xFE8E], 0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94], 0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], 0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], 0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA], 0x0630 => [0xFEAB, 0xFEAC], 0x0631 => [0xFEAD, 0xFEAE], 0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], 0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], 0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], 0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0], 0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], 0x0648 => [0xFEED, 0xFEEE], 0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // Alef wasla; Persian peh, tcheh, jeh, keheh, gaf, Farsi yeh; Urdu tteh, ddal, rreh, heh doachashmee, heh goal, yeh barree.
        // DejaVu has no forms for alef wasla, heh goal or yeh barree: they borrow alef's, heh's and alef maksura's.
        0x0671 => [0xFE8D, 0xFE8E], 0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B], 0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], 0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69], 0x0688 => [0xFB88, 0xFB89],
        0x0691 => [0xFB8C, 0xFB8D], 0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD], 0x06C1 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x06D2 => [0xFEEF, 0xFEF0],
    ];

    /** Lam followed by an alef: [isolated, final] ligature. */
    private const LAM_ALEF = [0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA], 0x0627 => [0xFEFB, 0xFEFC]];

    private const ZWNJ = 0x200C;
    private const ZWJ = 0x200D;

    /** Text the renderer can't draw correctly yet: CJK, Thai, Lao, Indic, Khmer, Myanmar, Ethiopic and the like. */
    public static function unsupported(string $text): bool
    {
        return (bool) preg_match('/[\x{0700}-\x{074F}\x{0780}-\x{07BF}\x{0900}-\x{0DFF}\x{0E00}-\x{0EFF}\x{0F00}-\x{0FFF}\x{1000}-\x{109F}\x{1200}-\x{139F}\x{1780}-\x{17FF}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7AF}\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FFEF}\x{20000}-\x{3FFFF}]/u', $text);
    }

    /** Whether the text reads right to left: its first strong letter is Hebrew or Arabic-script. */
    public static function isRtl(string $text): bool
    {
        foreach (self::codes($text) as $c) {
            $dir = self::direction($c);
            if ($dir === 'R') {
                return true;
            }
            if ($dir === 'L') {
                return false;
            }
        }

        return false;
    }

    /** The text in the order and forms GD should draw it, left to right. */
    public static function visual(string $text): string
    {
        $codes = self::codes($text);
        if (! preg_match('/[\x{0590}-\x{08FF}\x{FB1D}-\x{FEFF}]/u', $text)) {
            return $text;
        }

        return self::chars(self::reorder(self::shape($codes), self::isRtl($text)));
    }

    /**
     * Arabic contextual forms, in logical order.
     *
     * @param list<int> $codes
     * @return list<int>
     */
    public static function shape(array $codes): array
    {
        $out = [];
        $n = count($codes);
        for ($i = 0; $i < $n; $i++) {
            $c = $codes[$i];
            if (! isset(self::FORMS[$c])) {
                if ($c !== self::ZWNJ && $c !== self::ZWJ) {
                    $out[] = $c;
                }
                continue;
            }
            $prev = self::neighbour($codes, $i, -1);
            $next = self::neighbour($codes, $i, 1);
            $joinsBefore = $prev !== null && (self::dual($prev) || $prev === self::ZWJ) && $c !== 0x0621;
            // Lam + alef: one ligature.
            if ($c === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next]) && $codes[$i + 1] === $next) {
                $out[] = self::LAM_ALEF[$next][$joinsBefore ? 1 : 0];
                $i++;
                continue;
            }
            $joinsAfter = self::dual($c) && $next !== null && (isset(self::FORMS[$next]) || $next === self::ZWJ) && $next !== 0x0621;
            $forms = self::FORMS[$c];
            $out[] = match (true) {
                $joinsBefore && $joinsAfter => $forms[3],
                $joinsBefore => $forms[1],
                $joinsAfter => $forms[2],
                default => $forms[0],
            };
        }

        return $out;
    }

    /**
     * Visual order for one line: runs of right-to-left text are reversed (by
     * cluster, so marks stay with their letter); in a right-to-left line the
     * runs themselves are reversed too, keeping Latin words and numbers
     * readable left to right.
     *
     * @param list<int> $codes
     * @return list<int>
     */
    public static function reorder(array $codes, bool $rtl): array
    {
        $base = $rtl ? 'R' : 'L';
        $dirs = array_map(fn ($c) => self::direction($c), $codes);
        $n = count($codes);

        // Neutrals between two runs of the same direction take it; otherwise the line's direction.
        // Marks take their letter's direction.
        for ($i = 0; $i < $n; $i++) {
            if ($dirs[$i] === 'M') {
                $dirs[$i] = $i > 0 ? $dirs[$i - 1] : $base;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            if ($dirs[$i] !== 'N') {
                continue;
            }
            $j = $i;
            while ($j < $n && $dirs[$j] === 'N') {
                $j++;
            }
            $before = $i > 0 ? $dirs[$i - 1] : $base;
            $after = $j < $n ? $dirs[$j] : $base;
            $dir = $before === $after ? $before : $base;
            for ($k = $i; $k < $j; $k++) {
                $dirs[$k] = $dir;
            }
            $i = $j - 1;
        }

        // Runs.
        $runs = [];
        foreach ($codes as $i => $c) {
            if ($runs && end($runs)['dir'] === $dirs[$i]) {
                $runs[count($runs) - 1]['codes'][] = $c;
            } else {
                $runs[] = ['dir' => $dirs[$i], 'codes' => [$c]];
            }
        }
        if ($rtl) {
            $runs = array_reverse($runs);
        }

        $out = [];
        foreach ($runs as $run) {
            if ($run['dir'] !== 'R') {
                array_push($out, ...$run['codes']);
                continue;
            }
            // Reverse by cluster: a letter and the marks after it stay together, marks after.
            $clusters = [];
            foreach ($run['codes'] as $c) {
                if (self::isMark($c) && $clusters) {
                    $clusters[count($clusters) - 1][] = $c;
                } else {
                    $clusters[] = [$c];
                }
            }
            foreach (array_reverse($clusters) as $cluster) {
                foreach ($cluster as $c) {
                    $out[] = self::mirror($c);
                }
            }
        }

        return $out;
    }

    /** R (Hebrew, Arabic script), L (other letters and digits), M (combining mark) or N (spaces, punctuation). */
    private static function direction(int $c): string
    {
        if (self::isMark($c)) {
            return 'M';
        }
        if (($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9)) {
            return 'L'; // Arabic-Indic digits read left to right.
        }
        if (($c >= 0x0590 && $c <= 0x08FF) || ($c >= 0xFB1D && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF)) {
            return 'R';
        }

        return preg_match('/^[\p{L}\p{N}]$/u', self::chars([$c])) ? 'L' : 'N';
    }

    private static function isMark(int $c): bool
    {
        return ($c >= 0x064B && $c <= 0x065F) || $c === 0x0670 || ($c >= 0x0591 && $c <= 0x05C7 && $c !== 0x05BE && $c !== 0x05C0 && $c !== 0x05C3 && $c !== 0x05C6)
            || ($c >= 0x06D6 && $c <= 0x06ED && $c !== 0x06DD && $c !== 0x06DE && $c !== 0x06E5 && $c !== 0x06E6 && $c !== 0x06E9);
    }

    /** Brackets turn around in right-to-left text. */
    private static function mirror(int $c): int
    {
        return [0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0x3C => 0x3E, 0x3E => 0x3C, 0xAB => 0xBB, 0xBB => 0xAB][$c] ?? $c;
    }

    /** Letters that join on both sides. */
    private static function dual(int $c): bool
    {
        return isset(self::FORMS[$c]) && count(self::FORMS[$c]) === 4;
    }

    /** The nearest letter before or after, skipping marks. */
    private static function neighbour(array $codes, int $i, int $step): ?int
    {
        for ($j = $i + $step; $j >= 0 && $j < count($codes); $j += $step) {
            if (! self::isMark($codes[$j])) {
                return $codes[$j];
            }
        }

        return null;
    }

    /** @return list<int> */
    private static function codes(string $text): array
    {
        return array_map('mb_ord', mb_str_split($text));
    }

    /** @param list<int> $codes */
    private static function chars(array $codes): string
    {
        return implode('', array_map('mb_chr', $codes));
    }
}
