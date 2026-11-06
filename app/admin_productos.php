<?php

include 'conexion.php';
session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:login.php');
}

if (isset($_POST['add_product'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = $_POST['price'];
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $image_size = $_FILES['image']['size'];
    $stock = $_POST['stock'];

    $select_product_name = mysqli_query($conn, "SELECT name FROM `products` WHERE name = '$name'") or die('fallo en la consulta');

    if (mysqli_num_rows($select_product_name) > 0) {
        $message[] = 'El nombre del producto ya ha sido añadido';
    } else {
        if ($image_size > 2000000) {
            $message[] = 'El tamaño de la imagen es demasiado grande';
        } else {
            $image = guardar_imagen_subida($_FILES['image'], 'uploaded_img');
            if ($image === null) {
                $message[] = 'La imagen no es válida (solo JPG o PNG)';
            } else {
                $stmt = $conn->prepare("CALL agregar_producto(?, ?, ?, ?, ?,?)");
                $stmt->bind_param("sissis", $name,  $price, $category, $image, $stock, $description);
                $stmt->execute();
                $stmt->close();
                app_log('info', "producto añadido: $name (imagen $image)");
                $message[] = 'Producto añadido exitosamente';
            }
        }
    }
}


if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    $delete_image_query = mysqli_query($conn, "SELECT image FROM `products` WHERE id = '$delete_id'") or die('fallo en la consulta');
    $fetch_delete_image = mysqli_fetch_assoc($delete_image_query);
    @unlink('uploaded_img/' . basename($fetch_delete_image['image']));
    $stmt = $conn->prepare("CALL eliminar_producto(?)");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header('location:admin_productos.php');
}


if (isset($_POST['update_product'])) {
   $update_p_id = $_POST['update_p_id'];
   $update_name = mysqli_real_escape_string($conn, $_POST['update_name']);
   $update_price = $_POST['update_price'];
   $update_image = $_FILES['update_image']['name'];
   $update_image_size = $_FILES['update_image']['size'];
   $update_old_image = $_POST['update_old_image'];
   $update_stock = $_POST['stock'];

   if (!empty($update_image)) {
       if ($update_image_size > 2000000) {
           $message[] = 'El tamaño del archivo de imagen es demasiado grande';
       } else {
           $nueva = guardar_imagen_subida($_FILES['update_image'], 'uploaded_img');
           if ($nueva === null) {
               $message[] = 'La imagen no es válida (solo JPG o PNG)';
           } else {
               @unlink('uploaded_img/' . basename($update_old_image));
               $stmt = $conn->prepare("CALL actualizar_producto_con_stock(?, ?, ?, ?, ?)");
               $stmt->bind_param("isssi", $update_p_id, $update_name, $update_price, $nueva, $update_stock);
               $stmt->execute();
               $stmt->close();
           }
       }
   } else {
       $stmt = $conn->prepare("CALL actualizar_producto_con_stock(?, ?, ?, ?, ?)");
       $stmt->bind_param("isssi", $update_p_id, $update_name, $update_price, $update_old_image, $update_stock);
       $stmt->execute();
       $stmt->close();
   }

   header('location:admin_productos.php');
}


if(isset($_POST['order_btn'])){ /* Se usa para el botón ORDERNOW */

   $name = mysqli_real_escape_string($conn, $_POST['name']);
   $number = $_POST['number'];
   $email = mysqli_real_escape_string($conn, $_POST['email']);
   $method = mysqli_real_escape_string($conn, $_POST['method']);
   $address = mysqli_real_escape_string($conn, 'flat no. '. $_POST['flat'].', '. $_POST['street'].', '. $_POST['city'].', '. $_POST['country'].' - '. $_POST['pin_code']);
   $placed_on = date('d-M-Y');

   $cart_total = 0;
   $cart_products = array();

   $stmt = $conn->prepare("CALL checkCart(?)");
   $stmt->bind_param("i", $user_id);
   $stmt->execute();
   $result = $stmt->get_result();

   if($result->num_rows > 0){
      while($cart_item = $result->fetch_assoc()){
         $cart_products[] = $cart_item['name'].' ('.$cart_item['quantity'].') ';
         $sub_total = ($cart_item['price'] * $cart_item['quantity']);
         $cart_total += $sub_total; /* Calcula el costo total de los artículos en el carrito de un usuario */
      }
   }

   $stmt->close();

   $total_products = implode(', ', $cart_products);

   if($cart_total == 0){
      $message[] = 'Tu carrito está vacío'; /* Notificación de carrito vacío */
   }else{
      $stmt = $conn->prepare("CALL insertOrder(?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("issssssis", $user_id, $name, $number, $email, $method, $address, $total_products, $cart_total, $placed_on);
      $stmt->execute();
      $stmt->close();

      $message[] = '¡Pedido realizado con éxito!'; /* Pedido realizado con éxito */
      $stmt = $conn->prepare("CALL deleteCartItems(?)");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();
   }
}





?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Productos</title>

   <link rel="icon" id="png" href="images/icon2.png">

   <!-- enlace a font awesome cdn -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- enlace a archivo css personalizado de admin -->
   <link rel="stylesheet" href="css/admin_style.css">

</head>
<body>
   
<?php include 'admin_header.php'; ?>

<section class="add-products">

   <h1 class="title">Productos de la tienda</h1>

   <form action="" method="post" enctype="multipart/form-data">
      <h3>Añadir producto</h3>
      <input type="text" name="name" class="caja" placeholder="Ingrese el nombre del producto" required>
      <input type="text" name="description" class="caja" placeholder="Ingrese la descripcion del producto" required>
      <input type="number" min="0" name="price" class="caja" placeholder="Ingrese el precio del producto" required>
      <input type="file" name="image"   accept="image/jpg, image/jpeg, image/png" class="caja" required>
      <input type="number" min="0" name="stock" class="caja" placeholder="Ingrese el stock del producto" required>
      <select name="category" class="caja" required>
   <option value="">Selecciona una categoría</option>
   <option value="dulceria">Dulcería</option>
   <option value="uso personal">Uso Personal</option>
   <option value="escolar">Escolar</option>
</select>

      <input type="submit" value="Añadir producto" name="add_product" class="boton">
   </form>

   <section class="show-products">
   <div class="caja-container">
      <?php
         $select_products = mysqli_query($conn, "SELECT * FROM `products`") or die('fallo en la consulta');
         if (mysqli_num_rows($select_products) > 0) {
            while ($fetch_products = mysqli_fetch_assoc($select_products)) {
      ?>
      <div class="caja">
         <img src="uploaded_img/<?php echo $fetch_products['image']; ?>" alt="" class="image">
         <div class="name"><?php echo $fetch_products['name']; ?></div>
         <div class="price">$<?php echo $fetch_products['price']; ?> Mil</div>
         <div class="stock">Stock: <?php echo $fetch_products['stock']; ?></div>
         <a href="admin_productos.php?update=<?php echo $fetch_products['id']; ?>" class="boton-opcion">Actualizar</a>
         <a href="admin_productos.php?delete=<?php echo $fetch_products['id']; ?>" class="boton-eliminar" onclick="return confirm('¿Eliminar este producto?');">Eliminar</a>
      </div>
      <?php
         }
      } else {
         echo '<p class="vacio">¡No se han añadido productos aún!</p>';
      }
      ?>
   </div>
</section>


<section class="edit-product-form">

   <?php
      if (isset($_GET['update'])) {
         $update_id = $_GET['update'];
         $update_query = mysqli_query($conn, "SELECT * FROM `products` WHERE id = '$update_id'") or die('fallo en la consulta');
         if (mysqli_num_rows($update_query) > 0) {
            while ($fetch_update = mysqli_fetch_assoc($update_query)) {
   ?>
   <form action="" method="post" enctype="multipart/form-data">
      <input type="hidden" name="update_p_id" value="<?php echo $fetch_update['id']; ?>">
      <input type="hidden" name="update_old_image" value="<?php echo $fetch_update['image']; ?>">
      <img src="uploaded_img/<?php echo $fetch_update['image']; ?>" alt="">
      <input type="text" name="update_name" value="<?php echo $fetch_update['name']; ?>" class="caja" required placeholder="Ingrese el nombre del producto">
      <input type="number" name="update_price" value="<?php echo $fetch_update['price']; ?>" min="0" class="caja" required placeholder="Ingrese el precio del producto">
      <input type="number" name="update_stock" value="<?php echo $fetch_update['stock']; ?>" min="0" class="caja" required placeholder="Ingrese el stock del producto">
      <input type="file" name="update_image" accept="image/jpg, image/jpeg, image/png" class="caja">
      <input type="submit" value="Actualizar producto" name="update_product" class="boton">
      <input type="submit" value="Cancelar" class="boton" onclick="window.location.href='admin_productos.php';">
   </form>
   <?php
               }
            }
         } else {
            echo '<script>document.querySelector(".edit-product-form").style.display = "none";</script>';
         }
      ?>
</section>
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



<!-- enlace del archivo js personalizado  -->
<script src="js/script.js"></script>

</body>
</html>
