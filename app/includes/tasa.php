<?php
/**
 * Tasa de cambio pesos colombianos por dólar (PayPal no cobra en COP). Se consulta a una API pública, se guarda
 * en la tabla `config` y se reutiliza 6 horas; si la API falla se usa el último valor guardado y, en último
 * caso, la tasa fija de PAYPAL_COP_PER_USD.
 *
 * Variables: PAYPAL_TASA_AUTO=0 fuerza la tasa fija · TASA_API_URL cambia el origen (pruebas).
 */
const TASA_VIGENCIA = 21600;   // 6 horas

function tasa_cop_usd(mysqli $conn): float
{
    $fija = max(1.0, (float) (getenv('PAYPAL_COP_PER_USD') ?: 4000));
    if (getenv('PAYPAL_TASA_AUTO') === '0') {
        return $fija;
    }

    $guardada = consulta($conn, "SELECT valor, actualizado FROM `config` WHERE clave = 'tasa_cop_usd'")[0] ?? null;
    if ($guardada && time() - (int) $guardada['actualizado'] < TASA_VIGENCIA) {
        return (float) $guardada['valor'];
    }

    $nueva = tasa_desde_api();
    if ($nueva !== null) {
        consulta($conn,
            "INSERT INTO `config` (clave, valor, actualizado) VALUES ('tasa_cop_usd', ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado = VALUES(actualizado)",
            'si', [(string) $nueva, time()]);
        app_log('info', "tasa COP/USD actualizada: $nueva");
        return $nueva;
    }

    app_log('warning', 'no se pudo consultar la tasa COP/USD; se usa ' . ($guardada ? 'la última guardada' : 'la tasa fija'));
    return $guardada ? (float) $guardada['valor'] : $fija;
}

/** Consulta la API de tasas. Devuelve null si falla o si el valor no es razonable. */
function tasa_desde_api(): ?float
{
    $ch = curl_init(getenv('TASA_API_URL') ?: 'https://open.er-api.com/v6/latest/USD');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($cuerpo === false || $codigo !== 200) {
        return null;
    }
    $cop = (float) (json_decode($cuerpo, true)['rates']['COP'] ?? 0);
    return ($cop >= 500 && $cop <= 20000) ? round($cop, 2) : null;   // descarta valores absurdos
}