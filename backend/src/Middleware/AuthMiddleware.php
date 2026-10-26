<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request): ?Response
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            throw new HttpException(401, 'UNAUTHENTICATED', 'Debes iniciar sesión.');
        }

        $request->setAttribute('user', $user);

        return null;
    }
}