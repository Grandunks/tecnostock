DROP DATABASE IF EXISTS `tecnostock_db`;
CREATE DATABASE `tecnostock_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tecnostock_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categoria` (`id_categoria`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Mouse', 'Ratones ópticos, inalámbricos, ergonómicos y gamer de alta precisión', 1),
(2, 'Teclados', 'Teclados mecánicos, TKL, 60%, de membrana e inalámbricos', 1),
(3, 'Audífonos', 'Headsets gamer, audífonos over-ear con cancelación de ruido e in-ear', 1),
(4, 'Monitores', 'Pantallas IPS, VA, OLED y paneles de alta tasa de refresco', 1),
(5, 'Almacenamiento SSD', 'Unidades de estado sólido NVMe M.2, SATA 2.5 y SSD portátiles', 1),
(6, 'Memorias RAM', 'Módulos DDR4 y DDR5 de alto rendimiento para escritorio y laptop', 1),
(7, 'Tarjetas Gráficas', 'GPUs dedicadas NVIDIA GeForce RTX y AMD Radeon RX', 1),
(8, 'Procesadores', 'CPUs Intel Core y AMD Ryzen para estaciones de trabajo y gaming', 1),
(9, 'Placas Madre', 'Motherboards ATX, Micro-ATX y Mini-ITX para sockets AM5, AM4 e Intel', 1),
(10, 'Fuentes de Poder', 'Fuentes certificadas 80 Plus Bronze, Gold y Platinum modulares', 1),
(11, 'Gabinetes PC', 'Torres ATX, Micro-ATX con flujo de aire optimizado y vidrio templado', 1),
(12, 'Cables y Adaptadores', 'Cables HDMI 2.1, DisplayPort 1.4, USB-C Thunderbolt y hubs', 1);

CREATE TABLE `movimiento` (
  `id_movimiento` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` tinyint(1) NOT NULL CHECK (`tipo` in (1,2)),
  `motivo` varchar(100) NOT NULL,
  `cantidad` int(11) NOT NULL CHECK (`cantidad` > 0),
  `observacion` varchar(255) DEFAULT NULL,
  `editado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_edicion` datetime DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `movimiento` (`id_movimiento`, `fecha_hora`, `tipo`, `motivo`, `cantidad`, `observacion`, `editado`, `fecha_edicion`, `id_producto`, `id_usuario`, `id_proveedor`) VALUES
(1, '2026-10-01 09:15:00', 1, 'Compra a Proveedor', 20, 'Factura #9011 Logitech Chile', 0, NULL, 1, 1, 3),
(2, '2026-10-02 11:30:00', 1, 'Compra a Proveedor', 25, 'Factura #9012 Logitech Chile', 0, NULL, 2, 2, 3),
(3, '2026-10-03 15:45:00', 1, 'Compra a Proveedor', 15, 'Factura #4410 Razer Dist.', 0, NULL, 3, 3, 9),
(4, '2026-10-04 10:20:00', 2, 'Venta en tienda', 2, 'Boleta #3001 cliente mostrador', 0, NULL, 1, 2, NULL),
(5, '2026-10-05 16:10:00', 1, 'Compra a Proveedor', 18, 'Factura #7721 Sony Chile', 0, NULL, 21, 4, 1),
(6, '2026-10-06 12:05:00', 2, 'Despacho pedido', 3, 'Pedido web #4501 despacho Santiago', 0, NULL, 21, 4, NULL),
(7, '2026-10-07 09:50:00', 1, 'Compra a Proveedor', 30, 'Factura #5510 Samsung B2B', 0, NULL, 40, 5, 7),
(8, '2026-10-08 14:40:00', 2, 'Venta en tienda', 6, 'Boleta #3015 armado PCs', 1, '2026-10-08 15:10:00', 40, 5, NULL),
(9, '2026-10-09 17:25:00', 1, 'Compra a Proveedor', 10, 'Factura #8812 ASUS Chile', 0, NULL, 57, 6, 4),
(10, '2026-10-10 11:15:00', 2, 'Despacho pedido', 3, 'Pedido empresa #4518', 0, NULL, 57, 6, NULL),
(11, '2026-10-11 10:00:00', 1, 'Compra a Proveedor', 15, 'Factura #8815 ASUS Chile', 0, NULL, 65, 7, 4),
(12, '2026-10-12 13:30:00', 2, 'Venta en tienda', 4, 'Boleta #3042', 0, NULL, 65, 7, NULL),
(13, '2026-10-14 09:40:00', 1, 'Compra a Proveedor', 25, 'Factura #6102 Corsair Chile', 0, NULL, 49, 8, 5),
(14, '2026-10-15 16:50:00', 2, 'Venta en tienda', 5, 'Boleta #3060', 0, NULL, 49, 8, NULL),
(15, '2026-10-16 11:20:00', 1, 'Compra a Proveedor', 40, 'Factura #3310 Kingston Chile', 0, NULL, 41, 10, 6),
(16, '2026-10-17 15:10:00', 2, 'Despacho pedido', 10, 'Pedido distribuidor #4550', 0, NULL, 41, 10, NULL),
(17, '2026-10-18 10:45:00', 1, 'Compra a Proveedor', 15, 'Factura #9105 Logitech Chile', 0, NULL, 11, 2, 3),
(18, '2026-10-19 14:15:00', 2, 'Merma o daño', 1, 'Caja golpeada en recepción bodega', 0, NULL, 11, 2, NULL),
(19, '2026-10-20 09:30:00', 1, 'Compra a Proveedor', 20, 'Factura #2290 Xiaomi Chile', 0, NULL, 34, 3, 2),
(20, '2026-10-21 17:00:00', 1, 'Devolución de cliente', 1, 'Cambio por modelo de 27 pulgadas', 0, NULL, 34, 3, NULL),
(21, '2026-10-22 12:20:00', 1, 'Compra a Proveedor', 18, 'Factura #7012 MSI Chile', 0, NULL, 66, 4, 8),
(22, '2026-10-23 16:35:00', 2, 'Venta en tienda', 3, 'Boleta #3102', 0, NULL, 66, 4, NULL),
(23, '2026-10-24 10:10:00', 1, 'Compra a Proveedor', 20, 'Factura #6201 Corsair Chile', 0, NULL, 80, 5, 5),
(24, '2026-10-25 14:55:00', 2, 'Despacho pedido', 4, 'Pedido web #4602', 0, NULL, 80, 5, NULL),
(25, '2026-10-26 11:40:00', 1, 'Compra a Proveedor', 22, 'Factura #6210 Corsair Chile', 0, NULL, 87, 6, 5),
(26, '2026-10-27 15:25:00', 2, 'Venta en tienda', 4, 'Boleta #3140', 0, NULL, 87, 6, NULL),
(27, '2026-10-28 09:05:00', 1, 'Compra a Proveedor', 50, 'Factura #7800 Sony Chile', 0, NULL, 94, 7, 1),
(28, '2026-10-29 13:50:00', 2, 'Venta en tienda', 5, 'Boleta #3165', 0, NULL, 94, 7, NULL),
(29, '2026-10-30 16:15:00', 1, 'Compra a Proveedor', 20, 'Factura #1190 HyperX Chile', 0, NULL, 23, 8, 10),
(30, '2026-10-31 12:30:00', 2, 'Despacho pedido', 3, 'Pedido web #4630', 0, NULL, 23, 8, NULL),
(31, '2026-11-02 10:15:00', 1, 'Compra a Proveedor', 16, 'Factura #8910 ASUS Chile', 0, NULL, 73, 10, 4),
(32, '2026-11-03 14:40:00', 2, 'Venta en tienda', 4, 'Boleta #3201', 0, NULL, 73, 10, NULL),
(33, '2026-11-04 09:50:00', 1, 'Compra a Proveedor', 12, 'Factura #4502 Razer Dist.', 0, NULL, 12, 1, 9),
(34, '2026-11-05 17:10:00', 2, 'Despacho pedido', 4, 'Pedido web #4680', 1, '2026-11-05 17:45:00', 12, 1, NULL),
(35, '2026-11-06 11:25:00', 1, 'Compra a Proveedor', 15, 'Factura #5610 Samsung B2B', 0, NULL, 30, 2, 7),
(36, '2026-11-07 15:30:00', 2, 'Venta en tienda', 3, 'Boleta #3244', 0, NULL, 30, 2, NULL),
(37, '2026-11-08 10:05:00', 1, 'Compra a Proveedor', 25, 'Factura #3412 Kingston Chile', 0, NULL, 46, 3, 6),
(38, '2026-11-09 16:20:00', 2, 'Despacho pedido', 11, 'Pedido corporativo #4710', 0, NULL, 46, 3, NULL),
(39, '2026-11-10 12:45:00', 1, 'Compra a Proveedor', 16, 'Factura #7120 MSI Chile', 0, NULL, 81, 4, 8),
(40, '2026-11-11 14:10:00', 2, 'Venta en tienda', 2, 'Boleta #3289', 0, NULL, 81, 4, NULL),
(41, '2026-11-12 09:35:00', 1, 'Compra a Proveedor', 30, 'Factura #2350 Xiaomi Chile', 0, NULL, 96, 5, 2),
(42, '2026-11-13 15:50:00', 2, 'Venta en tienda', 6, 'Boleta #3310', 0, NULL, 96, 5, NULL),
(43, '2026-11-14 11:00:00', 1, 'Ajuste de inventario (+)', 3, 'Conteo cíclico mensual bodega', 0, NULL, 5, 6, NULL),
(44, '2026-11-15 16:40:00', 2, 'Ajuste de inventario (-)', 2, 'Unidades exhibición vitrina', 0, NULL, 4, 7, NULL),
(45, '2026-11-16 10:30:00', 1, 'Compra a Proveedor', 12, 'Factura #8995 ASUS Chile', 0, NULL, 15, 8, 4),
(46, '2026-11-17 17:15:00', 2, 'Despacho pedido', 10, 'Pedido web #4755', 0, NULL, 15, 8, NULL),
(47, '2026-11-18 12:10:00', 1, 'Compra a Proveedor', 10, 'Factura #4590 Razer Dist.', 0, NULL, 24, 10, 9),
(48, '2026-11-19 15:05:00', 2, 'Venta en tienda', 6, 'Boleta #3360', 0, NULL, 24, 10, NULL),
(49, '2026-11-20 09:45:00', 1, 'Compra a Proveedor', 20, 'Factura #5699 Samsung B2B', 0, NULL, 45, 2, 7),
(50, '2026-11-21 14:25:00', 2, 'Venta en tienda', 1, 'Boleta #3388', 0, NULL, 45, 2, NULL),
(51, '2026-11-22 11:50:00', 1, 'Compra a Proveedor', 15, 'Factura #9201 Logitech Chile', 0, NULL, 100, 3, 3),
(52, '2026-11-23 10:15:00', 2, 'Venta en tienda', 12, 'Boleta #3410 venta mayorista cables', 1, '2026-11-23 10:40:00', 100, 1, NULL);

CREATE TABLE `producto` (
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `precio` int(11) NOT NULL CHECK (`precio` >= 0),
  `stock_actual` int(11) NOT NULL DEFAULT 0 CHECK (`stock_actual` >= 0),
  `stock_minimo` int(11) NOT NULL DEFAULT 0 CHECK (`stock_minimo` >= 0),
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `id_categoria` int(11) NOT NULL,
  `id_proveedor` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `producto` (`id_producto`, `codigo`, `nombre`, `descripcion`, `imagen`, `precio`, `stock_actual`, `stock_minimo`, `estado`, `id_categoria`, `id_proveedor`) VALUES
(1, 'MOU-001', 'Logitech G Pro X Superlight 2', 'Mouse inalámbrico ultraligero 60g sensor HERO 2 32000 DPI', 'uploads/mou-001_2b15f0.jpg', 149990, 18, 5, 1, 1, 3),
(2, 'MOU-002', 'Logitech MX Master 3S Grafito', 'Mouse ergonómico inalámbrico silencioso 8000 DPI rueda MagSpeed', 'uploads/mou-002_df432e.jpg', 109990, 22, 6, 1, 1, 3),
(3, 'MOU-003', 'Razer DeathAdder V3 Pro Negro', 'Mouse gamer inalámbrico ergonómico 63g sensor Focus Pro 30K', 'uploads/mou-003_46f58d.png', 139990, 12, 4, 1, 1, 9),
(4, 'MOU-004', 'Razer Viper V2 Pro Blanco', 'Mouse ambidiestro ultraligero 58g switches ópticos Gen-3', 'https://rebajasperu.com/imagenes/Mouse-Razer-Viper-V2-Pro-30K-DPI-Wireless-blanco-Ultraligero,-Switch-Óptico-90M-RZ01-04390200-R3U1.jpg', 129990, 3, 5, 1, 1, 9),
(5, 'MOU-005', 'Logitech G502 HERO High Performance', 'Mouse gamer alámbrico 11 botones programables y pesas ajustables', 'uploads/mou-005_f22bf2.webp', 49990, 35, 10, 1, 1, 3),
(6, 'MOU-006', 'HyperX Pulsefire Haste 2 Wireless', 'Mouse gamer inalámbrico 61g Bluetooth y 2.4GHz blanco', 'https://athome.ge/wp-content/uploads/2025/09/HyperX-Pulsefire-Haste-2-Wireless-Gaming-Mouse-White.png', 79990, 14, 4, 1, 1, 10),
(7, 'MOU-007', 'Corsair M65 RGB Ultra Wireless', 'Mouse gamer con estructura de aluminio y sensor Marksman 26K', 'uploads/mou-007_088bfc.webp', 99990, 9, 3, 1, 1, 5),
(8, 'MOU-008', 'ASUS ROG Harpe Ace Aim Lab Edition', 'Mouse inalámbrico 54g co-desarrollado con Aim Lab 36000 DPI', 'uploads/mou-008_e97adf.png', 134990, 7, 3, 1, 1, 4),
(9, 'MOU-009', 'Xiaomi Mi Dual Mode Wireless Mouse', 'Mouse silencioso oficina Bluetooth y receptor USB 2.4GHz', 'https://gadgetstore.ec/wp-content/uploads/2025/08/2-1-600x600.jpg', 16990, 45, 12, 1, 1, 2),
(10, 'MOU-010', 'Logitech G305 Lightspeed Negro', 'Mouse inalámbrico sensor HERO 12000 DPI 250 horas batería', 'uploads/mou-010_e8c318.jpg', 39990, 28, 8, 1, 1, 3),
(11, 'TEC-001', 'Logitech G Pro X TKL Lightspeed', 'Teclado mecánico inalámbrico compacto sin pad numérico RGB', 'uploads/tec-001_988267.png', 169990, 14, 4, 1, 2, 3),
(12, 'TEC-002', 'Razer Huntsman V3 Pro Mini 60%', 'Teclado óptico analógico 60% con Rapid Trigger ajustable', 'uploads/tec-002_cf594f.jpg', 179990, 8, 3, 1, 2, 9),
(13, 'TEC-003', 'HyperX Alloy Origins 65 Aqua', 'Teclado mecánico compacto 65% cuerpo de aluminio switches táctiles', 'https://pckumar.in/wp-content/uploads/2024/12/56R64AA.jpg', 89990, 19, 5, 1, 2, 10),
(14, 'TEC-004', 'Corsair K70 RGB Pro Cherry MX Red', 'Teclado mecánico full-size aluminio cepillado polling 8000Hz', 'uploads/tec-004_39783e.png', 149990, 11, 4, 1, 2, 5),
(15, 'TEC-005', 'ASUS ROG Azoth 75% Wireless Custom', 'Teclado mecánico custom 75% con pantalla OLED y kit lubricación', 'uploads/tec-005_7d80df.jpg', 229990, 2, 3, 1, 2, 4),
(16, 'TEC-006', 'Logitech MX Keys S Inalámbrico', 'Teclado perfil bajo retroiluminado inteligente multi-dispositivo', 'uploads/tec-006_22d11d.jpg', 114990, 16, 5, 1, 2, 3),
(17, 'TEC-007', 'Razer BlackWidow V4 X Green Switch', 'Teclado mecánico gamer con 6 teclas macro dedicadas y Chroma RGB', 'uploads/tec-007_e91bd3.jpg', 119990, 10, 4, 1, 2, 9),
(18, 'TEC-008', 'MSI Vigor GK50 Low Profile TKL', 'Teclado mecánico perfil bajo switches Kailh Choc White', 'uploads/tec-008_7d79b5.png', 69990, 13, 4, 1, 2, 8),
(19, 'TEC-009', 'Corsair K65 Plus Wireless 75%', 'Teclado inalámbrico switches lineales MLX Red pre-lubricados', 'uploads/tec-009_80e569.webp', 139990, 9, 3, 1, 2, 5),
(20, 'TEC-010', 'Logitech K120 USB Español', 'Teclado estándar resistente a salpicaduras plug-and-play', 'uploads/tec-010_5963ce.jpg', 12990, 50, 15, 1, 2, 3),
(21, 'AUD-001', 'Sony WH-1000XM5 Cancelación Ruido', 'Audífonos inalámbricos over-ear ANC líder con 30h batería negro', 'uploads/aud-001_0a67d8.jpg', 319990, 15, 5, 1, 3, 1),
(22, 'AUD-002', 'Logitech G733 Lightspeed RGB', 'Headset gamer inalámbrico ultraligero 278g micrófono Blue VO!CE', 'uploads/aud-002_683b51.jpg', 129990, 20, 6, 1, 3, 3),
(23, 'AUD-003', 'HyperX Cloud III Wireless Negro/Rojo', 'Headset gamer inalámbrico 120 horas de batería drivers 53mm', 'uploads/aud-003_063037.png', 134990, 17, 5, 1, 3, 10),
(24, 'AUD-004', 'Razer BlackShark V2 Pro 2023', 'Headset esports inalámbrico micrófono HyperClear Super Wideband', 'uploads/aud-004_14e9f6.png', 179990, 4, 5, 1, 3, 9),
(25, 'AUD-005', 'Sony INZONE H9 Wireless ANC', 'Headset gamer espacial 360 para PC y PS5 con cancelación ruido', 'uploads/aud-005_867297.webp', 229990, 8, 3, 1, 3, 1),
(26, 'AUD-006', 'Corsair HS80 RGB Wireless Blanco', 'Headset inalámbrico Dolby Atmos banda flotante micrófono broadcast', 'uploads/aud-006_758510.jpg', 139990, 11, 4, 1, 3, 5),
(27, 'AUD-007', 'Xiaomi Redmi Buds 5 Pro Negro', 'Audífonos true wireless ANC 52dB audio Hi-Res LDAC', 'uploads/aud-007_0050fd.png', 54990, 32, 8, 1, 3, 2),
(28, 'AUD-008', 'Samsung Galaxy Buds3 Pro Plata', 'Audífonos inalámbricos Hi-Fi 24-bit con cancelación adaptativa', 'uploads/aud-008_9b36c3.jpg', 189990, 10, 4, 1, 3, 7),
(29, 'AUD-009', 'ASUS ROG Delta S Core', 'Headset gamer ligero 270g con DAC Hi-Res multiplataforma', 'uploads/aud-009_0df961.png', 94990, 9, 3, 1, 3, 4),
(30, 'AUD-010', 'Sony WF-1000XM5 In-Ear ANC', 'Audífonos compactos con procesador integrado V2 y sonido Hi-Res', 'uploads/aud-010_63e28e.jpg', 249990, 12, 4, 1, 3, 1),
(31, 'MON-001', 'Samsung Odyssey G5 27\" QHD 165Hz', 'Monitor curvo 1000R 2560x1440 1ms FreeSync Premium', 'uploads/mon-001_ea41fe.jpg', 259990, 12, 4, 1, 4, 7),
(32, 'MON-002', 'ASUS TUF Gaming VG27AQ 27\" IPS', 'Monitor 2K QHD 165Hz G-Sync Compatible HDR10', 'uploads/mon-002_b84f08.jpg', 289990, 9, 3, 1, 4, 4),
(33, 'MON-003', 'MSI Optix G2412 24\" FHD 170Hz', 'Monitor gamer panel IPS 1ms FreeSync bordes ultradelgados', 'uploads/mon-003_8b9788.png', 169990, 18, 5, 1, 4, 8),
(34, 'MON-004', 'Samsung Odyssey OLED G8 34\" UltraWide', 'Monitor curvo QD-OLED WQHD 175Hz 0.03ms Smart TV', 'uploads/mon-004_4f010c.webp', 899990, 3, 2, 1, 4, 7),
(35, 'MON-005', 'Xiaomi Gaming Monitor G27i 27\" 165Hz', 'Monitor Fast IPS Full HD 1ms 99% sRGB calibrado de fábrica', 'uploads/mon-005_798be3.jpg', 159990, 21, 6, 1, 4, 2),
(36, 'MON-006', 'ASUS ProArt Display PA278QV 27\"', 'Monitor profesional WQHD 100% sRGB Calman Verified para diseño', 'uploads/mon-006_2f2026.jpg', 349990, 6, 2, 1, 4, 4),
(37, 'MON-007', 'MSI MAG 274UPF 27\" 4K UHD 144Hz', 'Monitor Rapid IPS 4K HDMI 2.1 USB-C 65W Power Delivery', 'uploads/mon-007_142741.jpg', 479990, 5, 2, 1, 4, 8),
(38, 'MON-008', 'Sony INZONE M9 27\" 4K 144Hz HDR', 'Monitor IPS Full Array Local Dimming optimizado para PC y PS5', 'uploads/mon-008_ee7181.jpg', 699990, 2, 2, 1, 4, 1),
(39, 'MON-009', 'Samsung ViewFinity S6 27\" QHD 75Hz', 'Monitor oficina USB-C con hub LAN y soporte ergonómico', 'uploads/mon-009_ad7982.webp', 229990, 10, 3, 1, 4, 7),
(40, 'ALM-001', 'Samsung 990 PRO 2TB NVMe PCIe 4.0', 'SSD M.2 lectura hasta 7450 MB/s escritura 6900 MB/s', 'uploads/alm-001_d712bb.png', 189990, 24, 6, 1, 5, 7),
(41, 'ALM-002', 'Kingston KC3000 1TB PCIe 4.0 NVMe', 'SSD M.2 2280 alto rendimiento disipador grafeno 7000 MB/s', 'uploads/alm-002_dee589.jpg', 94990, 30, 8, 1, 5, 6),
(42, 'ALM-003', 'Kingston NV2 1TB M.2 2280 NVMe', 'SSD PCIe 4.0 económico ideal para laptops y PCs', 'uploads/alm-003_5fd777.jpg', 64990, 42, 10, 1, 5, 6),
(43, 'ALM-004', 'Samsung 980 PRO 1TB con Disipador', 'SSD NVMe M.2 compatible con PlayStation 5 y PC', 'uploads/alm-004_f968eb.webp', 119990, 15, 5, 1, 5, 7),
(44, 'ALM-005', 'Corsair MP600 PRO LPX 2TB NVMe', 'SSD M.2 Gen4 7100 MB/s disipador aluminio perfil bajo', 'uploads/alm-005_060ff8.jpg', 179990, 11, 4, 1, 5, 5),
(45, 'ALM-006', 'Samsung T7 Shield 1TB SSD Portátil', 'Disco externo USB 3.2 Gen2 resistente a agua y caídas IP65', 'uploads/alm-006_e15b8a.jpg', 109990, 19, 5, 1, 5, 7),
(46, 'ALM-007', 'Kingston XS2000 1TB SSD Externo', 'SSD de bolsillo USB-C 20Gbps hasta 2000 MB/s', 'uploads/alm-007_d1ea74.png', 99990, 14, 4, 1, 5, 6),
(47, 'ALM-008', 'Kingston A400 480GB SATA 2.5\"', 'Unidad de estado sólido SATA III para actualización de equipos', 'uploads/alm-008_26c0c3.webp', 36990, 38, 10, 1, 5, 6),
(48, 'ALM-009', 'Samsung 870 EVO 1TB SATA 2.5\"', 'SSD SATA líder en confiabilidad 560 MB/s lectura', 'https://store.qi.com.kw/wp-content/uploads/2024/12/s1t-2.png', 89990, 16, 5, 1, 5, 7),
(49, 'MEM-001', 'Corsair Vengeance RGB 32GB (2x16) DDR5', 'Kit DDR5 6000MHz CL30 Intel XMP / AMD EXPO Negro', 'uploads/mem-001_88d3c0.jpg', 139990, 20, 6, 1, 6, 5),
(50, 'MEM-002', 'Kingston Fury Beast 16GB (2x8) DDR4', 'Kit DDR4 3200MHz CL16 disipador aluminio perfil bajo', 'uploads/mem-002_d0ba98.jpg', 49990, 34, 10, 1, 6, 6),
(51, 'MEM-003', 'Corsair Dominator Titanium 32GB DDR5', 'Kit DDR5 6400MHz CL32 barra superior LED intercambiable', 'uploads/mem-003_02e312.png', 189990, 8, 3, 1, 6, 5),
(52, 'MEM-004', 'Kingston Fury Renegade 32GB (2x16) DDR5', 'Kit DDR5 6400MHz CL32 aluminio negro y plata', 'uploads/mem-004_dc0a85.jpg', 149990, 13, 4, 1, 6, 6),
(53, 'MEM-005', 'Corsair Vengeance LPX 32GB (2x16) DDR4', 'Kit DDR4 3600MHz CL18 optimizado para AMD Ryzen', 'uploads/mem-005_90b749.webp', 84990, 25, 6, 1, 6, 5),
(54, 'MEM-006', 'Kingston Fury Impact 16GB DDR5 SO-DIMM', 'Módulo RAM 5600MHz para notebook gamer', 'uploads/mem-006_7fe50f.jpg', 59990, 18, 5, 1, 6, 6),
(55, 'MEM-007', 'HyperX Fury 8GB DDR4 3200MHz', 'Módulo individual DDR4 CL16 Plug N Play', 'uploads/mem-007_4d7ffd.jpg', 26990, 40, 12, 1, 6, 10),
(56, 'MEM-008', 'Corsair Vengeance 64GB (2x32) DDR5', 'Kit workstation DDR5 5600MHz CL40 negro', 'uploads/mem-008_671d34.jpg', 219990, 4, 2, 1, 6, 5),
(57, 'TAR-001', 'ASUS Dual GeForce RTX 4070 SUPER 12GB', 'Tarjeta gráfica GDDR6X OC Edition doble ventilador Axial-tech', 'uploads/tar-001_887b1a.jpg', 689990, 7, 3, 1, 7, 4),
(58, 'TAR-002', 'MSI GeForce RTX 4060 Ventus 2X Black 8G', 'Tarjeta gráfica 8GB GDDR6 DLSS 3 Ray Tracing', 'uploads/tar-002_3792c0.png', 349990, 14, 4, 1, 7, 8),
(59, 'TAR-003', 'ASUS ROG Strix RTX 4080 SUPER 16GB', 'GPU entusiasta triple ventilador cámara de vapor Aura Sync', 'uploads/tar-003_60ffd7.jpg', 1299990, 2, 2, 1, 7, 4),
(60, 'TAR-004', 'MSI GeForce RTX 4070 Ti SUPER Gaming X', 'Tarjeta gráfica 16GB GDDR6X disipación Tri Frozr 3', 'uploads/tar-004_95a72f.png', 949990, 5, 2, 1, 7, 8),
(61, 'TAR-005', 'ASUS Dual Radeon RX 7800 XT OC 16GB', 'Tarjeta gráfica AMD RDNA 3 16GB GDDR6 HDMI 2.1', 'uploads/tar-005_ca912f.jpg', 579990, 6, 2, 1, 7, 4),
(62, 'TAR-006', 'MSI Radeon RX 7600 Mech 2X Classic 8G', 'GPU 1080p Ultra 8GB GDDR6 doble ventilador TORX', 'uploads/tar-006_0ba673.png', 299990, 11, 3, 1, 7, 8),
(63, 'TAR-007', 'ASUS TUF Gaming GeForce RTX 4090 24GB', 'Tarjeta gráfica tope de gama 24GB GDDR6X componentes grado militar', 'uploads/tar-007_049f37.webp', 1999990, 1, 1, 1, 7, 4),
(64, 'TAR-008', 'MSI GeForce GTX 1650 D6 Ventus XS OC', 'Tarjeta de video 4GB GDDR6 sin conector energía externo', 'uploads/tar-008_58a640.png', 169990, 0, 3, 0, 7, 8),
(65, 'PRO-001', 'AMD Ryzen 7 7800X3D 8-Core 5.0GHz', 'Procesador AM5 tecnología 3D V-Cache 96MB L3 gaming', 'uploads/pro-001_52ab60.jpg', 429990, 11, 4, 1, 8, 4),
(66, 'PRO-002', 'Intel Core i5-14600K 14-Core 5.3GHz', 'Procesador LGA1700 6 P-Cores + 8 E-Cores desbloqueado', 'uploads/pro-002_630004.png', 329990, 15, 4, 1, 8, 8),
(67, 'PRO-003', 'AMD Ryzen 5 7600 6-Core 5.1GHz AM5', 'Procesador 6 núcleos 12 hilos incluye disipador Wraith Stealth', 'uploads/pro-003_69b41b.jpg', 219990, 22, 6, 1, 8, 4),
(68, 'PRO-004', 'Intel Core i7-14700K 20-Core 5.6GHz', 'Procesador LGA1700 8 P-Cores + 12 E-Cores 33MB Smart Cache', 'uploads/pro-004_d80608.webp', 439990, 9, 3, 1, 8, 8),
(69, 'PRO-005', 'AMD Ryzen 9 7950X 16-Core 5.7GHz', 'Procesador workstation 16 núcleos 32 hilos Socket AM5', 'uploads/pro-005_bf6bfe.webp', 589990, 4, 2, 1, 8, 4),
(70, 'PRO-006', 'AMD Ryzen 5 5600 6-Core 4.4GHz AM4', 'Procesador calidad-precio socket AM4 PCIe 4.0 con cooler', 'uploads/pro-006_48a663.jpg', 139990, 27, 8, 1, 8, 4),
(71, 'PRO-007', 'Intel Core i3-14100F 4-Core 4.7GHz', 'Procesador de entrada LGA1700 requiere gráficos dedicados', 'uploads/pro-007_d87a60.jpg', 119990, 18, 5, 1, 8, 8),
(72, 'PRO-008', 'Intel Core i9-14900K 24-Core 6.0GHz', 'Procesador insignia LGA1700 24 núcleos 32 hilos', 'uploads/pro-008_282e9f.jpg', 629990, 3, 2, 1, 8, 8),
(73, 'PLA-001', 'ASUS TUF Gaming B650-PLUS WiFi AM5', 'Placa madre ATX DDR5 PCIe 5.0 M.2 WiFi 6 LAN 2.5Gb', 'uploads/pla-001_c27576.webp', 219990, 12, 4, 1, 9, 4),
(74, 'PLA-002', 'MSI MAG B760 Tomahawk WiFi DDR5', 'Placa madre ATX Intel LGA1700 VRM 12+1+1 fases WiFi 6E', 'uploads/pla-002_175b3f.webp', 209990, 10, 3, 1, 9, 8),
(75, 'PLA-003', 'ASUS Prime B550M-A WiFi II AM4', 'Placa madre Micro-ATX DDR4 dual M.2 Bluetooth 5.2', 'uploads/pla-003_22743e.webp', 119990, 19, 5, 1, 9, 4),
(76, 'PLA-004', 'MSI PRO Z790-P WiFi DDR5 ATX', 'Placa madre Intel Z790 overclocking 4x M.2 USB 3.2 Gen2x2', 'uploads/pla-004_2c8a57.webp', 249990, 7, 3, 1, 9, 8),
(77, 'PLA-005', 'ASUS ROG Strix X670E-F Gaming WiFi', 'Placa madre entusiasta AM5 PCIe 5.0 GPU y SSD audio SupremeFX', 'uploads/pla-005_b01fef.png', 429990, 4, 2, 1, 9, 4),
(78, 'PLA-006', 'MSI B650M Gaming Plus WiFi mATX', 'Placa madre Micro-ATX AM5 DDR5 7200+MHz disipador extendido', 'uploads/pla-006_8dd46b.jpg', 169990, 14, 4, 1, 9, 8),
(79, 'PLA-007', 'ASUS Prime H610M-K D4 LGA1700', 'Placa madre económica Micro-ATX DDR4 M.2 PCIe 3.0', 'https://www.geewiz.co.za/341732-large_default/asus-prime-h610m-k-argb-intel-h610-lga-1700-ddr5-pcie-40-m2-slot-realtek-1gb-ethernet-hdmi-vga-usb-32-gen-1-sata-6gbps-addressable-gen-2-header-aura-sync.jpg', 84990, 21, 6, 1, 9, 4),
(80, 'FUE-001', 'Corsair RM850e 850W 80+ Gold Modular', 'Fuente totalmente modular ATX 3.0 PCIe 5.0 cable 12VHPWR', 'uploads/fue-001_ae87ff.png', 139990, 16, 4, 1, 10, 5),
(81, 'FUE-002', 'MSI MAG A850GL PCIe5 850W Gold', 'Fuente modular compacta 80 Plus Gold conector nativo 16 pines', 'uploads/fue-002_5b7637.jpg', 129990, 14, 4, 1, 10, 8),
(82, 'FUE-003', 'Corsair CX650 650W 80+ Bronze', 'Fuente silenciosa ventilador 120mm bajo ruido cables negros', 'uploads/fue-003_f0a040.webp', 69990, 25, 7, 1, 10, 5),
(83, 'FUE-004', 'ASUS TUF Gaming 750W 80+ Gold Modular', 'Fuente con recubrimiento PCB protector y condensadores japoneses', 'uploads/fue-004_290560.jpg', 119990, 11, 3, 1, 10, 4),
(84, 'FUE-005', 'Corsair RM1000x SHIFT 1000W Gold', 'Fuente modular con conexiones laterales ATX 3.0 modo Zero RPM', 'uploads/fue-005_19b4a4.jpg', 219990, 5, 2, 1, 10, 5),
(85, 'FUE-006', 'MSI MAG A550BN 550W 80+ Bronze', 'Fuente confiable para ensambles de entrada protecciones OCP/OVP', 'uploads/fue-006_1641cb.jpg', 49990, 28, 8, 1, 10, 8),
(86, 'FUE-007', 'ASUS ROG Loki SFX-L 850W Platinum', 'Fuente compacta SFX-L 80 Plus Platinum ARGB para Mini-ITX', 'https://cdn.vatanbilgisayar.com/Upload/PRODUCT/asus/thumb/138621-3_large.jpg', 239990, 2, 2, 1, 10, 4),
(87, 'GAB-001', 'Corsair 4000D Airflow Negro ATX', 'Gabinete Mid-Tower panel frontal malla alto flujo 2 fans 120mm', 'uploads/gab-001_6ea766.webp', 99990, 18, 5, 1, 11, 5),
(88, 'GAB-002', 'MSI MAG Forge 100R Mid-Tower ARGB', 'Gabinete ATX vidrio templado 4mm incluye 2 fans ARGB frontales', 'uploads/gab-002_2eedff.webp', 64990, 22, 6, 1, 11, 8),
(89, 'GAB-003', 'ASUS TUF Gaming GT301 ATX ARGB', 'Gabinete compacto soporte audífonos frontal 3 fans ARGB', 'uploads/gab-003_95cc18.png', 89990, 12, 4, 1, 11, 4),
(90, 'GAB-004', 'Corsair 3000D RGB Airflow Blanco', 'Gabinete Mid-Tower blanco incluye 3 ventiladores AR120 RGB', 'uploads/gab-004_ef41b9.webp', 109990, 9, 3, 1, 11, 5),
(91, 'GAB-005', 'MSI MPG Gungnir 300R Airflow Negro', 'Gabinete full airflow soporte GPU vertical 4 ventiladores ARGB', 'uploads/gab-005_5191b6.png', 149990, 6, 2, 1, 11, 8),
(92, 'GAB-006', 'ASUS Prime AP201 MicroATX Mesh Blanco', 'Gabinete compacto 33L paneles microperforados soporte radiador 360', 'https://www.bhphotovideo.com/images/images2000x2000/asus_ap201_wht_mesh_prime_ap201_microatx_case_1722325.jpg', 84990, 11, 3, 1, 11, 4),
(93, 'GAB-007', 'Corsair iCUE 5000X RGB Negro', 'Gabinete Mid-Tower 4 paneles vidrio templado Commander Core XT', 'uploads/gab-007_6a1e3a.png', 179990, 0, 2, 0, 11, 5),
(94, 'CAB-001', 'Cable HDMI 2.1 Ultra High Speed 2m', 'Soporta 8K 60Hz y 4K 120Hz 48Gbps trenzado en nylon', 'uploads/cab-001_8407b9.webp', 12990, 45, 12, 1, 12, 1),
(95, 'CAB-002', 'Cable DisplayPort 1.4 Certificado 2m', 'Soporta 4K 144Hz y 2K 240Hz HDR conectores bañados en oro', 'uploads/cab-002_c1784a.webp', 14990, 36, 10, 1, 12, 4),
(96, 'CAB-003', 'Xiaomi Hub USB-C 5 en 1 HDMI 4K', 'Adaptador multipuerto USB-C a HDMI, 3x USB 3.0 y PD 100W', 'uploads/cab-003_96c665.jpg', 29990, 24, 6, 1, 12, 2),
(97, 'CAB-004', 'Kingston Workflow Station Dock USB-C', 'Estación lectora modular tarjetas SD/microSD UHS-II', 'uploads/cab-004_eb8b78.jpg', 79990, 8, 3, 1, 12, 6),
(98, 'CAB-005', 'Cable USB-C a USB-C 100W Trenzado 2m', 'Carga rápida Power Delivery 5A transferencia datos', 'uploads/cab-005_578d06.webp', 11990, 50, 15, 1, 12, 2),
(99, 'CAB-006', 'Corsair Cable 12VHPWR 600W PCIe 5.0', 'Cable directo fuente Corsair Tipo-4 a GPU RTX Serie 40', 'uploads/cab-006_0bc0ed.jpg', 24990, 15, 5, 1, 12, 5),
(100, 'CAB-007', 'Adaptador USB-C a Ethernet Gigabit', 'Tarjeta de red externa RJ45 1000Mbps carcasa aluminio', 'uploads/cab-007_3175e2.jpg', 16990, 3, 6, 1, 12, 3);

CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL,
  `rut` varchar(12) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `proveedor` (`id_proveedor`, `rut`, `razon_social`, `contacto`, `telefono`, `correo`, `estado`) VALUES
(1, '76.110.220-3', 'Sony Chile Ltda.', 'Alejandro Ríos', '+56 2 2450 1100', 'empresas@sony.cl', 1),
(2, '77.220.330-4', 'Xiaomi Technology Chile SpA', 'Daniela Chen', '+56 2 2450 2200', 'b2b@xiaomi-chile.cl', 1),
(3, '76.330.440-5', 'Logitech Chile SpA', 'Martín Escobar', '+56 2 2450 3300', 'canal@logitech-dist.cl', 1),
(4, '77.440.550-6', 'ASUS Computer Chile Ltda.', 'Carolina Vera', '+56 2 2450 4400', 'ventas@asus-chile.cl', 1),
(5, '76.550.660-7', 'Corsair Gaming Chile SpA', 'Felipe Navarro', '+56 2 2450 5500', 'distribucion@corsair.cl', 1),
(6, '77.660.770-8', 'Kingston Technology Chile', 'Lorena Salgado', '+56 2 2450 6600', 'pedidos@kingston.cl', 1),
(7, '76.770.880-9', 'Samsung Electronics Chile', 'Andrés Kim', '+56 2 2450 7700', 'it-b2b@samsung.cl', 1),
(8, '77.880.990-K', 'MSI Computer Chile SpA', 'Gonzalo Pizarro', '+56 2 2450 8800', 'canal@msi-chile.cl', 1),
(9, '76.990.100-1', 'Razer Inc. Chile Distribuidor', 'Sofía Méndez', '+56 2 2450 9900', 'ventas@razer-dist.cl', 1),
(10, '77.100.200-2', 'HyperX / HP Inc. Chile', 'Cristián Muñoz', '+56 2 2450 1010', 'perifericos@hp-hyperx.cl', 1);

CREATE TABLE `solicitud_clave` (
  `id_solicitud` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `rut_ingresado` varchar(15) NOT NULL,
  `nombre_ingresado` varchar(100) NOT NULL,
  `paterno_ingresado` varchar(60) NOT NULL,
  `materno_ingresado` varchar(60) NOT NULL,
  `correo_ingresado` varchar(120) NOT NULL,
  `rol_declarado` tinyint(1) NOT NULL,
  `dato_verificacion` varchar(255) NOT NULL,
  `coincide_rut` tinyint(1) NOT NULL DEFAULT 0,
  `coincide_nombre` tinyint(1) NOT NULL DEFAULT 0,
  `coincide_rol` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `solicitud_clave` (`id_solicitud`, `id_usuario`, `rut_ingresado`, `nombre_ingresado`, `paterno_ingresado`, `materno_ingresado`, `correo_ingresado`, `rol_declarado`, `dato_verificacion`, `coincide_rut`, `coincide_nombre`, `coincide_rol`, `estado`, `fecha_solicitud`, `fecha_resolucion`) VALUES
(1, 2, '18.222.222-2', 'Usuario', 'Bodega', 'General', 'usuario@tecnostock.cl', 2, 'Registré la salida de la boleta #3388 del SSD Samsung T7 Shield', 1, 1, 1, 0, '2026-11-22 18:10:00', NULL),
(2, 4, '20.123.456-7', 'Camila', 'Fuentes', 'Muñoz', 'cfuentesm@tecnostock.cl', 2, 'Ingresé la factura #7120 de MSI por fuentes de poder A850GL', 1, 1, 1, 0, '2026-11-23 08:45:00', NULL),
(3, 7, '16.789.012-9', 'Diego', 'Sepulveda', 'Gomez', 'dsepulvedag@tecnostock.cl', 1, 'Necesito entrar urgente a revisar el stock de cables HDMI', 0, 1, 0, 0, '2026-11-23 11:20:00', NULL);

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `rut` varchar(15) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_paterno` varchar(60) NOT NULL,
  `apellido_materno` varchar(60) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` tinyint(1) NOT NULL DEFAULT 2 CHECK (`rol` in (1,2)),
  `estado` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usuario` (`id_usuario`, `rut`, `nombre`, `apellido_paterno`, `apellido_materno`, `correo`, `telefono`, `direccion`, `clave`, `rol`, `estado`) VALUES
(1, '19.111.111-1', 'Admin', 'Sistema', 'TecnoStock', 'admin@tecnostock.cl', '+56 9 1111 1111', 'Av. Providencia 1234, Santiago', '$2y$10$dynO0rw/5evpsKRV9ohuseyk8FiRP0v8g6n./jOmzBNyKcrKx61Om', 1, 1),
(2, '18.222.222-2', 'Usuario', 'Bodega', 'General', 'usuario@tecnostock.cl', '+56 9 2222 2222', 'Av. Macul 450, Ñuñoa', '$2y$10$dynO0rw/5evpsKRV9ohuseyk8FiRP0v8g6n./jOmzBNyKcrKx61Om', 2, 1),
(3, '19.456.789-3', 'Matías', 'Valenzuela', 'Rojas', 'mvalenzuelar@tecnostock.cl', '+56 9 8765 4321', 'Los Leones 890, Providencia', '$2y$10$A/iZ/gR/7o.qS9q/A.W2eO2p8L2j.t6W5m1N0p8L2j.t6W5m1N0p8', 1, 1),
(4, '20.123.456-7', 'Camila', 'Fuentes', 'Muñoz', 'cfuentesm@tecnostock.cl', '+56 9 7654 3210', 'Av. Grecia 3210, Peñalolén', '$2y$10$2r8H2p8L2j.t6W5m1N0p8O2p8L2j.t6W5m1N0p8L2j.t6W5m1N0p8', 2, 1),
(5, '17.890.123-4', 'Sebastián', 'Contreras', 'Silva', 'scontrerass@tecnostock.cl', '+56 9 6543 2109', 'Av. Pajaritos 1850, Maipú', '$2y$10$3s9I3q9M3k.u7X6n2O1q9P3q9M3k.u7X6n2O1q9M3k.u7X6n2O1q9', 2, 1),
(6, '21.345.678-9', 'Valentina', 'Morales', 'Castro', 'vmoralesc@tecnostock.cl', '+56 9 5432 1098', 'Santa Rosa 540, Santiago Centro', '$2y$10$4t0J4r0N4l.v8Y7o3P2r0Q4r0N4l.v8Y7o3P2r0N4l.v8Y7o3P2r0', 2, 1),
(7, '16.789.012-5', 'Diego', 'Sepúlveda', 'Gómez', 'dsepulvedag@tecnostock.cl', '+56 9 4321 0987', 'Gran Avenida 4520, San Miguel', '$2y$10$5u1K5s1O5m.w9Z8p4Q3s1R5s1O5m.w9Z8p4Q3s1O5m.w9Z8p4Q3s1', 2, 1),
(8, '20.987.654-3', 'Fernanda', 'Araya', 'Paredes', 'farayap@tecnostock.cl', '+56 9 3210 9876', 'Av. La Florida 7890, La Florida', '$2y$10$6v2L6t2P6n.x0A9q5R4t2S6t2P6n.x0A9q5R4t2S6t2P6n.x0A9q5', 2, 1),
(9, '18.654.321-0', 'Nicolás', 'Herrera', 'Vargas', 'nherrerav@tecnostock.cl', '+56 9 2109 8765', 'Av. Apoquindo 4100, Las Condes', '$2y$10$7w3M7u3Q7o.y1B0r6S5u3T7u3Q7o.y1B0r6S5u3Q7o.y1B0r6S5u3', 2, 0),
(10, '19.321.654-8', 'Javiera', 'Tapia', 'Mendoza', 'jtapiam@tecnostock.cl', '+56 9 1098 7654', 'Irarrázaval 2400, Ñuñoa', '$2y$10$8x4N8v4R8p.z2C1s7T6v4U8v4R8p.z2C1s7T6v4R8p.z2C1s7T6v4', 2, 1);

ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre` (`nombre`);

ALTER TABLE `movimiento`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `fk_movimiento_producto` (`id_producto`),
  ADD KEY `fk_movimiento_usuario` (`id_usuario`),
  ADD KEY `fk_movimiento_proveedor` (`id_proveedor`);

ALTER TABLE `producto`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `fk_producto_categoria` (`id_categoria`),
  ADD KEY `fk_producto_proveedor` (`id_proveedor`);

ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `rut` (`rut`);

ALTER TABLE `solicitud_clave`
  ADD PRIMARY KEY (`id_solicitud`),
  ADD KEY `fk_solicitud_usuario` (`id_usuario`);

ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `rut` (`rut`),
  ADD UNIQUE KEY `correo` (`correo`);

ALTER TABLE `categoria`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `movimiento`
  MODIFY `id_movimiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

ALTER TABLE `producto`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

ALTER TABLE `proveedor`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `solicitud_clave`
  MODIFY `id_solicitud` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `movimiento`
  ADD CONSTRAINT `fk_movimiento_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimiento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE;

ALTER TABLE `producto`
  ADD CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_producto_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`) ON UPDATE CASCADE;

ALTER TABLE `solicitud_clave`
  ADD CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;