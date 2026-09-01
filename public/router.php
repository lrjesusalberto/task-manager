<?php

declare(strict_types=1);

/**
 * Enrutador para el servidor integrado de PHP (desarrollo y Railway).
 *
 * Sirve los archivos estáticos que existen y envía el resto a index.php,
 * que decide entre la API y el frontend.
 *
 * Con Apache o Nginx este archivo no se usa: basta con apuntar el
 * DocumentRoot a public/ y reescribir a index.php.
 */

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$archivo = __DIR__ . $ruta;

if ($ruta !== '/' && is_file($archivo)) {
    return false; // El servidor integrado sirve el archivo tal cual.
}

require __DIR__ . '/index.php';
