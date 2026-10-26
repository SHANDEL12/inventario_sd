<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

/** TEMPORAL: se usa solo para comprobar el control de roles. */
final class AdminController
{
    public function ping(Request $request): Response
    {
        $user = $request->attribute('user');

        return Response::success([
            'message'  => 'Acceso de administrador verificado.',
            'username' => $user['username'],
        ]);
    }
}