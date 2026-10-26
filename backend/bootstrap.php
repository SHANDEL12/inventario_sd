<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Support\Container;
use App\Support\Database;
use App\Support\Logger;
use App\Support\Session;
use App\Validation\Validator;
use Dotenv\Dotenv;
use App\Controllers\ProductController;
use App\Repositories\ProductRepository;

require __DIR__ . '/vendor/autoload.php';

// Los errores nunca se muestran al cliente; van a un archivo de log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/php-error.log');
error_reporting(E_ALL);
date_default_timezone_set('UTC');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0 || (error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER'])->notEmpty();
$dotenv->required('DB_PASS'); // puede estar vacío, pero debe existir

$config = require __DIR__ . '/config/config.php';

// Contenedor mínimo de dependencias (inyección por constructor).
$container = new Container();

$container->set('config', static fn (): array => $config);
$container->set(Logger::class, static fn (): Logger => new Logger(__DIR__ . '/storage/logs/app.log'));
$container->set(Database::class, static fn (Container $c): Database => new Database($c->get('config')['db'], $c->get(Logger::class)));
$container->set(Session::class, static fn (Container $c): Session => new Session($c->get('config')['session']));
$container->set(Validator::class, static fn (): Validator => new Validator());

$container->set(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(Database::class)));
$container->set(ProductRepository::class, static fn (Container $c): ProductRepository =>
    new ProductRepository($c->get(Database::class))
);

$container->set(ProductController::class, static fn (Container $c): ProductController =>
    new ProductController($c->get(ProductRepository::class))
);
$container->set(AuthService::class, static fn (Container $c): AuthService => new AuthService(
    $c->get(UserRepository::class),
    $c->get(Session::class),
    $c->get(Logger::class),
    $c->get('config')['auth']['max_attempts'],
    $c->get('config')['auth']['lock_minutes'],
));

$container->set(AuthController::class, static fn (Container $c): AuthController => new AuthController($c->get(AuthService::class), $c->get(Validator::class)));
$container->set(AdminController::class, static fn (): AdminController => new AdminController());

$container->set(CorsMiddleware::class, static fn (Container $c): CorsMiddleware => new CorsMiddleware($c->get('config')['allowed_origins']));
$container->set(AuthMiddleware::class, static fn (Container $c): AuthMiddleware => new AuthMiddleware($c->get(AuthService::class)));
$container->set(CsrfMiddleware::class, static fn (Container $c): CsrfMiddleware => new CsrfMiddleware($c->get(Session::class)));
$container->set('middleware.admin', static fn (Container $c): RoleMiddleware => new RoleMiddleware(['admin'], $c->get(Logger::class)));

return $container;