<?php

namespace App\Support;

/**
 * Gera paleta harmoniosa para todo o sistema a partir das 3 cores da instituição.
 */
class TemaInstitucional
{
    /**
     * @param  array<string, mixed>  $paginaPublica  Resultado de PaginaPublicaConfig::resolve()
     * @return array{light: array<string, string>, dark: array<string, string>, rgb: array<string, string>, welcome: array<string, string>}
     */
    public static function fromPaginaPublica(array $paginaPublica): array
    {
        $primary = $paginaPublica['cor_primaria'];
        $primaryDark = $paginaPublica['cor_primaria_escura'];
        $secondary = $paginaPublica['cor_secundaria'];
        $accent = self::mixHex($primary, $secondary, 0.42);

        $primaryRgb = self::rgbString($primary);
        $secondaryRgb = self::rgbString($secondary);
        $accentRgb = self::rgbString($accent);

        $primaryLight = PaginaPublicaConfig::clarear($primary, 0.28);
        $primaryDarkMode = PaginaPublicaConfig::clarear($primary, 0.32);
        $accentDarkMode = PaginaPublicaConfig::clarear($accent, 0.2);
        $secondaryDarkMode = PaginaPublicaConfig::clarear($secondary, 0.25);

        $light = [
            '--primary-color' => $primary,
            '--primary-dark' => $primaryDark,
            '--secondary-color' => $secondary,
            '--accent-color' => $accent,
            '--primary-rgb' => $primaryRgb,
            '--secondary-rgb' => $secondaryRgb,
            '--accent-rgb' => $accentRgb,
            '--primary-soft-bg' => "rgba({$primaryRgb}, 0.06)",
            '--primary-soft-border' => "rgba({$primaryRgb}, 0.14)",
            '--primary-soft-icon' => "rgba({$primaryRgb}, 0.12)",
            '--secondary-soft-bg' => "rgba({$secondaryRgb}, 0.06)",
            '--secondary-soft-icon' => "rgba({$secondaryRgb}, 0.12)",
            '--sidebar-text-active' => $primary,
            '--sidebar-icon-bg' => "rgba({$primaryRgb}, 0.08)",
            '--sidebar-hover-bg' => "rgba({$primaryRgb}, 0.06)",
            '--sidebar-active-bg' => "rgba({$primaryRgb}, 0.1)",
            '--sidebar-active-border' => $primary,
            '--sidebar-scroll-thumb' => "rgba({$primaryRgb}, 0.22)",
            '--sidebar-brand-color' => $primary,
            '--table-hover' => "rgba({$primaryRgb}, 0.03)",
            '--bs-primary' => $primary,
            '--bs-primary-rgb' => $primaryRgb,
            '--bs-info' => $secondary,
            '--bs-info-rgb' => $secondaryRgb,
            '--link-color' => $primary,
            '--link-hover-color' => $primaryDark,
        ];

        $dark = [
            '--primary-color' => $primaryLight,
            '--primary-dark' => $primaryDarkMode,
            '--secondary-color' => $secondaryDarkMode,
            '--accent-color' => $accentDarkMode,
            '--primary-rgb' => self::rgbString($primaryLight),
            '--secondary-rgb' => self::rgbString($secondaryDarkMode),
            '--accent-rgb' => self::rgbString($accentDarkMode),
            '--primary-soft-bg' => 'rgba('.self::rgbString($primaryLight).', 0.1)',
            '--primary-soft-border' => 'rgba('.self::rgbString($primaryLight).', 0.22)',
            '--primary-soft-icon' => 'rgba('.self::rgbString($primaryLight).', 0.18)',
            '--secondary-soft-bg' => 'rgba('.self::rgbString($secondaryDarkMode).', 0.1)',
            '--secondary-soft-icon' => 'rgba('.self::rgbString($secondaryDarkMode).', 0.16)',
            '--sidebar-text-active' => '#ffffff',
            '--sidebar-icon-bg' => 'rgba('.self::rgbString($primaryLight).', 0.18)',
            '--sidebar-hover-bg' => 'rgba('.self::rgbString($primaryLight).', 0.14)',
            '--sidebar-active-bg' => 'rgba('.self::rgbString($primaryLight).', 0.22)',
            '--sidebar-active-border' => $primaryLight,
            '--sidebar-scroll-thumb' => 'rgba('.self::rgbString($primaryLight).', 0.45)',
            '--sidebar-brand-color' => '#f8fafc',
            '--table-hover' => 'rgba('.self::rgbString($primaryLight).', 0.08)',
            '--bs-primary' => $primaryLight,
            '--bs-primary-rgb' => self::rgbString($primaryLight),
            '--bs-info' => $secondaryDarkMode,
            '--bs-info-rgb' => self::rgbString($secondaryDarkMode),
            '--link-color' => $primaryLight,
            '--link-hover-color' => PaginaPublicaConfig::clarear($primaryLight, 0.15),
        ];

        $hero = $paginaPublica['hero_gradiente'];

        $welcome = [
            '--primary' => $primary,
            '--primary-dark' => $primaryDark,
            '--accent' => $secondary,
            '--hero-inicio' => $hero['inicio'],
            '--hero-meio' => $hero['meio'],
            '--hero-fim' => $hero['fim'],
            '--hero-claro' => $hero['claro'],
        ];

        return [
            'light' => $light,
            'dark' => $dark,
            'rgb' => [
                'primary' => $primaryRgb,
                'secondary' => $secondaryRgb,
                'accent' => $accentRgb,
            ],
            'welcome' => $welcome,
            'cores' => [
                'primaria' => $primary,
                'primaria_escura' => $primaryDark,
                'secundaria' => $secondary,
                'acento' => $accent,
            ],
        ];
    }

    public static function mixHex(string $a, string $b, float $ratio): string
    {
        $ratio = max(0, min(1, $ratio));
        [$ar, $ag, $ab] = self::rgbParts($a);
        [$br, $bg, $bb] = self::rgbParts($b);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($ar + ($br - $ar) * $ratio),
            (int) round($ag + ($bg - $ag) * $ratio),
            (int) round($ab + ($bb - $ab) * $ratio)
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgbParts(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public static function rgbString(string $hex): string
    {
        [$r, $g, $b] = self::rgbParts($hex);

        return "{$r}, {$g}, {$b}";
    }
}
