<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\HttpException;
use App\Http\Response;
use Throwable;

final class ErrorHandler
{
    /** Rechazos de seguridad que se registran aunque sean errores 4xx. */
    private const AUDITED_CODES = ['ORIGIN_NOT_ALLOWED', 'CSRF_INVALID'];

    public static function toResponse(Throwable $e, Logger $logger, bool $debug): Response
    {
        if ($e instanceof HttpException) {
            if ($e->status >= 500) {
                $logger->error('http_error', ['status' => $e->status, 'code' => $e->errorCode]);
            } elseif (in_array($e->errorCode, self::AUDITED_CODES, true)) {
                $logger->warning('request_rejected', [
                    'code'   => $e->errorCode,
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'origin' => $_SERVER['HTTP_ORIGIN'] ?? '',
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);
            }

            return Response::error($e->status, $e->errorCode, $e->getMessage(), $e->details);
        }

        $logger->error('unhandled_exception', [
            'type'    => get_class($e),
            'message' => $e->getMessage(),
            'file'    => basename($e->getFile()),
            'line'    => $e->getLine(),
        ]);

        // El detalle solo se expone en desarrollo (APP_DEBUG=true).
        $details = $debug ? ['exception' => get_class($e), 'message' => $e->getMessage()] : [];

        return Response::error(500, 'INTERNAL_ERROR', 'Ocurrió un error interno.', $details);
    }
}