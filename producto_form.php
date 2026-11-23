<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

$errores = [];
$id_producto = isset($_GET['id']) ? (int)$_GET['id'] : null;
$esEdicion = !empty($id_producto);

$codigo       = '';
$nombre       = '';
$id_categoria = '';
$id_proveedor = '';
$precio       = '';
$stock_actual = '';
$stock_minimo = '';
$imagen       = '';
$descripcion  = '';

$categorias  = $pdo->query("SELECT id_categoria, nombre FROM categoria WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
$proveedores = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado = 1 ORDER BY razon_social ASC")->fetchAll();

if ($esEdicion) {
    $stmtProd = $pdo->prepare("SELECT * FROM producto WHERE id_producto = :id LIMIT 1");
    $stmtProd->execute(['id' => $id_producto]);
    $producto = $stmtProd->fetch();

    if (!$producto) {
        header('Location: index.php?error=producto_no_encontrado');
        exit;
    }

    $codigo       = $producto['codigo'];
    $nombre       = $producto['nombre'];
    $id_categoria = $producto['id_categoria'];
    $id_proveedor = $producto['id_proveedor'] ?? '';
    $precio       = $producto['precio'];
    $stock_actual = $producto['stock_actual'];
    $stock_minimo = $producto['stock_minimo'];
    $imagen       = $producto['imagen'] ?? '';
    $descripcion  = $producto['descripcion'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo       = strtoupper(trim($_POST['codigo'] ?? ''));
    $nombre       = trim($_POST['nombre'] ?? '');
    $id_categoria = (int)($_POST['id_categoria'] ?? 0);
    $id_proveedor = !empty($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : null;
    $precio       = trim($_POST['precio'] ?? '');
    $stock_actual = trim($_POST['stock_actual'] ?? '');
    $stock_minimo = trim($_POST['stock_minimo'] ?? '');
    $imagenUrl    = trim($_POST['imagen_url'] ?? '');
    $imagenActual = trim($_POST['imagen_actual'] ?? '');
    $descripcion  = trim($_POST['descripcion'] ?? '');

    if (empty($codigo))       $errores[] = 'El código del producto es obligatorio.';
    if (empty($nombre))       $errores[] = 'El nombre del producto es obligatorio.';
    if ($id_categoria <= 0)   $errores[] = 'Debe seleccionar una categoría válida.';

    if ($precio === '' || !is_numeric($precio) || (int)$precio < 0) {
        $errores[] = 'El precio debe ser un número entero mayor o igual a 0.';
    }
    if ($stock_actual === '' || !is_numeric($stock_actual) || (int)$stock_actual < 0) {
        $errores[] = 'El stock actual debe ser un número entero mayor o igual a 0.';
    }
    if ($stock_minimo === '' || !is_numeric($stock_minimo) || (int)$stock_minimo < 0) {
        $errores[] = 'El stock mínimo debe ser un número entero mayor o igual a 0.';
    }

    $imagenFinal = !empty($imagenUrl) ? $imagenUrl : $imagenActual;

    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $archivoTmp = $_FILES['imagen_archivo']['tmp_name'];
        $nombreOrig = $_FILES['imagen_archivo']['name'];
        $ext        = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

        if (!in_array($ext, $permitidas)) {
            $errores[] = 'Formato de imagen no válido. Use JPG, PNG, WEBP, AVIF o GIF.';
        } else {
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            $nuevoNombre = 'prod_' . uniqid() . '.' . $ext;
            $rutaDestino = 'uploads/' . $nuevoNombre;

            if (move_uploaded_file($archivoTmp, $rutaDestino)) {
                $imagenFinal = $rutaDestino;
            } else {
                $errores[] = 'No se pudo guardar el archivo de imagen en la carpeta uploads/.';
            }
        }
    }

    if (empty($errores)) {
        if ($esEdicion) {
            $stmtCheck = $pdo->prepare("SELECT id_producto FROM producto WHERE codigo = :codigo AND id_producto != :id LIMIT 1");
            $stmtCheck->execute(['codigo' => $codigo, 'id' => $id_producto]);
        } else {
            $stmtCheck = $pdo->prepare("SELECT id_producto FROM producto WHERE codigo = :codigo LIMIT 1");
            $stmtCheck->execute(['codigo' => $codigo]);
        }

        if ($stmtCheck->fetch()) {
            $errores[] = "El código '$codigo' ya está registrado en otro producto.";
        }
    }

    if (empty($errores)) {
        try {
            if ($esEdicion) {
                $sql = "UPDATE producto 
                        SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion, imagen = :imagen,
                            precio = :precio, stock_actual = :stock_actual, stock_minimo = :stock_minimo, 
                            id_categoria = :id_categoria, id_proveedor = :id_proveedor
                        WHERE id_producto = :id";
                $pdo->prepare($sql)->execute([
                    'codigo'       => $codigo,
                    'nombre'       => $nombre,
                    'descripcion'  => $descripcion ?: null,
                    'imagen'       => $imagenFinal ?: null,
                    'precio'       => (int)$precio,
                    'stock_actual' => (int)$stock_actual,
                    'stock_minimo' => (int)$stock_minimo,
                    'id_categoria' => $id_categoria,
                    'id_proveedor' => $id_proveedor,
                    'id'           => $id_producto,
                ]);
                header('Location: index.php?msg=editado');
                exit;
            } else {
                $pdo->beginTransaction();

                $sql = "INSERT INTO producto (codigo, nombre, descripcion, imagen, precio, stock_actual, stock_minimo, estado, id_categoria, id_proveedor) 
                        VALUES (:codigo, :nombre, :descripcion, :imagen, :precio, :stock_actual, :stock_minimo, 1, :id_categoria, :id_proveedor)";
                $pdo->prepare($sql)->execute([
                    'codigo'       => $codigo,
                    'nombre'       => $nombre,
                    'descripcion'  => $descripcion ?: null,
                    'imagen'       => $imagenFinal ?: null,
                    'precio'       => (int)$precio,
                    'stock_actual' => (int)$stock_actual,
                    'stock_minimo' => (int)$stock_minimo,
                    'id_categoria' => $id_categoria,
                    'id_proveedor' => $id_proveedor,
                ]);

                $idProductoNuevo = (int)$pdo->lastInsertId();
                $stockInicialInt = (int)$stock_actual;

                if ($stockInicialInt > 0) {
                    $idUsuarioSesion = (int)$_SESSION['id_usuario'];
                    $sqlMovInicial = "INSERT INTO movimiento (fecha_hora, tipo, motivo, cantidad, observacion, id_producto, id_usuario, id_proveedor)
                                      VALUES (NOW(), 1, 'Stock inicial de catálogo', :cant, 'Alta inicial desde formulario de producto', :id_prod, :id_usu, :id_prov)";
                    $pdo->prepare($sqlMovInicial)->execute([
                        'cant'    => $stockInicialInt,
                        'id_prod' => $idProductoNuevo,
                        'id_usu'  => $idUsuarioSesion,
                        'id_prov' => $id_proveedor
                    ]);
                }

                $pdo->commit();
                header('Location: index.php?msg=creado');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errores[] = 'Error al guardar en la base de datos: ' . $e->getMessage();
        }
    }
    $imagen = $imagenFinal;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esEdicion ? 'Editar Producto' : 'Registrar Producto' ?> | TecnoStock</title>
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
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255, 255, 255, 0.05); }
        .nav-pill-custom.active { color: #111827; background-color: var(--emerald-primary); }
        .btn-emerald { background-color: var(--emerald-primary); color: #fff; font-weight: 600; border: none; transition: all 0.2s; }
        .btn-emerald:hover { background-color: var(--emerald-dark); color: #fff; }
        .panel-card { background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(0,0,0,0.03); overflow: hidden; }
        .section-divider { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.4rem; margin-bottom: 1rem; }
        .form-control:focus, .form-select:focus { border-color: var(--emerald-primary); box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.18); }

        .live-preview-card {
            background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb;
            box-shadow: 0 10px 25px -5px rgba(17, 24, 39, 0.07); overflow: hidden;
            position: sticky; top: 88px;
        }
        .live-img-stage {
            width: 100%; height: 230px; background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 14px; position: relative;
        }
        .live-img-stage img {
            max-width: 100%; max-height: 100%; width: auto; height: auto;
            object-fit: contain; border-radius: 8px;
        }
        .sku-tag {
            background-color: #111827; color: #34d399; padding: 0.25rem 0.6rem;
            border-radius: 6px; font-size: 0.8rem; font-weight: 700; display: inline-block; white-space: nowrap;
        }
        .cat-pill {
            background-color: #eef2ff; color: #4f46e5; font-weight: 600;
            font-size: 0.76rem; padding: 0.28rem 0.65rem; border-radius: 6px; display: inline-block;
        }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container-fluid px-4 py-4" style="max-width: 1320px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="bi <?= $esEdicion ? 'bi-pencil-square' : 'bi-plus-square-fill' ?> text-success me-2"></i>
                <?= $esEdicion ? 'Modificar Producto del Catálogo' : 'Registrar Nuevo Producto' ?>
            </h3>
            <span class="text-muted small">Completa la ficha técnica y verifica en tiempo real cómo lucirá en el inventario</span>
        </div>
        <a href="index.php" class="btn btn-outline-dark btn-sm fw-semibold px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al Catálogo
        </a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrija los siguientes errores:</h6>
            <ul class="mb-0 small ps-3"><?php foreach ($errores as $err) echo "<li>" . htmlspecialchars($err) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center border-bottom border-success border-3">
                    <span class="fw-bold small"><i class="bi bi-sliders text-success me-2"></i>DATOS DEL ARTÍCULO</span>
                    <span class="badge bg-success bg-opacity-25 text-success mono" style="font-size: 0.7rem;">CAMPOS OBLIGATORIOS (*)</span>
                </div>

                <form method="POST" enctype="multipart/form-data" id="formProducto" class="p-4" novalidate>
                    <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($imagen) ?>">

                    <div class="section-divider"><i class="bi bi-upc-scan text-success me-1"></i> 1. Identificación y Clasificación</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="id_categoria" class="form-label fw-semibold small">Categoría <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="">Seleccione categoría...</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat['id_categoria'] ?>" <?= ($id_categoria == $cat['id_categoria']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-success" title="Crear nueva categoría" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="codigo" class="form-label fw-semibold small">
                                Código SKU <span class="text-danger">*</span>
                                <span class="text-success fw-normal" style="font-size: 0.72rem;">(Autogenerado)</span>
                            </label>
                            <input type="text" class="form-control mono fw-bold" id="codigo" name="codigo" 
                                   value="<?= htmlspecialchars($codigo) ?>" placeholder="Ej: TEC-003" required>
                        </div>

                        <div class="col-12">
                            <label for="nombre" class="form-label fw-semibold small">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej: Audífonos Gamer Inalámbricos G733 RGB" required>
                        </div>

                        <div class="col-12">
                            <label for="id_proveedor" class="form-label fw-semibold small">Proveedor Habitual (Opcional)</label>
                            <div class="input-group">
                                <select class="form-select" id="id_proveedor" name="id_proveedor">
                                    <option value="">-- Sin proveedor específico --</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= $prov['id_proveedor'] ?>" <?= ($id_proveedor == $prov['id_proveedor']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($prov['razon_social']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-success" title="Crear nuevo proveedor" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"><i class="bi bi-bar-chart-steps text-success me-1"></i> 2. Precio y Parámetros de Stock</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="precio" class="form-label fw-semibold small">Precio Unitario ($) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">$</span>
                                <input type="number" class="form-control mono fw-bold" id="precio" name="precio" min="0" step="1"
                                       value="<?= htmlspecialchars($precio) ?>" placeholder="0" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="stock_actual" class="form-label fw-semibold small">Stock Actual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control mono fw-bold" id="stock_actual" name="stock_actual" min="0" step="1"
                                   value="<?= htmlspecialchars($stock_actual) ?>" placeholder="0" required>
                        </div>

                        <div class="col-md-4">
                            <label for="stock_minimo" class="form-label fw-semibold small">
                                Stock Crítico <span class="text-danger">*</span>
                                <span class="text-muted fw-normal" style="font-size: 0.72rem;">(Sug. 20%)</span>
                            </label>
                            <input type="number" class="form-control mono fw-bold" id="stock_minimo" name="stock_minimo" min="0" step="1"
                                   value="<?= htmlspecialchars($stock_minimo) ?>" placeholder="0" required>
                        </div>
                    </div>

                    <div class="section-divider"><i class="bi bi-image text-success me-1"></i> 3. Fotografía y Especificaciones</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="imagen_archivo" class="form-label fw-semibold small text-success">
                                <i class="bi bi-upload me-1"></i>Subir desde tu dispositivo
                            </label>
                            <input type="file" class="form-control form-control-sm" id="imagen_archivo" name="imagen_archivo" accept="image/*">
                        </div>

                        <div class="col-md-6">
                            <label for="imagen_url" class="form-label fw-semibold small text-muted">
                                <i class="bi bi-link-45deg me-1"></i>O pegar enlace URL de imagen
                            </label>
                            <input type="url" class="form-control form-control-sm" id="imagen_url" name="imagen_url" 
                                   value="<?= (strpos($imagen, 'http') === 0) ? htmlspecialchars($imagen) : '' ?>" 
                                   placeholder="https://ejemplo.com/producto.png">
                        </div>

                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-semibold small">Descripción Técnica</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2" 
                                      placeholder="Especificaciones, conectividad, compatibilidad o características clave..."><?= htmlspecialchars($descripcion) ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="live-preview-card">
                <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center border-bottom border-success border-3">
                    <span class="fw-bold small"><i class="bi bi-eye-fill text-success me-2"></i>VISTA PREVIA EN CATÁLOGO</span>
                    <span class="badge bg-success bg-opacity-25 text-success" style="font-size: 0.68rem;">TIEMPO REAL</span>
                </div>

                <div class="live-img-stage">
                    <?php if (!empty($imagen)): ?>
                        <img src="<?= htmlspecialchars($imagen) ?>" id="livePreviewImg" alt="Vista previa">
                        <div id="livePreviewPlaceholder" class="text-center text-muted d-none">
                            <i class="bi bi-image fs-1 d-block mb-1 opacity-50"></i>
                            <span class="small">La imagen completa aparecerá aquí</span>
                        </div>
                    <?php else: ?>
                        <img src="" id="livePreviewImg" class="d-none" alt="Vista previa">
                        <div id="livePreviewPlaceholder" class="text-center text-muted">
                            <i class="bi bi-image fs-1 d-block mb-1 opacity-50"></i>
                            <span class="small">La imagen completa aparecerá aquí</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <span class="sku-tag mono" id="liveCodigo"><?= htmlspecialchars($codigo ?: 'SKU-000') ?></span>
                        <span class="cat-pill" id="liveCategoria">Sin categoría</span>
                    </div>

                    <h5 class="fw-bold text-dark mb-1" id="liveNombre">
                        <?= htmlspecialchars($nombre ?: 'Nombre del producto...') ?>
                    </h5>
                    <p class="text-muted small mb-3" id="liveDescripcion" style="min-height: 38px;">
                        <?= htmlspecialchars($descripcion ?: 'Aquí se mostrará la descripción técnica del producto en el catálogo.') ?>
                    </p>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="row text-center g-2">
                            <div class="col-4 border-end">
                                <span class="text-muted d-block" style="font-size: 0.7rem;">PRECIO UNIT.</span>
                                <span class="fw-bold mono fs-6 text-dark" id="livePrecio">
                                    $<?= $precio !== '' ? number_format((int)$precio, 0, ',', '.') : '0' ?>
                                </span>
                            </div>
                            <div class="col-4 border-end">
                                <span class="text-muted d-block" style="font-size: 0.7rem;">STOCK INICIAL</span>
                                <span class="fw-bold mono fs-6 text-success" id="liveStock">
                                    <?= $stock_actual !== '' ? (int)$stock_actual : '0' ?> uds.
                                </span>
                            </div>
                            <div class="col-4">
                                <span class="text-muted d-block" style="font-size: 0.7rem;">ALERTA MÍN.</span>
                                <span class="fw-bold mono fs-6 text-secondary" id="liveMinimo">
                                    <?= $stock_minimo !== '' ? (int)$stock_minimo : '0' ?> uds.
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between small text-muted">
                        <span><i class="bi bi-building text-success me-1"></i><span id="liveProveedor">Sin proveedor</span></span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">● Operativo</span>
                    </div>
                </div>

                <div class="px-4 py-3 bg-light border-top d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 fw-semibold">
                        Cancelar
                    </a>
                    <button type="submit" form="formProducto" class="btn btn-emerald px-4 fw-semibold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> <?= $esEdicion ? 'Confirmar Cambios' : 'Confirmar y Guardar' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalNuevaCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-tag-fill text-success me-2"></i>Nueva Categoría</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="errCat" class="alert alert-danger small d-none"></div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Nombre de Categoría *</label>
                    <input type="text" id="nuevaCatNombre" class="form-control" placeholder="Ej: Audífonos y Audio">
                </div>
                <div>
                    <label class="form-label fw-semibold small">Descripción (Opcional)</label>
                    <input type="text" id="nuevaCatDesc" class="form-control" placeholder="Breve detalle...">
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-emerald btn-sm px-4" onclick="guardarCategoria()">Guardar Categoría</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-truck text-success me-2"></i>Nuevo Proveedor</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="errProv" class="alert alert-danger small d-none"></div>
                <div class="row g-2 mb-2">
                    <div class="col-5">
                        <label class="form-label fw-semibold small">RUT *</label>
                        <input type="text" id="nuevoProvRut" class="form-control form-control-sm" placeholder="76.123.456-7">
                    </div>
                    <div class="col-7">
                        <label class="form-label fw-semibold small">Razón Social *</label>
                        <input type="text" id="nuevoProvRazon" class="form-control form-control-sm" placeholder="Importadora Tech SpA">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Contacto</label>
                        <input type="text" id="nuevoProvContacto" class="form-control form-control-sm" placeholder="Nombre ejecutivo">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Teléfono</label>
                        <input type="text" id="nuevoProvTel" class="form-control form-control-sm" placeholder="+569...">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-emerald btn-sm px-4" onclick="guardarProveedor()">Guardar Proveedor</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const esEdicion = <?= $esEdicion ? 'true' : 'false' ?>;

const inCategoria   = document.getElementById('id_categoria');
const inCodigo      = document.getElementById('codigo');
const inNombre      = document.getElementById('nombre');
const inProveedor   = document.getElementById('id_proveedor');
const inPrecio      = document.getElementById('precio');
const inStockActual = document.getElementById('stock_actual');
const inStockMinimo = document.getElementById('stock_minimo');
const inDescripcion = document.getElementById('descripcion');
const inArchivo     = document.getElementById('imagen_archivo');
const inUrl         = document.getElementById('imagen_url');

function actualizarVistaPreviaFicha() {
    document.getElementById('liveCodigo').textContent = inCodigo.value.trim().toUpperCase() || 'SKU-000';
    document.getElementById('liveNombre').textContent = inNombre.value.trim() || 'Nombre del producto...';
    document.getElementById('liveDescripcion').textContent = inDescripcion.value.trim() || 'Aquí se mostrará la descripción técnica del producto en el catálogo.';

    const catText = inCategoria.value ? inCategoria.options[inCategoria.selectedIndex].text : 'Sin categoría';
    document.getElementById('liveCategoria').textContent = catText;

    const provText = inProveedor.value ? inProveedor.options[inProveedor.selectedIndex].text : 'Sin proveedor';
    document.getElementById('liveProveedor').textContent = provText;

    const pNum = parseInt(inPrecio.value || '0', 10) || 0;
    document.getElementById('livePrecio').textContent = '$' + pNum.toLocaleString('es-CL');

    const sAct = parseInt(inStockActual.value || '0', 10) || 0;
    const sMin = parseInt(inStockMinimo.value || '0', 10) || 0;

    const liveStockEl = document.getElementById('liveStock');
    liveStockEl.textContent = sAct + ' uds.';
    liveStockEl.className = 'fw-bold mono fs-6 ' + (sAct < sMin ? 'text-danger' : 'text-success');
    document.getElementById('liveMinimo').textContent = sMin + ' uds.';
}

function mostrarImagenPrevia(src) {
    const img = document.getElementById('livePreviewImg');
    const ph  = document.getElementById('livePreviewPlaceholder');
    if (src) {
        img.src = src;
        img.classList.remove('d-none');
        ph.classList.add('d-none');
        img.onerror = () => {
            img.classList.add('d-none');
            ph.classList.remove('d-none');
        };
    } else {
        img.classList.add('d-none');
        ph.classList.remove('d-none');
    }
}

inCategoria.addEventListener('change', function() {
    actualizarVistaPreviaFicha();
    const idCat = this.value;
    if (!idCat || esEdicion) return;

    fetch('api_maestros.php?accion=siguiente_codigo&id_categoria=' + idCat)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                inCodigo.value = data.codigo;
                actualizarVistaPreviaFicha();
            }
        });
});

let stockMinimoEditadoManual = esEdicion;
inStockMinimo.addEventListener('input', () => {
    stockMinimoEditadoManual = true;
    actualizarVistaPreviaFicha();
});

inStockActual.addEventListener('input', function() {
    if (!stockMinimoEditadoManual) {
        const val = parseInt(this.value, 10);
        if (!isNaN(val) && val >= 0) {
            inStockMinimo.value = Math.round(val * 0.20);
        }
    }
    actualizarVistaPreviaFicha();
});

[inCodigo, inNombre, inProveedor, inPrecio, inDescripcion].forEach(el => {
    el.addEventListener('input', actualizarVistaPreviaFicha);
    el.addEventListener('change', actualizarVistaPreviaFicha);
});

inArchivo.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        inUrl.value = '';
        mostrarImagenPrevia(URL.createObjectURL(file));
    }
});

inUrl.addEventListener('input', function() {
    const link = this.value.trim();
    if (link !== '') {
        inArchivo.value = '';
        mostrarImagenPrevia(link);
    }
});

actualizarVistaPreviaFicha();

function guardarCategoria() {
    const fd = new FormData();
    fd.append('accion', 'crear_categoria');
    fd.append('nombre', document.getElementById('nuevaCatNombre').value);
    fd.append('descripcion', document.getElementById('nuevaCatDesc').value);

    fetch('api_maestros.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                const opt = new Option(res.nombre, res.id, true, true);
                inCategoria.add(opt);
                inCategoria.dispatchEvent(new Event('change'));
                bootstrap.Modal.getInstance(document.getElementById('modalNuevaCategoria')).hide();
                document.getElementById('nuevaCatNombre').value = '';
                document.getElementById('nuevaCatDesc').value = '';
            } else {
                const err = document.getElementById('errCat');
                err.textContent = res.error;
                err.classList.remove('d-none');
            }
        });
}

function guardarProveedor() {
    const fd = new FormData();
    fd.append('accion', 'crear_proveedor');
    fd.append('rut', document.getElementById('nuevoProvRut').value);
    fd.append('razon_social', document.getElementById('nuevoProvRazon').value);
    fd.append('contacto', document.getElementById('nuevoProvContacto').value);
    fd.append('telefono', document.getElementById('nuevoProvTel').value);

    fetch('api_maestros.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                const opt = new Option(res.nombre, res.id, true, true);
                inProveedor.add(opt);
                actualizarVistaPreviaFicha();
                bootstrap.Modal.getInstance(document.getElementById('modalNuevoProveedor')).hide();
                document.getElementById('nuevoProvRut').value = '';
                document.getElementById('nuevoProvRazon').value = '';
            } else {
                const err = document.getElementById('errProv');
                err.textContent = res.error;
                err.classList.remove('d-none');
            }
        });
}
</script>
</body>
</html>