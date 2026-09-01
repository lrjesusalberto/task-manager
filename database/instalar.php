<?php

declare(strict_types=1);

/**
 * Crea las tablas y, opcionalmente, datos de ejemplo.
 *
 *   php database/instalar.php           Solo el esquema
 *   php database/instalar.php --ejemplo Esquema + datos de ejemplo
 */

use App\Core\Config;
use App\Core\Database;

require __DIR__ . '/../src/autoload.php';

Config::cargar(dirname(__DIR__) . '/.env');

$esSqlite = Config::get('DB_SQLITE') !== null && Config::get('DB_SQLITE') !== '';
$esquema = __DIR__ . ($esSqlite ? '/schema.sqlite.sql' : '/schema.sql');

$pdo = Database::conexion();

// Cada sentencia por separado: algunos controladores no admiten varias juntas.
foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents($esquema)))) as $sentencia) {
    $pdo->exec($sentencia);
}

echo "Esquema creado ({$esquema}).\n";

if (!in_array('--ejemplo', $argv ?? [], true)) {
    exit(0);
}

if ((int) $pdo->query('SELECT COUNT(*) FROM tareas')->fetchColumn() > 0) {
    echo "Ya hay tareas: no se insertan datos de ejemplo.\n";
    exit(0);
}

$categorias = [
    ['Trabajo', '#0040ff'],
    ['Estudios', '#00a86b'],
    ['Personal', '#d94f00'],
];

$sentencia = $pdo->prepare('INSERT INTO categorias (nombre, color) VALUES (?, ?)');

foreach ($categorias as $categoria) {
    $sentencia->execute($categoria);
}

$hoy = new DateTimeImmutable('today');

$tareas = [
    ['Preparar la entrevista técnica', 'Repasar algoritmos y estructuras de datos.', 'alta', $hoy->modify('+2 days')->format('Y-m-d'), 1, 0],
    ['Terminar la práctica de bases de datos', 'Normalización hasta la tercera forma normal.', 'alta', $hoy->modify('+5 days')->format('Y-m-d'), 2, 0],
    ['Revisar el portfolio', 'Actualizar la sección de proyectos.', 'media', null, 1, 0],
    ['Leer sobre índices en MySQL', null, 'baja', $hoy->modify('+14 days')->format('Y-m-d'), 2, 0],
    ['Renovar el carnet', 'Pedir cita previa.', 'media', $hoy->modify('-1 day')->format('Y-m-d'), 3, 0],
    ['Configurar el entorno de desarrollo', 'PHP, MySQL y editor.', 'media', null, 1, 1],
];

$sentencia = $pdo->prepare(
    'INSERT INTO tareas (titulo, descripcion, prioridad, vence_el, categoria_id, completada)
     VALUES (?, ?, ?, ?, ?, ?)'
);

foreach ($tareas as $tarea) {
    $sentencia->execute($tarea);
}

printf("Insertadas %d categorías y %d tareas de ejemplo.\n", count($categorias), count($tareas));
