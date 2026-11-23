<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$idUsuario     = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];

$errores = [];

$stmtUser = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = :id LIMIT 1");
$stmtUser->execute(['id' => $idUsuario]);
$datosUsuario = $stmtUser->fetch();

if (!$datosUsuario) {
    header('Location: logout.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_datos') {
        $nombre    = trim($_POST['nombre'] ?? '');
        $paterno   = trim($_POST['apellido_paterno'] ?? '');
        $materno   = trim($_POST['apellido_materno'] ?? '');
        $telefono  = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($nombre) || empty($paterno) || empty($materno)) {
            $errores[] = 'El nombre y ambos apellidos son obligatorios.';
        }

        if (empty($errores)) {
            $stmtUpd = $pdo->prepare("UPDATE usuario SET nombre = :n, apellido_paterno = :ap, apellido_materno = :am, telefono = :t, direccion = :d WHERE id_usuario = :id");
            $stmtUpd->execute([
                'n'  => $nombre,
                'ap' => $paterno,
                'am' => $materno,
                't'  => $telefono ?: null,
                'd'  => $direccion ?: null,
                'id' => $idUsuario
            ]);

            $_SESSION['nombre'] = trim($nombre . ' ' . $paterno);
            header('Location: perfil.php?msg=datos_actualizados');
            exit;
        }
    } elseif ($accion === 'cambiar_clave') {
        $claveActual    = trim($_POST['clave_actual'] ?? '');
        $claveNueva     = trim($_POST['clave_nueva'] ?? '');
        $claveConfirmar = trim($_POST['clave_confirmar'] ?? '');

        if (!password_verify($claveActual, $datosUsuario['clave'])) {
            $errores[] = 'La contraseña actual ingresada es incorrecta.';
        } elseif (strlen($claveNueva) < 6) {
            $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($claveNueva !== $claveConfirmar) {
            $errores[] = 'La confirmación de la nueva contraseña no coincide.';
        }

        if (empty($errores)) {
            $nuevoHash = password_hash($claveNueva, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuario SET clave = :cl WHERE id_usuario = :id")
                ->execute(['cl' => $nuevoHash, 'id' => $idUsuario]);

            header('Location: perfil.php?msg=clave_actualizada');
            exit;
        }
    }
}

$nombreCompleto = trim($datosUsuario['nombre'] . ' ' . ($datosUsuario['apellido_paterno'] ?? '') . ' ' . ($datosUsuario['apellido_materno'] ?? ''));
$iniciales      = strtoupper(mb_substr(trim($datosUsuario['nombre']), 0, 1) . mb_substr(trim($datosUsuario['apellido_paterno'] ?? 'U'), 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | TecnoStock</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
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
        .panel-card { background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.03); }
        .profile-avatar {
            width: 82px; height: 82px; border-radius: 20px; background: #111827; color: #34d399;
            border: 3px solid #10b981; font-size: 1.8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center; margin: 0 auto;
        }
        .form-control:focus { border-color: #10b981; box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.18); }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container py-4" style="max-width: 1120px;">
    <div class="mb-4">
        <h3 class="fw-bold mb-0"><i class="bi bi-person-badge-fill text-success me-2"></i>Mi Perfil de Usuario</h3>
        <span class="text-muted small">Administra tu información de contacto y credenciales de acceso al sistema</span>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card p-4 text-center">
                <div class="profile-avatar mono mb-3 shadow-sm"><?= htmlspecialchars($iniciales) ?></div>
                <div class="mb-2">
                    <span class="badge bg-dark text-success mono px-3 py-1"><?= htmlspecialchars($datosUsuario['rut'] ?? 'Sin RUT') ?></span>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($nombreCompleto) ?></h5>
                <div class="mono small text-muted mb-3"><?= htmlspecialchars($datosUsuario['correo']) ?></div>

                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1 mb-4">
                    <?= strtoupper(obtenerNombreRol($rolUsuario)) ?>
                </span>

                <div class="text-start small border-top pt-3 d-flex flex-column gap-2">
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.72rem;">TELÉFONO DE CONTACTO</span>
                        <strong class="text-dark"><?= htmlspecialchars($datosUsuario['telefono'] ?: 'No registrado') ?></strong>
                    </div>
                    <div>
                        <span class="text-muted d-block" style="font-size: 0.72rem;">DIRECCIÓN</span>
                        <strong class="text-dark"><?= htmlspecialchars($datosUsuario['direccion'] ?: 'No registrada') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 d-flex flex-column gap-4">
            <div class="panel-card">
                <div class="p-3 bg-dark text-white fw-bold small border-bottom border-success border-3">
                    <i class="bi bi-card-text text-success me-2"></i>DATOS PERSONALES Y DE CONTACTO
                </div>
                <form method="POST" class="p-4">
                    <input type="hidden" name="accion" value="actualizar_datos">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">RUT <span class="text-muted fw-normal">(Solo lectura)</span></label>
                            <input type="text" class="form-control mono" value="<?= htmlspecialchars($datosUsuario['rut'] ?? '') ?>" disabled readonly>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Correo Electrónico <span class="text-muted fw-normal">(Solo lectura)</span></label>
                            <input type="email" class="form-control mono" value="<?= htmlspecialchars($datosUsuario['correo']) ?>" disabled readonly>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($datosUsuario['nombre']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Paterno <span class="text-danger">*</span></label>
                            <input type="text" name="apellido_paterno" class="form-control" value="<?= htmlspecialchars($datosUsuario['apellido_paterno'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Materno <span class="text-danger">*</span></label>
                            <input type="text" name="apellido_materno" class="form-control" value="<?= htmlspecialchars($datosUsuario['apellido_materno'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($datosUsuario['telefono'] ?? '') ?>" placeholder="Ej: +56 9 1234 5678">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($datosUsuario['direccion'] ?? '') ?>" placeholder="Ej: Av. Principal 1234, Santiago">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-emerald px-4 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Guardar Datos Personales
                        </button>
                    </div>
                </form>
            </div>

            <div class="panel-card">
                <div class="p-3 bg-dark text-white fw-bold small border-bottom border-success border-3">
                    <i class="bi bi-shield-lock text-success me-2"></i>SEGURIDAD Y CONTRASEÑA
                </div>
                <form method="POST" class="p-4">
                    <input type="hidden" name="accion" value="cambiar_clave">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Contraseña Actual <span class="text-danger">*</span></label>
                            <input type="password" name="clave_actual" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nueva Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="clave_nueva" class="form-control" placeholder="Mínimo 6 caracteres" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Confirmar Nueva <span class="text-danger">*</span></label>
                            <input type="password" name="clave_confirmar" class="form-control" placeholder="Repetir contraseña" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-outline-dark px-4 fw-semibold">
                            <i class="bi bi-key-fill text-success me-1"></i> Actualizar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="ts-toast-container">
            <div class="ts-toast">
                <i class="bi bi-check-circle-fill text-success fs-5 mt-0.5"></i>
                <div class="flex-grow-1 small">
                    <strong class="d-block ts-toast-title">Perfil actualizado</strong>
                    <span class="ts-toast-text">
                        <?= $_GET['msg'] === 'clave_actualizada' ? 'Tu contraseña ha sido cambiada con éxito.' : 'Tus datos personales fueron guardados correctamente.' ?>
                    </span>
                </div>
                <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                <div class="ts-toast-progress"></div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>