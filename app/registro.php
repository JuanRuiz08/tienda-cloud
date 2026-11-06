<?php

include 'conexion.php';

if(isset($_POST['submit'])){

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    // Siempre se crea como usuario normal: el rol admin no se puede elegir desde el formulario.
    $user_type = 'user';

    if(!hash_equals($_POST['password'], $_POST['cpassword'])){
        $message[] = '¡La confirmación de la contraseña no coincide!';
    }else{
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("CALL registerUser(?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $pass, $user_type);
        try {
            $stmt->execute();
            $stmt->close();
            app_log('info', 'usuario registrado: ' . $email);
            header('location:login.php');
            exit();
        } catch (mysqli_sql_exception $e) {
            $message[] = $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Registro</title>
   <link rel="icon" id="png" href="images/icon2.png">

   <!-- Enlace CDN de Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- Enlace del archivo CSS personalizado -->
   <link rel="stylesheet" href="css/style.css">

</head>
<body>

<?php
if(isset($message)){
    foreach($message as $mensaje){
        echo '
        <div class="mensaje">
            <span>'.$mensaje.'</span>
            <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
        </div>
        ';
    }
}
?>

<div class="form-container">

   <form action="" method="post">
      <h3>Regístrate ahora en Papelería U.C.P</h3>
      <input type="text" name="name" placeholder="Ingresa tu nombre" required class="caja">
      <input type="email" name="email" placeholder="Ingresa tu correo electrónico" required class="caja">
      <input type="password" name="password" placeholder="Ingresa tu contraseña" required class="caja">
      <input type="password" name="cpassword" placeholder="Confirma tu contraseña" required class="caja">
      <input type="submit" name="submit" value="Regístrate ahora" class="boton">
      <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión ahora</a></p>
   </form>

</div>

</body>
</html>

