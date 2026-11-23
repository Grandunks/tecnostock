<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

$busqueda     = trim($_GET['busqueda'] ?? '');
$id_categoria = trim($_GET['id_categoria'] ?? '');
$filtro_stock = trim($_GET['filtro_stock'] ?? '');

$categorias = $pdo->query("SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.imagen, p.precio, 
               p.stock_actual, p.stock_minimo, p.estado, 
               c.nombre AS categoria_nombre,
               pr.razon_social AS proveedor_nombre
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedor pr ON p.id_proveedor = pr.id_proveedor
        WHERE 1 = 1";

$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (p.codigo LIKE :busqueda1 OR p.nombre LIKE :busqueda2)";
    $params['busqueda1'] = "%$busqueda%";
    $params['busqueda2'] = "%$busqueda%";
}

if (!empty($id_categoria)) {
    $sql .= " AND p.id_categoria = :id_categoria";
    $params['id_categoria'] = $id_categoria;
}

if ($filtro_stock === 'bajo') {
    $sql .= " AND p.stock_actual < p.stock_minimo AND p.estado = 1";
} elseif ($filtro_stock === 'activos') {
    $sql .= " AND p.estado = 1";
} elseif ($filtro_stock === 'inactivos') {
    $sql .= " AND p.estado = 0";
}

$sql .= " ORDER BY p.estado DESC, (p.stock_actual < p.stock_minimo) DESC, p.id_producto DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$kpis = $pdo->query("
    SELECT 
        COUNT(*) AS total_productos,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS productos_activos,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS productos_inactivos,
        COALESCE(SUM(CASE WHEN estado = 1 THEN stock_actual ELSE 0 END), 0) AS total_unidades,
        SUM(CASE WHEN estado = 1 AND stock_actual < stock_minimo THEN 1 ELSE 0 END) AS alertas_criticas
    FROM producto
")->fetch();

$movsRaw = $pdo->query("
    SELECT m.id_producto, m.fecha_hora, m.tipo, m.cantidad, m.motivo, u.nombre AS usuario_nombre
    FROM movimiento m
    INNER JOIN usuario u ON m.id_usuario = u.id_usuario
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
")->fetchAll();

$movsPorProducto = [];
foreach ($movsRaw as $mv) {
    $idP = (int)$mv['id_producto'];
    if (!isset($movsPorProducto[$idP]) || count($movsPorProducto[$idP]) < 5) {
        $movsPorProducto[$idP][] = [
            'fecha'    => date('d/m/Y H:i', strtotime($mv['fecha_hora'])),
            'tipo'     => (int)$mv['tipo'],
            'cantidad' => (int)$mv['cantidad'],
            'motivo'   => $mv['motivo'],
            'usuario'  => $mv['usuario_nombre']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo | TecnoStock</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f4f7f6;
            --surface-dark: #111827;
            --emerald-primary: #10b981;
            --emerald-dark: #059669;
            --coral-danger: #ef4444;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: #1f2937; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .top-header { background-color: var(--surface-dark); border-bottom: 3px solid var(--emerald-primary); padding: 0.85rem 0; }
        .brand-logo { font-weight: 700; font-size: 1.35rem; color: #ffffff; text-decoration: none; letter-spacing: -0.03em; }
        .brand-dot { color: var(--emerald-primary); }
        .nav-pill-custom { color: #9ca3af; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0.9rem; border-radius: 8px; transition: all 0.2s ease; }
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255, 255, 255, 0.05); }
        .nav-pill-custom.active { color: #111827; background-color: var(--emerald-primary); }
        .btn-emerald { background-color: var(--emerald-primary); color: #ffffff; font-weight: 600; border: none; transition: background 0.2s; }
        .btn-emerald:hover { background-color: var(--emerald-dark); color: #ffffff; }
        .stat-card { background: #ffffff; border-radius: 12px; border: 1px solid #e5e7eb; border-left: 5px solid var(--emerald-primary); padding: 1.25rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .stat-card.stat-purple    { border-left-color: #6366f1; }
        .stat-card.stat-secondary { border-left-color: #64748b; }
        .stat-card.stat-danger    { border-left-color: var(--coral-danger); background: #fff5f5; }
        .filter-bar, .table-container { background: #ffffff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .table-container { overflow: hidden; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; padding: 0.95rem 1rem; border: none; }
        .table tbody td { padding: 0.9rem 1rem; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
        .fila-clicable { cursor: pointer; transition: background 0.15s; }
        .fila-clicable:hover { background-color: #ecfdf5 !important; }
        .prod-thumb { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; border: 1px solid #e5e7eb; }
        .prod-thumb-placeholder { width: 52px; height: 52px; border-radius: 10px; background-color: #f1f5f9; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.3rem; }
        .sku-tag {background-color: #111827; color: #34d399; padding: 0.28rem 0.6rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; display: inline-block; white-space: nowrap;}
        .cat-pill { background-color: #eef2ff; color: #4f46e5; font-weight: 600; font-size: 0.78rem; padding: 0.3rem 0.65rem; border-radius: 6px; display: inline-block; }
        .fila-alerta { background-color: #fffbeb !important; }
        .fila-apagada { opacity: 0.55; background-color: #f9fafb; }
        .modal-img-box { width: 100%; height: 220px; border-radius: 12px; background-color: #f8fafc; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .modal-img-box img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>

<header class="top-header sticky-top shadow-sm">
    <div class="container-fluid px-4 d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="index.php" class="brand-logo me-2"><i class="bi bi-terminal-fill brand-dot me-1"></i>TECNO<span class="brand-dot">STOCK</span></a>
            <nav class="d-flex gap-1 flex-wrap align-items-center">
                <a href="index.php" class="nav-pill-custom active"><i class="bi bi-layers-fill me-1"></i> Catálogo</a>
                <a href="movimientos.php" class="nav-pill-custom"><i class="bi bi-arrow-left-right me-1"></i> Movimientos</a>
                <a href="historial_movimientos.php" class="nav-pill-custom"><i class="bi bi-journal-text me-1"></i> Registro Global</a>
                <?php if ($rolUsuario === 1): ?>
                    <a href="usuarios.php" class="nav-pill-custom"><i class="bi bi-people-fill me-1"></i> Usuarios</a>
                    <a href="producto_form.php" class="nav-pill-custom"><i class="bi bi-plus-circle-fill me-1 text-success"></i> Registrar Producto</a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="text-white small fw-semibold d-none d-md-inline">
                <?= htmlspecialchars($nombreUsuario) ?>
                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 ms-1" style="font-size: 0.68rem;">
                    <?= strtoupper(obtenerNombreRol($rolUsuario)) ?>
                </span>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger px-3 fw-semibold">
                <i class="bi bi-box-arrow-right me-1"></i>Salir
            </a>
        </div>
    </div>
</header>

<main class="container-fluid px-4 py-4" style="max-width: 1400px;">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-dark border-start border-4 border-success shadow-sm d-flex align-items-center justify-content-between mb-4" role="alert">
            <div>
                <i class="bi bi-check2-all text-success fs-5 me-2"></i>
                <strong>Operación completada:</strong>
                <?php
                    if ($_GET['msg'] === 'creado') echo 'Producto incorporado al inventario.';
                    elseif ($_GET['msg'] === 'editado') echo 'Ficha del producto actualizada.';
                    elseif ($_GET['msg'] === 'estado_actualizado') echo 'Estado del producto modificado.';
                ?>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="fw-bold mb-0">Gestión de Existencias</h3>
        <span class="text-muted small">Haz clic en cualquier producto para visualizar su ficha técnica e historial reciente</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Ítems Activos</span>
                    <i class="bi bi-cpu text-success fs-4"></i>
                </div>
                <div class="mt-2">
                    <span class="fs-3 fw-bold mono"><?= (int)$kpis['productos_activos'] ?></span>
                    <span class="text-muted small ms-1">de <?= (int)$kpis['total_productos'] ?> registrados</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card stat-purple">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Stock Total Físico</span>
                    <i class="bi bi-boxes text-primary fs-4"></i>
                </div>
                <div class="mt-2">
                    <span class="fs-3 fw-bold mono"><?= number_format((int)$kpis['total_unidades'], 0, ',', '.') ?></span>
                    <span class="text-muted small ms-1">unidades</span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card stat-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Productos Inactivos</span>
                    <i class="bi bi-slash-circle text-secondary fs-4"></i>
                </div>
                <div class="mt-2 d-flex align-items-baseline justify-content-between">
                    <div>
                        <span class="fs-3 fw-bold mono text-secondary"><?= (int)$kpis['productos_inactivos'] ?></span>
                        <span class="text-muted small ms-1">en baja lógica</span>
                    </div>
                    <?php if ((int)$kpis['productos_inactivos'] > 0): ?>
                        <a href="index.php?filtro_stock=inactivos" class="badge bg-secondary text-decoration-none">Ver inactivos</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="stat-card <?= (int)$kpis['alertas_criticas'] > 0 ? 'stat-danger' : '' ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Quiebre / Críticos</span>
                    <i class="bi bi-shield-exclamation text-danger fs-4"></i>
                </div>
                <div class="mt-2 d-flex align-items-baseline justify-content-between">
                    <span class="fs-3 fw-bold mono <?= (int)$kpis['alertas_criticas'] > 0 ? 'text-danger' : '' ?>">
                        <?= (int)$kpis['alertas_criticas'] ?>
                    </span>
                    <?php if ((int)$kpis['alertas_criticas'] > 0): ?>
                        <a href="index.php?filtro_stock=bajo" class="badge bg-danger text-decoration-none">Filtrar críticos</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-bar p-3 mb-4">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="busqueda" class="form-control" 
                           placeholder="Buscar por código (ej. TEC-001) o nombre..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="id_categoria" class="form-select">
                    <option value="">Categoría: Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id_categoria'] ?>" <?= ($id_categoria == $cat['id_categoria']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="filtro_stock" class="form-select">
                    <option value="">Estado: Todos</option>
                    <option value="bajo" <?= $filtro_stock === 'bajo' ? 'selected' : '' ?>>Bajo Mínimo</option>
                    <option value="activos" <?= $filtro_stock === 'activos' ? 'selected' : '' ?>>Solo Activos</option>
                    <option value="inactivos" <?= $filtro_stock === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100 fw-semibold">Buscar</button>
                <?php if (!empty($busqueda) || !empty($id_categoria) || !empty($filtro_stock)): ?>
                    <a href="index.php" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Imagen</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría & Proveedor</th>
                        <th class="text-end">Precio</th>
                        <th class="text-center">Nivel de Stock</th>
                        <th class="text-center <?= $rolUsuario !== 1 ? 'pe-4' : '' ?>">Estado</th>
                        <?php if ($rolUsuario === 1): ?>
                            <th class="text-end pe-4">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="<?= $rolUsuario === 1 ? 8 : 7 ?>" class="text-center py-5 text-muted">
                                <i class="bi bi-box2 fs-1 d-block mb-2"></i>
                                No existen registros con los criterios seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <?php
                                $activo = ((int)$p['estado'] === 1);
                                $critico = ($activo && $p['stock_actual'] < $p['stock_minimo']);
                                $claseTr = !$activo ? 'fila-apagada' : ($critico ? 'fila-alerta' : '');
                                $prodJson = json_encode([
                                    'id'          => (int)$p['id_producto'],
                                    'codigo'      => $p['codigo'],
                                    'nombre'      => $p['nombre'],
                                    'descripcion' => $p['descripcion'] ?: 'Sin descripción técnica registrada.',
                                    'imagen'      => $p['imagen'] ?: '',
                                    'categoria'   => $p['categoria_nombre'],
                                    'proveedor'   => $p['proveedor_nombre'] ?: 'Sin proveedor habitual asociado',
                                    'precio'      => number_format($p['precio'], 0, ',', '.'),
                                    'stock'       => (int)$p['stock_actual'],
                                    'minimo'      => (int)$p['stock_minimo'],
                                    'activo'      => $activo,
                                    'critico'     => $critico,
                                    'movimientos' => $movsPorProducto[(int)$p['id_producto']] ?? []
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            ?>
                            <tr class="fila-clicable <?= $claseTr ?>" onclick='abrirDetalleProducto(<?= $prodJson ?>)'>
                                <td class="ps-4" style="width: 75px;">
                                    <?php if (!empty($p['imagen'])): ?>
                                        <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="prod-thumb"
                                             onerror="this.outerHTML='<div class=\'prod-thumb-placeholder\'><i class=\'bi bi-image\'></i></div>';">
                                    <?php else: ?>
                                        <div class="prod-thumb-placeholder"><i class="bi bi-cpu"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="sku-tag mono"><?= htmlspecialchars($p['codigo']) ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <div class="text-muted small text-truncate" style="max-width: 280px;">
                                        <?= htmlspecialchars($p['descripcion'] ?: 'Sin descripción') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="cat-pill mb-1"><?= htmlspecialchars($p['categoria_nombre']) ?></span>
                                    <?php if (!empty($p['proveedor_nombre'])): ?>
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            <i class="bi bi-building me-1"></i><?= htmlspecialchars($p['proveedor_nombre']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold mono">$<?= number_format($p['precio'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <div class="mono fw-bold fs-6 <?= $critico ? 'text-danger' : 'text-success' ?>">
                                        <?= $p['stock_actual'] ?> <span class="text-muted fw-normal small">/ mín. <?= $p['stock_minimo'] ?></span>
                                    </div>
                                    <?php if ($critico): ?>
                                        <span class="badge bg-danger text-white" style="font-size: 0.68rem;">
                                            <i class="bi bi-lightning-fill"></i> STOCK BAJO
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center <?= $rolUsuario !== 1 ? 'pe-4' : '' ?>">
                                    <?php if ($activo): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">● Operativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">○ Baja lógica</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($rolUsuario === 1): ?>
                                    <td class="text-end pe-4" onclick="event.stopPropagation();">
                                        <div class="btn-group btn-group-sm">
                                            <a href="producto_form.php?id=<?= $p['id_producto'] ?>" class="btn btn-outline-dark" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn <?= $activo ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                                    title="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?>"
                                                    onclick="abrirModalEstado(<?= $p['id_producto'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>', <?= $activo ? 0 : 1 ?>)">
                                                <i class="bi <?= $activo ? 'bi-power' : 'bi-arrow-clockwise' ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="modalDetalleProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-box-seam text-success me-2"></i>Ficha Técnica <span id="prodDetCodigo" class="sku-tag mono ms-2"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5">
                        <div class="modal-img-box" id="prodDetImgBox"></div>
                    </div>
                    <div class="col-md-7">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="cat-pill" id="prodDetCategoria"></span>
                            <span id="prodDetEstadoBadge"></span>
                        </div>
                        <h4 class="fw-bold text-dark mb-2" id="prodDetNombre"></h4>
                        <p class="text-muted small mb-3" id="prodDetDescripcion"></p>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="row text-center g-2">
                                <div class="col-4 border-end">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">PRECIO UNIT.</span>
                                    <span class="fw-bold mono fs-5 text-dark" id="prodDetPrecio"></span>
                                </div>
                                <div class="col-4 border-end">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">STOCK ACTUAL</span>
                                    <span class="fw-bold mono fs-5" id="prodDetStock"></span>
                                </div>
                                <div class="col-4">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">STOCK MÍNIMO</span>
                                    <span class="fw-bold mono fs-5 text-secondary" id="prodDetMinimo"></span>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted">
                            <i class="bi bi-building text-success me-1"></i> Proveedor habitual: <strong class="text-dark" id="prodDetProveedor"></strong>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <div>
                    <h6 class="fw-bold small text-uppercase text-muted mb-2">
                        <i class="bi bi-clock-history text-success me-1"></i> Últimos Movimientos del Producto
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Cant.</th>
                                    <th>Motivo</th>
                                    <th>Responsable</th>
                                </tr>
                            </thead>
                            <tbody id="prodDetMovimientos"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <?php if ($rolUsuario === 1): ?>
                    <a href="#" id="prodDetBtnEditar" class="btn btn-emerald px-4">
                        <i class="bi bi-pencil-square me-1"></i> Editar Producto
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEstadoProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold" id="modalEstadoTitulo">Confirmar Acción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div id="modalEstadoIcono" class="mb-3"></div>
                <p class="mb-1 text-muted">¿Estás seguro que deseas <strong id="modalEstadoAccionText"></strong> el producto:</p>
                <h5 class="fw-bold text-dark mb-0" id="modalEstadoNombreProd"></h5>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center gap-2 pb-4">
                <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="modalEstadoBtnConfirmar" class="btn px-4 fw-semibold">Confirmar</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirDetalleProducto(p) {
    document.getElementById('prodDetCodigo').textContent = p.codigo;
    document.getElementById('prodDetCategoria').textContent = p.categoria;
    document.getElementById('prodDetNombre').textContent = p.nombre;
    document.getElementById('prodDetDescripcion').textContent = p.descripcion;
    document.getElementById('prodDetPrecio').textContent = '$' + p.precio;
    document.getElementById('prodDetMinimo').textContent = p.minimo + ' uds.';
    document.getElementById('prodDetProveedor').textContent = p.proveedor;

    const stockEl = document.getElementById('prodDetStock');
    stockEl.textContent = p.stock + ' uds.';
    stockEl.className = 'fw-bold mono fs-5 ' + (p.critico ? 'text-danger' : 'text-success');

    const estadoEl = document.getElementById('prodDetEstadoBadge');
    estadoEl.innerHTML = p.activo
        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">● Operativo</span>'
        : '<span class="badge bg-secondary bg-opacity-10 text-secondary border">○ Baja lógica</span>';

    const imgBox = document.getElementById('prodDetImgBox');
    if (p.imagen) {
        imgBox.innerHTML = `<img src="${p.imagen}" alt="${p.nombre}" onerror="this.outerHTML='<div class=\\'text-center text-muted\\'><i class=\\'bi bi-cpu fs-1 d-block mb-1\\'></i><small>Imagen no disponible</small></div>';">`;
    } else {
        imgBox.innerHTML = '<div class="text-center text-muted"><i class="bi bi-cpu fs-1 d-block mb-1"></i><small>Sin imagen asignada</small></div>';
    }

    const tbodyMovs = document.getElementById('prodDetMovimientos');
    tbodyMovs.innerHTML = '';
    if (p.movimientos && p.movimientos.length > 0) {
        p.movimientos.forEach(m => {
            const esEntrada = m.tipo === 1;
            const badge = esEntrada
                ? '<span class="badge bg-success bg-opacity-10 text-success">ENTRADA</span>'
                : '<span class="badge bg-danger bg-opacity-10 text-danger">SALIDA</span>';
            const cantHtml = `<span class="fw-bold mono ${esEntrada ? 'text-success' : 'text-danger'}">${esEntrada ? '+' : '-'}${m.cantidad}</span>`;
            tbodyMovs.innerHTML += `<tr>
                <td class="mono text-muted" style="font-size:0.75rem;">${m.fecha}</td>
                <td>${badge}</td>
                <td class="text-center">${cantHtml}</td>
                <td>${m.motivo}</td>
                <td>${m.usuario}</td>
            </tr>`;
        });
    } else {
        tbodyMovs.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">Sin movimientos registrados.</td></tr>';
    }

    const btnEditar = document.getElementById('prodDetBtnEditar');
    if (btnEditar) {
        btnEditar.href = 'producto_form.php?id=' + p.id;
    }

    new bootstrap.Modal(document.getElementById('modalDetalleProducto')).show();
}

function abrirModalEstado(id, nombre, nuevoEstado) {
    const esDeshabilitar = (nuevoEstado === 0);
    document.getElementById('modalEstadoTitulo').textContent = esDeshabilitar ? 'Deshabilitar Producto' : 'Reactivar Producto';
    document.getElementById('modalEstadoAccionText').textContent = esDeshabilitar ? 'deshabilitar (baja lógica)' : 'habilitar nuevamente';
    document.getElementById('modalEstadoNombreProd').textContent = nombre;

    const iconoDiv = document.getElementById('modalEstadoIcono');
    const btnConfirmar = document.getElementById('modalEstadoBtnConfirmar');

    if (esDeshabilitar) {
        iconoDiv.innerHTML = '<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger" style="width:64px;height:64px;"><i class="bi bi-power fs-1"></i></span>';
        btnConfirmar.className = 'btn btn-danger px-4 fw-semibold';
        btnConfirmar.textContent = 'Sí, deshabilitar';
    } else {
        iconoDiv.innerHTML = '<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success" style="width:64px;height:64px;"><i class="bi bi-arrow-clockwise fs-1"></i></span>';
        btnConfirmar.className = 'btn btn-emerald px-4 fw-semibold';
        btnConfirmar.textContent = 'Sí, habilitar';
    }

    btnConfirmar.href = `producto_estado.php?id=${id}&estado=${nuevoEstado}`;
    new bootstrap.Modal(document.getElementById('modalEstadoProducto')).show();
}
</script>
</body>
</html>