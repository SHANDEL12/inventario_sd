<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;

final class Request
{
    private const MAX_BODY_BYTES = 16384;

    private array $attributes = [];
    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $headers,
        public readonly array $query,
        private string $rawBody,
        public readonly string $ip,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $path = self::resolvePath(
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            (string) ($_SERVER['SCRIPT_NAME'] ?? '')
        );

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        $raw = '';
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
            if (strlen($raw) > self::MAX_BODY_BYTES) {
                throw new HttpException(413, 'PAYLOAD_TOO_LARGE', 'El cuerpo de la solicitud es demasiado grande.');
            }
        }

        return new self($method, $path, $headers, $_GET, $raw, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /**
     * Quita la carpeta de instalación (p. ej. /inventario-sd/backend/public) y deja /api/...
     * La comparación no distingue mayúsculas porque en Windows Apache sirve
     * /Inventario-SD/... igual que /inventario-sd/...
     */
    private static function resolvePath(string $requestUri, string $scriptName): string
    {
        // Un "//" inicial haría que parse_url lo interprete como host.
        $uriPath = parse_url('/' . ltrim($requestUri, '/'), PHP_URL_PATH);
        $path = is_string($uriPath) ? $uriPath : '/';

        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($base !== '' && $base !== '.' && strncasecmp($path, $base, strlen($base)) === 0) {
            $rest = substr($path, strlen($base));
            if ($rest === '' || $rest[0] === '/') { // evita confundir /public con /publicidad
                $path = $rest;
            }
        }

        return '/' . trim($path, '/');
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** Cuerpo JSON (debe ser un objeto). */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        $contentType = strtolower((string) $this->header('content-type'));
        if (!str_starts_with($contentType, 'application/json')) {
            throw new HttpException(415, 'UNSUPPORTED_MEDIA_TYPE', 'El Content-Type debe ser application/json.');
        }

        try {
            $data = json_decode($this->rawBody, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'INVALID_JSON', 'El cuerpo de la solicitud no es un JSON válido.');
        }

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new HttpException(400, 'INVALID_JSON', 'El cuerpo debe ser un objeto JSON.');
        }

        return $this->json = $data;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}