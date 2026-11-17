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

/** Agrega ?v=<fecha del archivo> para que el navegador recargue CSS/JS cuando cambian (nginx los cachea 7 días). */
function url_con_version(string $ruta): string
{
    $archivo = __DIR__ . '/../' . $ruta;
    return is_file($archivo) ? $ruta . '?v=' . filemtime($archivo) : $ruta;
}

/** Abre el documento: <head> completo y <body>. $css es la hoja principal (style.css o admin_style.css). */
function render_head(string $titulo, string $css = 'style.css', array $css_extra = []): void
{
    $titulo = e($titulo);
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
   <link rel="icon" id="png" href="images/icon2.png">
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
      <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
   </div>
';
    }
    echo "</div>\n";
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
    echo '<script src="' . e(url_con_version("js/$js")) . "\"></script>\n\n</body>\n</html>\n";
}
