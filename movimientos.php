<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario                 = (int)$_SESSION['id_usuario'];
$rolUsuario                = (int)$_SESSION['rol'];
$nombreUsuario             = $_SESSION['nombre'];
$idProductoPreseleccionado = isset($_GET['producto']) ? (int)$_GET['producto'] : 0;

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
        $errores[] = 'La cantidad debe ser un número entero mayor a 0.';
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
                throw new Exception("Stock insuficiente para '{$prodActual['nombre']}'. Disponible: {$prodActual['stock_actual']}, solicitado: {$cantidadInt}.");
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

$productosActivos = $pdo->query("
    SELECT p.id_producto, p.codigo, p.nombre, p.imagen, p.stock_actual, p.stock_minimo, c.nombre AS categoria_nombre
    FROM producto p
    INNER JOIN categoria c ON p.id_categoria = c.id_categoria
    WHERE p.estado = 1
    ORDER BY p.nombre ASC
")->fetchAll();

$proveedores = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();

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

$productosCatalogoJson = [];
foreach ($productosActivos as $pa) {
    $productosCatalogoJson[] = [
        'id'        => (int)$pa['id_producto'],
        'codigo'    => $pa['codigo'],
        'nombre'    => $pa['nombre'],
        'imagen'    => $pa['imagen'] ?? '',
        'stock'     => (int)$pa['stock_actual'],
        'minimo'    => (int)$pa['stock_minimo'],
        'categoria' => $pa['categoria_nombre']
    ];
}
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
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-pill-custom.active { color: #111827; background-color: #10b981; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; transition: all 0.2s; }
        .btn-emerald:hover { background-color: #059669; color: #fff; }
        .panel-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .table-panel { overflow: hidden; }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.95rem 0.9rem; border: none; }
        .sku-tag { background-color: #111827; color: #34d399; padding: 0.24rem 0.55rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .fila-clicable { cursor: pointer; transition: background 0.15s; }
        .fila-clicable:hover { background-color: #ecfdf5 !important; }

        .prod-thumb-sm {
            width: 44px; height: 44px; border-radius: 8px;
            object-fit: contain; background-color: #ffffff; padding: 2px;
            border: 1px solid #e5e7eb; flex-shrink: 0;
        }
        .prod-thumb-sm-placeholder {
            width: 44px; height: 44px; border-radius: 8px; background-color: #f1f5f9;
            border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;
            color: #94a3b8; font-size: 1.1rem; flex-shrink: 0;
        }

        .tipo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .tipo-option input[type="radio"] { display: none; }
        .tipo-label {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 0.85rem 0.5rem; border-radius: 12px; border: 2px solid #e5e7eb;
            background-color: #f9fafb; cursor: pointer; transition: all 0.2s ease; text-align: center;
        }
        .tipo-label:hover { border-color: #cbd5e1; background-color: #f1f5f9; }
        .tipo-option input[value="1"]:checked + .tipo-label {
            border-color: var(--emerald-primary); background-color: #ecfdf5; color: #065f46;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.15);
        }
        .tipo-option input[value="2"]:checked + .tipo-label {
            border-color: var(--coral-danger); background-color: #fef2f2; color: #991b1b;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.15);
        }

        .visual-picker-wrapper { position: relative; }
        .visual-dropdown {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px;
            box-shadow: 0 15px 30px -5px rgba(17, 24, 39, 0.18);
            max-height: 290px; overflow-y: auto; z-index: 1050;
        }
        .visual-item {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.12s;
        }
        .visual-item:last-child { border-bottom: none; }
        .visual-item:hover { background-color: #ecfdf5; }
        .visual-item-thumb {
            width: 46px; height: 46px; border-radius: 8px;
            object-fit: contain; background: #ffffff; padding: 2px;
            border: 1px solid #e2e8f0; flex-shrink: 0;
        }
        .visual-item-placeholder {
            width: 46px; height: 46px; border-radius: 8px; background: #f1f5f9;
            border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;
            color: #94a3b8; font-size: 1.2rem; flex-shrink: 0;
        }

        .product-preview-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #cbd5e1; border-radius: 12px; padding: 0.9rem;
        }
        .preview-thumb {
            width: 58px; height: 58px; border-radius: 10px;
            object-fit: contain; background: #ffffff; padding: 3px;
            border: 1px solid #cbd5e1; flex-shrink: 0;
        }
        .preview-placeholder {
            width: 58px; height: 58px; border-radius: 10px; background: #e2e8f0;
            display: flex; align-items: center; justify-content: center; color: #64748b;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .stock-meter {
            background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 0.65rem 0.85rem; margin-top: 0.75rem;
        }
        .quick-qty-btn {
            font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.55rem;
            border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #334155;
            transition: all 0.15s;
        }
        .quick-qty-btn:hover { background: #111827; color: #34d399; border-color: #111827; }
        .form-control:focus, .form-select:focus { border-color: var(--emerald-primary); box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.18); }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container-fluid px-4 py-4" style="max-width: 1400px;">

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="mb-4">
        <h3 class="fw-bold mb-0">Control de Entradas y Salidas</h3>
        <span class="text-muted small">Busca visualmente el producto, define la cantidad y verifica el nuevo saldo antes de confirmar</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card">
                <div class="p-3 bg-dark text-white d-flex align-items-center justify-content-between border-bottom border-success border-3 rounded-top-4">
                    <span class="fw-bold"><i class="bi bi-sliders2-vertical text-success me-2"></i>Nueva Operación</span>
                    <span class="badge bg-success bg-opacity-25 text-success mono" style="font-size: 0.7rem;">BÚSQUEDA VISUAL</span>
                </div>

                <div class="p-4">
                    <form method="POST" id="formMovimiento" novalidate>
                        <input type="hidden" name="id_producto" id="id_producto" value="">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-2">1. ¿Qué operación vas a realizar?</label>
                            <div class="tipo-grid">
                                <label class="tipo-option">
                                    <input type="radio" name="tipo" value="1" id="radioEntrada" checked>
                                    <div class="tipo-label">
                                        <i class="bi bi-box-arrow-in-down fs-4 text-success mb-1"></i>
                                        <span class="fw-bold small">ENTRADA</span>
                                        <span style="font-size: 0.72rem; opacity: 0.8;">Suma unidades (+ Stock)</span>
                                    </div>
                                </label>
                                <label class="tipo-option">
                                    <input type="radio" name="tipo" value="2" id="radioSalida">
                                    <div class="tipo-label">
                                        <i class="bi bi-box-arrow-up fs-4 text-danger mb-1"></i>
                                        <span class="fw-bold small">SALIDA</span>
                                        <span style="font-size: 0.72rem; opacity: 0.8;">Descuenta (- Stock)</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="buscadorProductoVisual" class="form-label fw-bold small text-uppercase text-muted mb-1">
                                2. Busca y Selecciona el Producto
                            </label>
                            <div class="visual-picker-wrapper" id="pickerWrapper">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-success"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="buscadorProductoVisual" autocomplete="off"
                                           placeholder="Escribe nombre, código o haz clic para ver fotos...">
                                    <button type="button" class="btn btn-outline-secondary d-none" id="btnLimpiarSeleccion" title="Cambiar producto" onclick="limpiarProductoSeleccionado()">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback d-block d-none" id="errorProductoVisual">
                                    Por favor selecciona un producto de la lista.
                                </div>

                                <div id="listaProductosVisual" class="visual-dropdown d-none"></div>
                            </div>

                            <div id="tarjetaPreviewProducto" class="product-preview-card mt-3 d-none">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width: 0;">
                                        <div id="prevImgContainer"></div>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                <span class="sku-tag mono" id="prevCodigo"></span>
                                                <span class="text-muted small" id="prevCategoria"></span>
                                            </div>
                                            <div class="fw-bold text-dark lh-sm" style="word-break: break-word;" id="prevNombre"></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light border text-muted flex-shrink-0" onclick="limpiarProductoSeleccionado()" title="Cambiar producto">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>

                                <div class="stock-meter">
                                    <div class="d-flex justify-content-between align-items-center small">
                                        <span class="text-muted">Proyección de Inventario:</span>
                                        <span class="mono fw-bold" id="prevSimulacionTexto"></span>
                                    </div>
                                    <div id="prevAlertaStock" class="small mt-1 d-none"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="cantidad" class="form-label fw-bold small text-uppercase text-muted mb-0">3. Cantidad</label>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="quick-qty-btn mono" onclick="sumarCantidad(1)">+1</button>
                                        <button type="button" class="quick-qty-btn mono" onclick="sumarCantidad(5)">+5</button>
                                        <button type="button" class="quick-qty-btn mono" onclick="sumarCantidad(10)">+10</button>
                                    </div>
                                </div>
                                <input type="number" class="form-control mono fw-bold fs-6" id="cantidad" name="cantidad" min="1" step="1" placeholder="0" required>
                                <div class="invalid-feedback" id="feedbackCantidad">Ingrese una cantidad mayor a 0.</div>
                            </div>

                            <div class="col-sm-6">
                                <label for="motivo" class="form-label fw-bold small text-uppercase text-muted mb-1">4. Motivo</label>
                                <select class="form-select" id="motivo" name="motivo" required>
                                    <option value="Compra a Proveedor">Compra a Proveedor</option>
                                    <option value="Devolución de cliente">Devolución de cliente</option>
                                    <option value="Ajuste de inventario (+)">Ajuste de inventario (+)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3" id="bloqueProveedor">
                            <label for="id_proveedor" class="form-label fw-semibold small text-muted">Proveedor (Opcional)</label>
                            <select class="form-select" id="id_proveedor" name="id_proveedor">
                                <option value="">-- Sin proveedor específico --</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= $prov['id_proveedor'] ?>"><?= htmlspecialchars($prov['razon_social']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="observacion" class="form-label fw-semibold small text-muted">Documento / Nota de respaldo</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-file-earmark-text"></i></span>
                                <input type="text" class="form-control" id="observacion" name="observacion" placeholder="Ej: Factura #4890, Boleta #201...">
                            </div>
                        </div>

                        <button type="button" id="btnSubmitVisual" class="btn btn-emerald w-100 py-2 fw-bold shadow-sm" onclick="validarYConfirmarMovimiento()">
                            <i class="bi bi-box-arrow-in-down me-1"></i> Registrar Entrada
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card table-panel">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0"><i class="bi bi-person-check text-success me-2"></i>Mis Movimientos Registrados</h6>
                        <small class="text-muted">Haz clic en cualquier fila para ver el detalle completo o editar</small>
                    </div>
                    <span class="badge bg-dark mono px-3 py-2"><?= count($misMovimientos) ?> registros</span>
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
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        Aún no has registrado movimientos.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($misMovimientos as $m): ?>
                                    <?php
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
                                            "usuario"       => $nombreUsuario,
                                            "es_mio"        => true,
                                            "editado"       => (int)$m["editado"],
                                            "fecha_edicion" => $m["fecha_edicion"] ? date("d/m/Y H:i", strtotime($m["fecha_edicion"])) : ""
                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="fila-clicable" data-mov="<?= $infoMov ?>" onclick="abrirDetalleDesdeFila(this)">
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
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($m['prod_imagen'])): ?>
                                                    <img src="<?= htmlspecialchars($m['prod_imagen']) ?>" alt="<?= htmlspecialchars($m['prod_nombre']) ?>" class="prod-thumb-sm"
                                                         onerror="this.outerHTML='<div class=\'prod-thumb-sm-placeholder\'><i class=\'bi bi-cpu\'></i></div>';">
                                                <?php else: ?>
                                                    <div class="prod-thumb-sm-placeholder"><i class="bi bi-cpu"></i></div>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 210px;">
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="ts-toast-container">
            <div class="ts-toast">
                <i class="bi bi-check-circle-fill text-success fs-5 mt-0.5"></i>
                <div class="flex-grow-1 small">
                    <strong class="d-block ts-toast-title">Operación completada</strong>
                    <span class="ts-toast-text">
                        <?= $_GET['msg'] === 'editado' ? 'Movimiento modificado y stock recalculado correctamente.' : 'Movimiento registrado y stock actualizado automáticamente.' ?>
                    </span>
                </div>
                <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                <div class="ts-toast-progress"></div>
            </div>
        </div>
    <?php endif; ?>
</main>

<div class="modal fade" id="modalConfirmarMov" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check text-success me-2"></i>Confirmar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small text-center mb-3">Estás a punto de registrar la siguiente operación en inventario:</p>
                <div class="p-3 bg-light rounded-3 border mb-3 d-flex align-items-center gap-3">
                    <div id="confImagenBox" style="width: 72px; height: 72px; flex-shrink: 0;"></div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div id="confTipoBadge" class="mb-1"></div>
                        <h6 class="fw-bold text-dark mb-1 lh-sm" style="word-break: break-word;" id="confProducto"></h6>
                        <div class="fs-5 fw-bold mono text-dark" id="confCantidad"></div>
                        <div class="small text-muted mono" id="confSaldoFinal"></div>
                    </div>
                </div>
                <div class="small text-muted text-center">
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
const catalogoProductos = <?= json_encode($productosCatalogoJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let productoSeleccionadoObj = null;

const radioEntrada         = document.getElementById('radioEntrada');
const radioSalida          = document.getElementById('radioSalida');
const bloqueProveedor      = document.getElementById('bloqueProveedor');
const selectMotivo         = document.getElementById('motivo');
const btnSubmitVisual      = document.getElementById('btnSubmitVisual');
const inputHiddenProd      = document.getElementById('id_producto');
const inputBuscador        = document.getElementById('buscadorProductoVisual');
const listaDropdown        = document.getElementById('listaProductosVisual');
const btnLimpiarSeleccion  = document.getElementById('btnLimpiarSeleccion');
const errorProductoVisual  = document.getElementById('errorProductoVisual');
const cantidadInput        = document.getElementById('cantidad');

const motivosEntrada = ['Compra a Proveedor', 'Devolución de cliente', 'Ajuste de inventario (+)'];
const motivosSalida  = ['Venta en tienda', 'Despacho pedido', 'Merma o daño', 'Ajuste de inventario (-)'];

function obtenerTipoSeleccionado() {
    return radioSalida.checked ? 2 : 1;
}

function actualizarInterfazPorTipo() {
    const tipo = obtenerTipoSeleccionado();
    const lista = (tipo === 1) ? motivosEntrada : motivosSalida;

    selectMotivo.innerHTML = '';
    lista.forEach(m => selectMotivo.add(new Option(m, m)));

    if (tipo === 2) {
        bloqueProveedor.style.display = 'none';
        document.getElementById('id_proveedor').value = '';
        btnSubmitVisual.className = 'btn btn-danger w-100 py-2 fw-bold shadow-sm';
        btnSubmitVisual.innerHTML = '<i class="bi bi-box-arrow-up me-1"></i> Registrar Salida';
    } else {
        bloqueProveedor.style.display = 'block';
        btnSubmitVisual.className = 'btn btn-emerald w-100 py-2 fw-bold shadow-sm';
        btnSubmitVisual.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Registrar Entrada';
    }
    actualizarPreviewProducto();
}

function renderizarListaProductos(filtroTexto = '') {
    const q = filtroTexto.trim().toLowerCase();
    const filtrados = catalogoProductos.filter(p =>
        !q ||
        p.codigo.toLowerCase().includes(q) ||
        p.nombre.toLowerCase().includes(q) ||
        p.categoria.toLowerCase().includes(q)
    );

    listaDropdown.innerHTML = '';

    if (filtrados.length === 0) {
        listaDropdown.innerHTML = `
            <div class="p-4 text-center text-muted small">
                <i class="bi bi-search fs-4 d-block mb-1 opacity-50"></i>
                No se encontraron productos para "<strong>${filtroTexto}</strong>"
            </div>`;
        listaDropdown.classList.remove('d-none');
        return;
    }

    filtrados.forEach(p => {
        const esCritico = p.stock < p.minimo;
        const badgeStockClass = p.stock === 0
            ? 'bg-danger text-white'
            : (esCritico ? 'bg-warning text-dark' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25');

        const itemDiv = document.createElement('div');
        itemDiv.className = 'visual-item';

        if (p.imagen) {
            const img = document.createElement('img');
            img.src = p.imagen;
            img.alt = p.nombre;
            img.className = 'visual-item-thumb';
            img.onerror = () => {
                img.outerHTML = '<div class="visual-item-placeholder"><i class="bi bi-cpu"></i></div>';
            };
            itemDiv.appendChild(img);
        } else {
            const ph = document.createElement('div');
            ph.className = 'visual-item-placeholder';
            ph.innerHTML = '<i class="bi bi-cpu"></i>';
            itemDiv.appendChild(ph);
        }

        const infoDiv = document.createElement('div');
        infoDiv.className = 'flex-grow-1 min-w-0';
        infoDiv.innerHTML = `
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="sku-tag mono" style="font-size:0.72rem;padding:0.15rem 0.45rem;">${p.codigo}</span>
                <span class="badge ${badgeStockClass} mono" style="font-size:0.72rem;">Stock: ${p.stock}</span>
            </div>
            <div class="fw-bold text-dark small text-truncate mt-1">${p.nombre}</div>
            <div class="text-muted" style="font-size:0.72rem;">${p.categoria}</div>
        `;
        itemDiv.appendChild(infoDiv);

        itemDiv.addEventListener('click', () => seleccionarProductoVisual(p));
        listaDropdown.appendChild(itemDiv);
    });

    listaDropdown.classList.remove('d-none');
}

function seleccionarProductoVisual(prod) {
    productoSeleccionadoObj = prod;
    inputHiddenProd.value = prod.id;
    inputBuscador.value = `[${prod.codigo}] ${prod.nombre}`;
    inputBuscador.classList.remove('is-invalid');
    errorProductoVisual.classList.add('d-none');
    btnLimpiarSeleccion.classList.remove('d-none');
    listaDropdown.classList.add('d-none');

    actualizarPreviewProducto();
    cantidadInput.focus();
}

function limpiarProductoSeleccionado() {
    productoSeleccionadoObj = null;
    inputHiddenProd.value = '';
    inputBuscador.value = '';
    btnLimpiarSeleccion.classList.add('d-none');
    document.getElementById('tarjetaPreviewProducto').classList.add('d-none');
    renderizarListaProductos('');
    inputBuscador.focus();
}

inputBuscador.addEventListener('focus', () => {
    const texto = productoSeleccionadoObj ? '' : inputBuscador.value;
    renderizarListaProductos(texto);
});

inputBuscador.addEventListener('input', function() {
    if (productoSeleccionadoObj) {
        productoSeleccionadoObj = null;
        inputHiddenProd.value = '';
        btnLimpiarSeleccion.classList.add('d-none');
        document.getElementById('tarjetaPreviewProducto').classList.add('d-none');
    }
    renderizarListaProductos(this.value);
});

document.addEventListener('click', function(e) {
    if (!document.getElementById('pickerWrapper').contains(e.target)) {
        listaDropdown.classList.add('d-none');
    }
});

function sumarCantidad(n) {
    const actual = parseInt(cantidadInput.value || '0', 10);
    cantidadInput.value = (isNaN(actual) ? 0 : actual) + n;
    cantidadInput.classList.remove('is-invalid');
    actualizarPreviewProducto();
}

function actualizarPreviewProducto() {
    const card = document.getElementById('tarjetaPreviewProducto');
    if (!productoSeleccionadoObj) {
        card.classList.add('d-none');
        return;
    }

    const p = productoSeleccionadoObj;
    card.classList.remove('d-none');

    document.getElementById('prevCodigo').textContent    = p.codigo;
    document.getElementById('prevCategoria').textContent = p.categoria;
    document.getElementById('prevNombre').textContent    = p.nombre;

    const imgCont = document.getElementById('prevImgContainer');
    imgCont.innerHTML = '';
    if (p.imagen) {
        const img = document.createElement('img');
        img.src = p.imagen;
        img.alt = p.nombre;
        img.className = 'preview-thumb';
        img.onerror = () => {
            imgCont.innerHTML = '<div class="preview-placeholder"><i class="bi bi-cpu"></i></div>';
        };
        imgCont.appendChild(img);
    } else {
        imgCont.innerHTML = '<div class="preview-placeholder"><i class="bi bi-cpu"></i></div>';
    }

    const cant = parseInt(cantidadInput.value || '0', 10) || 0;
    const tipo = obtenerTipoSeleccionado();
    const nuevoStock = (tipo === 1) ? (p.stock + cant) : (p.stock - cant);

    const simText = document.getElementById('prevSimulacionTexto');
    const alerta  = document.getElementById('prevAlertaStock');

    if (cant === 0) {
        simText.innerHTML = `<span class="text-dark">${p.stock} uds. disponibles</span>`;
        alerta.classList.add('d-none');
    } else if (nuevoStock < 0) {
        simText.innerHTML = `<span class="text-muted">${p.stock}</span> → <span class="text-danger">${nuevoStock} uds.</span>`;
        alerta.className = 'small mt-1 text-danger fw-semibold';
        alerta.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i>Excede el stock disponible (máximo ${p.stock} uds.)`;
    } else {
        const colorNuevo = (nuevoStock < p.minimo) ? 'text-warning' : 'text-success';
        simText.innerHTML = `<span class="text-muted">${p.stock}</span> <i class="bi bi-arrow-right mx-1 text-muted"></i> <span class="${colorNuevo}">${nuevoStock} uds.</span>`;
        if (nuevoStock < p.minimo) {
            alerta.className = 'small mt-1 text-warning fw-semibold';
            alerta.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>El saldo quedará bajo el stock mínimo (${p.minimo} uds.)`;
        } else {
            alerta.classList.add('d-none');
        }
    }
}

radioEntrada.addEventListener('change', actualizarInterfazPorTipo);
radioSalida.addEventListener('change', actualizarInterfazPorTipo);
cantidadInput.addEventListener('input', () => {
    cantidadInput.classList.remove('is-invalid');
    actualizarPreviewProducto();
});

actualizarInterfazPorTipo();

const idPreseleccionado = <?= $idProductoPreseleccionado ?>;
if (idPreseleccionado > 0) {
    const prodEncontrado = catalogoProductos.find(p => p.id === idPreseleccionado);
    if (prodEncontrado) {
        seleccionarProductoVisual(prodEncontrado);
    }
}

function validarYConfirmarMovimiento() {
    let esValido = true;
    const cantidad = Number(cantidadInput.value);
    const tipo = obtenerTipoSeleccionado();
    const feedbackCantidad = document.getElementById('feedbackCantidad');

    if (!productoSeleccionadoObj || !inputHiddenProd.value) {
        inputBuscador.classList.add('is-invalid');
        errorProductoVisual.classList.remove('d-none');
        esValido = false;
    }

    if (cantidadInput.value.trim() === '' || isNaN(cantidad) || cantidad <= 0) {
        cantidadInput.classList.add('is-invalid');
        feedbackCantidad.textContent = 'La cantidad debe ser mayor a 0.';
        esValido = false;
    } else if (tipo === 2 && productoSeleccionadoObj && cantidad > productoSeleccionadoObj.stock) {
        cantidadInput.classList.add('is-invalid');
        feedbackCantidad.textContent = `Stock insuficiente. Disponible: ${productoSeleccionadoObj.stock}.`;
        esValido = false;
    }

    if (!esValido) return;

    const esEntrada = (tipo === 1);
    const saldoFinal = esEntrada ? (productoSeleccionadoObj.stock + cantidad) : (productoSeleccionadoObj.stock - cantidad);

    const confImgBox = document.getElementById('confImagenBox');
    confImgBox.innerHTML = '';
    if (productoSeleccionadoObj.imagen) {
        const img = document.createElement('img');
        img.src = productoSeleccionadoObj.imagen;
        img.alt = productoSeleccionadoObj.nombre;
        img.style.cssText = 'width:72px;height:72px;object-fit:contain;background:#ffffff;padding:4px;border-radius:10px;border:1px solid #cbd5e1;';
        img.onerror = () => {
            confImgBox.innerHTML = '<div style="width:72px;height:72px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
        };
        confImgBox.appendChild(img);
    } else {
        confImgBox.innerHTML = '<div style="width:72px;height:72px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
    }

    document.getElementById('confTipoBadge').innerHTML = esEntrada
        ? '<span class="badge bg-success px-3 py-1">ENTRADA (+ Stock)</span>'
        : '<span class="badge bg-danger px-3 py-1">SALIDA (- Stock)</span>';
    document.getElementById('confProducto').textContent = `[${productoSeleccionadoObj.codigo}] ${productoSeleccionadoObj.nombre}`;
    document.getElementById('confCantidad').textContent = (esEntrada ? '+' : '-') + cantidad + ' unidades';
    document.getElementById('confSaldoFinal').textContent = `Nuevo stock en bodega: ${saldoFinal} uds.`;
    document.getElementById('confMotivo').textContent = selectMotivo.value;

    new bootstrap.Modal(document.getElementById('modalConfirmarMov')).show();
}

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
        btnEditar.href = 'movimiento_editar.php?id=' + info.id;
    } else {
        btnEditar.classList.add('d-none');
    }

    new bootstrap.Modal(document.getElementById('modalDetalleMov')).show();
}
</script>
</body>
</html>