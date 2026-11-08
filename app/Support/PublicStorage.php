<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorage
{
    /** Subpastas permitidas em /arquivos/{path} e storage público. */
    public const ALLOWED_PREFIXES = [
        'pagina-publica/',
        'instituicao/',
        'logos/',
    ];

    public static function isPathAllowed(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');

        if ($path === '' || str_contains($path, '..')) {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function url(?string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($path === '' || ! self::isPathAllowed($path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        if (file_exists(public_path('storage/'.$path))) {
            return '/storage/'.$path;
        }

        return '/arquivos/'.$path;
    }
}
