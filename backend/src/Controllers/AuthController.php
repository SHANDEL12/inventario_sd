<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Validation\Validator;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private Validator $validator,
    ) {
    }

    public function login(Request $request): Response
    {
        $data = $this->validator->validateLogin($request->json());

        return Response::success($this->auth->login($data['username'], $data['password'], $request->ip));
    }

    public function me(Request $request): Response
    {
        return Response::success([
            'user'       => $request->attribute('user'),
            'csrf_token' => $this->auth->csrfToken(),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->attribute('user'), $request->ip);

        return Response::success(['message' => 'Sesión cerrada.']);
    }
}