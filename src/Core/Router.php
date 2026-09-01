<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador mínimo: asocia método HTTP y patrón de ruta con un manejador.
 * Los segmentos {nombre} se capturan y se pasan como argumentos.
 */
final class Router
{
    /** @var array<int, array{metodo: string, patron: string, manejador: callable}> */
    private array $rutas = [];

    public function get(string $patron, callable $manejador): void
    {
        $this->añadir('GET', $patron, $manejador);
    }

    public function post(string $patron, callable $manejador): void
    {
        $this->añadir('POST', $patron, $manejador);
    }

    public function put(string $patron, callable $manejador): void
    {
        $this->añadir('PUT', $patron, $manejador);
    }

    public function patch(string $patron, callable $manejador): void
    {
        $this->añadir('PATCH', $patron, $manejador);
    }

    public function delete(string $patron, callable $manejador): void
    {
        $this->añadir('DELETE', $patron, $manejador);
    }

    private function añadir(string $metodo, string $patron, callable $manejador): void
    {
        $this->rutas[] = ['metodo' => $metodo, 'patron' => $patron, 'manejador' => $manejador];
    }

    public function despachar(string $metodo, string $uri): void
    {
        $ruta = parse_url($uri, PHP_URL_PATH) ?: '/';
        $ruta = rtrim($ruta, '/') ?: '/';

        $rutaExiste = false;

        foreach ($this->rutas as $definicion) {
            $regex = '#^' . preg_replace('#\{[a-z_]+\}#', '([^/]+)', $definicion['patron']) . '$#';

            if (preg_match($regex, $ruta, $coincidencias) !== 1) {
                continue;
            }

            $rutaExiste = true;

            if ($definicion['metodo'] === $metodo) {
                array_shift($coincidencias);
                ($definicion['manejador'])(...$coincidencias);
                return;
            }
        }

        // Distinguir 405 de 404 ayuda a quien consume la API.
        if ($rutaExiste) {
            Respuesta::error('Método no permitido para esta ruta.', 405);
            return;
        }

        Respuesta::error('Recurso no encontrado.', 404);
    }
}
