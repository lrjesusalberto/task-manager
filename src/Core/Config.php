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

    /**
     * Algunos proveedores dan la conexión completa en una URL
     * (mysql://usuario:clave@host:puerto/base). Si existe, tiene
     * prioridad: es la que garantiza la conectividad entre servicios.
     *
     * @return array<string, string>
     */
    private static function desdeUrl(): array
    {
        static $partes = null;

        if ($partes !== null) {
            return $partes;
        }

        $partes = [];

        foreach (['MYSQL_URL', 'DATABASE_URL', 'MYSQL_PUBLIC_URL'] as $variable) {
            $url = getenv($variable);

            if ($url === false || $url === '') {
                continue;
            }

            $componentes = parse_url($url);

            if (!is_array($componentes) || !isset($componentes['host'])) {
                continue;
            }

            $partes = array_filter([
                'DB_HOST'     => $componentes['host'] ?? null,
                'DB_PORT'     => isset($componentes['port']) ? (string) $componentes['port'] : null,
                'DB_NAME'     => isset($componentes['path']) ? ltrim($componentes['path'], '/') : null,
                'DB_USER'     => isset($componentes['user']) ? urldecode($componentes['user']) : null,
                'DB_PASSWORD' => isset($componentes['pass']) ? urldecode($componentes['pass']) : null,
            ], static fn ($valor): bool => $valor !== null && $valor !== '');

            break;
        }

        return $partes;
    }

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

        $deUrl = self::desdeUrl();

        if (isset($deUrl[$clave])) {
            return $deUrl[$clave];
        }

        return self::$valores[$clave] ?? $porDefecto;
    }
}
