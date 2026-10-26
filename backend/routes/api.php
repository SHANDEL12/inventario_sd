<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Controllers\ProductController;

/** @var \App\Http\Router $router */
/** @var \App\Support\Container $container */

$auth  = $container->get(AuthMiddleware::class);
$csrf  = $container->get(CsrfMiddleware::class);
$admin = $container->get('middleware.admin');

$authController  = $container->get(AuthController::class);
$adminController = $container->get(AdminController::class);
$productController = $container->get(ProductController::class);

$router->get('/api/productos', [$productController, 'index'], [$auth]);
$router->post('/api/productos', [$productController, 'store'], [$auth, $csrf]);

// Autenticación
$router->post('/api/auth/login',  [$authController, 'login']);
$router->post('/api/auth/logout', [$authController, 'logout'], [$auth, $csrf]);
$router->get('/api/auth/me',      [$authController, 'me'],     [$auth]);

// TEMPORAL: sirve solo para comprobar el control de roles. Se eliminará más adelante.
$router->get('/api/admin/ping', [$adminController, 'ping'], [$auth, $admin]);