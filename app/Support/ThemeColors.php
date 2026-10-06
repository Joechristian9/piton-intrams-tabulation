<?php

namespace App\Support;

/**
 * Turns a custom theme's two colors into the CSS variables the app reads
 * (--accent-*, --accent2-*, --surface-*, as "r g b"; see tailwind.config.js), and
 * checks the pair stays readable. Mirrors resources/js/lib/themeColors.js, which
 * the theme editor uses for its live preview; both read themeScale.json.
 */
class ThemeColors
{
    public const HEX = '/^#[0-9a-f]{6}$/i';

    /** @return array<string, string> e.g. ['--accent-400' => '250 204 21', ...] */
    public static function variables(string $accent, string $surface): array
    {
        $scale = once(fn () => json_decode(file_get_contents(resource_path('js/lib/themeScale.json')), true));
        $vars = [];

        foreach (['accent' => $accent, 'accent2' => $accent, 'surface' => $surface] as $role => $hex) {
            $rgb = self::rgb($hex);
            foreach ($scale[$role === 'surface' ? 'surface' : 'accent'] as $shade => $weight) {
                $vars["--{$role}-{$shade}"] = implode(' ', self::mix($rgb, $weight));
            }
        }

        return $vars;
    }

    /**
     * Why this pair would be hard to read, keyed by field ('accent' / 'surface'), or [].
     * The app's buttons put black text on the accent; its text is white and gray on the
     * background; headings and highlights put accent-colored text on the background.
     */
    public static function problems(string $accent, string $surface): array
    {
        $a = self::luminance(self::rgb($accent));
        $s = self::luminance(self::rgb($surface));
        $problems = [];

        if (self::contrast($a, 0.0) < 4.5) {
            $problems['accent'] = "This accent is too dark: black text on its buttons wouldn't be readable. Pick a lighter color.";
        }
        if ($s > 0.04) {
            $problems['surface'] = "This background is too light for the app's white text. Pick a darker color.";
        }
        if (! $problems && self::contrast($a, $s) < 4.5) {
            $problems['accent'] = 'The accent and background are too close, so highlighted text would not stand out. Pick a brighter accent or a darker background.';
        }

        return $problems;
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $n = hexdec(substr($hex, 1));

        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    /** Mix toward white (weight > 0) or black (weight < 0). */
    private static function mix(array $rgb, float $weight): array
    {
        $target = $weight > 0 ? 255 : 0;

        return array_map(fn ($c) => (int) round($c + ($target - $c) * abs($weight)), $rgb);
    }

    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function contrast(float $l1, float $l2): float
    {
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }
}
