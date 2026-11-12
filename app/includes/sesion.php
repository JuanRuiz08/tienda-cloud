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

/** Devuelve el id del cliente en sesión; si no hay sesión, manda al login. */
function requerir_usuario(): int
{
    iniciar_sesion();
    if (empty($_SESSION['user_id'])) {
        redirigir('login.php');
    }
    return (int) $_SESSION['user_id'];
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
