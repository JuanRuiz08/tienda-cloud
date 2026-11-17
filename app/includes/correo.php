<?php
/**
 * Correos de confirmación al cliente por SMTP (sin librerías). Si no hay SMTP configurado no se envía nada y
 * la tienda funciona igual. Los correos se mandan al terminar la respuesta (fastcgi_finish_request), así el
 * cliente no espera al servidor de correo, y un fallo nunca rompe un pedido.
 *
 * Variables: SMTP_HOST, SMTP_PORT (587), SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_SEGURIDAD (starttls | ssl | none).
 */

function correo_configurado(): bool
{
    return (string) getenv('SMTP_HOST') !== '';
}

/** Conversación SMTP. Devuelve true si el servidor aceptó el mensaje. */
function smtp_enviar(string $para, string $asunto, string $texto, string $html): bool
{
    $host = (string) getenv('SMTP_HOST');
    $puerto = (int) (getenv('SMTP_PORT') ?: 587);
    $usuario = (string) getenv('SMTP_USER');
    $clave = (string) getenv('SMTP_PASS');
    $de = (string) (getenv('SMTP_FROM') ?: $usuario ?: 'tienda@localhost');
    $seguridad = strtolower((string) (getenv('SMTP_SEGURIDAD') ?: ($puerto === 465 ? 'ssl' : ($puerto === 587 ? 'starttls' : 'none'))));

    $fp = @stream_socket_client(($seguridad === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$puerto", $errno, $errstr, 8);
    if (!$fp) {
        throw new RuntimeException("SMTP: no se pudo conectar a $host:$puerto ($errstr)");
    }
    stream_set_timeout($fp, 8);

    $leer = function () use ($fp): array {
        $texto = '';
        while (($linea = fgets($fp, 1024)) !== false) {
            $texto .= $linea;
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                break;
            }
        }
        return [(int) substr($texto, 0, 3), trim($texto)];
    };
    $cmd = function (string $linea, array $esperados) use ($fp, $leer): string {
        fwrite($fp, $linea . "\r\n");
        [$codigo, $resp] = $leer();
        if (!in_array($codigo, $esperados, true)) {
            throw new RuntimeException("SMTP: respuesta inesperada a «" . (str_starts_with($linea, 'AUTH') || strlen($linea) > 60 ? '…' : $linea) . "»: $resp");
        }
        return $resp;
    };

    try {
        [$codigo, $resp] = $leer();
        if ($codigo !== 220) {
            throw new RuntimeException("SMTP: saludo inesperado: $resp");
        }
        $cmd('EHLO tienda', [250]);
        if ($seguridad === 'starttls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new RuntimeException('SMTP: no se pudo iniciar TLS');
            }
            $cmd('EHLO tienda', [250]);
        }
        if ($usuario !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($usuario), [334]);
            $cmd(base64_encode($clave), [235]);
        }
        $cmd("MAIL FROM:<$de>", [250]);
        $cmd("RCPT TO:<$para>", [250, 251]);
        $cmd('DATA', [354]);

        $limite = 'b' . bin2hex(random_bytes(8));
        $cabeceras = [
            "From: Papelería U.C.P. <$de>",
            "To: <$para>",
            'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@tienda>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$limite\"",
        ];
        $cuerpo = implode("\r\n", $cabeceras) . "\r\n\r\n"
            . "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto)) . "\r\n"
            . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
            . "--$limite--\r\n";
        fwrite($fp, $cuerpo . "\r\n.\r\n");
        [$codigo, $resp] = $leer();
        if ($codigo !== 250) {
            throw new RuntimeException("SMTP: el servidor rechazó el mensaje: $resp");
        }
        $cmd('QUIT', [221]);
        return true;
    } finally {
        fclose($fp);
    }
}

/** Arma y envía el correo de un pedido. $tipo: creado | pagado | completado. Nunca lanza excepciones. */
function correo_pedido(mysqli $conn, int $pedido_id, string $tipo): void
{
    if (!correo_configurado()) {
        return;
    }
    $pedido = consulta($conn, 'SELECT * FROM `orders` WHERE id = ?', 'i', [$pedido_id])[0] ?? null;
    if ($pedido === null || !filter_var($pedido['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $items = consulta($conn, 'SELECT name, price, quantity FROM `order_items` WHERE order_id = ?', 'i', [$pedido_id]);

    $titulos = [
        'creado' => ["Recibimos tu pedido #$pedido_id", 'Recibimos tu pedido', $pedido['method'] === 'tarjeta'
            ? 'Para terminar, paga en línea desde «Mis pedidos» en la tienda. Tienes ' . minutos_para_pagar() . ' minutos; después el pedido se cancela solo.'
            : 'Pagarás en efectivo cuando recibas tu pedido.'],
        'pagado' => ["Pago recibido — pedido #$pedido_id", '¡Pago recibido!', 'Confirmamos tu pago en línea. Ya estamos preparando tu pedido.'],
        'completado' => ["Tu pedido #$pedido_id está completo", 'Pedido completado', 'Tu pedido quedó marcado como completado. ¡Gracias por tu compra!'],
    ];
    [$asunto, $titulo, $mensaje] = $titulos[$tipo] ?? $titulos['creado'];

    $lineas_txt = '';
    $filas_html = '';
    foreach ($items as $i) {
        $sub = precio($i['price'] * $i['quantity']);
        $lineas_txt .= "  - {$i['name']} × {$i['quantity']}  {$sub}\n";
        $filas_html .= '<tr><td style="padding:6px 0">' . e($i['name']) . ' × ' . (int) $i['quantity']
            . '</td><td style="padding:6px 0;text-align:right">' . e($sub) . '</td></tr>';
    }
    $total = precio($pedido['total_price']);
    $nombre = $pedido['name'];

    $texto = "Hola $nombre,\n\n$titulo.\n$mensaje\n\nPedido #$pedido_id\n$lineas_txt\nTotal: $total\nDirección: {$pedido['address']}\n\nPapelería U.C.P.\n";
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#18212b">'
        . '<h2 style="margin:0 0 8px">' . e($titulo) . '</h2>'
        . '<p>Hola ' . e($nombre) . ', ' . e($mensaje) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;border-top:1px solid #e6e2d8;border-bottom:1px solid #e6e2d8;margin:16px 0">'
        . $filas_html
        . '<tr><td style="padding:10px 0;font-weight:bold">Total</td><td style="padding:10px 0;text-align:right;font-weight:bold">' . e($total) . '</td></tr></table>'
        . '<p style="color:#6b7686;font-size:14px">Pedido #' . $pedido_id . ' · ' . e($pedido['address']) . '</p>'
        . '<p style="color:#6b7686;font-size:14px">Papelería U.C.P.</p></div>';

    try {
        smtp_enviar($pedido['email'], $asunto, $texto, $html);
        app_log('info', "correo '$tipo' enviado para el pedido $pedido_id");
    } catch (Throwable $e) {
        app_log('warning', "no se pudo enviar el correo del pedido $pedido_id: " . $e->getMessage());
    }
}

/** Programa el correo para después de responder al cliente (si PHP-FPM lo permite; si no, se envía al terminar). */
function correo_pedido_diferido(mysqli $conn, int $pedido_id, string $tipo): void
{
    if (!correo_configurado()) {
        return;
    }
    register_shutdown_function(function () use ($conn, $pedido_id, $tipo): void {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        correo_pedido($conn, $pedido_id, $tipo);
    });
}