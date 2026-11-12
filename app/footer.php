<section class="footer">

    <div class="caja-container">

        <div class="caja"> <!-- Info del creador y info de la página -->
            <h3>Enlaces rápidos</h3>
            <a href="inicio.php">Inicio</a>
            <a href="acerca.php">Acerca de</a>
            <a href="tienda.php">Tienda</a>
        </div>

        <div class="caja">
            <h3>Enlaces adicionales</h3>
            <?php if (usuario_actual()): ?>
            <a href="carrito.php">Carrito</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="cerrar_sesion.php">Cerrar sesión</a>
            <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
            <a href="registro.php">Registrarse</a>
            <?php endif; ?>
        </div>

        <div class="caja"> 
            <h3>Información de contacto</h3>
            <p> <i class="fas fa-phone"></i> +57 3136019671 Desarrollador </p>
            <p> <i class="fas fa-phone"></i> +57 3122007849 Atención papeleria </p>
            <p> <i class="fas fa-envelope"></i> papeleria@ucp.edu.co</p>
            <p> <i class="fas fa-map-marker-alt"></i> Pereira, Colombia </p>
        </div>

        <div class="caja">
            <h3>Seguir</h3>
            
            <a href="https://www.instagram.com/papeleriaucp/"> <i class="fab fa-instagram"></i> Instagram </a>
            
        </div>
    </div>
    <p class="credit">Creado por <span>Diego Andres Carmona</span> · <span>Juan David Ruiz Orozco</span> · <span>Yarlinson Mosquera</span> — <?= date('Y') ?></p>

</section>
