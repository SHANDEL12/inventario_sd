<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Session
{
    public function __construct(private array $config)
    {
    }

    /** Reanuda una sesión existente. Devuelve false si no hay cookie o la sesión ya no es válida. */
    public function resume(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (!isset($_COOKIE[$this->config['name']])) {
            return false; // no crea sesiones para visitantes anónimos
        }

        $this->start();

        if (!$this->isValid()) {
            $this->destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();

        return true;
    }

    /** Inicia sesión: regenera el ID (contra fijación de sesión) y crea el token CSRF. */
    public function login(int $userId): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }

        $_SESSION = [];

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('No se pudo regenerar el identificador de sesión.');
        }

        $now = time();
        $_SESSION = [
            'user_id'       => $userId,
            'created_at'    => $now,
            'last_activity' => $now,
            'csrf_token'    => bin2hex(random_bytes(32)),
        ];

        return $_SESSION['csrf_token'];
    }

    public function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function csrfToken(): ?string
    {
        $token = $_SESSION['csrf_token'] ?? null;

        return is_string($token) ? $token : null;
    }

    public function validateCsrf(string $token): bool
    {
        $expected = $this->csrfToken();

        return $expected !== null && hash_equals($expected, $token);
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        if (!headers_sent()) {
            setcookie($this->config['name'], '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $this->isSecure(),
                'httponly' => true,
                'samesite' => $this->config['samesite'],
            ]);
        }
    }

    private function start(): void
    {
        session_name($this->config['name']);

        // Carpeta propia: otras aplicaciones de XAMPP no pueden borrar nuestras sesiones.
        session_save_path($this->prepareSavePath());

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $this->config['absolute_timeout']);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_set_cookie_params([
            'lifetime' => 0, // cookie de sesión: se borra al cerrar el navegador
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->isSecure(),
            'httponly' => true,
            'samesite' => $this->config['samesite'],
        ]);

        if (!session_start()) {
            throw new RuntimeException('No se pudo iniciar la sesión.');
        }
    }

    /** backend/storage/sessions (la carpeta backend/ no es accesible desde la web). */
    private function prepareSavePath(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de sesiones.');
        }

        return $dir;
    }

    private function isValid(): bool
    {
        if (!isset($_SESSION['user_id'], $_SESSION['created_at'], $_SESSION['last_activity'])) {
            return false;
        }

        $now = time();

        return ($now - (int) $_SESSION['last_activity']) <= $this->config['idle_timeout']
            && ($now - (int) $_SESSION['created_at']) <= $this->config['absolute_timeout'];
    }

    private function isSecure(): bool
    {
        return match ($this->config['secure']) {
            'true'  => true,
            'false' => false,
            default => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        };
    }
}