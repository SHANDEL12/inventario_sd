<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class UserRepository
{
    private const COLUMNS = 'id, nombre_completo, username, password_hash, rol, '
        . 'puede_registrar_movimientos, puede_ver_reportes, activo, intentos_fallidos, bloqueado_hasta';

    public function __construct(private Database $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM usuarios WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(
        string $username,
        string $nombreCompleto,
        string $passwordHash,
        string $rol,
        bool $puedeRegistrarMovimientos,
        bool $puedeVerReportes,
    ): int {
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre_completo, username, password_hash, rol, puede_registrar_movimientos, puede_ver_reportes)
             VALUES (:nombre, :username, :hash, :rol, :mov, :rep)'
        );
        $stmt->execute([
            'nombre'   => $nombreCompleto,
            'username' => $username,
            'hash'     => $passwordHash,
            'rol'      => $rol,
            'mov'      => $puedeRegistrarMovimientos ? 1 : 0,
            'rep'      => $puedeVerReportes ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function registerFailedAttempt(int $id, int $attempts, ?string $lockedUntil): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE usuarios SET intentos_fallidos = :attempts, bloqueado_hasta = :locked WHERE id = :id'
        );
        $stmt->execute(['attempts' => $attempts, 'locked' => $lockedUntil, 'id' => $id]);
    }

    public function registerSuccessfulLogin(int $id): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = :now WHERE id = :id'
        );
        $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }
}