<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Tarea
{
    private const PRIORIDADES = ['baja', 'media', 'alta'];

    /**
     * Devuelve las tareas con el nombre de su categoría.
     *
     * @param array{estado?: string, categoria_id?: string, prioridad?: string, buscar?: string} $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function todas(array $filtros = []): array
    {
        $sql = 'SELECT t.*, c.nombre AS categoria_nombre, c.color AS categoria_color
                FROM tareas t
                LEFT JOIN categorias c ON c.id = t.categoria_id';

        $condiciones = [];
        $parametros = [];

        if (isset($filtros['estado']) && $filtros['estado'] !== 'todas') {
            $condiciones[] = 't.completada = :completada';
            $parametros[':completada'] = $filtros['estado'] === 'completadas' ? 1 : 0;
        }

        if (!empty($filtros['categoria_id'])) {
            $condiciones[] = 't.categoria_id = :categoria';
            $parametros[':categoria'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['prioridad']) && in_array($filtros['prioridad'], self::PRIORIDADES, true)) {
            $condiciones[] = 't.prioridad = :prioridad';
            $parametros[':prioridad'] = $filtros['prioridad'];
        }

        if (!empty($filtros['buscar'])) {
            $condiciones[] = 't.titulo LIKE :buscar';
            $parametros[':buscar'] = '%' . $filtros['buscar'] . '%';
        }

        if ($condiciones !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        // Pendientes primero, luego por prioridad y por fecha de vencimiento.
        $sql .= " ORDER BY t.completada ASC,
                  CASE t.prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END,
                  t.vence_el IS NULL, t.vence_el ASC, t.id DESC";

        $sentencia = Database::conexion()->prepare($sql);
        $sentencia->execute($parametros);

        return array_map(self::normalizar(...), $sentencia->fetchAll());
    }

    public static function buscar(int $id): ?array
    {
        $sentencia = Database::conexion()->prepare(
            'SELECT t.*, c.nombre AS categoria_nombre, c.color AS categoria_color
             FROM tareas t
             LEFT JOIN categorias c ON c.id = t.categoria_id
             WHERE t.id = :id'
        );
        $sentencia->execute([':id' => $id]);

        $fila = $sentencia->fetch();

        return $fila === false ? null : self::normalizar($fila);
    }

    public static function crear(array $datos): array
    {
        $pdo = Database::conexion();

        $sentencia = $pdo->prepare(
            'INSERT INTO tareas (titulo, descripcion, prioridad, vence_el, categoria_id)
             VALUES (:titulo, :descripcion, :prioridad, :vence_el, :categoria_id)'
        );

        $sentencia->execute([
            ':titulo'       => $datos['titulo'],
            ':descripcion'  => $datos['descripcion'] ?? null,
            ':prioridad'    => $datos['prioridad'] ?? 'media',
            ':vence_el'     => $datos['vence_el'] ?? null,
            ':categoria_id' => $datos['categoria_id'] ?? null,
        ]);

        return self::buscar((int) $pdo->lastInsertId());
    }

    public static function actualizar(int $id, array $datos): ?array
    {
        if (self::buscar($id) === null) {
            return null;
        }

        // Solo se actualizan los campos presentes en la petición.
        $asignaciones = [];
        $parametros = [':id' => $id];

        foreach (['titulo', 'descripcion', 'prioridad', 'vence_el', 'categoria_id', 'completada'] as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }

            $asignaciones[] = "$campo = :$campo";
            $parametros[":$campo"] = $campo === 'completada'
                ? (int) (bool) $datos[$campo]
                : $datos[$campo];
        }

        if ($asignaciones === []) {
            return self::buscar($id);
        }

        $sentencia = Database::conexion()->prepare(
            'UPDATE tareas SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $sentencia->execute($parametros);

        return self::buscar($id);
    }

    public static function eliminar(int $id): bool
    {
        $sentencia = Database::conexion()->prepare('DELETE FROM tareas WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return $sentencia->rowCount() > 0;
    }

    /** Recuento de tareas por estado, para la barra de resumen. */
    public static function resumen(): array
    {
        $sentencia = Database::conexion()->query(
            'SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END) AS completadas
             FROM tareas'
        );

        $fila = $sentencia->fetch();
        $total = (int) ($fila['total'] ?? 0);
        $completadas = (int) ($fila['completadas'] ?? 0);

        return [
            'total'       => $total,
            'completadas' => $completadas,
            'pendientes'  => $total - $completadas,
        ];
    }

    /** Convierte los tipos que la base de datos devuelve como texto. */
    private static function normalizar(array $fila): array
    {
        $fila['id'] = (int) $fila['id'];
        $fila['completada'] = (bool) $fila['completada'];
        $fila['categoria_id'] = $fila['categoria_id'] === null ? null : (int) $fila['categoria_id'];

        return $fila;
    }

    public static function prioridadesValidas(): array
    {
        return self::PRIORIDADES;
    }
}
