<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario     = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];
$idMov         = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$origen        = $_GET['origen'] ?? $_POST['origen'] ?? 'movimientos';

if ($rolUsuario === 1) {
    $stmtMov = $pdo->prepare("SELECT * FROM movimiento WHERE id_movimiento = :id LIMIT 1");
    $stmtMov->execute(['id' => $idMov]);
} else {
    $stmtMov = $pdo->prepare("SELECT * FROM movimiento WHERE id_movimiento = :id AND id_usuario = :u LIMIT 1");
    $stmtMov->execute(['id' => $idMov, 'u' => $idUsuario]);
}
$mov = $stmtMov->fetch();

if (!$mov) {
    header('Location: movimientos.php?error=no_autorizado');
    exit;
}

$tipo = (int)$mov['tipo'];
$errores = [];
$urlVolver = ($origen === 'global') ? 'historial_movimientos.php' : 'movimientos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto_nuevo = (int)($_POST['id_producto'] ?? 0);
    $cantidad_nueva    = (int)($_POST['cantidad'] ?? 0);
    $motivo            = trim($_POST['motivo'] ?? '');
    $id_proveedor      = ($tipo === 1 && !empty($_POST['id_proveedor'])) ? (int)$_POST['id_proveedor'] : null;
    $observacion       = trim($_POST['observacion'] ?? '');

    if ($id_producto_nuevo <= 0) $errores[] = 'Debe seleccionar un producto válido.';
    if ($cantidad_nueva <= 0) $errores[] = 'La cantidad debe ser mayor a 0.';
    if (empty($motivo)) $errores[] = 'El motivo es obligatorio.';

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            $stmtLock = $pdo->prepare("SELECT nombre, stock_actual FROM producto WHERE id_producto = :id FOR UPDATE");
            $stmtUpd  = $pdo->prepare("UPDATE producto SET stock_actual = :st WHERE id_producto = :id");

            $stmtLock->execute(['id' => $mov['id_producto']]);
            $prodAnt = $stmtLock->fetch();
            $stockRevertido = ($tipo === 1) 
                ? ($prodAnt['stock_actual'] - (int)$mov['cantidad']) 
                : ($prodAnt['stock_actual'] + (int)$mov['cantidad']);

            if ($stockRevertido < 0) {
                throw new Exception("No se puede revertir '{$prodAnt['nombre']}' porque parte de ese stock ya fue descontado en salidas posteriores.");
            }
            $stmtUpd->execute(['st' => $stockRevertido, 'id' => $mov['id_producto']]);

            $stmtLock->execute(['id' => $id_producto_nuevo]);
            $prodNuevo = $stmtLock->fetch();

            if ($tipo === 2 && ($prodNuevo['stock_actual'] - $cantidad_nueva) < 0) {
                throw new Exception("Stock insuficiente para '{$prodNuevo['nombre']}'. Disponible: {$prodNuevo['stock_actual']}, solicitado: {$cantidad_nueva}.");
            }

            $stockFinal = ($tipo === 1) 
                ? ($prodNuevo['stock_actual'] + $cantidad_nueva) 
                : ($prodNuevo['stock_actual'] - $cantidad_nueva);
            $stmtUpd->execute(['st' => $stockFinal, 'id' => $id_producto_nuevo]);

            $sqlUpdMov = "UPDATE movimiento 
                          SET id_producto = :p, cantidad = :c, motivo = :m, observacion = :o, 
                              id_proveedor = :pr, editado = 1, fecha_edicion = NOW() 
                          WHERE id_movimiento = :id";
            $pdo->prepare($sqlUpdMov)->execute([
                'p'  => $id_producto_nuevo,
                'c'  => $cantidad_nueva,
                'm'  => $motivo,
                'o'  => $observacion ?: null,
                'pr' => $id_proveedor,
                'id' => $idMov
            ]);

            $pdo->commit();
            header("Location: {$urlVolver}?msg=editado");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }
}

$productosActivos = $pdo->query("
    SELECT p.id_producto, p.codigo, p.nombre, p.imagen, p.stock_actual, c.nombre AS categoria_nombre
    FROM producto p
    INNER JOIN categoria c ON p.id_categoria = c.id_categoria
    WHERE p.estado = 1
    ORDER BY p.nombre ASC
")->fetchAll();
$proveedores = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Movimiento #<?= $idMov ?> | TecnoStock</title>
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
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: #1f2937; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .top-header { background-color: var(--surface-dark); border-bottom: 3px solid var(--emerald-primary); padding: 0.85rem 0; }
        .brand-logo { font-weight: 700; font-size: 1.35rem; color: #ffffff; text-decoration: none; letter-spacing: -0.03em; }
        .brand-dot { color: var(--emerald-primary); }
        .nav-pill-custom { color: #9ca3af; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0.9rem; border-radius: 8px; transition: all 0.2s ease; }
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-pill-custom.active { color: #111827; background-color: #10b981; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; }
        .btn-emerald:hover { background-color: #059669; color: #fff; }
        .panel-card { background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(0,0,0,0.03); overflow: hidden; }
        .sku-tag { background-color: #111827; color: #34d399; padding: 0.24rem 0.55rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; display: inline-block; white-space: nowrap; }
        .preview-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
        .preview-img { width: 68px; height: 68px; border-radius: 10px; object-fit: contain; background: #fff; padding: 4px; border: 1px solid #cbd5e1; flex-shrink: 0; }
        .form-control:focus, .form-select:focus { border-color: var(--emerald-primary); box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.18); }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container py-4" style="max-width: 820px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="bi bi-pencil-square text-success me-2"></i>Editar Movimiento <span class="mono text-success">#<?= $idMov ?></span>
            </h3>
            <span class="text-muted small">Al guardar, el sistema revertirá automáticamente el stock anterior y aplicará la nueva cantidad</span>
        </div>
        <a href="<?= $urlVolver ?>" class="btn btn-outline-dark btn-sm fw-semibold px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="panel-card">
        <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center border-bottom border-success border-3">
            <span class="fw-bold small"><i class="bi bi-sliders text-success me-2"></i>CORRECCIÓN DE REGISTRO</span>
            <span class="badge <?= $tipo === 1 ? 'bg-success' : 'bg-danger' ?> mono">
                <?= $tipo === 1 ? 'ENTRADA (+ STOCK)' : 'SALIDA (- STOCK)' ?>
            </span>
        </div>

        <div class="p-4">
            <!-- Vista previa del producto seleccionado -->
            <div class="preview-box product-preview-card mb-4 d-flex align-items-center gap-3">
                <div id="editImgBox"></div>
                <div class="flex-grow-1" style="min-width: 0;">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="sku-tag mono" id="editProdCodigo"></span>
                        <span class="text-muted small" id="editProdCat"></span>
                    </div>
                    <h6 class="fw-bold text-dark mb-1" id="editProdNombre"></h6>
                    <div class="small mono text-muted" id="editProdStock"></div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="origen" value="<?= htmlspecialchars($origen) ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Producto Asociado</label>
                    <select name="id_producto" id="selectProductoEdit" class="form-select" required>
                        <?php foreach ($productosActivos as $prod): ?>
                            <option value="<?= $prod['id_producto'] ?>"
                                    data-codigo="<?= htmlspecialchars($prod['codigo']) ?>"
                                    data-nombre="<?= htmlspecialchars($prod['nombre']) ?>"
                                    data-cat="<?= htmlspecialchars($prod['categoria_nombre']) ?>"
                                    data-imagen="<?= htmlspecialchars($prod['imagen'] ?? '') ?>"
                                    data-stock="<?= (int)$prod['stock_actual'] ?>"
                                    <?= ($prod['id_producto'] == $mov['id_producto']) ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($prod['codigo']) ?>] <?= htmlspecialchars($prod['nombre']) ?> (Stock actual: <?= $prod['stock_actual'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Cantidad</label>
                        <input type="number" name="cantidad" class="form-control mono fw-bold" min="1" value="<?= (int)$mov['cantidad'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Motivo de la Operación</label>
                        <input type="text" name="motivo" class="form-control" value="<?= htmlspecialchars($mov['motivo']) ?>" required>
                    </div>
                </div>

                <?php if ($tipo === 1): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Proveedor</label>
                        <select name="id_proveedor" class="form-select">
                            <option value="">-- Ninguno --</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id_proveedor'] ?>" <?= ($p['id_proveedor'] == $mov['id_proveedor']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['razon_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="form-label fw-semibold small">Observación / Documento</label>
                    <input type="text" name="observacion" class="form-control" value="<?= htmlspecialchars($mov['observacion'] ?? '') ?>" placeholder="Ej: Factura, boleta o nota aclaratoria...">
                </div>

                <div class="pt-3 border-top d-flex justify-content-end gap-2">
                    <a href="<?= $urlVolver ?>" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-emerald px-4 fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Cambios y Recalcular Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const selProdEdit = document.getElementById('selectProductoEdit');

function actualizarTarjetaProductoEdit() {
    const opt = selProdEdit.options[selProdEdit.selectedIndex];
    if (!opt) return;

    document.getElementById('editProdCodigo').textContent = opt.getAttribute('data-codigo') || '';
    document.getElementById('editProdCat').textContent    = opt.getAttribute('data-cat') || '';
    document.getElementById('editProdNombre').textContent = opt.getAttribute('data-nombre') || '';
    document.getElementById('editProdStock').textContent  = 'Stock actual en bodega: ' + (opt.getAttribute('data-stock') || '0') + ' uds.';

    const imgUrl = opt.getAttribute('data-imagen') || '';
    const box = document.getElementById('editImgBox');
    box.innerHTML = '';

    if (imgUrl) {
        const img = document.createElement('img');
        img.src = imgUrl;
        img.className = 'preview-img';
        img.onerror = () => {
            box.innerHTML = '<div style="width:68px;height:68px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
        };
        box.appendChild(img);
    } else {
        box.innerHTML = '<div style="width:68px;height:68px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="bi bi-cpu fs-3"></i></div>';
    }
}

selProdEdit.addEventListener('change', actualizarTarjetaProductoEdit);
actualizarTarjetaProductoEdit();
</script>
</body>
</html>