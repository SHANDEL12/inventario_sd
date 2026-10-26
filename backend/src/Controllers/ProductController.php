<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\ProductRepository;

final class ProductController
{
    public function __construct(private ProductRepository $products)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) ($request->query['q'] ?? ''));

        if (strlen($search) > 100) {
            throw new HttpException(422, 'INVALID_SEARCH', 'La búsqueda es demasiado larga.');
        }

        return Response::success([
            'productos' => $this->products->all($search),
            'estadisticas' => $this->products->stats(),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = $request->json();

        $fields = [
            'numero_serie' => 100,
            'tipo_equipo' => 80,
            'marca' => 100,
            'modelo' => 150,
        ];

        $data = [];

        foreach ($fields as $field => $maxLength) {
            $value = $input[$field] ?? null;

            if (!is_string($value) || trim($value) === '') {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Completa todos los campos obligatorios.', [
                    $field => 'Este campo es obligatorio.',
                ]);
            }

            $value = trim($value);

            if (mb_strlen($value) > $maxLength) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Uno de los campos excede la longitud permitida.', [
                    $field => 'Máximo ' . $maxLength . ' caracteres.',
                ]);
            }

            $data[$field] = $value;
        }

        $clasificacion = $input['clasificacion'] ?? 'Stock';
        $estado = $input['estado_equipo'] ?? 'Nuevo';
        $cantidad = filter_var($input['cantidad'] ?? 1, FILTER_VALIDATE_INT);
        $observaciones = $input['observaciones'] ?? '';

        if (!in_array($clasificacion, ['Basura', 'Repuesto', 'Pendiente', 'Stock'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'La clasificación no es válida.');
        }

        if (!in_array($estado, ['Nuevo', 'Usado'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'El estado del equipo no es válido.');
        }

        if ($cantidad === false || $cantidad < 1 || $cantidad > 100000) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'La cantidad debe ser un entero entre 1 y 100000.');
        }

        if (!is_string($observaciones) || mb_strlen($observaciones) > 5000) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Las observaciones no son válidas.');
        }

        $data['clasificacion'] = $clasificacion;
        $data['estado_equipo'] = $estado;
        $data['cantidad'] = $cantidad;
        $data['observaciones'] = trim($observaciones);

        $user = $request->attribute('user');
        $userId = is_array($user) && isset($user['id']) ? (int) $user['id'] : null;

        try {
            $id = $this->products->create($data, $userId);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new HttpException(409, 'DUPLICATE_SERIAL', 'Ya existe un producto con ese número de serie.');
            }

            throw $e;
        }

        return Response::success([
            'id' => $id,
            'message' => 'Producto registrado correctamente.',
        ], 201);
    }
}
