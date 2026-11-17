<?php
/** 002: registro de auditoría del panel, intentos de inicio de sesión y ajustes (tasa de cambio en caché). */
return function (mysqli $db): void {
    $tabla = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $db->query("CREATE TABLE IF NOT EXISTS auditoria (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        admin_nombre VARCHAR(100) NOT NULL,
        accion VARCHAR(60) NOT NULL,
        detalle VARCHAR(500) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_auditoria_creado (creado)
    ) $tabla");

    $db->query("CREATE TABLE IF NOT EXISTS intentos_login (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        correo VARCHAR(190) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        creado INT UNSIGNED NOT NULL,
        KEY idx_intentos_correo (correo, creado),
        KEY idx_intentos_ip (ip, creado)
    ) $tabla");

    $db->query("CREATE TABLE IF NOT EXISTS config (
        clave VARCHAR(60) NOT NULL PRIMARY KEY,
        valor VARCHAR(255) NOT NULL,
        actualizado INT UNSIGNED NOT NULL
    ) $tabla");
};