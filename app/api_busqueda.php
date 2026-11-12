<?php
// Vista previa del buscador: devuelve en JSON hasta 6 productos cuyo nombre (o alguna de sus
// palabras) empieza por lo escrito. Es público: el catálogo se puede consultar sin sesión.
require_once __DIR__ . '/includes/app.php';

header('Content-Type: application/json; charset=utf-8');

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40);
if ($q === '') {
   echo '[]';
   exit();
}

// Los comodines escritos por el usuario se tratan como texto normal.
$literal = addcslashes($q, '%_\\');
$inicio = $literal . '%';
$palabra = '% ' . $literal . '%';

$productos = consulta($conn,
   'SELECT id, name, price, image, stock FROM `products`
    WHERE name LIKE ? OR name LIKE ?
    ORDER BY (name LIKE ?) DESC, name
    LIMIT 6',
   'sss', [$inicio, $palabra, $inicio]);

echo json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
