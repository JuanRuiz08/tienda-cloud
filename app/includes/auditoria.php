<?php
/** Registro de auditoría: quién hizo qué en el panel de administración (se consulta en admin_actividad.php). */

function auditar(mysqli $conn, string $accion, string $detalle = ''): void
{
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    consulta($conn,
        'INSERT INTO `auditoria` (admin_id, admin_nombre, accion, detalle, ip) VALUES (?, ?, ?, ?, ?)',
        'issss',
        [$id ?: null, (string) ($_SESSION['admin_name'] ?? 'desconocido'), $accion, mb_substr($detalle, 0, 500), ip_cliente()]);
    app_log('info', "auditoría: $accion — $detalle");
}