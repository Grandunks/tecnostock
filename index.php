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
               p.stock_actual, p.stock_minimo, p.estado, p.id_categoria,
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
    <link href="assets/theme.css" rel="stylesheet">
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

        .kpi-tile {
            background: #ffffff; border-radius: 14px; border: 1px solid #e5e7eb;
            padding: 1.15rem 1.25rem; cursor: pointer; transition: all 0.2s ease;
            position: relative; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .kpi-tile:hover { transform: translateY(-2px); border-color: var(--emerald-primary); box-shadow: 0 8px 18px rgba(16,185,129,0.09); }
        .kpi-tile.kpi-selected { border: 2px solid var(--emerald-primary); background-color: #ecfdf5; }
        .kpi-tile.kpi-danger-tile.kpi-selected { border-color: var(--coral-danger); background-color: #fef2f2; }
        body.dark-mode .kpi-tile { background-color: #111827; border-color: #1f2937; }
        body.dark-mode .kpi-tile.kpi-selected { background-color: rgba(16, 185, 129, 0.14); border-color: #10b981; }
        body.dark-mode .kpi-tile.kpi-danger-tile.kpi-selected { background-color: rgba(239, 68, 68, 0.15); border-color: #ef4444; }
        .kpi-icon-box {
            width: 42px; height: 42px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; font-size: 1.25rem;
        }

        .toolbar-box { background: #ffffff; border-radius: 14px; border: 1px solid #e5e7eb; padding: 1rem 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .cat-chip {
            border: 1px solid #e2e8f0; background: #f8fafc; color: #475569;
            font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.85rem;
            border-radius: 999px; cursor: pointer; transition: all 0.15s; white-space: nowrap;
        }
        .cat-chip:hover { border-color: var(--emerald-primary); color: #059669; }
        .cat-chip.active { background: #111827; color: #34d399; border-color: #111827; }
        body.dark-mode .cat-chip { background: #161f32; border-color: #334155; color: #cbd5e1; }
        body.dark-mode .cat-chip.active { background: #10b981; color: #111827; border-color: #10b981; }

        .catalogo-wrapper { min-height: 460px; transition: opacity 0.14s ease; }
        .catalogo-wrapper.filtrando { opacity: 0.6; }
        .product-card {
            background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb;
            overflow: hidden; height: 100%; display: flex; flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            cursor: pointer; position: relative;
        }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px -8px rgba(17, 24, 39, 0.12); border-color: var(--emerald-primary); }
        .product-card.card-critica { border-top: 4px solid var(--coral-danger); }
        .product-card.card-inactiva { opacity: 0.6; filter: grayscale(0.4); }
        body.dark-mode .product-card { background: #111827; border-color: #1f2937; }
        body.dark-mode .product-card:hover { border-color: #10b981; }

        .card-img-stage {
            height: 175px; width: 100%; background: #ffffff; padding: 14px;
            display: flex; align-items: center; justify-content: center;
            border-bottom: 1px solid #f1f5f9; position: relative;
        }
        .card-img-stage img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
        .card-badge-top-left { position: absolute; top: 10px; left: 10px; z-index: 2; }
        .card-badge-top-right { position: absolute; top: 10px; right: 10px; z-index: 2; }

        .stock-bar-bg { height: 6px; border-radius: 999px; background-color: #e2e8f0; overflow: hidden; }
        body.dark-mode .stock-bar-bg { background-color: #1f2937; }
        .stock-bar-fill { height: 100%; border-radius: 999px; transition: width 0.3s ease; }

        .table-container { background: #ffffff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; padding: 0.95rem 1rem; border: none; }
        .table tbody td { padding: 0.85rem 1rem; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
        .fila-clicable { cursor: pointer; transition: background 0.15s; }
        .fila-clicable:hover { background-color: #ecfdf5 !important; }
        .prod-thumb { width: 48px; height: 48px; border-radius: 10px; object-fit: contain; background-color: #ffffff; padding: 3px; border: 1px solid #e5e7eb; }
        .prod-thumb-placeholder { width: 48px; height: 48px; border-radius: 10px; background-color: #f1f5f9; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.2rem; }
        .sku-tag { background-color: #111827; color: #34d399; padding: 0.25rem 0.55rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .cat-pill { background-color: #eef2ff; color: #4f46e5; font-weight: 600; font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; display: inline-block; }
        .fila-alerta { background-color: #fffbeb !important; }
        .fila-apagada { opacity: 0.55; background-color: #f9fafb; }

        .modal-img-box { width: 100%; height: 220px; border-radius: 12px; background-color: #ffffff; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 12px; }
        .modal-img-box img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container-fluid px-4 py-4" style="max-width: 1420px;">

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile kpi-selected" id="tileTodos" onclick="seleccionarFiltroKpi('', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Catálogo Total</span>
                        <span class="fs-3 fw-bold mono"><?= (int)$kpis['total_productos'] ?></span>
                        <span class="text-muted small ms-1">ítems</span>
                    </div>
                    <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-grid-1x2-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile" id="tileActivos" onclick="seleccionarFiltroKpi('activos', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Activos / Stock Físico</span>
                        <span class="fs-3 fw-bold mono"><?= (int)$kpis['productos_activos'] ?></span>
                        <span class="text-success small fw-semibold ms-1 mono">(<?= number_format((int)$kpis['total_unidades'], 0, ',', '.') ?> uds)</span>
                    </div>
                    <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile kpi-danger-tile" id="tileCriticos" onclick="seleccionarFiltroKpi('bajo', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Stock Crítico</span>
                        <span class="fs-3 fw-bold mono <?= (int)$kpis['alertas_criticas'] > 0 ? 'text-danger' : '' ?>">
                            <?= (int)$kpis['alertas_criticas'] ?>
                        </span>
                        <span class="text-muted small ms-1">por reponer</span>
                    </div>
                    <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile" id="tileInactivos" onclick="seleccionarFiltroKpi('inactivos', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">En Baja Lógica</span>
                        <span class="fs-3 fw-bold mono text-secondary"><?= (int)$kpis['productos_inactivos'] ?></span>
                        <span class="text-muted small ms-1">inactivos</span>
                    </div>
                    <div class="kpi-icon-box bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-eye-slash-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toolbar-box mb-4">
        <div class="row g-2 align-items-center mb-3">
            <div class="col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-white text-success"><i class="bi bi-search"></i></span>
                    <input type="text" id="inputBusqueda" class="form-control" autocomplete="off"
                           placeholder="Buscar producto por nombre o código SKU..." value="<?= htmlspecialchars($busqueda) ?>">
                </div>
            </div>

            <div class="col-md-3 col-lg-3">
                <select id="selectEstado" class="form-select">
                    <option value="">Estado: Todos los ítems</option>
                    <option value="activos">Solo Operativos</option>
                    <option value="bajo">Stock Bajo Mínimo</option>
                    <option value="inactivos">Inactivos (Baja lógica)</option>
                </select>
            </div>

            <div class="col-md-3 col-lg-4 d-flex justify-content-md-end align-items-center gap-2">
                <span class="text-muted small fw-semibold me-1 d-none d-xl-inline" id="contadorResultados"><?= count($productos) ?> mostrados</span>
                <div class="btn-group" role="group" aria-label="Cambiar vista">
                    <button type="button" id="btnVistaGrid" class="btn btn-sm btn-dark fw-semibold px-3" onclick="cambiarVista('grid')" title="Vista Vitrina">
                        <i class="bi bi-grid-fill me-1"></i> Tarjetas
                    </button>
                    <button type="button" id="btnVistaTabla" class="btn btn-sm btn-outline-dark fw-semibold px-3" onclick="cambiarVista('tabla')" title="Vista Tabla">
                        <i class="bi bi-list-ul me-1"></i> Lista
                    </button>
                </div>
                <button type="button" onclick="limpiarFiltrosEnVivo()" class="btn btn-sm btn-outline-secondary" title="Restablecer filtros">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap pt-2 border-top">
            <span class="text-muted small fw-bold me-1"><i class="bi bi-tags text-success me-1"></i>Categorías:</span>
            <button type="button" class="cat-chip active" data-cat="" onclick="seleccionarCategoriaChip('', this)">Todas</button>
            <?php foreach ($categorias as $cat): ?>
                <button type="button" class="cat-chip" data-cat="<?= (int)$cat['id_categoria'] ?>" onclick="seleccionarCategoriaChip('<?= (int)$cat['id_categoria'] ?>', this)">
                    <?= htmlspecialchars($cat['nombre']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="catalogo-wrapper" id="catalogoWrapper">

        <div id="vistaCuadricula" class="row g-3">
            <?php foreach ($productos as $p): ?>
                <?php
                    $activo  = ((int)$p['estado'] === 1);
                    $critico = ($activo && $p['stock_actual'] < $p['stock_minimo']);
                    $porcentajeStock = ($p['stock_minimo'] > 0)
                        ? min(100, round(($p['stock_actual'] / ($p['stock_minimo'] * 2)) * 100))
                        : ($p['stock_actual'] > 0 ? 100 : 0);
                    $barColor = !$activo ? 'bg-secondary' : ($critico ? 'bg-danger' : 'bg-success');

                    $prodJson = htmlspecialchars(json_encode([
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
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3 item-catalogo"
                     data-codigo="<?= htmlspecialchars(mb_strtolower($p['codigo'])) ?>"
                     data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>"
                     data-categoria="<?= (int)$p['id_categoria'] ?>"
                     data-activo="<?= $activo ? '1' : '0' ?>"
                     data-critico="<?= $critico ? '1' : '0' ?>">
                    <div class="product-card <?= !$activo ? 'card-inactiva' : ($critico ? 'card-critica' : '') ?>"
                         data-prod="<?= $prodJson ?>"
                         onclick="abrirDetalleDesdeElemento(this)">

                        <div class="card-badge-top-left">
                            <span class="sku-tag mono shadow-sm"><?= htmlspecialchars($p['codigo']) ?></span>
                        </div>
                        <div class="card-badge-top-right">
                            <?php if (!$activo): ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php elseif ($critico): ?>
                                <span class="badge bg-danger"><i class="bi bi-lightning-fill"></i> Bajo</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-img-stage">
                            <?php if (!empty($p['imagen'])): ?>
                                <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>"
                                     onerror="this.outerHTML='<div class=\'text-muted opacity-50\'><i class=\'bi bi-cpu\' style=\'font-size:3rem;\'></i></div>';">
                            <?php else: ?>
                                <div class="text-muted opacity-50"><i class="bi bi-cpu" style="font-size: 3rem;"></i></div>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="mb-1">
                                <span class="cat-pill"><?= htmlspecialchars($p['categoria_nombre']) ?></span>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($p['nombre']) ?>">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </h6>
                            <p class="text-muted small mb-3 text-truncate">
                                <?= htmlspecialchars($p['descripcion'] ?: 'Sin descripción técnica') ?>
                            </p>

                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <span class="fw-bold mono fs-5 text-dark">$<?= number_format($p['precio'], 0, ',', '.') ?></span>
                                    <span class="mono small fw-bold <?= $critico ? 'text-danger' : 'text-success' ?>">
                                        <?= $p['stock_actual'] ?> <span class="text-muted fw-normal">/ <?= $p['stock_minimo'] ?> mín</span>
                                    </span>
                                </div>
                                <div class="stock-bar-bg mb-3">
                                    <div class="stock-bar-fill <?= $barColor ?>" style="width: <?= $porcentajeStock ?>%;"></div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top" onclick="event.stopPropagation();">
                                    <?php if ($activo): ?>
                                        <a href="movimientos.php?producto=<?= (int)$p['id_producto'] ?>" class="btn btn-sm btn-outline-success fw-semibold px-2" title="Registrar entrada o salida de este producto">
                                            <i class="bi bi-arrow-left-right me-1"></i>Ajustar Stock
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Producto inactivo</span>
                                    <?php endif; ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="producto_form.php?id=<?= $p['id_producto'] ?>" class="btn btn-outline-dark" title="Editar ficha">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button"
                                                class="btn <?= $activo ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                title="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?>"
                                                onclick="abrirModalEstado(<?= $p['id_producto'] ?>, '<?= htmlspecialchars(addslashes($p['nombre'])) ?>', <?= $activo ? 0 : 1 ?>)">
                                            <i class="bi <?= $activo ? 'bi-power' : 'bi-arrow-clockwise' ?>"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="vistaTabla" class="table-container d-none">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 80px;">Imagen</th>
                            <th style="width: 120px;">Código</th>
                            <th>Producto</th>
                            <th style="width: 220px;">Categoría & Proveedor</th>
                            <th class="text-end" style="width: 120px;">Precio</th>
                            <th class="text-center" style="width: 150px;">Nivel de Stock</th>
                            <th class="text-center" style="width: 125px;">Estado</th>
                            <th class="text-end pe-4" style="width: 145px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $p): ?>
                            <?php
                                $activo = ((int)$p['estado'] === 1);
                                $critico = ($activo && $p['stock_actual'] < $p['stock_minimo']);
                                $claseTr = !$activo ? 'fila-apagada' : ($critico ? 'fila-alerta' : '');
                                $prodJson = htmlspecialchars(json_encode([
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
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="fila-clicable item-catalogo-tabla <?= $claseTr ?>"
                                data-codigo="<?= htmlspecialchars(mb_strtolower($p['codigo'])) ?>"
                                data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'])) ?>"
                                data-categoria="<?= (int)$p['id_categoria'] ?>"
                                data-activo="<?= $activo ? '1' : '0' ?>"
                                data-critico="<?= $critico ? '1' : '0' ?>"
                                data-prod="<?= $prodJson ?>"
                                onclick="abrirDetalleDesdeElemento(this)">
                                <td class="ps-4">
                                    <?php if (!empty($p['imagen'])): ?>
                                        <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" class="prod-thumb"
                                             onerror="this.outerHTML='<div class=\'prod-thumb-placeholder\'><i class=\'bi bi-cpu\'></i></div>';">
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
                                <td class="text-center">
                                    <?php if ($activo): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">● Operativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">○ Baja lógica</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4" onclick="event.stopPropagation();">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($activo): ?>
                                            <a href="movimientos.php?producto=<?= (int)$p['id_producto'] ?>" class="btn btn-outline-success" title="Ajustar Stock">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </a>
                                        <?php endif; ?>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="estadoVacioCatalogo" class="panel-card text-center py-5 text-muted <?= empty($productos) ? '' : 'd-none' ?>">
            <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
            <h6 class="fw-bold text-dark">No se encontraron productos</h6>
            <p class="small mb-3">Intenta con otro nombre, código SKU o limpia los filtros activos.</p>
            <button type="button" onclick="limpiarFiltrosEnVivo()" class="btn btn-sm btn-emerald px-4">Ver todos los productos</button>
        </div>

    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="ts-toast-container">
            <div class="ts-toast">
                <i class="bi bi-check-circle-fill text-success fs-5 mt-0.5"></i>
                <div class="flex-grow-1 small">
                    <strong class="d-block ts-toast-title">Operación completada</strong>
                    <span class="ts-toast-text">
                        <?php
                            if ($_GET['msg'] === 'creado') echo 'Producto incorporado al inventario.';
                            elseif ($_GET['msg'] === 'editado') echo 'Ficha del producto actualizada.';
                            elseif ($_GET['msg'] === 'estado_actualizado') echo 'Estado del producto modificado.';
                        ?>
                    </span>
                </div>
                <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                <div class="ts-toast-progress"></div>
            </div>
        </div>
    <?php endif; ?>
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
                <a href="movimientos.php" id="prodDetBtnMover" class="btn btn-outline-success px-3">
                    <i class="bi bi-arrow-left-right me-1"></i> Ajustar Stock
                </a>
                <a href="#" id="prodDetBtnEditar" class="btn btn-emerald px-4">
                    <i class="bi bi-pencil-square me-1"></i> Editar Producto
                </a>
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
const inputBusqueda      = document.getElementById('inputBusqueda');
const selectEstado       = document.getElementById('selectEstado');
const catalogoWrapper    = document.getElementById('catalogoWrapper');
const contadorResultados = document.getElementById('contadorResultados');
let categoriaActiva      = '';
let timerFiltro          = null;

function cambiarVista(modo) {
    const gridDiv  = document.getElementById('vistaCuadricula');
    const tablaDiv = document.getElementById('vistaTabla');
    const btnGrid  = document.getElementById('btnVistaGrid');
    const btnTabla = document.getElementById('btnVistaTabla');

    if (modo === 'tabla') {
        gridDiv.classList.add('d-none');
        tablaDiv.classList.remove('d-none');
        btnTabla.className = 'btn btn-sm btn-dark fw-semibold px-3';
        btnGrid.className  = 'btn btn-sm btn-outline-dark fw-semibold px-3';
    } else {
        tablaDiv.classList.add('d-none');
        gridDiv.classList.remove('d-none');
        btnGrid.className  = 'btn btn-sm btn-dark fw-semibold px-3';
        btnTabla.className = 'btn btn-sm btn-outline-dark fw-semibold px-3';
    }
    localStorage.setItem('tecnostock_vista_catalogo', modo);
}

const vistaGuardada = localStorage.getItem('tecnostock_vista_catalogo');
if (vistaGuardada === 'tabla') cambiarVista('tabla');

function seleccionarFiltroKpi(estado, tileEl) {
    document.querySelectorAll('.kpi-tile').forEach(t => t.classList.remove('kpi-selected'));
    if (tileEl) tileEl.classList.add('kpi-selected');
    selectEstado.value = estado;
    aplicarFiltrosEnVivo();
}

function seleccionarCategoriaChip(idCat, chipEl) {
    categoriaActiva = idCat;
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    if (chipEl) chipEl.classList.add('active');
    aplicarFiltrosEnVivo();
}

function aplicarFiltrosEnVivo() {
    const texto = inputBusqueda.value.trim().toLowerCase();
    const est   = selectEstado.value;

    const cards = document.querySelectorAll('.item-catalogo');
    const filas = document.querySelectorAll('.item-catalogo-tabla');
    let visibles = 0;

    cards.forEach((card, idx) => {
        const codigo   = card.getAttribute('data-codigo') || '';
        const nombre   = card.getAttribute('data-nombre') || '';
        const filaCat  = card.getAttribute('data-categoria') || '';
        const esActivo = card.getAttribute('data-activo') === '1';
        const esCrit   = card.getAttribute('data-critico') === '1';

        const coincideTexto = !texto || codigo.includes(texto) || nombre.includes(texto);
        const coincideCat   = !categoriaActiva || filaCat === categoriaActiva;

        let coincideEst = true;
        if (est === 'bajo') coincideEst = esCrit;
        else if (est === 'activos') coincideEst = esActivo;
        else if (est === 'inactivos') coincideEst = !esActivo;

        const mostrar = (coincideTexto && coincideCat && coincideEst);
        card.classList.toggle('d-none', !mostrar);
        if (filas[idx]) filas[idx].classList.toggle('d-none', !mostrar);

        if (mostrar) visibles++;
    });

    contadorResultados.textContent = `${visibles} ${visibles === 1 ? 'mostrado' : 'mostrados'}`;

    const estadoVacio = document.getElementById('estadoVacioCatalogo');
    if (estadoVacio) {
        estadoVacio.classList.toggle('d-none', visibles > 0);
    }
}

function programarFiltroSuave() {
    clearTimeout(timerFiltro);
    catalogoWrapper.classList.add('filtrando');
    timerFiltro = setTimeout(() => {
        aplicarFiltrosEnVivo();
        catalogoWrapper.classList.remove('filtrando');
    }, 120);
}

function limpiarFiltrosEnVivo() {
    inputBusqueda.value = '';
    selectEstado.value = '';
    categoriaActiva = '';
    document.querySelectorAll('.cat-chip').forEach((c, i) => c.classList.toggle('active', i === 0));
    document.querySelectorAll('.kpi-tile').forEach((t, i) => t.classList.toggle('kpi-selected', i === 0));
    aplicarFiltrosEnVivo();
}

inputBusqueda.addEventListener('input', programarFiltroSuave);
selectEstado.addEventListener('change', () => {
    document.querySelectorAll('.kpi-tile').forEach(t => t.classList.remove('kpi-selected'));
    if (selectEstado.value === '') document.getElementById('tileTodos').classList.add('kpi-selected');
    else if (selectEstado.value === 'activos') document.getElementById('tileActivos').classList.add('kpi-selected');
    else if (selectEstado.value === 'bajo') document.getElementById('tileCriticos').classList.add('kpi-selected');
    else if (selectEstado.value === 'inactivos') document.getElementById('tileInactivos').classList.add('kpi-selected');
    aplicarFiltrosEnVivo();
});

function abrirDetalleDesdeElemento(el) {
    const p = JSON.parse(el.getAttribute('data-prod'));

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
    imgBox.innerHTML = '';
    if (p.imagen) {
        const img = document.createElement('img');
        img.src = p.imagen;
        img.alt = p.nombre;
        img.onerror = () => {
            imgBox.innerHTML = '<div class="text-center text-muted"><i class="bi bi-cpu fs-1 d-block mb-1"></i><small>Imagen no disponible</small></div>';
        };
        imgBox.appendChild(img);
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

    const btnMover = document.getElementById('prodDetBtnMover');
    if (p.activo) {
        btnMover.classList.remove('d-none');
        btnMover.href = 'movimientos.php?producto=' + p.id;
    } else {
        btnMover.classList.add('d-none');
    }

    document.getElementById('prodDetBtnEditar').href = 'producto_form.php?id=' + p.id;
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