<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario     = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

$usuariosFiltro = $pdo->query("SELECT id_usuario, nombre FROM usuario ORDER BY nombre ASC")->fetchAll();

$todosMovs = $pdo->query("
    SELECT m.*, p.codigo AS prod_codigo, p.nombre AS prod_nombre, p.imagen AS prod_imagen,
           u.nombre AS usuario_nombre, u.rol AS usuario_rol,
           pr.razon_social AS proveedor_nombre
    FROM movimiento m
    INNER JOIN producto p ON m.id_producto = p.id_producto
    INNER JOIN usuario u ON m.id_usuario = u.id_usuario
    LEFT JOIN proveedor pr ON m.id_proveedor = pr.id_proveedor
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
")->fetchAll();

$totalOps      = count($todosMovs);
$totalEntradas = 0;
$totalSalidas  = 0;
$totalEditados = 0;

foreach ($todosMovs as $mv) {
    if ((int)$mv['tipo'] === 1) $totalEntradas++;
    else $totalSalidas++;
    if ((int)$mv['editado'] === 1) $totalEditados++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Global | TecnoStock</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        :root {
            --emerald-primary: #10b981;
            --emerald-dark: #059669;
            --coral-danger: #ef4444;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f4f7f6; color: #1f2937; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .top-header { background-color: #111827; border-bottom: 3px solid #10b981; padding: 0.85rem 0; }
        .brand-logo { font-weight: 700; font-size: 1.35rem; color: #ffffff; text-decoration: none; letter-spacing: -0.03em; }
        .brand-dot { color: #10b981; }
        .nav-pill-custom { color: #9ca3af; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0.9rem; border-radius: 8px; transition: all 0.2s ease; }
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-pill-custom.active { color: #111827; background-color: #10b981; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; }
        .btn-emerald:hover { background-color: #059669; color: #fff; }

        .kpi-tile {
            background: #ffffff; border-radius: 14px; border: 1px solid #e5e7eb;
            padding: 1.1rem 1.25rem; cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .kpi-tile:hover { transform: translateY(-2px); border-color: var(--emerald-primary); box-shadow: 0 8px 18px rgba(16,185,129,0.09); }
        .kpi-tile.kpi-selected { border: 2px solid var(--emerald-primary); background-color: #ecfdf5; }
        .kpi-tile.kpi-danger-tile.kpi-selected { border-color: var(--coral-danger); background-color: #fef2f2; }
        body.dark-mode .kpi-tile { background-color: #111827; border-color: #1f2937; }
        body.dark-mode .kpi-tile.kpi-selected { background-color: rgba(16, 185, 129, 0.14); border-color: #10b981; }
        body.dark-mode .kpi-tile.kpi-danger-tile.kpi-selected { background-color: rgba(239, 68, 68, 0.15); border-color: #ef4444; }
        .kpi-icon-box { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }

        .filter-bar, .panel-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .panel-card { overflow: hidden; min-height: 440px; }
        #tbodyMovimientos { transition: opacity 0.14s ease; }
        #tbodyMovimientos.filtrando { opacity: 0.65; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.95rem 1rem; border: none; }
        .table tbody td { padding: 0.85rem 1rem; vertical-align: middle; border-bottom: 1px solid #f3f4f6; }
        .prod-thumb {
            width: 50px; height: 50px; border-radius: 10px;
            object-fit: contain; background-color: #ffffff; padding: 3px;
            border: 1px solid #e5e7eb; flex-shrink: 0;
        }
        .prod-thumb-placeholder {
            width: 50px; height: 50px; border-radius: 10px; background-color: #f1f5f9;
            border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;
            color: #94a3b8; font-size: 1.2rem; flex-shrink: 0;
        }
        .sku-tag { background-color: #111827; color: #34d399; padding: 0.22rem 0.55rem; border-radius: 5px; font-size: 0.76rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .fila-clicable { cursor: pointer; transition: background 0.15s; }
        .fila-clicable:hover { background-color: #ecfdf5 !important; }
        .form-control:focus, .form-select:focus { border-color: #10b981; box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.18); }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container-fluid px-4 py-4" style="max-width: 1420px;">

    <!-- KPIs Interactivos (Clic para filtrar al instante) -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile kpi-selected" id="tileGlobalTodos" onclick="filtrarPorTileGlobal('todos', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Total Auditoría</span>
                        <span class="fs-3 fw-bold mono"><?= $totalOps ?></span>
                        <span class="text-muted small ms-1">movimientos</span>
                    </div>
                    <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile" id="tileGlobalEntradas" onclick="filtrarPorTileGlobal('1', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Entradas (+ Stock)</span>
                        <span class="fs-3 fw-bold mono text-success"><?= $totalEntradas ?></span>
                        <span class="text-muted small ms-1">ingresos</span>
                    </div>
                    <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile kpi-danger-tile" id="tileGlobalSalidas" onclick="filtrarPorTileGlobal('2', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Salidas (- Stock)</span>
                        <span class="fs-3 fw-bold mono text-danger"><?= $totalSalidas ?></span>
                        <span class="text-muted small ms-1">despachos</span>
                    </div>
                    <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-tile" id="tileGlobalEditados" onclick="filtrarPorTileGlobal('editados', this)">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-bold text-uppercase d-block">Modificados</span>
                        <span class="fs-3 fw-bold mono text-warning"><?= $totalEditados ?></span>
                        <span class="text-muted small ms-1">editados</span>
                    </div>
                    <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="filter-bar p-3 mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white text-success"><i class="bi bi-search"></i></span>
                    <input type="text" id="filtroProducto" class="form-control" autocomplete="off"
                           placeholder="Buscar por producto, SKU, motivo o folio...">
                </div>
            </div>

            <div class="col-md-3">
                <select id="filtroUsuario" class="form-select">
                    <option value="">Responsable: Todos</option>
                    <?php foreach ($usuariosFiltro as $uf): ?>
                        <option value="<?= (int)$uf['id_usuario'] ?>">
                            <?= htmlspecialchars($uf['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select id="filtroTipo" class="form-select">
                    <option value="">Filtro: Todos los registros</option>
                    <option value="1">Solo Entradas (+ Stock)</option>
                    <option value="2">Solo Salidas (- Stock)</option>
                    <option value="editados">Solo Editados</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-center gap-2">
                <button type="button" onclick="limpiarFiltrosGlobal()" class="btn btn-outline-dark w-100 fw-semibold">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla Principal -->
    <div class="panel-card">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
            <span class="small text-muted fw-semibold">
                <i class="bi bi-hand-index-thumb text-success me-1"></i>Haz clic en cualquier fila para inspeccionar el detalle <?= $rolUsuario === 1 ? 'o editar el registro' : '' ?>
            </span>
            <span class="badge bg-dark mono px-3 py-1" id="contadorOperaciones"><?= $totalOps ?> operaciones</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 115px;">Folio</th>
                        <th style="width: 155px;">Fecha / Hora</th>
                        <th style="width: 115px;">Tipo</th>
                        <th>Producto</th>
                        <th class="text-center" style="width: 95px;">Cant.</th>
                        <th style="width: 210px;">Motivo</th>
                        <th class="pe-4" style="width: 220px;">Responsable</th>
                    </tr>
                </thead>
                <tbody id="tbodyMovimientos">
                    <?php if (empty($todosMovs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-50"></i>
                                No existen movimientos registrados en el sistema.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todosMovs as $m): ?>
                            <?php
                                $esMio = ((int)$m['id_usuario'] === $idUsuario);
                                $puedeEditar = ($esMio || $rolUsuario === 1);
                                $infoMov = htmlspecialchars(json_encode([
                                    "id"            => (int)$m["id_movimiento"],
                                    "fecha"         => date("d/m/Y H:i", strtotime($m["fecha_hora"])),
                                    "tipo"          => (int)$m["tipo"],
                                    "codigo"        => $m["prod_codigo"],
                                    "producto"      => $m["prod_nombre"],
                                    "imagen"        => $m["prod_imagen"] ?? "",
                                    "cantidad"      => (int)$m["cantidad"],
                                    "motivo"        => $m["motivo"],
                                    "obs"           => $m["observacion"] ?? "Sin observaciones",
                                    "proveedor"     => $m["proveedor_nombre"] ?? "No aplica",
                                    "usuario"       => $m["usuario_nombre"],
                                    "es_mio"        => $puedeEditar,
                                    "editado"       => (int)$m["editado"],
                                    "fecha_edicion" => $m["fecha_edicion"] ? date("d/m/Y H:i", strtotime($m["fecha_edicion"])) : ""
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr class="fila-clicable fila-movimiento"
                                data-folio="<?= (int)$m['id_movimiento'] ?>"
                                data-codigo="<?= htmlspecialchars(mb_strtolower($m['prod_codigo'])) ?>"
                                data-producto="<?= htmlspecialchars(mb_strtolower($m['prod_nombre'])) ?>"
                                data-motivo="<?= htmlspecialchars(mb_strtolower($m['motivo'])) ?>"
                                data-usuario="<?= (int)$m['id_usuario'] ?>"
                                data-tipo="<?= (int)$m['tipo'] ?>"
                                data-editado="<?= (int)$m['editado'] ?>"
                                data-mov="<?= $infoMov ?>"
                                onclick="abrirDetalleDesdeFila(this)">
                                <td class="ps-4">
                                    <span class="fw-bold mono">#<?= $m['id_movimiento'] ?></span>
                                    <?php if ((int)$m['editado'] === 1): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.68rem;">Editado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono small text-muted"><?= date('d/m/Y H:i', strtotime($m['fecha_hora'])) ?></td>
                                <td>
                                    <?php if ((int)$m['tipo'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ENTRADA</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">SALIDA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($m['prod_imagen'])): ?>
                                            <img src="<?= htmlspecialchars($m['prod_imagen']) ?>" alt="<?= htmlspecialchars($m['prod_nombre']) ?>" class="prod-thumb"
                                                 onerror="this.outerHTML='<div class=\'prod-thumb-placeholder\'><i class=\'bi bi-cpu\'></i></div>';">
                                        <?php else: ?>
                                            <div class="prod-thumb-placeholder"><i class="bi bi-cpu"></i></div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 270px;">
                                                <?= htmlspecialchars($m['prod_nombre']) ?>
                                            </div>
                                            <span class="sku-tag mono"><?= htmlspecialchars($m['prod_codigo']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold fs-6 mono <?= (int)$m['tipo'] === 1 ? 'text-success' : 'text-danger' ?>">
                                    <?= (int)$m['tipo'] === 1 ? '+' : '-' ?><?= $m['cantidad'] ?>
                                </td>
                                <td class="fw-semibold"><?= htmlspecialchars($m['motivo']) ?></td>
                                <td class="pe-4">
                                    <span class="fw-semibold"><?= htmlspecialchars($m['usuario_nombre']) ?></span>
                                    <?php if ($esMio): ?>
                                        <span class="badge bg-success ms-1" style="font-size: 0.65rem;">Tú</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="filaSinMovs" class="d-none">
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                No se encontraron movimientos con los filtros seleccionados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'editado'): ?>
        <div class="ts-toast-container">
            <div class="ts-toast">
                <i class="bi bi-check-circle-fill text-success fs-5 mt-0.5"></i>
                <div class="flex-grow-1 small">
                    <strong class="d-block ts-toast-title">Operación completada</strong>
                    <span class="ts-toast-text">Movimiento actualizado y stock recalculado correctamente.</span>
                </div>
                <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                <div class="ts-toast-progress"></div>
            </div>
        </div>
    <?php endif; ?>
</main>

<div class="modal fade" id="modalDetalleMov" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-receipt text-success me-2"></i>Movimiento <span id="detId" class="mono text-success"></span>
                    <span id="detBadgeEditado" class="badge bg-warning text-dark ms-2 d-none" style="font-size: 0.72rem;">Editado</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded-3 border mb-3 d-flex align-items-center gap-3">
                    <div id="detImagenBox" style="width: 76px; height: 76px; flex-shrink: 0;"></div>
                    <div>
                        <span id="detCodigo" class="sku-tag mono"></span>
                        <h5 class="fw-bold text-dark mt-1 mb-0" id="detProducto"></h5>
                        <div class="fs-6 fw-bold mono mt-1" id="detCantidadTipo"></div>
                    </div>
                </div>
                <div class="row g-2 small">
                    <div class="col-6"><strong class="text-muted d-block">Fecha Registro:</strong> <span id="detFecha" class="mono"></span></div>
                    <div class="col-6"><strong class="text-muted d-block">Responsable:</strong> <span id="detUsuario"></span></div>
                    <div class="col-6"><strong class="text-muted d-block">Proveedor:</strong> <span id="detProveedor"></span></div>
                    <div class="col-6"><strong class="text-muted d-block">Motivo:</strong> <span id="detMotivo"></span></div>
                    <div class="col-12"><strong class="text-muted d-block">Observación:</strong> <span id="detObs"></span></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <a href="#" id="btnEditarMovModal" class="btn btn-emerald px-4 d-none">
                    <i class="bi bi-pencil-square me-1"></i> Editar Movimiento
                </a>
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const filtroProducto      = document.getElementById('filtroProducto');
const filtroUsuario       = document.getElementById('filtroUsuario');
const filtroTipo          = document.getElementById('filtroTipo');
const tbodyMovimientos    = document.getElementById('tbodyMovimientos');
const contadorOperaciones = document.getElementById('contadorOperaciones');
let timerFiltroGlobal     = null;

function filtrarPorTileGlobal(valor, tileEl) {
    document.querySelectorAll('.kpi-tile').forEach(t => t.classList.remove('kpi-selected'));
    if (tileEl) tileEl.classList.add('kpi-selected');
    filtroTipo.value = (valor === 'todos') ? '' : valor;
    aplicarFiltrosGlobal();
}

function aplicarFiltrosGlobal() {
    const q    = filtroProducto.value.trim().toLowerCase().replace(/^#/, '');
    const usu  = filtroUsuario.value;
    const tipo = filtroTipo.value;

    const filas = document.querySelectorAll('.fila-movimiento');
    let visibles = 0;

    filas.forEach(fila => {
        const folio    = fila.getAttribute('data-folio') || '';
        const codigo   = fila.getAttribute('data-codigo') || '';
        const producto = fila.getAttribute('data-producto') || '';
        const motivo   = fila.getAttribute('data-motivo') || '';
        const filaUsu  = fila.getAttribute('data-usuario') || '';
        const filaTipo = fila.getAttribute('data-tipo') || '';
        const esEdit   = fila.getAttribute('data-editado') === '1';

        const matchTexto = !q || folio === q || codigo.includes(q) || producto.includes(q) || motivo.includes(q);
        const matchUsu   = !usu || filaUsu === usu;

        let matchTipo = true;
        if (tipo === '1') matchTipo = (filaTipo === '1');
        else if (tipo === '2') matchTipo = (filaTipo === '2');
        else if (tipo === 'editados') matchTipo = esEdit;

        if (matchTexto && matchUsu && matchTipo) {
            fila.classList.remove('d-none');
            visibles++;
        } else {
            fila.classList.add('d-none');
        }
    });

    contadorOperaciones.textContent = `${visibles} ${visibles === 1 ? 'operación' : 'operaciones'}`;

    const filaSin = document.getElementById('filaSinMovs');
    if (filaSin) {
        if (visibles === 0 && filas.length > 0) filaSin.classList.remove('d-none');
        else filaSin.classList.add('d-none');
    }
}

function programarFiltroGlobal() {
    clearTimeout(timerFiltroGlobal);
    tbodyMovimientos.classList.add('filtrando');
    timerFiltroGlobal = setTimeout(() => {
        aplicarFiltrosGlobal();
        tbodyMovimientos.classList.remove('filtrando');
    }, 120);
}

function limpiarFiltrosGlobal() {
    filtroProducto.value = '';
    filtroUsuario.value = '';
    filtroTipo.value = '';
    document.querySelectorAll('.kpi-tile').forEach((t, i) => t.classList.toggle('kpi-selected', i === 0));
    aplicarFiltrosGlobal();
}

filtroProducto.addEventListener('input', programarFiltroGlobal);
filtroUsuario.addEventListener('change', aplicarFiltrosGlobal);
filtroTipo.addEventListener('change', () => {
    document.querySelectorAll('.kpi-tile').forEach(t => t.classList.remove('kpi-selected'));
    if (filtroTipo.value === '') document.getElementById('tileGlobalTodos').classList.add('kpi-selected');
    else if (filtroTipo.value === '1') document.getElementById('tileGlobalEntradas').classList.add('kpi-selected');
    else if (filtroTipo.value === '2') document.getElementById('tileGlobalSalidas').classList.add('kpi-selected');
    else if (filtroTipo.value === 'editados') document.getElementById('tileGlobalEditados').classList.add('kpi-selected');
    aplicarFiltrosGlobal();
});

function abrirDetalleDesdeFila(tr) {
    const info = JSON.parse(tr.getAttribute('data-mov'));

    document.getElementById('detId').textContent = '#' + info.id;
    document.getElementById('detCodigo').textContent = info.codigo;
    document.getElementById('detProducto').textContent = info.producto;
    document.getElementById('detFecha').textContent = info.fecha;
    document.getElementById('detUsuario').textContent = info.usuario;
    document.getElementById('detProveedor').textContent = info.proveedor;
    document.getElementById('detMotivo').textContent = info.motivo;
    document.getElementById('detObs').textContent = info.obs;

    const imgBox = document.getElementById('detImagenBox');
    imgBox.innerHTML = '';
    if (info.imagen) {
        const img = document.createElement('img');
        img.src = info.imagen;
        img.alt = info.producto;
        img.style.cssText = 'width:76px;height:76px;object-fit:contain;background:#ffffff;padding:4px;border-radius:10px;border:1px solid #cbd5e1;';
        img.onerror = () => {
            imgBox.innerHTML = '<div style="width:76px;height:76px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
        };
        imgBox.appendChild(img);
    } else {
        imgBox.innerHTML = '<div style="width:76px;height:76px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
    }

    document.getElementById('detCantidadTipo').innerHTML = info.tipo === 1 
        ? `<span class="text-success">+${info.cantidad} uds. (ENTRADA)</span>` 
        : `<span class="text-danger">-${info.cantidad} uds. (SALIDA)</span>`;

    const badgeEd = document.getElementById('detBadgeEditado');
    if (info.editado === 1) {
        badgeEd.classList.remove('d-none');
        badgeEd.title = 'Editado el: ' + info.fecha_edicion;
    } else {
        badgeEd.classList.add('d-none');
    }

    const btnEditar = document.getElementById('btnEditarMovModal');
    if (info.es_mio) {
        btnEditar.classList.remove('d-none');
        btnEditar.href = 'movimiento_editar.php?id=' + info.id + '&origen=global';
    } else {
        btnEditar.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('modalDetalleMov')).show();
}
</script>
</body>
</html>