<?php
/** Cabeceras de seguridad por respuesta, IP del cliente y límite de intentos de inicio de sesión. */

/**
 * Política de contenido: solo se cargan scripts, estilos, fuentes e imágenes de este sitio y de los servicios
 * que usa (Google Fonts, Font Awesome). Un script inyectado desde otro origen no se ejecuta.
 * CSP_MODO: "enforce" (por defecto), "report" (solo registra en la consola del navegador) u "off".
 */
function enviar_cabeceras_seguridad(): void
{
    $modo = getenv('CSP_MODO') ?: 'enforce';
    if ($modo !== 'off') {
        $politica = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
        ]);
        header(($modo === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy') . ': ' . $politica);
    }
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (https_activo()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** IP del cliente. Detrás del proxy de Railway se toma el último salto de X-Forwarded-For (el que añadió el proxy). */
function ip_cliente(): string
{
    $reenviada = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($reenviada !== '') {
        $saltos = array_map('trim', explode(',', $reenviada));
        $ip = (string) end($saltos);
    } else {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
    return substr($ip, 0, 45);
}

const LOGIN_MAX_POR_CORREO = 6;     // fallos por correo en la ventana
const LOGIN_MAX_POR_IP = 30;        // fallos por IP en la ventana (más holgado: varias personas pueden compartir IP)
const LOGIN_VENTANA = 900;          // 15 minutos

/** Segundos que faltan para poder volver a intentar iniciar sesión (0 = permitido). */
function login_bloqueado_segundos(mysqli $conn, string $correo): int
{
    $desde = time() - LOGIN_VENTANA;
    $ip = ip_cliente();
    $correo = mb_strtolower(trim($correo));

    consulta($conn, 'DELETE FROM `intentos_login` WHERE creado < ?', 'i', [time() - 86400]);   // limpieza

    foreach ([
        ['SELECT COUNT(*), MIN(creado) FROM `intentos_login` WHERE correo = ? AND creado >= ?', 'si', [$correo, $desde], LOGIN_MAX_POR_CORREO],
        ['SELECT COUNT(*), MIN(creado) FROM `intentos_login` WHERE ip = ? AND creado >= ?', 'si', [$ip, $desde], LOGIN_MAX_POR_IP],
    ] as [$sql, $tipos, $params, $maximo]) {
        $fila = consulta($conn, $sql, $tipos, $params)[0] ?? null;
        $fila = $fila ? array_values($fila) : [0, null];
        if ((int) $fila[0] >= $maximo && $fila[1] !== null) {
            return max(1, (int) $fila[1] + LOGIN_VENTANA - time());
        }
    }
    return 0;
}

function login_registrar_fallo(mysqli $conn, string $correo): void
{
    consulta($conn, 'INSERT INTO `intentos_login` (correo, ip, creado) VALUES (?, ?, ?)', 'ssi',
        [mb_strtolower(trim($correo)), ip_cliente(), time()]);
}

/** Un inicio de sesión correcto borra los fallos previos de ese correo. */
function login_limpiar(mysqli $conn, string $correo): void
{
    consulta($conn, 'DELETE FROM `intentos_login` WHERE correo = ?', 's', [mb_strtolower(trim($correo))]);
}

const CLAVE_MINIMA = 8;

/** Política de contraseñas: mínimo 8 caracteres, con al menos una letra y un número. Devuelve el error o null. */
function validar_clave(string $clave): ?string
{
    if (mb_strlen($clave) < CLAVE_MINIMA) {
        return 'La contraseña debe tener al menos ' . CLAVE_MINIMA . ' caracteres.';
    }
    if (!preg_match('/\pL/u', $clave) || !preg_match('/\d/', $clave)) {
        return 'La contraseña debe combinar letras y números.';
    }
    return null;
}