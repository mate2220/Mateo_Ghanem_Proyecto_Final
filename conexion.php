<?php

$host     = 'localhost';
$dbname   = 'TP_CONTROL_GASTOS';
$usuario  = 'root';
$password = '';

try {
    $conexion = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $conexion->exec("CREATE DATABASE IF NOT EXISTS `$dbname`
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conexion->exec("USE `$dbname`");
    $conexion->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre        VARCHAR(80)  NOT NULL,
        apellido      VARCHAR(80)  NOT NULL,
        dni           VARCHAR(20)  NOT NULL,
        email         VARCHAR(254) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        rol           ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
        creado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archivado_en  TIMESTAMP    NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_usuarios_dni (dni),
        UNIQUE KEY uq_usuarios_email (email)
    )");
    $conexion->exec("CREATE TABLE IF NOT EXISTS categorias (
        id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_id INT UNSIGNED NOT NULL,
        nombre     VARCHAR(60)  NOT NULL,
        tipo       ENUM('ingreso','gasto') NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_categoria_usuario (usuario_id, nombre, tipo),
        CONSTRAINT fk_categorias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    )");
    $conexion->exec("CREATE TABLE IF NOT EXISTS movimientos (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario_id   INT UNSIGNED NOT NULL,
        categoria_id INT UNSIGNED NOT NULL,
        monto        DECIMAL(12,2) NOT NULL,
        descripcion  VARCHAR(255) DEFAULT NULL,
        fecha        DATE NOT NULL,
        creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_mov_usuario_fecha (usuario_id, fecha),
        CONSTRAINT fk_mov_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE CASCADE,
        CONSTRAINT fk_mov_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT
    )");
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}