<?php

declare(strict_types=1);

use App\Controllers\CategoriaController;
use App\Controllers\TareaController;
use App\Core\Config;
use App\Core\Respuesta;
use App\Core\Router;

require __DIR__ . '/../src/autoload.php';

Config::cargar(dirname(__DIR__) . '/.env');

// La API puede consumirse desde un frontend alojado en otro dominio.
header('Access-Control-Allow-Origin: ' . (Config::get('CORS_ORIGIN', '*') ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Todo lo que no sea /api/... es el frontend: se sirve el HTML y el
// navegador pide los assets aparte.
$rutaPeticion = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!str_starts_with($rutaPeticion, '/api')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

$router = new Router();
$tareas = new TareaController();
$categorias = new CategoriaController();

$router->get('/api/tareas', $tareas->indice(...));
$router->post('/api/tareas', $tareas->crear(...));
$router->get('/api/tareas/{id}', $tareas->mostrar(...));
$router->put('/api/tareas/{id}', $tareas->actualizar(...));
$router->patch('/api/tareas/{id}/completar', $tareas->alternar(...));
$router->delete('/api/tareas/{id}', $tareas->eliminar(...));

$router->get('/api/categorias', $categorias->indice(...));
$router->post('/api/categorias', $categorias->crear(...));
$router->put('/api/categorias/{id}', $categorias->actualizar(...));
$router->delete('/api/categorias/{id}', $categorias->eliminar(...));

$router->get('/api/salud', static function (): void {
    Respuesta::json(['estado' => 'ok']);
});

try {
    $router->despachar(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/',
    );
} catch (Throwable $e) {
    // En producción no se filtran detalles internos al cliente.
    error_log((string) $e);

    $depurar = Config::get('APP_DEBUG') === 'true';

    Respuesta::error(
        $depurar ? $e->getMessage() : 'Ha ocurrido un error en el servidor.',
        500,
    );
}
