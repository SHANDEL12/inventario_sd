<?php

declare(strict_types=1);

namespace App\Support;

/** Registro básico en archivo. Nunca pases contraseñas ni tokens en $context. */
final class Logger
{
    public function __construct(private string $file)
    {
    }

    public function info(string $event, array $context = []): void
    {
        $this->write('INFO', $event, $context);
    }

    public function warning(string $event, array $context = []): void
    {
        $this->write('WARNING', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('ERROR', $event, $context);
    }

    private function write(string $level, string $event, array $context): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $safe[$key] = (is_scalar($value) || $value === null) ? $this->clean((string) $value) : '[omitido]';
        }

        $line = sprintf(
            "%s [%s] %s %s\n",
            gmdate('c'),
            $level,
            $event,
            json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Quita saltos de línea y caracteres de control (evita inyección en el log). */
    private function clean(string $value): string
    {
        return mb_substr((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value), 0, 200);
    }
}