<?php
require_once __DIR__ . '/includes/app.php';

requerir_usuario();

render_head('Acerca de');
include 'header.php';
render_encabezado('Acerca de nosotros', 'Acerca de');
?>

<section class="acerca">

   <div class="flex">
      <div class="image">
         <img src="images/about-img.jpg" alt="imagen sobre nosotros">
      </div>
   </div>

</section>

<?php render_cierre(); ?>
