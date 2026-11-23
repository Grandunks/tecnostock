<?php
// movimiento_editar.php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario = (int)$_SESSION['id_usuario'];
$idMov     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmtMov = $pdo->prepare("SELECT * FROM movimiento WHERE id_movimiento = :id AND id_usuario = :u LIMIT 1");
$stmtMov->execute(['id' => $idMov, 'u' => $idUsuario]);
$mov = $stmtMov->fetch();

if (!$mov) {
    header('Location: movimientos.php?error=no_autorizado');
    exit;
}

$tipo = (int)$mov['tipo'];
$errores = [];

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
            header('Location: movimientos.php?msg=editado');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }
}

$productosActivos = $pdo->query("SELECT id_producto, codigo, nombre, stock_actual FROM producto WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$proveedores      = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Movimiento #<?= $idMov ?> | TecnoStock</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f4f7f6; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; }
        .btn-emerald:hover { background-color: #059669; color: #fff; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 680px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">
            <i class="bi bi-pencil-square text-success me-2"></i>Editar Movimiento #<?= $idMov ?>
            <span class="badge <?= $tipo === 1 ? 'bg-success' : 'bg-danger' ?> fs-6 ms-2"><?= $tipo === 1 ? 'ENTRADA' : 'SALIDA' ?></span>
        </h3>
        <a href="movimientos.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Producto</label>
                    <select name="id_producto" class="form-select" required>
                        <?php foreach ($productosActivos as $prod): ?>
                            <option value="<?= $prod['id_producto'] ?>" <?= ($prod['id_producto'] == $mov['id_producto']) ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($prod['codigo']) ?>] <?= htmlspecialchars($prod['nombre']) ?> (Stock actual: <?= $prod['stock_actual'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Cantidad</label>
                        <input type="number" name="cantidad" class="form-control" min="1" value="<?= (int)$mov['cantidad'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Motivo</label>
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
                    <input type="text" name="observacion" class="form-control" value="<?= htmlspecialchars($mov['observacion'] ?? '') ?>">
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="movimientos.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-emerald px-4">Guardar Cambios y Recalcular Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>