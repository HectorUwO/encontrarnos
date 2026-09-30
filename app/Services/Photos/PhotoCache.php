<?php

namespace App\Services\Photos;

use Illuminate\Http\Request;

/**
 * Política de caché de las fotografías públicas. Cada dirección lleva una huella
 * corta de la foto (`?v=`): mientras coincida con la actual, el navegador la
 * conserva una semana sin volver a preguntar; si la ficha cambia de foto, la
 * dirección cambia con ella. Sin huella (enlaces viejos) se conserva un día.
 */
class PhotoCache
{
    private const VERSIONED = 'public, max-age=604800, immutable';

    private const UNVERSIONED = 'public, max-age=86400';

    public static function version(string $photoPath): string
    {
        return substr(sha1($photoPath), 0, 8);
    }

    /**
     * @return array<string, string>
     */
    public static function headers(Request $request, string $photoPath): array
    {
        return [
            'Cache-Control' => $request->query('v') === self::version($photoPath) ? self::VERSIONED : self::UNVERSIONED,
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
