<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario     = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto  = (int)($_POST['id_producto'] ?? 0);
    $tipo         = (int)($_POST['tipo'] ?? 0);
    $motivo       = trim($_POST['motivo'] ?? '');
    $cantidad     = trim($_POST['cantidad'] ?? '');
    $id_proveedor = ($tipo === 1 && !empty($_POST['id_proveedor'])) ? (int)$_POST['id_proveedor'] : null;
    $observacion  = trim($_POST['observacion'] ?? '');

    if ($id_producto <= 0) $errores[] = 'Debe seleccionar un producto válido.';
    if (!in_array($tipo, [1, 2])) $errores[] = 'Tipo de movimiento inválido.';
    if (empty($motivo)) $errores[] = 'El motivo es obligatorio.';
    if (!is_numeric($cantidad) || (int)$cantidad <= 0) {
        $errores[] = 'La cantidad debe ser un número entero mayor a 0 (RN02).';
    }

    if (empty($errores)) {
        $cantidadInt = (int)$cantidad;
        try {
            $pdo->beginTransaction();

            $stmtProd = $pdo->prepare("SELECT nombre, stock_actual, estado FROM producto WHERE id_producto = :id FOR UPDATE");
            $stmtProd->execute(['id' => $id_producto]);
            $prodActual = $stmtProd->fetch();

            if (!$prodActual || (int)$prodActual['estado'] === 0) {
                throw new Exception('El producto seleccionado no existe o está inactivo.');
            }
            if ($tipo === 2 && ($prodActual['stock_actual'] - $cantidadInt) < 0) {
                throw new Exception("Stock insuficiente para '{$prodActual['nombre']}'. Disponible: {$prodActual['stock_actual']}, solicitado: {$cantidadInt} (RN04).");
            }

            $nuevoStock = ($tipo === 1) 
                ? ($prodActual['stock_actual'] + $cantidadInt) 
                : ($prodActual['stock_actual'] - $cantidadInt);

            $sqlMov = "INSERT INTO movimiento (fecha_hora, tipo, motivo, cantidad, observacion, id_producto, id_usuario, id_proveedor) 
                       VALUES (NOW(), :t, :m, :c, :o, :p, :u, :pr)";
            $pdo->prepare($sqlMov)->execute([
                't'  => $tipo,
                'm'  => $motivo,
                'c'  => $cantidadInt,
                'o'  => $observacion ?: null,
                'p'  => $id_producto,
                'u'  => $idUsuario,
                'pr' => $id_proveedor
            ]);

            $pdo->prepare("UPDATE producto SET stock_actual = :st WHERE id_producto = :id")
                ->execute(['st' => $nuevoStock, 'id' => $id_producto]);

            $pdo->commit();
            header('Location: movimientos.php?msg=registrado');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }
}

$productosActivos = $pdo->query("SELECT id_producto, codigo, nombre, stock_actual FROM producto WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$proveedores      = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();

$stmtMisMovs = $pdo->prepare("
    SELECT m.*, p.codigo AS prod_codigo, p.nombre AS prod_nombre, p.imagen AS prod_imagen,
           pr.razon_social AS proveedor_nombre
    FROM movimiento m
    INNER JOIN producto p ON m.id_producto = p.id_producto
    LEFT JOIN proveedor pr ON m.id_proveedor = pr.id_proveedor
    WHERE m.id_usuario = :id_u
    ORDER BY m.fecha_hora DESC, m.id_movimiento DESC
");
$stmtMisMovs->execute(['id_u' => $idUsuario]);
$misMovimientos = $stmtMisMovs->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos | TecnoStock</title>
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
        .btn-emerald:hover { background-color: #059669; color: #fff; }
        .panel-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.76rem; text-transform: uppercase; padding: 0.9rem; border: none; }
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
                <a href="movimientos.php" class="nav-pill-custom active"><i class="bi bi-arrow-left-right me-1"></i> Movimientos</a>
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
            <a href="logout.php" class="btn btn-sm btn-outline-danger px-3 fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
        </div>
    </div>
</header>

<main class="container-fluid px-4 py-4" style="max-width: 1400px;">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-dark border-start border-4 border-success shadow-sm mb-4">
            <i class="bi bi-check2-all text-success fs-5 me-2"></i>
            <?= $_GET['msg'] === 'editado' ? 'Movimiento modificado y stock recalculado correctamente.' : 'Movimiento registrado y stock actualizado automáticamente.' ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <div class="p-3 bg-dark text-white fw-bold">
                    <i class="bi bi-plus-square-fill text-success me-2"></i>Registrar Movimiento
                </div>
                <div class="p-4">
                    <form method="POST" id="formMovimiento" novalidate>
                        <div class="mb-3">
                            <label for="id_producto" class="form-label fw-semibold small">Producto Activo <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_producto" name="id_producto" required>
                                <option value="" data-stock="0" data-nombre="">Seleccione un producto...</option>
                                <?php foreach ($productosActivos as $prod): ?>
                                    <option value="<?= $prod['id_producto'] ?>" 
                                            data-stock="<?= $prod['stock_actual'] ?>"
                                            data-nombre="[<?= htmlspecialchars($prod['codigo']) ?>] <?= htmlspecialchars($prod['nombre']) ?>">
                                        [<?= htmlspecialchars($prod['codigo']) ?>] <?= htmlspecialchars($prod['nombre']) ?> (Stock: <?= $prod['stock_actual'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un producto.</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="tipo" class="form-label fw-semibold small">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="1">ENTRADA (+ Stock)</option>
                                    <option value="2">SALIDA (- Stock)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cantidad" class="form-label fw-semibold small">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" step="1" placeholder="Ej: 5" required>
                                <div class="invalid-feedback" id="feedbackCantidad">Cantidad mayor a 0.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="motivo" class="form-label fw-semibold small">Motivo <span class="text-danger">*</span></label>
                            <select class="form-select" id="motivo" name="motivo" required>
                                <option value="Compra a Proveedor">Compra a Proveedor</option>
                                <option value="Venta en tienda">Venta en tienda</option>
                                <option value="Devolución">Devolución</option>
                                <option value="Ajuste de inventario">Ajuste de inventario</option>
                            </select>
                        </div>

                        <div class="mb-3" id="bloqueProveedor">
                            <label for="id_proveedor" class="form-label fw-semibold small">Proveedor (Opcional)</label>
                            <select class="form-select" id="id_proveedor" name="id_proveedor">
                                <option value="">-- Ninguno --</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= $prov['id_proveedor'] ?>"><?= htmlspecialchars($prov['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="observacion" class="form-label fw-semibold small">Observación / Documento</label>
                            <input type="text" class="form-control" id="observacion" name="observacion" placeholder="Ej: Factura #4890 o Boleta #201">
                        </div>

                        <button type="button" class="btn btn-emerald w-100 py-2" onclick="validarYConfirmarMovimiento()">
                            <i class="bi bi-check2-circle me-1"></i> Registrar Movimiento
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel-card">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0"><i class="bi bi-person-check text-success me-2"></i>Mis Movimientos Registrados</h6>
                        <small class="text-muted">Haz clic en cualquier fila para ver el detalle completo o editar</small>
                    </div>
                    <span class="badge bg-dark mono"><?= count($misMovimientos) ?> registros</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th class="ps-3">Folio / Fecha</th>
                                <th>Tipo</th>
                                <th>Producto</th>
                                <th class="text-center">Cant.</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($misMovimientos)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Aún no has registrado movimientos.</td></tr>
                            <?php else: ?>
                                <?php foreach ($misMovimientos as $m): ?>
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
                                        "usuario"       => $nombreUsuario,
                                        "es_mio"        => true,
                                        "editado"       => (int)$m["editado"],
                                        "fecha_edicion" => $m["fecha_edicion"] ? date("d/m/Y H:i", strtotime($m["fecha_edicion"])) : ""
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <td class="ps-3">
                                            <span class="fw-bold mono text-dark">#<?= $m['id_movimiento'] ?></span>
                                            <?php if ((int)$m['editado'] === 1): ?>
                                                <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Editado</span>
                                            <?php endif; ?>
                                            <div class="text-muted mono" style="font-size: 0.75rem;"><?= date('d/m/Y H:i', strtotime($m['fecha_hora'])) ?></div>
                                        </td>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalConfirmarMov" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check text-success me-2"></i>Confirmar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted small mb-2">Estás a punto de registrar la siguiente operación en inventario:</p>
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div id="confTipoBadge" class="mb-2"></div>
                    <h6 class="fw-bold text-dark mb-1" id="confProducto"></h6>
                    <div class="fs-4 fw-bold mono text-dark" id="confCantidad"></div>
                </div>
                <div class="small text-muted">
                    Motivo: <strong id="confMotivo" class="text-dark"></strong>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center gap-2 pb-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Revisar</button>
                <button type="button" class="btn btn-emerald px-4 fw-semibold" onclick="document.getElementById('formMovimiento').submit()">
                    Sí, confirmar
                </button>
            </div>
        </div>
    </div>
</div>

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
const selectTipo = document.getElementById('tipo');
const bloqueProveedor = document.getElementById('bloqueProveedor');
function toggleProv() {
    bloqueProveedor.style.display = (selectTipo.value === '2') ? 'none' : 'block';
    if (selectTipo.value === '2') document.getElementById('id_proveedor').value = '';
}
selectTipo.addEventListener('change', toggleProv);
toggleProv();

function validarYConfirmarMovimiento() {
    let esValido = true;
    const prodSelect = document.getElementById('id_producto');
    const cantidadInput = document.getElementById('cantidad');
    const feedbackCantidad = document.getElementById('feedbackCantidad');
    const opcionSel = prodSelect.options[prodSelect.selectedIndex];
    const stockDisponible = Number(opcionSel?.getAttribute('data-stock') || 0);
    const cantidad = Number(cantidadInput.value);

    if (prodSelect.value === '') { prodSelect.classList.add('is-invalid'); esValido = false; }
    else { prodSelect.classList.remove('is-invalid'); }

    if (cantidadInput.value.trim() === '' || isNaN(cantidad) || cantidad <= 0) {
        cantidadInput.classList.add('is-invalid');
        feedbackCantidad.textContent = 'La cantidad debe ser mayor a 0.';
        esValido = false;
    } else if (selectTipo.value === '2' && prodSelect.value !== '' && cantidad > stockDisponible) {
        cantidadInput.classList.add('is-invalid');
        feedbackCantidad.textContent = `Stock insuficiente (RN04). Disponible: ${stockDisponible}.`;
        esValido = false;
    } else {
        cantidadInput.classList.remove('is-invalid');
    }

    if (!esValido) return;

    const esEntrada = (selectTipo.value === '1');
    document.getElementById('confTipoBadge').innerHTML = esEntrada
        ? '<span class="badge bg-success px-3 py-1">ENTRADA (+ Stock)</span>'
        : '<span class="badge bg-danger px-3 py-1">SALIDA (- Stock)</span>';
    document.getElementById('confProducto').textContent = opcionSel.getAttribute('data-nombre');
    document.getElementById('confCantidad').textContent = (esEntrada ? '+' : '-') + cantidad + ' unidades';
    document.getElementById('confMotivo').textContent = document.getElementById('motivo').value;

    new bootstrap.Modal(document.getElementById('modalConfirmarMov')).show();
}

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