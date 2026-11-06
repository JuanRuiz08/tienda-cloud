<?php

include 'conexion.php';
session_start();

if (isset($_POST['submit'])) {

    $email_form = trim($_POST['email']);
    $clave = $_POST['password'];

    // loginUser ahora devuelve el hash guardado; la contraseña se verifica en PHP.
    $stmt = $conn->prepare("CALL loginUser(?)");
    $stmt->bind_param("s", $email_form);
    $stmt->execute();
    $stmt->bind_result($user_type, $name, $email, $id, $hash);
    $encontrado = $stmt->fetch();
    $stmt->close();
    while ($conn->more_results()) { $conn->next_result(); }

    $valida = false;
    if ($encontrado && $hash) {
        if (password_verify($clave, $hash)) {
            $valida = true;
        } elseif (preg_match('/^[a-f0-9]{32}$/', $hash) && hash_equals($hash, md5($clave))) {
            // Cuenta antigua con MD5: se acepta una vez y se migra a bcrypt.
            $valida = true;
            $nuevo_hash = password_hash($clave, PASSWORD_DEFAULT);
            $up = $conn->prepare("CALL actualizarPasswordUsuario(?, ?)");
            $up->bind_param("is", $id, $nuevo_hash);
            $up->execute();
            $up->close();
            while ($conn->more_results()) { $conn->next_result(); }
            app_log('info', "contraseña migrada de MD5 a bcrypt para user_id=$id");
        }
    }

    if ($valida) {
        session_regenerate_id(true);
        app_log('info', "login correcto user_id=$id rol=$user_type");
        if ($user_type == 'admin') {
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_id'] = $id;
            header('location:admin_pagina.php');
            exit();
        } else {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_id'] = $id;
            header('location:inicio.php');
            exit();
        }
    } else {
        app_log('warning', 'login fallido para el correo ' . $email_form);
        $message[] = '¡correo electrónico o contraseña incorrectos!';
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>iniciar sesión</title>
   <link rel="icon" id="png" href="images/icon2.png">

   <!-- enlace de cdn de font awesome  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

   <!-- enlace del archivo css personalizado  -->
   <link rel="stylesheet" href="css/style.css">

</head>
<body>

<?php
if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="mensaje">
         <span>'.$message.'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>
   
<div class="form-container">

   <form action="" method="post">
      <h3>iniciar sesión ahora en papeleria U.C.P</h3>
      <input type="email" name="email" placeholder="ingresa tu correo electrónico" required class="caja">
      <input type="password" name="password" placeholder="ingresa tu contraseña" required class="caja">
      <input type="submit" name="submit" value="iniciar sesión ahora" class="boton">
      <p>¿no tienes una cuenta? <a href="registro.php">regístrate ahora</a></p>
   </form>

</div>

</body>
</html>
