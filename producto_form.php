<?php
// producto_form.php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarRol([1]);

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
        $errores[] = 'El precio debe ser un número entero mayor o igual a 0 (RN02).';
    }
    if ($stock_actual === '' || !is_numeric($stock_actual) || (int)$stock_actual < 0) {
        $errores[] = 'El stock actual debe ser un número entero mayor o igual a 0 (RN02).';
    }
    if ($stock_minimo === '' || !is_numeric($stock_minimo) || (int)$stock_minimo < 0) {
        $errores[] = 'El stock mínimo debe ser un número entero mayor o igual a 0 (RN02).';
    }

    // Procesar subida de archivo de imagen desde PC si se seleccionó uno
    $imagenFinal = !empty($imagenUrl) ? $imagenUrl : $imagenActual;

    if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
        $archivoTmp  = $_FILES['imagen_archivo']['tmp_name'];
        $nombreOrig  = $_FILES['imagen_archivo']['name'];
        $ext         = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        $permitidas  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $permitidas)) {
            $errores[] = 'Formato de imagen no válido. Use JPG, PNG, WEBP o GIF.';
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
            $errores[] = "El código '$codigo' ya está registrado en otro producto (RN01).";
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
                // Iniciar transacción para crear producto + movimiento inicial si tiene stock > 0
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

                // Si el producto nace con stock > 0, dejar trazabilidad en Registro de Movimientos
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
        .btn-emerald { background-color: var(--emerald-primary); color: #fff; font-weight: 600; border: none; }
        .btn-emerald:hover { background-color: var(--emerald-dark); color: #fff; }
        .form-card { background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); overflow: hidden; }
        .preview-box {
            width: 100%; height: 190px; border-radius: 12px; border: 2px dashed #cbd5e1;
            background-color: #f8fafc; display: flex; flex-direction: column;
            align-items: center; justify-content: center; overflow: hidden; position: relative;
        }
        .preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .form-control:focus, .form-select:focus { border-color: var(--emerald-primary); box-shadow: 0 0 0 0.2rem rgba(16,185,129,0.18); }
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
                <a href="historial_movimientos.php" class="nav-pill-custom"><i class="bi bi-journal-text me-1"></i> Registro Global</a>
                <a href="usuarios.php" class="nav-pill-custom"><i class="bi bi-people-fill me-1"></i> Usuarios</a>
                <a href="producto_form.php" class="nav-pill-custom active"><i class="bi bi-plus-circle-fill me-1"></i> Registrar Producto</a>
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

<main class="container py-4" style="max-width: 960px;">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold mb-0">
                <i class="bi <?= $esEdicion ? 'bi-pencil-square' : 'bi-plus-square-fill' ?> text-success me-2"></i>
                <?= $esEdicion ? 'Editar Ficha de Producto' : 'Registrar Nuevo Producto' ?>
            </h3>
            <span class="text-muted small">Complete los datos técnicos, clasificación y parámetros de stock</span>
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

    <div class="form-card">
        <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center border-bottom border-success border-3">
            <span class="fw-semibold small"><i class="bi bi-cpu text-success me-2"></i>FICHA MAESTRA DE INVENTARIO</span>
            <span class="badge bg-success bg-opacity-25 text-success mono">3FN / PDO</span>
        </div>

        <form method="POST" enctype="multipart/form-data" id="formProducto" class="p-4" novalidate>
            <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($imagen) ?>">

            <div class="row g-4">
                <!-- Columna Izquierda: Datos Principales -->
                <div class="col-lg-8">
                    <div class="row g-3">
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
                                <button type="button" class="btn btn-outline-success" title="Nueva Categoría" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="codigo" class="form-label fw-semibold small">
                                Código SKU <span class="text-danger">*</span>
                                <span class="text-success fw-normal" style="font-size: 0.75rem;">(Autogenerado por categoría)</span>
                            </label>
                            <input type="text" class="form-control mono fw-bold" id="codigo" name="codigo" 
                                   value="<?= htmlspecialchars($codigo) ?>" placeholder="Ej: CAB-002" required>
                        </div>

                        <div class="col-12">
                            <label for="nombre" class="form-label fw-semibold small">Nombre del Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej: Cable DisplayPort 1.4 Mallado 2m" required>
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
                                <button type="button" class="btn btn-outline-success" title="Nuevo Proveedor" data-bs-toggle="modal" data-bs-target="#modalNuevoProveedor">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="precio" class="form-label fw-semibold small">Precio Unitario ($) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control mono" id="precio" name="precio" min="0" step="1"
                                   value="<?= htmlspecialchars($precio) ?>" placeholder="0" required>
                        </div>

                        <div class="col-md-4">
                            <label for="stock_actual" class="form-label fw-semibold small">Stock Actual <span class="text-danger">*</span></label>
                            <input type="number" class="form-control mono" id="stock_actual" name="stock_actual" min="0" step="1"
                                   value="<?= htmlspecialchars($stock_actual) ?>" placeholder="0" required>
                        </div>

                        <div class="col-md-4">
                            <label for="stock_minimo" class="form-label fw-semibold small">
                                Stock Crítico <span class="text-danger">*</span>
                                <span class="text-muted fw-normal" style="font-size: 0.72rem;">(Sug. 20%)</span>
                            </label>
                            <input type="number" class="form-control mono" id="stock_minimo" name="stock_minimo" min="0" step="1"
                                   value="<?= htmlspecialchars($stock_minimo) ?>" placeholder="0" required>
                        </div>

                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-semibold small">Descripción Técnica</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2" 
                                      placeholder="Especificaciones, compatibilidad o características..."><?= htmlspecialchars($descripcion) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Imagen del Producto -->
                <div class="col-lg-4">
                    <label class="form-label fw-semibold small">Imagen del Producto</label>
                    <div class="preview-box mb-3" id="previewContainer">
                        <?php if (!empty($imagen)): ?>
                            <img src="<?= htmlspecialchars($imagen) ?>" id="previewImg" alt="Vista previa">
                        <?php else: ?>
                            <i class="bi bi-image fs-1 text-muted mb-1" id="previewIcon"></i>
                            <span class="text-muted small" id="previewText">Sin imagen seleccionada</span>
                            <img src="" id="previewImg" class="d-none" alt="Vista previa">
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="imagen_archivo" class="form-label fw-semibold small text-success">
                            <i class="bi bi-upload me-1"></i>Subir desde tu dispositivo
                        </label>
                        <input type="file" class="form-control form-control-sm" id="imagen_archivo" name="imagen_archivo" accept="image/*">
                    </div>

                    <div>
                        <label for="imagen_url" class="form-label fw-semibold small text-muted">O pegar enlace URL directo</label>
                        <input type="url" class="form-control form-control-sm" id="imagen_url" name="imagen_url" 
                               value="<?= (strpos($imagen, 'http') === 0) ? htmlspecialchars($imagen) : '' ?>" 
                               placeholder="https://...">
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                <a href="index.php" class="btn btn-outline-secondary px-4">Cancelar</a>
                <button type="submit" class="btn btn-emerald px-5">
                    <i class="bi bi-check2-circle me-1"></i> Guardar Producto
                </button>
            </div>
        </form>
    </div>
</main>

<!-- Modal Crear Nueva Categoría -->
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
                    <input type="text" id="nuevaCatNombre" class="form-control" placeholder="Ej: Monitores y Pantallas">
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

<!-- Modal Crear Nuevo Proveedor -->
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

// 1. Autocompletar Código SKU al elegir categoría (solo en productos nuevos)
document.getElementById('id_categoria').addEventListener('change', function() {
    const idCat = this.value;
    if (!idCat || esEdicion) return;

    fetch('api_maestros.php?accion=siguiente_codigo&id_categoria=' + idCat)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('codigo').value = data.codigo;
            }
        });
});

// 2. Sugerir automáticamente el 20% del Stock Actual en Stock Crítico (editable)
let stockMinimoEditadoManual = esEdicion;
document.getElementById('stock_minimo').addEventListener('input', () => {
    stockMinimoEditadoManual = true;
});
document.getElementById('stock_actual').addEventListener('input', function() {
    if (!stockMinimoEditadoManual) {
        const val = parseInt(this.value, 10);
        if (!isNaN(val) && val >= 0) {
            document.getElementById('stock_minimo').value = Math.round(val * 0.20);
        }
    }
});

// 3. Vista previa en vivo al subir archivo o pegar URL
function mostrarPreview(src) {
    const img = document.getElementById('previewImg');
    const icon = document.getElementById('previewIcon');
    const txt = document.getElementById('previewText');
    if (src) {
        img.src = src;
        img.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        if (txt) txt.classList.add('d-none');
    }
}

document.getElementById('imagen_archivo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('imagen_url').value = '';
        mostrarPreview(URL.createObjectURL(file));
    }
});

document.getElementById('imagen_url').addEventListener('input', function() {
    if (this.value.trim() !== '') {
        document.getElementById('imagen_archivo').value = '';
        mostrarPreview(this.value.trim());
    }
});

// 4. Guardar Nueva Categoría vía AJAX
function guardarCategoria() {
    const fd = new FormData();
    fd.append('accion', 'crear_categoria');
    fd.append('nombre', document.getElementById('nuevaCatNombre').value);
    fd.append('descripcion', document.getElementById('nuevaCatDesc').value);

    fetch('api_maestros.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                const sel = document.getElementById('id_categoria');
                const opt = new Option(res.nombre, res.id, true, true);
                sel.add(opt);
                sel.dispatchEvent(new Event('change'));
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

// 5. Guardar Nuevo Proveedor vía AJAX
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
                const sel = document.getElementById('id_proveedor');
                const opt = new Option(res.nombre, res.id, true, true);
                sel.add(opt);
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