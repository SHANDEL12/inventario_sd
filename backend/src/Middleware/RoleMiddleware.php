<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Support\Logger;

final class RoleMiddleware implements MiddlewareInterface
{
    /** @param string[] $roles */
    public function __construct(private array $roles, private Logger $logger)
    {
    }

    public function handle(Request $request): ?Response
    {
        $user = $request->attribute('user');

        if (!is_array($user) || !in_array($user['rol'] ?? '', $this->roles, true)) {
            $this->logger->warning('access_denied', [
                'user_id' => is_array($user) ? ($user['id'] ?? null) : null,
                'path'    => $request->path,
                'ip'      => $request->ip,
            ]);
            throw new HttpException(403, 'FORBIDDEN', 'No tienes permisos para esta operación.');
        }

        return null;
    }
}