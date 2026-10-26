<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Router;
use App\Middleware\CorsMiddleware;
use App\Support\ErrorHandler;
use App\Support\Logger;

// Nunca se muestran errores de PHP al cliente; van a los archivos de log.
ini_set('display_errors', '0');

// Respaldo para errores fatales (no son excepciones): por ejemplo, si falta la carpeta vendor.
// No usa clases de la aplicación porque el autoload podría no estar disponible.
register_shutdown_function(static function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error === null || !in_array($error['type'], $fatalTypes, true) || headers_sent()) {
        return;
    }

    $line = sprintf(
        "%s [ERROR] fatal_error %s\n",
        gmdate('c'),
        json_encode(
            ['message' => $error['message'], 'file' => basename($error['file']), 'line' => $error['line']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        )
    );
    @file_put_contents(dirname(__DIR__) . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"success":false,"error":{"code":"INTERNAL_ERROR","message":"Ocurrió un error interno."}}';
});

$container = null;

try {
    $container = require dirname(__DIR__) . '/bootstrap.php';

    $router = new Router();
    $router->addGlobalMiddleware($container->get(CorsMiddleware::class));

    require dirname(__DIR__) . '/routes/api.php';

    $router->dispatch(Request::fromGlobals())->send();
} catch (Throwable $e) {
    $logger = $container?->get(Logger::class)
        ?? new Logger(dirname(__DIR__) . '/storage/logs/app.log');
    $debug = $container?->get('config')['debug'] ?? false;

    ErrorHandler::toResponse($e, $logger, (bool) $debug)->send();
}