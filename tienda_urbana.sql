-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-11-2025 a las 01:09:17
-- Versión del servidor: 10.4.25-MariaDB
-- Versión de PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tienda_urbana`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `actualizar_stock_producto` (IN `producto_id` INT, IN `cantidad_vendida` INT)   BEGIN
  UPDATE productos 
  SET stock = stock - cantidad_vendida 
  WHERE id = producto_id AND stock >= cantidad_vendida;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedidos`
--

CREATE TABLE `detalle_pedidos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `producto_nombre` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `detalle_pedidos`
--

INSERT INTO `detalle_pedidos` (`id`, `pedido_id`, `producto_nombre`, `cantidad`, `precio`) VALUES
(1, 1, 'Remera Oversize Negra', 2, '5900.00'),
(2, 1, 'Gorra Snapback Negra', 1, '3200.00'),
(3, 2, 'Campera Bomber Negro', 1, '15000.00'),
(4, 2, 'Joggers Negros', 1, '8700.00'),
(5, 3, 'Joggers Negros', 1, '8700.00');

--
-- Disparadores `detalle_pedidos`
--
DELIMITER $$
CREATE TRIGGER `validar_stock_antes_pedido` BEFORE INSERT ON `detalle_pedidos` FOR EACH ROW BEGIN
  DECLARE stock_actual INT;
  
  SELECT stock INTO stock_actual 
  FROM productos 
  WHERE nombre = NEW.producto_nombre;
  
  IF stock_actual < NEW.cantidad THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Stock insuficiente para este producto';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT 'pendiente',
  `cliente_nombre` varchar(100) DEFAULT NULL,
  `cliente_email` varchar(100) DEFAULT NULL,
  `cliente_telefono` varchar(50) DEFAULT NULL,
  `cliente_direccion` text DEFAULT NULL,
  `cliente_ciudad` varchar(100) DEFAULT NULL,
  `cliente_cp` varchar(20) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `total`, `fecha`, `estado`, `cliente_nombre`, `cliente_email`, `cliente_telefono`, `cliente_direccion`, `cliente_ciudad`, `cliente_cp`, `metodo_pago`) VALUES
(1, '15700.00', '2025-11-18 00:00:07', 'completado', 'Juan Pérez', 'juan@email.com', '+54 11 1234-5678', 'Av. Corrientes 1234, Piso 5', 'Buenos Aires', '1043', 'tarjeta_credito'),
(2, '23400.00', '2025-11-18 00:00:07', 'completado', 'María González', 'maria@email.com', '+54 11 9876-5432', 'Calle Falsa 456', 'Córdoba', '5000', 'mercadopago'),
(3, '8700.00', '2025-11-18 00:00:07', 'pendiente', 'Carlos López', 'carlos@email.com', '+54 341 555-1234', 'San Martín 789', 'Rosario', '2000', 'transferencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `imagen` varchar(500) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `descripcion` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `precio`, `categoria`, `imagen`, `stock`, `descripcion`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Remera Oversize Negra', '5900.00', 'remera', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&h=400&fit=crop', 15, 'Remera oversize 100% algodón, corte holgado y cómodo. Perfecta para el día a día.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(2, 'Remera Oversize Blanca', '5900.00', 'remera', 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=400&h=400&fit=crop', 20, 'Remera oversize blanca básica, ideal para combinar con cualquier outfit urbano.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(3, 'Remera Estampada Urban', '6500.00', 'remera', 'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=400&h=400&fit=crop', 18, 'Remera con estampado exclusivo, diseño urbano y moderno.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(4, 'Remera Tie Dye', '7200.00', 'remera', 'https://images.unsplash.com/photo-1622445275576-721325763afe?w=400&h=400&fit=crop', 12, 'Remera con efecto tie dye único, cada prenda es diferente.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(5, 'Remera Gráfica Street', '6800.00', 'remera', 'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=400&h=400&fit=crop', 16, 'Remera con diseño gráfico urbano, estilo streetwear auténtico.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(6, 'Campera Denim Clásica', '12500.00', 'campera', 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400&h=400&fit=crop', 8, 'Campera de jean clásica, corte regular. Material resistente y duradero.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(7, 'Campera Bomber Negro', '15000.00', 'campera', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=400&h=400&fit=crop', 5, 'Bomber jacket estilo aviador, con cierre y bolsillos laterales.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(8, 'Buzo Hoodie Negro', '11000.00', 'campera', 'https://images.unsplash.com/photo-1544022613-e87ca75a784a?w=400&h=400&fit=crop', 14, 'Buzo con capucha, algodón premium, interior afelpado.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(9, 'Campera Cortaviento', '13500.00', 'campera', 'https://images.unsplash.com/photo-1577697011110-86b207c7d321?w=400&h=400&fit=crop', 10, 'Campera liviana resistente al agua, perfecta para entretiempo.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(10, 'Campera Puffer Negra', '16500.00', 'campera', 'https://images.unsplash.com/photo-1580657018950-c7f7d6a6d990?w=400&h=400&fit=crop', 7, 'Campera acolchada tipo puffer, aislante térmico de alta calidad.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(11, 'Joggers Negros', '8700.00', 'pantalon', 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=400&h=400&fit=crop', 20, 'Joggers de algodón con puño elástico, comodidad total.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(12, 'Pantalón Cargo Beige', '9800.00', 'pantalon', 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=400&h=400&fit=crop', 10, 'Pantalón cargo con múltiples bolsillos, estilo militar urbano.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(13, 'Pantalón Cargo Verde Militar', '9800.00', 'pantalon', 'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=400&h=400&fit=crop', 12, 'Cargo pants color verde militar, fit regular.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(14, 'Jean Recto Negro', '10500.00', 'pantalon', 'https://images.unsplash.com/photo-1542272454315-7ad9f8f8a6b3?w=400&h=400&fit=crop', 15, 'Jean negro de corte recto, tela de calidad premium.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(15, 'Pantalón Deportivo Gris', '7500.00', 'pantalon', 'https://images.unsplash.com/photo-1555689502-c4b22d76c56f?w=400&h=400&fit=crop', 18, 'Pantalón deportivo con cordón ajustable, ideal para streetwear.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(16, 'Jean Skinny Azul', '9500.00', 'pantalon', 'https://images.unsplash.com/photo-1517438476312-10d79c077509?w=400&h=400&fit=crop', 14, 'Jean skinny fit azul oscuro, diseño moderno y cómodo.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(17, 'Gorra Snapback Negra', '3200.00', 'accesorio', 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=400&h=400&fit=crop', 25, 'Gorra snapback ajustable, visera plana, diseño minimalista.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(18, 'Gorra New Era Yankees', '4500.00', 'accesorio', 'https://images.unsplash.com/photo-1575428652377-a2d80e2277fc?w=400&h=400&fit=crop', 18, 'Gorra oficial New Era, logo bordado, calce perfecto.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(19, 'Mochila Urbana', '8900.00', 'accesorio', 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400&h=400&fit=crop', 10, 'Mochila con compartimento para laptop, resistente al agua.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(20, 'Riñonera Crossbody', '4200.00', 'accesorio', 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=400&h=400&fit=crop', 15, 'Riñonera convertible en crossbody, múltiples bolsillos.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(21, 'Medias Pack x3', '2500.00', 'accesorio', 'https://images.unsplash.com/photo-1586350977771-b3b0abd50c82?w=400&h=400&fit=crop', 30, 'Pack de 3 pares de medias de algodón, colores surtidos.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(22, 'Lentes de Sol Wayfarer', '5500.00', 'accesorio', 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=400&h=400&fit=crop', 12, 'Lentes de sol estilo wayfarer, protección UV400.', '2025-11-18 00:00:07', '2025-11-18 00:00:07'),
(23, 'Billetera de Cuero', '3800.00', 'accesorio', 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=400&h=400&fit=crop', 20, 'Billetera de cuero genuino con múltiples compartimentos.', '2025-11-18 00:00:07', '2025-11-18 00:00:07');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `productos_bajo_stock`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `productos_bajo_stock` (
`id` int(11)
,`nombre` varchar(100)
,`categoria` varchar(50)
,`stock` int(11)
,`precio` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `resumen_pedidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `resumen_pedidos` (
`id` int(11)
,`fecha` timestamp
,`cliente_nombre` varchar(100)
,`cliente_email` varchar(100)
,`total` decimal(10,2)
,`estado` varchar(50)
,`metodo_pago` varchar(50)
,`cantidad_productos` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `ventas_por_producto`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `ventas_por_producto` (
`producto_nombre` varchar(100)
,`total_pedidos` bigint(21)
,`unidades_vendidas` decimal(32,0)
,`ingresos_totales` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `productos_bajo_stock`
--
DROP TABLE IF EXISTS `productos_bajo_stock`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `productos_bajo_stock`  AS SELECT `productos`.`id` AS `id`, `productos`.`nombre` AS `nombre`, `productos`.`categoria` AS `categoria`, `productos`.`stock` AS `stock`, `productos`.`precio` AS `precio` FROM `productos` WHERE `productos`.`stock` <= 5 ORDER BY `productos`.`stock` ASC  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `resumen_pedidos`
--
DROP TABLE IF EXISTS `resumen_pedidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `resumen_pedidos`  AS SELECT `p`.`id` AS `id`, `p`.`fecha` AS `fecha`, `p`.`cliente_nombre` AS `cliente_nombre`, `p`.`cliente_email` AS `cliente_email`, `p`.`total` AS `total`, `p`.`estado` AS `estado`, `p`.`metodo_pago` AS `metodo_pago`, count(`dp`.`id`) AS `cantidad_productos` FROM (`pedidos` `p` left join `detalle_pedidos` `dp` on(`p`.`id` = `dp`.`pedido_id`)) GROUP BY `p`.`id` ORDER BY `p`.`fecha` AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `ventas_por_producto`
--
DROP TABLE IF EXISTS `ventas_por_producto`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `ventas_por_producto`  AS SELECT `dp`.`producto_nombre` AS `producto_nombre`, count(distinct `dp`.`pedido_id`) AS `total_pedidos`, sum(`dp`.`cantidad`) AS `unidades_vendidas`, sum(`dp`.`cantidad` * `dp`.`precio`) AS `ingresos_totales` FROM (`detalle_pedidos` `dp` join `pedidos` `p` on(`dp`.`pedido_id` = `p`.`id`)) WHERE `p`.`estado` = 'completado' GROUP BY `dp`.`producto_nombre` ORDER BY sum(`dp`.`cantidad` * `dp`.`precio`) AS `DESCdesc` ASC  ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_detalle_producto` (`producto_nombre`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_email` (`cliente_email`),
  ADD KEY `idx_pedido_cliente` (`cliente_nombre`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_stock` (`stock`),
  ADD KEY `idx_producto_nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_pedidos`
--
ALTER TABLE `detalle_pedidos`
  ADD CONSTRAINT `detalle_pedidos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
