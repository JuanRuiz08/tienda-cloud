<?php
include 'conexion.php';
session_start();

$user_id = $_SESSION['user_id'];

if (!isset($user_id)) {
    header('location:login.php');
    exit();
}

$cart_total = 0;
$cart_products = [];
$cart_query = mysqli_query($conn, "SELECT * FROM `cart` WHERE user_id = '$user_id'") or die('Error en la consulta');
if (mysqli_num_rows($cart_query) > 0) {
    while ($cart_item = mysqli_fetch_assoc($cart_query)) {
        $cart_products[] = $cart_item['name'] . ' (' . $cart_item['quantity'] . ') ';
        $sub_total = ($cart_item['price'] * $cart_item['quantity']);
        $cart_total += $sub_total;
    }
} else {
    $message[] = 'Tu carrito está vacío';
}

$total_products = implode(', ', $cart_products);
$grand_total = $cart_total;

if (isset($_POST['order_btn']) && $cart_total > 0) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $number = mysqli_real_escape_string($conn, $_POST['number']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $method = mysqli_real_escape_string($conn, $_POST['method']);
    $address = mysqli_real_escape_string($conn, 'flat no. ' . $_POST['flat'] . ', ' . $_POST['street'] . ', ' . $_POST['city'] . ', ' . $_POST['country'] . ' - ' . $_POST['pin_code']);
    $placed_on = date('d-M-Y');

    $insert_order = mysqli_query($conn, "INSERT INTO `orders`(user_id, name, number, email, method, address, total_products, total_price, placed_on) 
    VALUES('$user_id', '$name', '$number', '$email', '$method', '$address', '$total_products', '$cart_total', '$placed_on')") or die('Error en la consulta');

    if ($insert_order) {
        mysqli_query($conn, "DELETE FROM `cart` WHERE user_id = '$user_id'") or die('Error en la consulta');
        app_log('info', "pedido creado user_id=$user_id total=$cart_total");

        $_SESSION['order'] = [
            'user_id' => $user_id,
            'name' => $name,
            'number' => $number,
            'email' => $email,
            'method' => $method,
            'address' => $address,
            'total_products' => $total_products,
            'total_price' => $cart_total,
            'placed_on' => $placed_on
        ];

        header('Location: pago-paypal.php');
        exit();
    } else {
        $message[] = 'Error al realizar el pedido. Inténtalo de nuevo.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Verificación de compra</title>
   <link rel="icon" id="png" href="images/icon2.png">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <link rel="stylesheet" href="css/style.css">
   <link rel="stylesheet" href="css/estilo_verificacion.css"> <!-- Nueva hoja de estilos -->
</head>
<body>
<?php include 'header.php'; ?>

<div class="heading">
   <h3>Verificación</h3>
   <p><a href="inicio.php">Inicio</a> / Verificación</p>
</div>

<section class="display-order">
   <?php
      if (mysqli_num_rows($cart_query) > 0) {
         while ($fetch_cart = mysqli_fetch_assoc($cart_query)) {
            $total_price = ($fetch_cart['price'] * $fetch_cart['quantity']);
            $grand_total += $total_price;
   ?>
   <p><?php echo $fetch_cart['name']; ?> <span>(<?php echo '$' . $fetch_cart['price'] . '/-' . ' x ' . $fetch_cart['quantity']; ?>)</span></p>
   <?php
         }
      } else {
         echo '<p class="vacio">Tu carrito está vacío</p>';
      }
   ?>
   <div class="grand-total">Total general: <span>$<?php echo $grand_total; ?></span></div>
</section>

<section class="checkout">
   <h1 class="title">Realizar Pedido</h1>
   <form action="" method="post">
      <div class="flex">
         <div class="inputBox">
            <span>Nombre:</span>
            <input type="text" name="name" required>
         </div>
         <div class="inputBox">
            <span>Número:</span>
            <input type="number" name="number" required>
         </div>
         <div class="inputBox">
            <span>Correo Electrónico:</span>
            <input type="email" name="email" required>
         </div>
         <div class="inputBox">
            <span>Método de Pago:</span>
            <select name="method" required>
               <option value="efectivo">Efectivo</option>
               <option value="tarjeta">Tarjeta</option>
            </select>
         </div>
         <div class="inputBox">
            <span>Dirección:</span>
            <input type="text" name="flat" placeholder="Apartamento" required>
            <input type="text" name="street" placeholder="Calle" required>
            <input type="text" name="city" placeholder="Ciudad" required>
            <input type="text" name="country" placeholder="País" required>
            <input type="text" name="pin_code" placeholder="Código Postal" required>
         </div>
      </div>
      <input type="submit" name="order_btn" value="Realizar Pedido" class="boton-opcion">
   </form>
</section>

<?php include 'footer.php'; ?>
<script src="js/script.js"></script>
</body>
</html>