<?php

declare(strict_types=1);

namespace App\Core;

/** Acceso al cuerpo de la petición. */
final class Peticion
{
    private static ?array $cuerpoCacheado = null;

    /** @return array<string, mixed> */
    public static function cuerpoJson(): array
    {
        if (self::$cuerpoCacheado !== null) {
            return self::$cuerpoCacheado;
        }

        $crudo = file_get_contents('php://input') ?: '';
        $datos = json_decode($crudo, true);

        self::$cuerpoCacheado = is_array($datos) ? $datos : [];

        return self::$cuerpoCacheado;
    }

    /** Reinicia la caché entre peticiones en las pruebas. */
    public static function reiniciar(): void
    {
        self::$cuerpoCacheado = null;
    }
}
