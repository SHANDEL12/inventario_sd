<?php

declare(strict_types=1);

namespace App\Http;

use App\Middleware\MiddlewareInterface;
use LogicException;

final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable, middleware:array}> */
    private array $routes = [];

    /** @var MiddlewareInterface[] */
    private array $globalMiddleware = [];

    public function addGlobalMiddleware(MiddlewareInterface $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => $this->compile($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->globalMiddleware as $middleware) {
            $early = $middleware->handle($request);
            if ($early !== null) {
                return $early;
            }
        }

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $request->setAttribute('params', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

            foreach ($route['middleware'] as $middleware) {
                $early = $middleware->handle($request);
                if ($early !== null) {
                    return $early;
                }
            }

            $response = ($route['handler'])($request);
            if (!$response instanceof Response) {
                throw new LogicException('El controlador debe devolver una Response.');
            }

            return $response;
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            throw new HttpException(405, 'METHOD_NOT_ALLOWED', 'Método no permitido.');
        }

        throw new HttpException(404, 'NOT_FOUND', 'Recurso no encontrado.');
    }

    /**
     * Convierte "/api/products/{id}" en una expresión regular.
     * Los segmentos literales se escapan; {nombre} captura cualquier texto sin "/".
     */
    private function compile(string $path): string
    {
        $segments = explode('/', '/' . trim($path, '/'));

        $parts = array_map(static function (string $segment): string {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $m) === 1) {
                return '(?P<' . $m[1] . '>[^/]+)';
            }

            return preg_quote($segment, '#');
        }, $segments);

        // \z: fin absoluto de la cadena (a diferencia de $, no acepta un salto de línea final).
        return '#^' . implode('/', $parts) . '\z#';
    }
}