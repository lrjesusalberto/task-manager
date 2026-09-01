<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Peticion;
use App\Core\Respuesta;
use App\Models\Categoria;

final class CategoriaController
{
    public function indice(): void
    {
        Respuesta::json(Categoria::todas());
    }

    public function crear(): void
    {
        $datos = Peticion::cuerpoJson();
        $errores = $this->validar($datos, true);

        if ($errores !== []) {
            Respuesta::error('Los datos enviados no son válidos.', 422, $errores);
            return;
        }

        Respuesta::json(Categoria::crear($datos), 201);
    }

    public function actualizar(string $id): void
    {
        $datos = Peticion::cuerpoJson();
        $errores = $this->validar($datos, false, (int) $id);

        if ($errores !== []) {
            Respuesta::error('Los datos enviados no son válidos.', 422, $errores);
            return;
        }

        $categoria = Categoria::actualizar((int) $id, $datos);

        if ($categoria === null) {
            Respuesta::error('La categoría no existe.', 404);
            return;
        }

        Respuesta::json($categoria);
    }

    public function eliminar(string $id): void
    {
        if (!Categoria::eliminar((int) $id)) {
            Respuesta::error('La categoría no existe.', 404);
            return;
        }

        Respuesta::sinContenido();
    }

    /** @return array<string, string> */
    private function validar(array $datos, bool $esCreacion, ?int $id = null): array
    {
        $errores = [];

        if ($esCreacion || array_key_exists('nombre', $datos)) {
            $nombre = trim((string) ($datos['nombre'] ?? ''));

            if ($nombre === '') {
                $errores['nombre'] = 'El nombre es obligatorio.';
            } elseif (mb_strlen($nombre) > 60) {
                $errores['nombre'] = 'El nombre no puede superar los 60 caracteres.';
            } elseif (Categoria::existeNombre($nombre, $id)) {
                $errores['nombre'] = 'Ya existe una categoría con ese nombre.';
            }
        }

        if (!empty($datos['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $datos['color']) !== 1) {
            $errores['color'] = 'El color debe estar en formato hexadecimal (#rrggbb).';
        }

        return $errores;
    }
}
