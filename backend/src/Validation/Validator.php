<?php

declare(strict_types=1);

namespace App\Validation;

use App\Http\HttpException;

final class Validator
{
    // bcrypt solo usa los primeros 72 bytes de la contraseña.
    private const MAX_PASSWORD_BYTES = 72;

    public static function isValidUsername(string $value): bool
    {
        // \z (y no $) para no aceptar un salto de línea al final.
        return preg_match('/^[A-Za-z0-9._-]{3,50}\z/', $value) === 1;
    }

    /** @return array{username: string, password: string} */
    public function validateLogin(array $input): array
    {
        $errors = [];
        $username = $input['username'] ?? null;
        $password = $input['password'] ?? null;

        if (!is_string($username) || trim($username) === '') {
            $errors['username'] = 'El usuario es obligatorio.';
        } elseif (!self::isValidUsername(trim($username))) {
            $errors['username'] = 'El usuario tiene un formato no válido.';
        }

        if (!is_string($password) || $password === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        } elseif (strlen($password) > self::MAX_PASSWORD_BYTES) {
            $errors['password'] = 'La contraseña es demasiado larga.';
        }

        if ($errors !== []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', $errors);
        }

        return ['username' => trim($username), 'password' => $password];
    }
}