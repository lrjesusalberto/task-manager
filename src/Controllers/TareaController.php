<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Categoria;
use App\Models\Tarea;

final class TareaController
{
    public function indice(): void
    {
        $filtros = [
            'estado'       => $_GET['estado'] ?? 'todas',
            'categoria_id' => $_GET['categoria_id'] ?? null,
            'prioridad'    => $_GET['prioridad'] ?? null,
            'buscar'       => $_GET['buscar'] ?? null,
        ];

        Respuesta::json([
            'tareas'  => Tarea::todas($filtros),
            'resumen' => Tarea::resumen(),
        ]);
    }

    public function mostrar(string $id): void
    {
        $tarea = Tarea::buscar((int) $id);

        if ($tarea === null) {
            Respuesta::error('La tarea no existe.', 404);
            return;
        }

        Respuesta::json($tarea);
    }

    public function crear(): void
    {
        $datos = Peticion::cuerpoJson();
        $errores = $this->validar($datos, true);

        if ($errores !== []) {
            Respuesta::error('Los datos enviados no son válidos.', 422, $errores);
            return;
        }

        Respuesta::json(Tarea::crear($datos), 201);
    }

    public function actualizar(string $id): void
    {
        $datos = Peticion::cuerpoJson();
        $errores = $this->validar($datos, false);

        if ($errores !== []) {
            Respuesta::error('Los datos enviados no son válidos.', 422, $errores);
            return;
        }

        $tarea = Tarea::actualizar((int) $id, $datos);

        if ($tarea === null) {
            Respuesta::error('La tarea no existe.', 404);
            return;
        }

        Respuesta::json($tarea);
    }

    /** Alterna el estado de completada sin tocar el resto de campos. */
    public function alternar(string $id): void
    {
        $tarea = Tarea::buscar((int) $id);

        if ($tarea === null) {
            Respuesta::error('La tarea no existe.', 404);
            return;
        }

        Respuesta::json(Tarea::actualizar((int) $id, ['completada' => !$tarea['completada']]));
    }

    public function eliminar(string $id): void
    {
        if (!Tarea::eliminar((int) $id)) {
            Respuesta::error('La tarea no existe.', 404);
            return;
        }

        Respuesta::sinContenido();
    }

    /**
     * @return array<string, string> Errores indexados por campo.
     */
    private function validar(array $datos, bool $esCreacion): array
    {
        $errores = [];

        if ($esCreacion || array_key_exists('titulo', $datos)) {
            $titulo = trim((string) ($datos['titulo'] ?? ''));

            if ($titulo === '') {
                $errores['titulo'] = 'El título es obligatorio.';
            } elseif (mb_strlen($titulo) > 160) {
                $errores['titulo'] = 'El título no puede superar los 160 caracteres.';
            }
        }

        if (!empty($datos['prioridad']) && !in_array($datos['prioridad'], Tarea::prioridadesValidas(), true)) {
            $errores['prioridad'] = 'La prioridad debe ser baja, media o alta.';
        }

        if (!empty($datos['vence_el'])) {
            $fecha = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $datos['vence_el']);

            if ($fecha === false || $fecha->format('Y-m-d') !== $datos['vence_el']) {
                $errores['vence_el'] = 'La fecha debe tener el formato AAAA-MM-DD.';
            }
        }

        if (!empty($datos['categoria_id']) && Categoria::buscar((int) $datos['categoria_id']) === null) {
            $errores['categoria_id'] = 'La categoría indicada no existe.';
        }

        return $errores;
    }
}
