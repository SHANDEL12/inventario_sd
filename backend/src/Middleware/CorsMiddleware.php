<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;

/**
 * Regla única:
 *  - Petición SIN cabecera Origin (PowerShell, Postman, curl): pasa. La protección real
 *    de las operaciones que modifican datos es la sesión + el token CSRF.
 *  - Petición CON Origin que no esté en ALLOWED_ORIGINS: 403, con cualquier método.
 *  - Petición CON Origin permitido: se añaden las cabeceras CORS.
 *
 * Limitación: Origin solo protege frente a navegadores; un script puede falsificarlo.
 * En producción, el origen del sitio (https://tu-dominio) debe estar en ALLOWED_ORIGINS.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    /** @param string[] $allowedOrigins */
    public function __construct(array $allowedOrigins)
    {
        // Normaliza (minúsculas, sin "/" final) e ignora "*", que no es válido con credenciales.
        $normalized = array_map(
            static fn (mixed $origin): string => rtrim(strtolower(trim((string) $origin)), '/'),
            $allowedOrigins
        );

        $this->allowedOrigins = array_values(array_unique(array_filter(
            $normalized,
            static fn (string $origin): bool => $origin !== '' && $origin !== '*'
        )));
    }

    public function handle(Request $request): ?Response
    {
        // La respuesta depende del Origin: evita que una caché mezcle respuestas.
        header('Vary: Origin', false);

        $origin = $request->header('origin');

        if ($origin === null) {
            return $request->method === 'OPTIONS' ? Response::noContent() : null;
        }

        $normalized = strtolower(trim($origin));

        if (!in_array($normalized, $this->allowedOrigins, true)) {
            throw new HttpException(403, 'ORIGIN_NOT_ALLOWED', 'Origen no permitido.');
        }

        // Solo se refleja un valor que ya está en la lista blanca.
        header('Access-Control-Allow-Origin: ' . $normalized);
        header('Access-Control-Allow-Credentials: true');

        if ($request->method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
            header('Access-Control-Max-Age: 600');

            return Response::noContent();
        }

        return null;
    }
}