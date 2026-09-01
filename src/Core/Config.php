<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lee la configuración de las variables de entorno, con respaldo en un
 * archivo .env para el desarrollo local.
 */
final class Config
{
    private static ?array $valores = null;

    public static function cargar(string $rutaEnv): void
    {
        $valores = [];

        if (is_readable($rutaEnv)) {
            foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
                $linea = trim($linea);

                if ($linea === '' || str_starts_with($linea, '#')) {
                    continue;
                }

                [$clave, $valor] = array_pad(explode('=', $linea, 2), 2, '');
                $valores[trim($clave)] = trim($valor, " \t\"'");
            }
        }

        self::$valores = $valores;
    }

    /**
     * Nombres que usan algunos proveedores para las credenciales de MySQL.
     * Railway, por ejemplo, expone MYSQLHOST en lugar de DB_HOST.
     */
    private const ALIAS = [
        'DB_HOST'     => ['MYSQLHOST', 'MYSQL_HOST'],
        'DB_PORT'     => ['MYSQLPORT', 'MYSQL_PORT'],
        'DB_NAME'     => ['MYSQLDATABASE', 'MYSQL_DATABASE'],
        'DB_USER'     => ['MYSQLUSER', 'MYSQL_USER'],
        'DB_PASSWORD' => ['MYSQLPASSWORD', 'MYSQL_PASSWORD'],
    ];

    public static function get(string $clave, ?string $porDefecto = null): ?string
    {
        // El entorno manda sobre el .env: en producción no hay archivo.
        $delEntorno = getenv($clave);

        if ($delEntorno !== false && $delEntorno !== '') {
            return $delEntorno;
        }

        foreach (self::ALIAS[$clave] ?? [] as $alternativa) {
            $valor = getenv($alternativa);

            if ($valor !== false && $valor !== '') {
                return $valor;
            }
        }

        return self::$valores[$clave] ?? $porDefecto;
    }
}
