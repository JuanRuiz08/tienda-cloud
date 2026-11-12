<?php
/** Piezas de HTML compartidas: cabecera del documento, mensajes, encabezados y cierre de página. */

/** Escapa un valor para imprimirlo en HTML. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Abre el documento: <head> completo y <body>. $css es la hoja principal (style.css o admin_style.css). */
function render_head(string $titulo, string $css = 'style.css', array $css_extra = []): void
{
    $titulo = e($titulo);
    $hojas = '';
    foreach (array_merge([$css], $css_extra) as $hoja) {
        $url = str_starts_with($hoja, 'http') ? $hoja : "css/$hoja";
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
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
$hojas</head>
<body>

HTML;
}

/** Muestra los avisos (éxito/error) acumulados en $message[]. */
function render_mensajes(array $mensajes): void
{
    foreach ($mensajes as $mensaje) {
        echo '<div class="mensaje">
   <span>' . e($mensaje) . '</span>
   <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
</div>
';
    }
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
    echo '<script src="js/' . e($js) . "\"></script>\n\n</body>\n</html>\n";
}
