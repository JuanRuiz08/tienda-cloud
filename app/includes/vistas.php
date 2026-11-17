<?php
/** Piezas de HTML compartidas: cabecera del documento, mensajes, encabezados y cierre de página. */

/** Escapa un valor para imprimirlo en HTML. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Precio en pesos con separador de miles: 10000 → "$10.000". */
function precio(int|string|float $pesos): string
{
    return '$' . number_format((int) $pesos, 0, ',', '.');
}

/** Fecha corta en español: "2026-11-17 15:30:00" → "17 nov 2026". */
function fecha_corta(?string $fecha): string
{
    if (!$fecha || !($d = date_create($fecha))) {
        return '';
    }
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $d->format('j') . ' ' . $meses[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
}

/** Agrega ?v=<fecha del archivo> para que el navegador recargue CSS/JS cuando cambian (nginx los cachea 7 días). */
function url_con_version(string $ruta): string
{
    $archivo = __DIR__ . '/../' . $ruta;
    return is_file($archivo) ? $ruta . '?v=' . filemtime($archivo) : $ruta;
}

/** Abre el documento: <head> completo y <body>. $css es la hoja principal (style.css o admin_style.css). */
function render_head(string $titulo, string $css = 'style.css', array $css_extra = []): void
{
    enviar_cabeceras_seguridad();
    $titulo = e($titulo);
    $csrf = e(csrf_token());
    $hojas = '';
    foreach (array_merge(['base.css', $css], $css_extra) as $hoja) {
        $url = str_starts_with($hoja, 'http') ? $hoja : url_con_version("css/$hoja");
        $hojas .= '   <link rel="stylesheet" href="' . e($url) . "\">\n";
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>$titulo</title>
   <meta name="csrf-token" content="$csrf">
   <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
$hojas</head>
<body>

HTML;
}

/** Muestra los avisos (éxito/error) acumulados en $message[]. */
function render_mensajes(array $mensajes): void
{
    if (!$mensajes) {
        return;
    }
    echo '<div class="avisos" role="status" aria-live="polite">' . "\n";
    foreach ($mensajes as $mensaje) {
        echo '   <div class="mensaje">
      <span>' . e($mensaje) . '</span>
      <i class="fas fa-times" data-cerrar role="button" aria-label="Cerrar aviso"></i>
   </div>
';
    }
    echo "</div>\n";
}

/**
 * Botón de borrado (o cancelación) que envía un POST con token CSRF y pide confirmación en el navegador
 * (js/comun.js lee data-confirmar). Los borrados nunca son enlaces GET.
 * $etiqueta puede llevar HTML de confianza (por ejemplo un icono).
 */
function form_borrar(string $campo, int $id, string $etiqueta, string $confirmar, string $clase = 'boton-eliminar'): string
{
    return '<form action="" method="post" class="form-borrar" data-confirmar="' . e($confirmar) . '">'
        . csrf_campo()
        . '<input type="hidden" name="' . e($campo) . '" value="' . $id . '">'
        . '<button type="submit" class="' . e($clase) . '">' . $etiqueta . '</button></form>';
}

/** Navegación entre páginas ("‹ 1 2 3 ›"). $extra son los demás parámetros de la URL que se conservan (p. ej. el filtro). */
function render_paginador(array $pag, array $extra = []): void
{
    if ($pag['paginas'] <= 1) {
        return;
    }
    $enlace = fn (int $n): string => '?' . http_build_query($extra + ['pagina' => $n]);
    $inicio = max(1, $pag['pagina'] - 2);
    $fin = min($pag['paginas'], $inicio + 4);
    $inicio = max(1, $fin - 4);

    echo '<nav class="paginador" aria-label="Páginas">';
    echo $pag['pagina'] > 1
        ? '<a href="' . e($enlace($pag['pagina'] - 1)) . '" aria-label="Anterior"><i class="fas fa-chevron-left"></i></a>'
        : '<span class="off"><i class="fas fa-chevron-left"></i></span>';
    for ($n = $inicio; $n <= $fin; $n++) {
        echo $n === $pag['pagina']
            ? '<span class="actual" aria-current="page">' . $n . '</span>'
            : '<a href="' . e($enlace($n)) . '">' . $n . '</a>';
    }
    echo $pag['pagina'] < $pag['paginas']
        ? '<a href="' . e($enlace($pag['pagina'] + 1)) . '" aria-label="Siguiente"><i class="fas fa-chevron-right"></i></a>'
        : '<span class="off"><i class="fas fa-chevron-right"></i></span>';
    echo '<small>' . $pag['total'] . ' en total</small></nav>' . "\n";
}

/** Banda de título con migas de pan: "Inicio / <pagina>". */
function render_encabezado(string $titulo, string $pagina): void
{
    echo '<div class="heading">
   <h3>' . e($titulo) . '</h3>
   <p> <a href="inicio.php">Inicio</a> / ' . e($pagina) . ' </p>
</div>
';
}

/** Cierra una página de la tienda: pie de página, script y </html>. */
function render_cierre(string $js = 'script.js', bool $con_pie = true): void
{
    if ($con_pie) {
        include __DIR__ . '/../footer.php';
    }
    echo '<script src="' . e(url_con_version('js/comun.js')) . "\"></script>\n";
    echo '<script src="' . e(url_con_version("js/$js")) . "\"></script>\n\n</body>\n</html>\n";
}
