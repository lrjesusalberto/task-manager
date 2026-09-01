<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Categoria
{
    /** Categorías con el número de tareas asociadas. */
    public static function todas(): array
    {
        $sentencia = Database::conexion()->query(
            'SELECT c.*, COUNT(t.id) AS total_tareas
             FROM categorias c
             LEFT JOIN tareas t ON t.categoria_id = c.id
             GROUP BY c.id, c.nombre, c.color, c.creada_en
             ORDER BY c.nombre ASC'
        );

        return array_map(
            static function (array $fila): array {
                $fila['id'] = (int) $fila['id'];
                $fila['total_tareas'] = (int) $fila['total_tareas'];

                return $fila;
            },
            $sentencia->fetchAll(),
        );
    }

    public static function buscar(int $id): ?array
    {
        $sentencia = Database::conexion()->prepare('SELECT * FROM categorias WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        $fila = $sentencia->fetch();

        if ($fila === false) {
            return null;
        }

        $fila['id'] = (int) $fila['id'];

        return $fila;
    }

    public static function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categorias WHERE nombre = :nombre';
        $parametros = [':nombre' => $nombre];

        if ($excepto !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $excepto;
        }

        $sentencia = Database::conexion()->prepare($sql);
        $sentencia->execute($parametros);

        return (int) $sentencia->fetchColumn() > 0;
    }

    public static function crear(array $datos): array
    {
        $pdo = Database::conexion();

        $sentencia = $pdo->prepare(
            'INSERT INTO categorias (nombre, color) VALUES (:nombre, :color)'
        );
        $sentencia->execute([
            ':nombre' => $datos['nombre'],
            ':color'  => $datos['color'] ?? '#6b6b6b',
        ]);

        return self::buscar((int) $pdo->lastInsertId());
    }

    public static function actualizar(int $id, array $datos): ?array
    {
        if (self::buscar($id) === null) {
            return null;
        }

        $asignaciones = [];
        $parametros = [':id' => $id];

        foreach (['nombre', 'color'] as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }

            $asignaciones[] = "$campo = :$campo";
            $parametros[":$campo"] = $datos[$campo];
        }

        if ($asignaciones === []) {
            return self::buscar($id);
        }

        $sentencia = Database::conexion()->prepare(
            'UPDATE categorias SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $sentencia->execute($parametros);

        return self::buscar($id);
    }

    /**
     * Elimina la categoría. Las tareas que la usaban quedan sin categoría,
     * por la regla ON DELETE SET NULL del esquema.
     */
    public static function eliminar(int $id): bool
    {
        $sentencia = Database::conexion()->prepare('DELETE FROM categorias WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return $sentencia->rowCount() > 0;
    }
}
