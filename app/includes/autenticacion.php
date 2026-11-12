<?php
/** Inicio de sesión: verificación de la contraseña y datos de sesión según el rol. */

/**
 * Devuelve el usuario (user_type, name, email, id) si el correo y la contraseña son correctos; si no, null.
 * Las cuentas antiguas con hash MD5 se aceptan una vez y se migran a bcrypt.
 */
function autenticar(mysqli $conn, string $email, string $clave): ?array
{
    // loginUser devuelve el hash guardado; la contraseña se verifica aquí con password_verify().
    $usuario = consulta($conn, 'CALL loginUser(?)', 's', [$email])[0] ?? null;
    if (!$usuario || !$usuario['password']) {
        return null;
    }

    $hash = $usuario['password'];
    if (password_verify($clave, $hash)) {
        return $usuario;
    }
    if (preg_match('/^[a-f0-9]{32}$/', $hash) && hash_equals($hash, md5($clave))) {
        consulta($conn, 'CALL actualizarPasswordUsuario(?, ?)', 'is', [$usuario['id'], password_hash($clave, PASSWORD_DEFAULT)]);
        app_log('info', 'contraseña migrada de MD5 a bcrypt para user_id=' . $usuario['id']);
        return $usuario;
    }
    return null;
}

/** Guarda al usuario en sesión (admin_* o user_* según su rol) y devuelve la página a la que debe ir. */
function abrir_sesion_de(array $usuario): string
{
    session_regenerate_id(true);
    app_log('info', "login correcto user_id={$usuario['id']} rol={$usuario['user_type']}");

    if ($usuario['user_type'] === 'admin') {
        $_SESSION['admin_name'] = $usuario['name'];
        $_SESSION['admin_email'] = $usuario['email'];
        $_SESSION['admin_id'] = $usuario['id'];
        return 'admin_pagina.php';
    }
    $_SESSION['user_name'] = $usuario['name'];
    $_SESSION['user_email'] = $usuario['email'];
    $_SESSION['user_id'] = $usuario['id'];
    return 'inicio.php';
}
