<?php
/**
 * Sesión y control de acceso compartidos por todas las páginas.
 * (El manejador de sesiones en MySQL lo registra bootstrap.php antes de cada script.)
 */

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function redirigir(string $url): never
{
    header("Location: $url");
    exit();
}

/** Devuelve el id del cliente en sesión, o null si es un invitado (para las páginas públicas). */
function usuario_actual(): ?int
{
    iniciar_sesion();
    return empty($_SESSION['user_id']) ? null : (int) $_SESSION['user_id'];
}

/** Para acciones que exigen cuenta (carrito, pago, pedidos): sin sesión, manda al login con un aviso. */
function requerir_usuario(): int
{
    $id = usuario_actual();
    if ($id === null) {
        redirigir('login.php?requiere=1');
    }
    return $id;
}

/** Devuelve el id del administrador en sesión; si no hay sesión, manda al login. */
function requerir_admin(): int
{
    iniciar_sesion();
    if (empty($_SESSION['admin_id'])) {
        redirigir('login.php');
    }
    return (int) $_SESSION['admin_id'];
}
