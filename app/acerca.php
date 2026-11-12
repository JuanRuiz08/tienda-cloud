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
         <img src="images/universidad.jpg" alt="Campus de la Universidad Católica de Pereira">
      </div>

      <div class="contenedor">
         <h3>La papelería del campus, ahora en línea</h3>
         <p>Papelería U.C.P. reúne útiles escolares, dulcería y artículos de uso personal para la comunidad de la Universidad Católica de Pereira. Pide desde aquí y paga con tarjeta o en efectivo.</p>
         <ul>
            <li><i class="fas fa-phone"></i> +57 3122007849 · Atención papelería</li>
            <li><i class="fas fa-envelope"></i> papeleria@ucp.edu.co</li>
            <li><i class="fas fa-map-marker-alt"></i> Pereira, Colombia</li>
         </ul>
      </div>
   </div>

</section>

<?php render_cierre(); ?>
