<?php
/**
 * Protección CSRF: cada sesión tiene un token secreto que viaja en un campo oculto de todos los formularios
 * (y en la cabecera X-CSRF-Token de las llamadas fetch). Toda petición POST sin el token correcto se rechaza,
 * así otro sitio no puede hacer que el navegador del usuario envíe acciones en su nombre.
 */

function csrf_token(): string
{
    iniciar_sesion();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto para poner dentro de cada <form method="post">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Rechaza (403) cualquier POST cuyo token no coincida con el de la sesión. Se llama desde includes/app.php. */
function csrf_exigir(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'api_wompi.php') {
        return;   // webhook de Wompi: no trae sesión; api_wompi.php valida el evento y reconsulta la transacción a Wompi
    }
    iniciar_sesion();
    $enviado = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $esperado = (string) ($_SESSION['csrf'] ?? '');

    if ($esperado !== '' && hash_equals($esperado, $enviado)) {
        return;
    }

    app_log('warning', 'POST rechazado por token CSRF inválido: ' . ($_SERVER['REQUEST_URI'] ?? ''));
    http_response_code(403);
    $json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || isset($_SERVER['HTTP_X_CSRF_TOKEN']);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'La sesión de seguridad venció. Recarga la página.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Sesión vencida</title>'
           . '<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1.5rem;line-height:1.5">'
           . '<h1>La sesión de seguridad venció</h1>'
           . '<p>Por tu seguridad no se hizo ningún cambio. Vuelve atrás, recarga la página e inténtalo de nuevo.</p>'
           . '<p><a href="javascript:history.back()">Volver</a> · <a href="inicio.php">Ir al inicio</a></p></body></html>';
    }
    exit();
}