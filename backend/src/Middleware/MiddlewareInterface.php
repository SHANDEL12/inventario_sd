<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

interface MiddlewareInterface
{
    /**
     * Devuelve null para continuar, una Response para responder de inmediato,
     * o lanza HttpException para rechazar la petición.
     */
    public function handle(Request $request): ?Response;
}