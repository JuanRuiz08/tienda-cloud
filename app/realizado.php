<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['order'])) {
    header('Location: realizado.php');
    exit();
}

$order = $_SESSION['order'];

// Inserta el pedido en la base de datos
mysqli_query($conn, "INSERT INTO `orders`(user_id, name, number, email, method, address, total_products, total_price, placed_on) VALUES('{$order['user_id']}', '{$order['name']}', '{$order['number']}', '{$order['email']}', '{$order['method']}', '{$order['address']}', '{$order['total_products']}', '{$order['total_price']}', '{$order['placed_on']}')") or die('Error en la consulta');

// Borra el carrito del usuario
mysqli_query($conn, "DELETE FROM `cart` WHERE user_id = '{$order['user_id']}'") or die('Error en la consulta');

// Limpia la sesión de pedido
unset($_SESSION['order']);

?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Pedido Completo</title>
</head>
<body>
   <h2>¡Pedido realizado con éxito!</h2>
   <p>Gracias por tu compra. Tu pedido ha sido procesado.</p>
   <a href="inicio.php">Volver a la tienda</a>
</body>
</html>
