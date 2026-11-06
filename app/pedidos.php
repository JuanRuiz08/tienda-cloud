<?php

include 'conexion.php';
session_start();

$user_id = $_SESSION['user_id'];

if (!isset($user_id)) {
    header('location:login.php');
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Mis Pedidos</title>
   <link rel="icon" id="png" href="images/icon2.png">

   <!-- Enlace CDN de Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- Enlace del archivo CSS personalizado -->
   <link rel="stylesheet" href="css/style.css">

</head>
<body>

<?php include 'header.php'; ?>

<section class="pedidos">

   <h1 class="title">Mis Pedidos</h1>

   <div class="caja-container">

      <?php
         $stmt = $conn->prepare("CALL getUserOrders(?)");
         $stmt->bind_param("i", $user_id);
         $stmt->execute();
         $result = $stmt->get_result();

         if ($result->num_rows > 0) {
            while ($fetch_order = $result->fetch_assoc()) {
      ?>
      <div class="caja">
         <p> Pedido ID: <span><?php echo $fetch_order['id']; ?></span> </p>
         <p> Nombre: <span><?php echo $fetch_order['name']; ?></span> </p>
         <p> Número: <span><?php echo $fetch_order['number']; ?></span> </p>
         <p> Correo: <span><?php echo $fetch_order['email']; ?></span> </p>
         <p> Método: <span><?php echo $fetch_order['method']; ?></span> </p>
         <p> Dirección: <span><?php echo $fetch_order['address']; ?></span> </p>
         <p> Productos Totales: <span><?php echo $fetch_order['total_products']; ?></span> </p>
         <p> Precio Total: <span>$<?php echo $fetch_order['total_price']; ?> Mil</span> </p>
         <p> Pedido Realizado en: <span><?php echo $fetch_order['placed_on']; ?></span> </p>
         <p> Estado del Pago: <span><?php echo $fetch_order['payment_status']; ?></span> </p>
      </div>
      <?php
            }
         } else {
            echo '<p class="vacio">No has realizado pedidos aún</p>';
         }
         $stmt->close();
      ?>

   </div>

</section>

<?php include 'footer.php'; ?>

<!-- Enlace del archivo JS personalizado -->
<script src="js/script.js"></script>

</body>
</html>
