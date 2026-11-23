<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario     = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

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
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f4f7f6; color: #1f2937; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .top-header { background-color: #111827; border-bottom: 3px solid #10b981; padding: 0.85rem 0; }
        .brand-logo { font-weight: 700; font-size: 1.35rem; color: #ffffff; text-decoration: none; letter-spacing: -0.03em; }
        .brand-dot { color: #10b981; }
        .nav-pill-custom { color: #9ca3af; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0.9rem; border-radius: 8px; }
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-pill-custom.active { color: #111827; background-color: #10b981; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; }
        .panel-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.78rem; text-transform: uppercase; padding: 0.95rem 1rem; border: none; }
        .sku-tag {background-color: #111827; color: #34d399; padding: 0.28rem 0.6rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; display: inline-block; white-space: nowrap;}
        .fila-clicable { cursor: pointer; transition: background 0.15s; }
        .fila-clicable:hover { background-color: #ecfdf5 !important; }
    </style>
</head>
<body>

<header class="top-header sticky-top shadow-sm">
    <div class="container-fluid px-4 d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="index.php" class="brand-logo me-2"><i class="bi bi-terminal-fill brand-dot me-1"></i>TECNO<span class="brand-dot">STOCK</span></a>
            <nav class="d-flex gap-1 flex-wrap align-items-center">
                <a href="index.php" class="nav-pill-custom"><i class="bi bi-layers-fill me-1"></i> Catálogo</a>
                <a href="movimientos.php" class="nav-pill-custom"><i class="bi bi-arrow-left-right me-1"></i> Movimientos</a>
                <a href="historial_movimientos.php" class="nav-pill-custom active"><i class="bi bi-journal-text me-1"></i> Registro Global</a>
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
            <a href="logout.php" class="btn btn-sm btn-outline-danger px-3 fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
        </div>
    </div>
</header>

<main class="container-fluid px-4 py-4" style="max-width: 1400px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Registro Global de Movimientos</h3>
            <span class="text-muted small">Historial completo de todas las operaciones del sistema (clic en una fila para ver detalle)</span>
        </div>
        <span class="badge bg-dark fs-6 mono px-3 py-2"><?= count($todosMovs) ?> operaciones</span>
    </div>

    <div class="panel-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Folio</th>
                        <th>Fecha / Hora</th>
                        <th>Tipo</th>
                        <th>Producto</th>
                        <th class="text-center">Cant.</th>
                        <th>Motivo</th>
                        <th class="pe-4">Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todosMovs as $m): ?>
                        <?php $esMio = ((int)$m['id_usuario'] === $idUsuario); ?>
                        <tr class="fila-clicable" onclick='abrirDetalleMovimiento(<?= json_encode([
                            "id"            => $m["id_movimiento"],
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
                            "es_mio"        => $esMio,
                            "editado"       => (int)$m["editado"],
                            "fecha_edicion" => $m["fecha_edicion"] ? date("d/m/Y H:i", strtotime($m["fecha_edicion"])) : ""
                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
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
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($m['prod_nombre']) ?></div>
                                <span class="sku-tag mono"><?= htmlspecialchars($m['prod_codigo']) ?></span>
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
                </tbody>
            </table>
        </div>
    </div>
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
                    <div id="detImagenBox" style="width: 72px; height: 72px; flex-shrink: 0;"></div>
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
function abrirDetalleMovimiento(info) {
    document.getElementById('detId').textContent = '#' + info.id;
    document.getElementById('detCodigo').textContent = info.codigo;
    document.getElementById('detProducto').textContent = info.producto;
    document.getElementById('detFecha').textContent = info.fecha;
    document.getElementById('detUsuario').textContent = info.usuario;
    document.getElementById('detProveedor').textContent = info.proveedor;
    document.getElementById('detMotivo').textContent = info.motivo;
    document.getElementById('detObs').textContent = info.obs;

    const imgBox = document.getElementById('detImagenBox');
    if (info.imagen) {
        imgBox.innerHTML = `<img src="${info.imagen}" alt="${info.producto}" style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;" onerror="this.outerHTML='<div style=\\'width:72px;height:72px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;\\'><i class=\\'bi bi-cpu fs-3\\'></i></div>';">`;
    } else {
        imgBox.innerHTML = '<div style="width:72px;height:72px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
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
        btnEditar.href = 'movimiento_editar.php?id=' + info.id;
    } else {
        btnEditar.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('modalDetalleMov')).show();
}
</script>
</body>
</html>