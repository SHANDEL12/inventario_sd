-- INVENTARIO SD - Esquema inicial (solo usuarios)
-- Compatible con MariaDB 10.4+ (XAMPP) y MySQL 8.

CREATE DATABASE IF NOT EXISTS inventario_sd
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventario_sd;

CREATE TABLE IF NOT EXISTS usuarios (
    id                          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre_completo             VARCHAR(100)     NOT NULL,
    username                    VARCHAR(50)      NOT NULL,
    password_hash               VARCHAR(255)     NOT NULL,
    rol                         ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    puede_registrar_movimientos TINYINT(1)       NOT NULL DEFAULT 1,
    puede_ver_reportes          TINYINT(1)       NOT NULL DEFAULT 0,
    activo                      TINYINT(1)       NOT NULL DEFAULT 1,
    intentos_fallidos           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta             DATETIME         NULL DEFAULT NULL, -- en UTC
    ultimo_acceso               DATETIME         NULL DEFAULT NULL, -- en UTC
    creado_en                   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en              DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;