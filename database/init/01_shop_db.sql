-- Esquema inicial de la tienda (shop_db). MySQL lo ejecuta una sola vez, al crear el volumen db_data.
-- Cambios frente al dump original: sin DEFINER root@localhost, loginUser devuelve el hash,
-- tabla sessions para sesiones sin estado local y datos de muestra sin información personal.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `shop_db`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE PROCEDURE `actualizar_cantidad_carrito` (IN `p_cart_id` INT, IN `p_cart_quantity` INT)   BEGIN
    UPDATE `cart`
    SET quantity = p_cart_quantity
    WHERE id = p_cart_id;
END$$

CREATE PROCEDURE `actualizar_estado_pago` (IN `p_pedido_id` INT, IN `p_estado_pago` VARCHAR(50))   BEGIN
    -- Actualizar el estado de pago en la tabla de pedidos
    UPDATE pedidos
    SET estado_pago = p_estado_pago
    WHERE id = p_pedido_id;
END$$

CREATE PROCEDURE `actualizar_producto` (IN `p_product_id` INT, IN `p_name` VARCHAR(100), IN `p_price` INT, IN `p_image` VARCHAR(100))   BEGIN
    UPDATE products SET name = p_name, price = p_price, image = p_image WHERE id = p_product_id;
END$$

CREATE PROCEDURE `actualizar_producto_con_stock` (IN `p_product_id` INT, IN `p_name` VARCHAR(100), IN `p_price` INT, IN `p_image` VARCHAR(100), IN `p_stock` INT)   BEGIN
    UPDATE products 
    SET name = p_name, price = p_price, image = p_image, stock = p_stock 
    WHERE id = p_product_id;
END$$

CREATE PROCEDURE `addCartItem` (IN `p_user_id` INT, IN `p_product_name` VARCHAR(100), IN `p_product_price` INT, IN `p_product_quantity` INT, IN `p_product_image` VARCHAR(100))   BEGIN
    INSERT INTO cart (user_id, name, price, quantity, image) 
    VALUES (p_user_id, p_product_name, p_product_price, p_product_quantity, p_product_image);
END$$

CREATE PROCEDURE `agregar_producto` (IN `p_name` VARCHAR(100), IN `p_price` INT, IN `p_category` VARCHAR(50), IN `p_image` VARCHAR(100), IN `p_stock` INT, IN `p_description` VARCHAR(100))   BEGIN
    INSERT INTO products (name, description, price, category, image, stock) 
    VALUES (p_name, p_description, p_price, p_category, p_image, p_stock);
END$$

CREATE PROCEDURE `checkCartItem` (IN `p_user_id` INT, IN `p_product_name` VARCHAR(100))   BEGIN
    SELECT * FROM cart WHERE name = p_product_name AND user_id = p_user_id;
END$$

CREATE PROCEDURE `eliminar_pedido` (IN `p_order_id` INT)   BEGIN
    DELETE FROM orders WHERE id = p_order_id;
END$$

CREATE PROCEDURE `eliminar_producto` (IN `p_product_id` INT)   BEGIN
    DELETE FROM products WHERE id = p_product_id;
END$$

CREATE PROCEDURE `eliminar_producto_carrito` (IN `p_cart_id` INT)   BEGIN
    DELETE FROM cart WHERE id = p_cart_id;
END$$

CREATE PROCEDURE `getUserOrders` (IN `p_user_id` INT)   BEGIN
    SELECT * 
    FROM orders 
    WHERE user_id = p_user_id;
END$$

CREATE PROCEDURE `insertOrder` (IN `p_user_id` INT, IN `p_name` VARCHAR(100), IN `p_number` VARCHAR(12), IN `p_email` VARCHAR(100), IN `p_method` VARCHAR(50), IN `p_address` VARCHAR(500), IN `p_total_products` VARCHAR(1000), IN `p_total_price` INT, IN `p_placed_on` VARCHAR(50))   BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE cart_name VARCHAR(100);
    DECLARE cart_quantity INT;
    DECLARE cur CURSOR FOR 
        SELECT name, quantity 
        FROM cart 
        WHERE user_id = p_user_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO cart_name, cart_quantity;
        IF done THEN
            LEAVE read_loop;
        END IF;
        UPDATE products 
        SET stock = stock - cart_quantity 
        WHERE name = cart_name;
    END LOOP;
    CLOSE cur;

    INSERT INTO orders (user_id, name, number, email, method, address, total_products, total_price, placed_on)
    VALUES (p_user_id, p_name, p_number, p_email, p_method, p_address, p_total_products, p_total_price, p_placed_on);
END$$

CREATE PROCEDURE `loginUser` (IN `p_email` VARCHAR(100))   BEGIN
    -- Devuelve el hash guardado; la contraseña se verifica en PHP con password_verify().
    SELECT user_type, name, email, id, password
    FROM users
    WHERE email = p_email
    LIMIT 1;
END$$

CREATE PROCEDURE `actualizarPasswordUsuario` (IN `p_user_id` INT, IN `p_password` VARCHAR(100))   BEGIN
    UPDATE users SET password = p_password WHERE id = p_user_id;
END$$

CREATE PROCEDURE `registerUser` (IN `p_name` VARCHAR(100), IN `p_email` VARCHAR(100), IN `p_password` VARCHAR(100), IN `p_user_type` VARCHAR(20))   BEGIN
    DECLARE v_count INT;
    SET v_count = (SELECT COUNT(*) FROM users WHERE email = p_email);

    IF v_count > 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario ya existe';
    ELSE
        INSERT INTO users (name, email, password, user_type) 
        VALUES (p_name, p_email, p_password, p_user_type);
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cart`
--

CREATE TABLE `cart` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(100) NOT NULL,
  `quantity` int(100) NOT NULL,
  `image` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cart`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `message`
--

CREATE TABLE `message` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `message` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

CREATE TABLE `orders` (
  `id` int(100) NOT NULL,
  `user_id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `number` varchar(12) NOT NULL,
  `email` varchar(100) NOT NULL,
  `method` varchar(50) NOT NULL,
  `address` varchar(500) NOT NULL,
  `total_products` varchar(1000) NOT NULL,
  `total_price` int(100) NOT NULL,
  `placed_on` varchar(50) NOT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `orders`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

CREATE TABLE `products` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(100) NOT NULL,
  `image` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `stock` int(100) NOT NULL DEFAULT 0,
  `description` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image`, `category`, `stock`, `description`) VALUES
(44, 'Ancheta ', 40, 'ancheta 1.jpg', 'dulceria', 2, 'Regalo para un cumpleaños.'),
(45, 'Termo verde ', 20, 'Termo.jpg', 'uso personal', 3, 'termo de acero inoxidable de color verde, ideal para mantener tus bebidas a la temperatura deseada p'),
(46, 'Pañitos Humedo', 15, 'pañitos p.jpg', 'uso personal', 5, 'toallitas húmedas para bebés de la marca Pequeñin'),
(47, 'Cuaderno', 5, 'Cuaderno 3.jpg', 'escolar', 3, 'cuardeno negro'),
(48, 'Galletas tosh', 1, 'galletas tosh.jpg', 'dulceria', 8, 'galletas integrales de avena'),
(49, 'Billetera ', 5, 'Billeteras-para-hombre-45650016-79807_2.jpg', 'uso personal', 6, 'billetera de cuero negra para hombres'),
(50, 'Bolso', 30, 'Mochila t60.png', 'escolar', 5, 'mochila o bolso para estudiantes'),
(51, 'Perfume', 50, 'humor.jpg', 'uso personal', 9, 'perfume de marca humor para hombres'),
(53, 'Lapicero', 1, 'lapicero.jpg', 'escolar', 8, 'Lapicero de tinta negra'),
(54, 'Ancheta f', 30, 'ancheta 2.jpg', 'dulceria', 6, 'ancheta para ocasión especiales');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `user_type` varchar(20) NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--



-- Sesiones de PHP guardadas en la base de datos (la app no guarda estado en el contenedor).
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumblob NOT NULL,
  `updated_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Usuarios de demostración (cambiar las contraseñas fuera de un entorno de pruebas).
-- admin@tienda.local / Admin2026!    cliente@tienda.local / Cliente2026!
INSERT INTO `users` (`id`, `name`, `email`, `password`, `user_type`) VALUES
(1, 'Administrador', 'admin@tienda.local', '$2y$10$wBde7j2Vuyv084hklXqvs.hvYw3U7ERQ6zwouHTuP9taFtX41PZC2', 'admin'),
(2, 'Cliente de prueba', 'cliente@tienda.local', '$2y$10$5/Ped21uYPYfCirOlZOq4ORX/I4y9KncME/ztlPkoVCuCGSp4Qac.', 'user');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `message`
--
ALTER TABLE `message`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `products`
--
ALTER TABLE `products`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
