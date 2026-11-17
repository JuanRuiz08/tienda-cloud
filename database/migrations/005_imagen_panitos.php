<?php
// El archivo de imagen de los pañitos se llama panitos.jpg (sin ñ ni espacios, que fallan según el sistema de archivos).
return function (mysqli $db): void {
    $db->query("UPDATE products SET image = 'panitos.jpg' WHERE image LIKE 'pa%itos p.jpg'");
};