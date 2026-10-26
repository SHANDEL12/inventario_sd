<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Support\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private Session $session)
    {
    }

    public function handle(Request $request): ?Response
    {
        if (!in_array($request->method, self::UNSAFE_METHODS, true)) {
            return null;
        }

        $token = $request->header('x-csrf-token');

        if ($token === null || !$this->session->validateCsrf($token)) {
            throw new HttpException(403, 'CSRF_INVALID', 'Token CSRF ausente o inválido.');
        }

        return null;
    }
}