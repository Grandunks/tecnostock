<?php
// index.php
require_once 'config/conexion.php';
require_once 'config/auth.php';

// Control de acceso: solo usuarios autenticados
verificarAutenticacion();

$rolUsuario = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

// Captura de parámetros de búsqueda y filtro
$busqueda = trim($_GET['busqueda'] ?? '');
$id_categoria = trim($_GET['id_categoria'] ?? '');
$filtro_stock = trim($_GET['filtro_stock'] ?? '');

// 1. Obtener categorías activas para el selector de filtro
$stmtCat = $pdo->query("SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre ASC");
$categorias = $stmtCat->fetchAll();

// 2. Construcción dinámica de la consulta con PDO y parámetros preparados
$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.precio, 
               p.stock_actual, p.stock_minimo, p.estado, c.nombre AS categoria_nombre
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        WHERE 1 = 1";

$params = [];

// Filtro por texto (código o nombre)
if (!empty($busqueda)) {
    $sql .= " AND (p.codigo LIKE :busqueda OR p.nombre LIKE :busqueda)";
    $params['busqueda'] = "%$busqueda%";
}

// Filtro por categoría seleccionada
if (!empty($id_categoria)) {
    $sql .= " AND p.id_categoria = :id_categoria";
    $params['id_categoria'] = $id_categoria;
}

// Filtro opcional: solo productos con alerta de stock bajo
if ($filtro_stock === 'bajo') {
    $sql .= " AND p.stock_actual < p.stock_minimo";
}

$sql .= " ORDER BY p.id_producto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// 3. Conteo de alertas globales de stock bajo (para el banner de notificación)
$stmtAlertas = $pdo->query("SELECT COUNT(*) FROM producto WHERE stock_actual < stock_minimo AND estado = 1");
$totalAlertas = (int)$stmtAlertas->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Panel de Inventario</title>
    <!-- Bootstrap 5 CSS e Iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .badge-alerta { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>

<!-- Barra de Navegación -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-cpu"></i> TecnoStock
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php"><i class="bi bi-box-seam"></i> Catálogo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="movimientos.php"><i class="bi bi-arrow-left-right"></i> Movimientos</a>
                </li>
            </ul>
            <div class="d-flex align-items-center text-white gap-3">
                <span class="small">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($nombreUsuario) ?> 
                    <span class="badge bg-secondary"><?= obtenerNombreRol($rolUsuario) ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <!-- Notificaciones del sistema -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'creado'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Producto registrado exitosamente en el catálogo.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'editado'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Producto actualizado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'estado_actualizado'): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                El estado del producto fue actualizado.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Alerta Crítica de Stock Bajo (RF08) -->
    <?php if ($totalAlertas > 0): ?>
        <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center justify-content-between p-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning me-3"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Alerta de Existencias Críticas</h6>
                    <span class="small">Hay <strong><?= $totalAlertas ?></strong> producto(s) activos cuyo stock actual está por debajo del mínimo establecido.</span>
                </div>
            </div>
            <a href="index.php?filtro_stock=bajo" class="btn btn-sm btn-outline-dark fw-semibold">Ver productos críticos</a>
        </div>
    <?php endif; ?>

    <!-- Encabezado y Barra de Filtros -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center justify-content-between mb-3">
                <div class="col-md-6">
                    <h4 class="fw-bold mb-0 text-secondary">Control de Existencias</h4>
                    <p class="text-muted small mb-0">Gestión de catálogo, disponibilidad y estados de productos</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <!-- Solo el Administrador puede registrar nuevos productos -->
                    <?php if ($rolUsuario === 1): ?>
                        <a href="producto_form.php" class="btn btn-primary fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario de Búsqueda y Filtro (RF04) -->
            <form method="GET" action="index.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="busqueda" class="form-control" placeholder="Buscar por código o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="id_categoria" class="form-select">
                        <option value="">Todas las Categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id_categoria'] ?>" <?= ($id_categoria == $cat['id_categoria']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 fw-semibold">Filtrar</button>
                    <?php if (!empty($busqueda) || !empty($id_categoria) || !empty($filtro_stock)): ?>
                        <a href="index.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla del Catálogo de Productos (RF03) -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th class="text-center">Stock Actual</th>
                            <th class="text-center">Stock Mínimo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    No se encontraron productos que coincidan con la búsqueda.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                                <?php 
                                    $alertaStock = ($p['stock_actual'] < $p['stock_minimo'] && $p['estado'] == 1);
                                ?>
                                <tr class="<?= $alertaStock ? 'table-warning' : '' ?>">
                                    <td class="ps-4 fw-bold">
                                        <code><?= htmlspecialchars($p['codigo']) ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($p['nombre']) ?></div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 280px;">
                                            <?= htmlspecialchars($p['descripcion'] ?? 'Sin descripción') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($p['categoria_nombre']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($p['precio'], 0, ',', '.') ?></td>
                                    <td class="text-center">
                                        <span class="fw-bold fs-6 <?= $alertaStock ? 'text-danger' : 'text-success' ?>">
                                            <?= $p['stock_actual'] ?>
                                        </span>
                                        <?php if ($alertaStock): ?>
                                            <span class="badge bg-danger badge-alerta ms-1" title="Stock bajo el mínimo">
                                                <i class="bi bi-arrow-down"></i> Bajo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted"><?= $p['stock_minimo'] ?></td>
                                    <td class="text-center">
                                        <?php if ($p['estado'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Desactivado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <!-- Opciones según rol -->
                                        <?php if ($rolUsuario === 1): ?>
                                            <a href="producto_form.php?id=<?= $p['id_producto'] ?>" class="btn btn-sm btn-outline-primary" title="Editar Producto">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Desactivar / Activar (Borrado lógico RN05) -->
                                            <a href="producto_estado.php?id=<?= $p['id_producto'] ?>&estado=<?= $p['estado'] == 1 ? 0 : 1 ?>" 
                                               class="btn btn-sm <?= $p['estado'] == 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                               title="<?= $p['estado'] == 1 ? 'Desactivar producto' : 'Reactivar producto' ?>"
                                               onclick="return confirm('¿Seguro que deseas cambiar el estado de este producto?');">
                                                <i class="bi <?= $p['estado'] == 1 ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Solo lectura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>