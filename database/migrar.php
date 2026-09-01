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

// Sin openssl, mysqlnd no negocia caching_sha2_password (el metodo por
// defecto de MySQL 8) y la conexion muere en el handshake.
printf(
    "Extensiones: pdo=%s openssl=%s sodium=%s
",
    implode(',', PDO::getAvailableDrivers()),
    extension_loaded('openssl') ? 'si' : 'NO',
    extension_loaded('sodium') ? 'si' : 'NO',
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
    fwrite(STDERR, 'Error al preparar la base de datos: ' . $e->getMessage() . PHP_EOL);

    // La causa real (host inalcanzable, credenciales, permisos) viaja en la
    // excepcion anterior; sin mostrarla el log no permite diagnosticar nada.
    for ($causa = $e->getPrevious(); $causa !== null; $causa = $causa->getPrevious()) {
        fwrite(STDERR, 'Causa: ' . $causa->getMessage() . PHP_EOL);
    }

    exit(1);
}
