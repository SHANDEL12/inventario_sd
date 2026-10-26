<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\HttpException;
use PDO;
use PDOException;

/** Crea la conexión PDO de forma perezosa (solo cuando se necesita) y la reutiliza. */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private array $config, private Logger $logger)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['name']
        );

        try {
            $pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => $this->config['connect_timeout'],
            ]);
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            $this->logger->error('db_connection_failed', ['code' => (string) $e->getCode(), 'message' => $e->getMessage()]);
            throw new HttpException(503, 'SERVICE_UNAVAILABLE', 'El servicio no está disponible. Intenta más tarde.');
        }

        return $this->pdo = $pdo;
    }
}