<?php

declare(strict_types=1);

namespace App\Core;

/** Envía respuestas JSON con el código de estado adecuado. */
final class Respuesta
{
    public static function json(mixed $datos, int $estado = 200): void
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $mensaje, int $estado = 400, array $detalles = []): void
    {
        $cuerpo = ['error' => $mensaje];

        if ($detalles !== []) {
            $cuerpo['detalles'] = $detalles;
        }

        self::json($cuerpo, $estado);
    }

    public static function sinContenido(): void
    {
        http_response_code(204);
    }
}
