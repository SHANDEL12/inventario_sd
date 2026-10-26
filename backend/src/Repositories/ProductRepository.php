<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class ProductRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(string $search = ''): array
    {
        $sql = 'SELECT id, numero_serie, tipo_equipo, marca, modelo,
                       clasificacion, cantidad, estado_equipo,
                       observaciones, creado_en
                FROM productos';

        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE numero_serie LIKE :serie
                      OR tipo_equipo LIKE :tipo
                      OR marca LIKE :marca
                      OR modelo LIKE :modelo';
            $term = '%' . $search . '%';
            $params = [
                'serie' => $term,
                'tipo' => $term,
                'marca' => $term,
                'modelo' => $term,
            ];
        }

        $sql .= ' ORDER BY creado_en DESC, id DESC LIMIT 500';

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(array $data, ?int $userId): int
    {
        $sql = 'INSERT INTO productos
                (numero_serie, tipo_equipo, marca, modelo, clasificacion,
                 cantidad, estado_equipo, observaciones, creado_por)
                VALUES
                (:serie, :tipo, :marca, :modelo, :clasificacion,
                 :cantidad, :estado, :observaciones, :usuario)';

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([
            'serie' => $data['numero_serie'],
            'tipo' => $data['tipo_equipo'],
            'marca' => $data['marca'],
            'modelo' => $data['modelo'],
            'clasificacion' => $data['clasificacion'],
            'cantidad' => $data['cantidad'],
            'estado' => $data['estado_equipo'],
            'observaciones' => $data['observaciones'] !== '' ? $data['observaciones'] : null,
            'usuario' => $userId,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function stats(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total_productos,
                    COALESCE(SUM(cantidad), 0) AS total_unidades,
                    COALESCE(SUM(clasificacion = 'Stock'), 0) AS en_stock,
                    COALESCE(SUM(clasificacion = 'Pendiente'), 0) AS pendientes,
                    COALESCE(SUM(clasificacion = 'Repuesto'), 0) AS repuestos,
                    COALESCE(SUM(clasificacion = 'Basura'), 0) AS basura
                FROM productos";

        return $this->db->pdo()->query($sql)->fetch() ?: [];
    }
}
