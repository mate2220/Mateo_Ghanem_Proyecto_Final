CREATE DATABASE IF NOT EXISTS TP_CONTROL_GASTOS
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE TP_CONTROL_GASTOS;

CREATE TABLE IF NOT EXISTS usuarios (
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
);

CREATE TABLE IF NOT EXISTS categorias (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nombre     VARCHAR(60)  NOT NULL,
    tipo       ENUM('ingreso','gasto') NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categoria_usuario (usuario_id, nombre, tipo),
    CONSTRAINT fk_categorias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS movimientos (
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
);