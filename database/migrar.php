<?php

declare(strict_types=1);

/**
 * Crea las tablas si no existen. Pensado para ejecutarse al desplegar:
 * es idempotente, así que se puede lanzar en cada arranque sin riesgo.
 *
 * Railway expone las credenciales de MySQL con nombres propios
 * (MYSQLHOST, MYSQLUSER...); aquí se traducen a las que usa la aplicación.
 */

use App\Core\Config;
use App\Core\Database;

require __DIR__ . '/../src/autoload.php';

Config::cargar(dirname(__DIR__) . '/.env');

// Traduce las variables de Railway a las de la aplicación si no están puestas.
$equivalencias = [
    'DB_HOST'     => 'MYSQLHOST',
    'DB_PORT'     => 'MYSQLPORT',
    'DB_NAME'     => 'MYSQLDATABASE',
    'DB_USER'     => 'MYSQLUSER',
    'DB_PASSWORD' => 'MYSQLPASSWORD',
];

foreach ($equivalencias as $propia => $railway) {
    if (getenv($propia) === false && getenv($railway) !== false) {
        putenv("$propia=" . getenv($railway));
    }
}

$esSqlite = Config::get('DB_SQLITE') !== null && Config::get('DB_SQLITE') !== '';
$esquema = __DIR__ . ($esSqlite ? '/schema.sqlite.sql' : '/schema.sql');

// Diagnóstico: sin esto, un fallo de conexión no dice qué falta.
$host = Config::get('DB_HOST');
$nombre = Config::get('DB_NAME');
$usuario = Config::get('DB_USER');
$clave = Config::get('DB_PASSWORD');

printf(
    "Configuración detectada: host=%s puerto=%s bd=%s usuario=%s contraseña=%s
",
    $host ?? '(vacío)',
    Config::get('DB_PORT') ?? '(vacío)',
    $nombre ?? '(vacío)',
    $usuario ?? '(vacío)',
    $clave === null || $clave === '' ? '(vacía)' : '(definida)',
);

if ($host === null || $host === '') {
    fwrite(STDERR, "No hay host de base de datos configurado.
");
    fwrite(STDERR, "Añade las variables del servicio MySQL al servicio de la aplicación.
");
    exit(1);
}

try {
    $pdo = Database::conexion();

    foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($esquema)))) as $sentencia) {
        $pdo->exec($sentencia);
    }

    echo "Tablas verificadas correctamente.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al preparar la base de datos: ' . $e->getMessage() . "\n");
    exit(1);
}
