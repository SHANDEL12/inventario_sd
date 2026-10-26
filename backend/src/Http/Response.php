<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    private function __construct(
        private int $status,
        private ?array $payload,
    ) {
    }

    public static function success(mixed $data = null, int $status = 200): self
    {
        return new self($status, ['success' => true, 'data' => $data]);
    }

    public static function error(int $status, string $code, string $message, array $details = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return new self($status, ['success' => false, 'error' => $error]);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public function send(): void
    {
        // Se codifica primero: si falla, todavía no se tocó ninguna cabecera
        // y el manejador de errores puede enviar una respuesta de error limpia.
        $body = $this->payload === null
            ? ''
            : json_encode(
                $this->payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );

        http_response_code($this->status);
        if ($this->payload !== null) {
            header('Content-Type: application/json; charset=utf-8');
        }
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        echo $body;
    }
}