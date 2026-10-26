<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;
use App\Repositories\UserRepository;
use App\Support\Logger;
use App\Support\Session;

final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private Session $session,
        private Logger $logger,
        private int $maxAttempts,
        private int $lockMinutes,
    ) {
    }

    /** @return array{user: array, csrf_token: string} */
    public function login(string $username, string $password, string $ip): array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            password_hash($password, PASSWORD_DEFAULT); // iguala el tiempo de respuesta con un usuario real
            $this->logger->warning('login_failed', ['reason' => 'unknown_user', 'username' => $username, 'ip' => $ip]);
            throw $this->invalidCredentials();
        }

        if ($this->isLocked($user)) {
            $this->logger->warning('login_blocked', ['user_id' => $user['id'], 'ip' => $ip]);
            throw new HttpException(429, 'TOO_MANY_ATTEMPTS', 'Demasiados intentos fallidos. Intenta de nuevo más tarde.');
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $this->registerFailure($user, $ip);
            throw $this->invalidCredentials();
        }

        if ((int) $user['activo'] !== 1) {
            $this->logger->warning('login_failed', ['reason' => 'inactive_user', 'user_id' => $user['id'], 'ip' => $ip]);
            throw $this->invalidCredentials();
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        $this->users->registerSuccessfulLogin((int) $user['id']);
        $csrfToken = $this->session->login((int) $user['id']);

        $this->logger->info('login_success', ['user_id' => $user['id'], 'username' => $username, 'ip' => $ip]);

        return ['user' => $this->publicUser($user), 'csrf_token' => $csrfToken];
    }

    /** Usuario de la sesión actual (leído de la BD en cada petición) o null. */
    public function currentUser(): ?array
    {
        if (!$this->session->resume()) {
            return null;
        }

        $id = $this->session->userId();
        $user = $id !== null ? $this->users->findById($id) : null;

        if ($user === null || (int) $user['activo'] !== 1) {
            $this->session->destroy();
            return null;
        }

        return $this->publicUser($user);
    }

    public function csrfToken(): ?string
    {
        return $this->session->csrfToken();
    }

    public function logout(array $user, string $ip): void
    {
        $this->logger->info('logout', ['user_id' => $user['id'], 'ip' => $ip]);
        $this->session->destroy();
    }

    private function registerFailure(array $user, string $ip): void
    {
        $attempts = (int) $user['intentos_fallidos'] + 1;
        $lockedUntil = null;

        if ($attempts >= $this->maxAttempts) {
            $lockedUntil = gmdate('Y-m-d H:i:s', time() + $this->lockMinutes * 60);
            $attempts = 0;
            $this->logger->warning('account_locked', ['user_id' => $user['id'], 'ip' => $ip]);
        }

        $this->users->registerFailedAttempt((int) $user['id'], $attempts, $lockedUntil);
        $this->logger->warning('login_failed', ['reason' => 'bad_password', 'user_id' => $user['id'], 'ip' => $ip]);
    }

    private function isLocked(array $user): bool
    {
        $until = $user['bloqueado_hasta'] ?? null;

        return $until !== null && strtotime($until . ' UTC') > time();
    }

    private function invalidCredentials(): HttpException
    {
        return new HttpException(401, 'INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
    }

    private function publicUser(array $user): array
    {
        $isAdmin = $user['rol'] === 'admin';

        return [
            'id'              => (int) $user['id'],
            'username'        => $user['username'],
            'nombre_completo' => $user['nombre_completo'],
            'rol'             => $user['rol'],
            'permisos'        => [
                'registrar_movimientos' => $isAdmin || (int) $user['puede_registrar_movimientos'] === 1,
                'ver_reportes'          => $isAdmin || (int) $user['puede_ver_reportes'] === 1,
            ],
        ];
    }
}