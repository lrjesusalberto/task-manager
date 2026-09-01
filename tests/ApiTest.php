<?php

declare(strict_types=1);

/**
 * Pruebas de la API sin dependencias externas.
 * Usan una base de datos SQLite en memoria, así que no tocan datos reales.
 *
 *   php tests/ApiTest.php
 */

use App\Core\Database;
use App\Models\Categoria;
use App\Models\Tarea;

require __DIR__ . '/../src/autoload.php';

final class Pruebas
{
    private int $pasadas = 0;
    private int $fallidas = 0;

    public function comprobar(string $descripcion, mixed $esperado, mixed $obtenido): void
    {
        if ($esperado === $obtenido) {
            $this->pasadas++;
            echo "  OK   {$descripcion}\n";
            return;
        }

        $this->fallidas++;
        echo "  FALLA {$descripcion}\n";
        echo '       esperado: ' . var_export($esperado, true) . "\n";
        echo '       obtenido: ' . var_export($obtenido, true) . "\n";
    }

    public function cierto(string $descripcion, bool $condicion): void
    {
        $this->comprobar($descripcion, true, $condicion);
    }

    public function resumen(): int
    {
        $total = $this->pasadas + $this->fallidas;
        echo "\n{$this->pasadas}/{$total} pruebas pasadas\n";

        return $this->fallidas === 0 ? 0 : 1;
    }
}

// --- Preparación: base de datos en memoria ---

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');

$esquema = (string) file_get_contents(__DIR__ . '/../database/schema.sqlite.sql');

foreach (array_filter(array_map('trim', explode(';', $esquema))) as $sentencia) {
    $pdo->exec($sentencia);
}

Database::usar($pdo);

$t = new Pruebas();

// --- Categorías ---

echo "Categorías\n";

$trabajo = Categoria::crear(['nombre' => 'Trabajo', 'color' => '#0040ff']);
$t->comprobar('crea una categoría con su nombre', 'Trabajo', $trabajo['nombre']);
$t->cierto('asigna un id numérico', is_int($trabajo['id']));

$t->cierto('detecta nombres duplicados', Categoria::existeNombre('Trabajo'));
$t->cierto('no confunde con otros nombres', !Categoria::existeNombre('Estudios'));
$t->cierto(
    'excluye la propia categoría al comprobar duplicados',
    !Categoria::existeNombre('Trabajo', $trabajo['id']),
);

// --- Tareas ---

echo "\nTareas\n";

$tarea = Tarea::crear([
    'titulo'       => 'Escribir pruebas',
    'descripcion'  => 'Cubrir el CRUD completo',
    'prioridad'    => 'alta',
    'categoria_id' => $trabajo['id'],
    'vence_el'     => '2026-12-31',
]);

$t->comprobar('crea una tarea', 'Escribir pruebas', $tarea['titulo']);
$t->comprobar('empieza sin completar', false, $tarea['completada']);
$t->comprobar('incluye el nombre de la categoría', 'Trabajo', $tarea['categoria_nombre']);

$actualizada = Tarea::actualizar($tarea['id'], ['titulo' => 'Pruebas escritas']);
$t->comprobar('actualiza el título', 'Pruebas escritas', $actualizada['titulo']);
$t->comprobar('conserva la prioridad al no enviarla', 'alta', $actualizada['prioridad']);

$completada = Tarea::actualizar($tarea['id'], ['completada' => true]);
$t->comprobar('marca la tarea como completada', true, $completada['completada']);

$t->comprobar('devuelve null si la tarea no existe', null, Tarea::actualizar(9999, ['titulo' => 'X']));
$t->comprobar('buscar una tarea inexistente devuelve null', null, Tarea::buscar(9999));

// --- Filtros ---

echo "\nFiltros\n";

Tarea::crear(['titulo' => 'Pendiente de prioridad baja', 'prioridad' => 'baja']);
Tarea::crear(['titulo' => 'Otra pendiente', 'prioridad' => 'alta']);

$t->comprobar('filtra las completadas', 1, count(Tarea::todas(['estado' => 'completadas'])));
$t->comprobar('filtra las pendientes', 2, count(Tarea::todas(['estado' => 'pendientes'])));
$t->comprobar('sin filtro devuelve todas', 3, count(Tarea::todas()));
$t->comprobar('filtra por prioridad', 1, count(Tarea::todas(['prioridad' => 'baja'])));
$t->comprobar('filtra por categoría', 1, count(Tarea::todas(['categoria_id' => (string) $trabajo['id']])));
$t->comprobar('busca por título', 1, count(Tarea::todas(['buscar' => 'Otra'])));
$t->comprobar('la búsqueda sin coincidencias devuelve vacío', 0, count(Tarea::todas(['buscar' => 'zzzz'])));

$orden = Tarea::todas(['estado' => 'pendientes']);
$t->comprobar('ordena por prioridad descendente', 'alta', $orden[0]['prioridad']);

// --- Resumen ---

echo "\nResumen\n";

$resumen = Tarea::resumen();
$t->comprobar('cuenta el total', 3, $resumen['total']);
$t->comprobar('cuenta las completadas', 1, $resumen['completadas']);
$t->comprobar('cuenta las pendientes', 2, $resumen['pendientes']);

// --- Integridad referencial ---

echo "\nIntegridad referencial\n";

Categoria::eliminar($trabajo['id']);
$huerfana = Tarea::buscar($tarea['id']);

$t->cierto('la tarea sobrevive al borrar su categoría', $huerfana !== null);
$t->comprobar('la tarea queda sin categoría', null, $huerfana['categoria_id']);

// --- Borrado ---

echo "\nBorrado\n";

$t->cierto('elimina una tarea existente', Tarea::eliminar($tarea['id']));
$t->cierto('eliminar una inexistente devuelve false', !Tarea::eliminar(9999));
$t->comprobar('la tarea ya no aparece', 2, count(Tarea::todas()));

exit($t->resumen());
