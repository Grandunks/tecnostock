DROP DATABASE IF EXISTS tecnostock_db;
CREATE DATABASE tecnostock_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecnostock_db;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(120) NOT NULL UNIQUE,
    clave VARCHAR(255) NOT NULL,
    rol TINYINT(1) NOT NULL DEFAULT 2 CHECK (rol IN (1, 2)),
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    descripcion VARCHAR(255) DEFAULT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE proveedor (
    id_proveedor INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) NOT NULL UNIQUE,
    razon_social VARCHAR(150) NOT NULL,
    contacto VARCHAR(100) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    correo VARCHAR(120) DEFAULT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    imagen VARCHAR(255) DEFAULT NULL,
    precio INT NOT NULL CHECK (precio >= 0),
    stock_actual INT NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo INT NOT NULL DEFAULT 0 CHECK (stock_minimo >= 0),
    estado TINYINT(1) NOT NULL DEFAULT 1,
    id_categoria INT NOT NULL,
    id_proveedor INT DEFAULT NULL,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria(id_categoria)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_producto_proveedor FOREIGN KEY (id_proveedor)
        REFERENCES proveedor(id_proveedor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE movimiento (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo TINYINT(1) NOT NULL CHECK (tipo IN (1, 2)),
    motivo VARCHAR(100) NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad > 0),
    observacion VARCHAR(255) DEFAULT NULL,
    editado TINYINT(1) NOT NULL DEFAULT 0,
    fecha_edicion DATETIME DEFAULT NULL,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    id_proveedor INT DEFAULT NULL,
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (id_producto)
        REFERENCES producto(id_producto)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_proveedor FOREIGN KEY (id_proveedor)
        REFERENCES proveedor(id_proveedor)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO usuario (nombre, correo, clave, rol, estado) VALUES
('Admin', 'admin@tecnostock.cl', '$2y$10$dynO0rw/5evpsKRV9ohuseyk8FiRP0v8g6n./jOmzBNyKcrKx61Om', 1, 1),
('Usuario', 'usuario@tecnostock.cl', '$2y$10$dynO0rw/5evpsKRV9ohuseyk8FiRP0v8g6n./jOmzBNyKcrKx61Om', 2, 1);

INSERT INTO categoria (nombre, descripcion, estado) VALUES
('Periféricos', 'Teclados, ratones, audífonos y accesorios de entrada/salida', 1),
('Almacenamiento', 'Unidades SSD, discos externos y pendrives USB', 1),
('Cables y Adaptadores', 'Cables HDMI, DisplayPort, USB-C y hubs multipuerto', 1),
('Componentes PC', 'Fuentes de poder, ventiladores, gabinetes y memorias RAM', 1);

INSERT INTO proveedor (rut, razon_social, contacto, telefono, correo, estado) VALUES
('76.543.210-K', 'Distribuidora Mayorista Tech Chile Ltda.', 'Marcos Lagos', '+5622334455', 'ventas@techchile.cl', 1),
('77.890.123-4', 'Global Import Express SpA', 'Camila Rivas', '+5622889900', 'comercial@globalimport.cl', 1);

INSERT INTO producto (codigo, nombre, descripcion, imagen, precio, stock_actual, stock_minimo, estado, id_categoria, id_proveedor) VALUES
('TEC-001', 'Mouse Gamer Óptico RGB', 'Mouse USB 6 botones con sensor de 6400 DPI', 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=200&q=80', 19990, 15, 5, 1, 1, 1),
('TEC-002', 'Teclado Mecánico TKL Switch Red', 'Teclado compacto retroiluminado en español', 'https://images.unsplash.com/photo-1595225476474-87563907a212?auto=format&fit=crop&w=200&q=80', 34990, 2, 5, 1, 1, 1),
('ALM-001', 'SSD NVMe M.2 1TB PCIe 4.0', 'Unidad de estado sólido de alta velocidad', 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=200&q=80', 64990, 8, 4, 1, 2, 2),
('CAB-001', 'Cable HDMI 2.1 Ultra High Speed 2m', 'Soporta 4K 120Hz y 8K 60Hz mallado', 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=200&q=80', 9990, 1, 6, 1, 3, 2),
('COM-001', 'Ventilador Gabinete 120mm PWM', 'Cooler silencioso de 120mm conexión 4 pines', 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=200&q=80', 7990, 0, 4, 0, 4, 1);

INSERT INTO movimiento (fecha_hora, tipo, motivo, cantidad, observacion, id_producto, id_usuario, id_proveedor) VALUES
(NOW(), 1, 'Compra a Proveedor', 15, 'Recepción factura 4820', 1, 2, 1),
(NOW(), 1, 'Compra a Proveedor', 5, 'Recepción factura 4820', 2, 2, 1),
(NOW(), 2, 'Venta en tienda', 3, 'Boleta 1024 - Entrega inmediata', 2, 2, NULL),
(NOW(), 1, 'Compra a Proveedor', 8, 'Recepción factura 5102', 3, 2, 2),
(NOW(), 1, 'Compra a Proveedor', 1, 'Recepción factura 5102', 4, 2, 2);