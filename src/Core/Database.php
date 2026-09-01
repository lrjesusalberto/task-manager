<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión única a la base de datos.
 *
 * Admite MySQL (producción) y SQLite (desarrollo y pruebas): al usar PDO,
 * el resto de la aplicación no necesita saber cuál está detrás.
 */
final class Database
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $sqlite = Config::get('DB_SQLITE');

            if ($sqlite !== null && $sqlite !== '') {
                // Las rutas relativas se resuelven desde la raíz del proyecto,
                // no desde el directorio donde se lanzó el proceso.
                if (!preg_match('#^([a-zA-Z]:[\\/]|/)#', $sqlite)) {
                    $sqlite = dirname(__DIR__, 2) . '/' . $sqlite;
                }

                $pdo = new PDO('sqlite:' . $sqlite, null, null, $opciones);
                // SQLite no aplica las claves foráneas si no se piden.
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    Config::get('DB_HOST', 'localhost'),
                    Config::get('DB_PORT', '3306'),
                    Config::get('DB_NAME', 'task_manager'),
                );

                $pdo = new PDO(
                    $dsn,
                    Config::get('DB_USER', 'root'),
                    Config::get('DB_PASSWORD', ''),
                    $opciones,
                );
            }
        } catch (PDOException $e) {
            // El mensaje original puede contener credenciales: no se propaga.
            throw new RuntimeException('No se ha podido conectar con la base de datos.', 0, $e);
        }

        self::$conexion = $pdo;

        return $pdo;
    }

    /** Permite inyectar una conexión distinta en las pruebas. */
    public static function usar(?PDO $pdo): void
    {
        self::$conexion = $pdo;
    }
}
