<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarRol([1]);

$idAdminActual = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];
$errores = [];

function normalizarRutUsuario(string $rut): string {
    $limpio = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
    if (strlen($limpio) < 2) return $limpio;
    $cuerpo = substr($limpio, 0, -1);
    $dv     = substr($limpio, -1);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
}

function claveDesdeRut(string $rut): string {
    $limpio = preg_replace('/[^0-9kK]/', '', strtolower(trim($rut)));
    return substr($limpio, -6);
}

// Procesar aprobación o rechazo de solicitud de cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_solicitud'])) {
    $idSol   = (int)($_POST['id_solicitud'] ?? 0);
    $accionS = $_POST['accion_solicitud'];

    $stmtSol = $pdo->prepare("
        SELECT s.*, u.rut AS real_rut 
        FROM solicitud_clave s 
        INNER JOIN usuario u ON s.id_usuario = u.id_usuario 
        WHERE s.id_solicitud = :id AND s.estado = 0 LIMIT 1
    ");
    $stmtSol->execute(['id' => $idSol]);
    $sol = $stmtSol->fetch();

    if ($sol) {
        if ($accionS === 'aceptar') {
            $claveRut = claveDesdeRut($sol['real_rut'] ?? '');
            if (strlen($claveRut) < 6) {
                $claveRut = 'user123';
            }
            $hashRut = password_hash($claveRut, PASSWORD_BCRYPT);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE usuario SET clave = :cl WHERE id_usuario = :id_u")
                ->execute(['cl' => $hashRut, 'id_u' => $sol['id_usuario']]);
            $pdo->prepare("UPDATE solicitud_clave SET estado = 1, fecha_resolucion = NOW() WHERE id_solicitud = :id")
                ->execute(['id' => $idSol]);
            $pdo->commit();
            header("Location: usuarios.php?msg=clave_restablecida&pass={$claveRut}");
            exit;
        } elseif ($accionS === 'rechazar') {
            $pdo->prepare("UPDATE solicitud_clave SET estado = 2, fecha_resolucion = NOW() WHERE id_solicitud = :id")
                ->execute(['id' => $idSol]);
            header('Location: usuarios.php?msg=solicitud_rechazada');
            exit;
        }
    }
}

if (isset($_GET['accion'], $_GET['id'])) {
    $idTarget = (int)$_GET['id'];
    $accion = $_GET['accion'];

    if ($idTarget === $idAdminActual) {
        header('Location: usuarios.php?error=auto_accion');
        exit;
    }

    if ($accion === 'estado' && isset($_GET['val'])) {
        $val = ((int)$_GET['val'] === 1) ? 1 : 0;
        $pdo->prepare("UPDATE usuario SET estado = :est WHERE id_usuario = :id")->execute(['est' => $val, 'id' => $idTarget]);
        header('Location: usuarios.php?msg=estado');
        exit;
    } elseif ($accion === 'eliminar') {
        try {
            $pdo->prepare("DELETE FROM usuario WHERE id_usuario = :id")->execute(['id' => $idTarget]);
            header('Location: usuarios.php?msg=eliminado');
            exit;
        } catch (PDOException $e) {
            header('Location: usuarios.php?error=tiene_registros');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['accion_solicitud'])) {
    $operacion = $_POST['operacion'] ?? 'crear';
    $idEdit    = (int)($_POST['id_usuario_edit'] ?? 0);
    $rut       = normalizarRutUsuario($_POST['rut'] ?? '');
    $nombre    = trim($_POST['nombre'] ?? '');
    $paterno   = trim($_POST['apellido_paterno'] ?? '');
    $materno   = trim($_POST['apellido_materno'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $clave     = trim($_POST['clave'] ?? '');
    $rol       = (int)($_POST['rol'] ?? 2);

    if (strlen(preg_replace('/[^0-9kK]/', '', $rut)) < 7) $errores[] = 'Ingrese un RUT válido.';
    if (empty($nombre))  $errores[] = 'El nombre es obligatorio.';
    if (empty($paterno)) $errores[] = 'El apellido paterno es obligatorio.';
    if (empty($materno)) $errores[] = 'El apellido materno es obligatorio.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido.';
    if (!in_array($rol, [1, 2])) $errores[] = 'Rol inválido.';

    if ($operacion === 'crear') {
        if (empty($clave)) {
            $clave = claveDesdeRut($rut);
        }
        if (strlen($clave) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';

        $check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :c OR rut = :r LIMIT 1");
        $check->execute(['c' => $correo, 'r' => $rut]);
        if ($check->fetch()) $errores[] = 'El RUT o el correo electrónico ya están registrados.';

        if (empty($errores)) {
            $hash = password_hash($clave, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO usuario (rut, nombre, apellido_paterno, apellido_materno, correo, clave, rol, estado) VALUES (:r, :n, :ap, :am, :c, :cl, :rol, 1)")
                ->execute([
                    'r'   => $rut,
                    'n'   => $nombre,
                    'ap'  => $paterno,
                    'am'  => $materno,
                    'c'   => $correo,
                    'cl'  => $hash,
                    'rol' => $rol
                ]);
            header('Location: usuarios.php?msg=creado');
            exit;
        }
    } elseif ($operacion === 'editar' && $idEdit > 0) {
        $check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE (correo = :c OR rut = :r) AND id_usuario != :id LIMIT 1");
        $check->execute(['c' => $correo, 'r' => $rut, 'id' => $idEdit]);
        if ($check->fetch()) $errores[] = 'El RUT o el correo pertenecen a otro usuario.';

        if (empty($errores)) {
            if (!empty($clave)) {
                if (strlen($clave) < 6) {
                    $errores[] = 'Si cambias la contraseña, debe tener al menos 6 caracteres.';
                } else {
                    $hash = password_hash($clave, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE usuario SET rut = :r, nombre = :n, apellido_paterno = :ap, apellido_materno = :am, correo = :c, clave = :cl, rol = :rol WHERE id_usuario = :id")
                        ->execute(['r' => $rut, 'n' => $nombre, 'ap' => $paterno, 'am' => $materno, 'c' => $correo, 'cl' => $hash, 'rol' => $rol, 'id' => $idEdit]);
                }
            } else {
                $pdo->prepare("UPDATE usuario SET rut = :r, nombre = :n, apellido_paterno = :ap, apellido_materno = :am, correo = :c, rol = :rol WHERE id_usuario = :id")
                    ->execute(['r' => $rut, 'n' => $nombre, 'ap' => $paterno, 'am' => $materno, 'c' => $correo, 'rol' => $rol, 'id' => $idEdit]);
            }

            if (empty($errores)) {
                if ($idEdit === $idAdminActual) {
                    $_SESSION['nombre'] = trim($nombre . ' ' . $paterno);
                }
                header('Location: usuarios.php?msg=editado');
                exit;
            }
        }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuario ORDER BY id_usuario ASC")->fetchAll();

$totalUsers   = count($usuarios);
$totalAdmins  = 0;
$totalActivos = 0;
foreach ($usuarios as $us) {
    if ((int)$us['rol'] === 1) $totalAdmins++;
    if ((int)$us['estado'] === 1) $totalActivos++;
}

$solicitudesPendientes = [];
try {
    $solicitudesPendientes = $pdo->query("
        SELECT s.*, u.rut AS real_rut, u.nombre AS real_nombre, u.apellido_paterno AS real_paterno, u.apellido_materno AS real_materno, u.rol AS real_rol
        FROM solicitud_clave s
        INNER JOIN usuario u ON s.id_usuario = u.id_usuario
        WHERE s.estado = 0
        ORDER BY s.fecha_solicitud DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $solicitudesPendientes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | TecnoStock</title>
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
        .nav-pill-custom { color: #9ca3af; text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 0.5rem 0.9rem; border-radius: 8px; }
        .nav-pill-custom:hover { color: #ffffff; background-color: rgba(255,255,255,0.05); }
        .nav-pill-custom.active { color: #111827; background-color: #10b981; }
        .btn-emerald { background-color: #10b981; color: #fff; font-weight: 600; border: none; }
        .btn-emerald:hover { background-color: #059669; color: #fff; }
        .panel-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }

        .user-card {
            background: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb;
            padding: 1.25rem; height: 100%; display: flex; flex-direction: column;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .user-card:hover { transform: translateY(-3px); border-color: #10b981; box-shadow: 0 12px 24px -8px rgba(17, 24, 39, 0.12); }
        .user-card.user-inactivo { opacity: 0.6; }
        body.dark-mode .user-card { background-color: #111827; border-color: #1f2937; }
        body.dark-mode .user-card:hover { border-color: #10b981; }

        .avatar-circle {
            width: 48px; height: 48px; border-radius: 12px; font-weight: 700; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .rut-pill {
            background-color: #111827; color: #34d399; font-size: 0.72rem;
            padding: 0.15rem 0.5rem; border-radius: 5px; font-weight: 700; display: inline-block;
        }
        .solicitud-alert-banner {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            border: 1px solid #374151;
            border-left: 5px solid #f59e0b;
            border-radius: 14px;
            color: #f9fafb;
            box-shadow: 0 10px 25px -5px rgba(17, 24, 39, 0.25);
        }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<?php include 'includes/navbar.php'; ?>

<main class="container-fluid px-4 py-4" style="max-width: 1380px;">

    <!-- Notificación Neutra de Solicitudes Pendientes -->
    <?php if (!empty($solicitudesPendientes)): ?>
        <div class="solicitud-alert-banner p-3 px-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-25 text-warning" style="width: 46px; height: 46px;">
                    <i class="bi bi-key-fill fs-4"></i>
                </span>
                <div>
                    <div class="fw-bold d-flex align-items-center gap-2">
                        <span>Solicitud de Cambio de Contraseña Pendiente</span>
                        <span class="badge bg-warning text-dark mono"><?= count($solicitudesPendientes) ?></span>
                    </div>
                    <div class="small text-white-50">
                        Hay solicitudes de restablecimiento de acceso esperando validación de identidad.
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-warning fw-bold px-4" onclick="abrirModalSolicitudes()">
                <i class="bi bi-shield-check me-1"></i> Revisar Solicitudes
            </button>
        </div>
    <?php endif; ?>

    <!-- Barra Superior de Gestión de Usuarios -->
    <div class="panel-card p-3 px-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="fw-bold mb-0">Gestión de Usuarios</h4>
                <span class="text-muted small">
                    <strong class="text-dark mono"><?= $totalUsers ?></strong> cuentas registradas · 
                    <strong class="text-success mono"><?= $totalActivos ?></strong> activas · 
                    <strong class="text-primary mono"><?= $totalAdmins ?></strong> administradores
                </span>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 260px;">
                    <span class="input-group-text bg-white text-success"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscarUsuarioInput" class="form-control" placeholder="Buscar por RUT, nombre o correo...">
                </div>
                <select id="filtroRolUsuario" class="form-select form-select-sm" style="width: 155px;">
                    <option value="">Rol: Todos</option>
                    <option value="1">Administradores</option>
                    <option value="2">Usuarios</option>
                </select>
                <button type="button" class="btn btn-emerald btn-sm px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
                    <i class="bi bi-person-plus-fill me-1"></i> Nuevo Usuario
                </button>
            </div>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Cuadrícula de Tarjetas de Usuarios -->
    <div class="row g-3" id="gridUsuarios">
        <?php foreach ($usuarios as $u): ?>
            <?php
                $activo         = ((int)$u['estado'] === 1);
                $esAdmin        = ((int)$u['rol'] === 1);
                $nombreCompleto = trim($u['nombre'] . ' ' . ($u['apellido_paterno'] ?? '') . ' ' . ($u['apellido_materno'] ?? ''));
                $iniciales      = strtoupper(mb_substr(trim($u['nombre']), 0, 1) . mb_substr(trim($u['apellido_paterno'] ?? 'U'), 0, 1));
            ?>
            <div class="col-md-6 col-lg-4 item-usuario"
                 data-busqueda="<?= htmlspecialchars(mb_strtolower(($u['rut'] ?? '') . ' ' . $nombreCompleto . ' ' . $u['correo'])) ?>"
                 data-rol="<?= (int)$u['rol'] ?>">
                <div class="user-card <?= !$activo ? 'user-inactivo' : '' ?>">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="avatar-circle mono <?= $esAdmin ? 'bg-dark text-success border border-success border-opacity-50' : 'bg-success bg-opacity-10 text-success' ?>">
                                <?= htmlspecialchars($iniciales) ?>
                            </div>
                            <div class="min-w-0">
                                <div class="mb-1">
                                    <span class="rut-pill mono"><?= htmlspecialchars($u['rut'] ?? 'Sin RUT') ?></span>
                                </div>
                                <div class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($nombreCompleto) ?>">
                                    <?= htmlspecialchars($nombreCompleto) ?>
                                </div>
                                <div class="text-muted small mono text-truncate"><?= htmlspecialchars($u['correo']) ?></div>
                            </div>
                        </div>
                        <?php if ((int)$u['id_usuario'] === $idAdminActual): ?>
                            <span class="badge bg-success">Tú</span>
                        <?php endif; ?>
                    </div>

                    <div class="small text-muted mb-3 flex-grow-1 d-flex flex-column gap-1">
                        <div>
                            <i class="bi bi-telephone text-success me-1"></i>
                            <?= htmlspecialchars($u['telefono'] ?? 'Sin teléfono registrado') ?>
                        </div>
                        <div class="text-truncate">
                            <i class="bi bi-geo-alt text-success me-1"></i>
                            <?= htmlspecialchars($u['direccion'] ?? 'Sin dirección registrada') ?>
                        </div>
                    </div>

                    <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= $esAdmin ? 'bg-dark text-success border border-success border-opacity-25' : 'bg-secondary' ?>">
                                <?= obtenerNombreRol((int)$u['rol']) ?>
                            </span>
                            <?php if ($activo): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">● Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">○ Inactivo</span>
                            <?php endif; ?>
                        </div>

                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-dark" title="Editar usuario"
                                    onclick='abrirEditarUsuario(<?= json_encode([
                                        "id"      => $u["id_usuario"],
                                        "rut"     => $u["rut"] ?? "",
                                        "nombre"  => $u["nombre"],
                                        "paterno" => $u["apellido_paterno"] ?? "",
                                        "materno" => $u["apellido_materno"] ?? "",
                                        "correo"  => $u["correo"],
                                        "rol"     => (int)$u["rol"]
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ((int)$u['id_usuario'] !== $idAdminActual): ?>
                                <button type="button" class="btn <?= $activo ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                        title="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?>"
                                        onclick="confirmarUsuario('estado', <?= $u['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($nombreCompleto)) ?>', <?= $activo ? 0 : 1 ?>)">
                                    <i class="bi <?= $activo ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                        onclick="confirmarUsuario('eliminar', <?= $u['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($nombreCompleto)) ?>', 0)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (isset($_GET['msg']) || isset($_GET['error'])): ?>
        <div class="ts-toast-container">
            <?php if (isset($_GET['msg'])): ?>
                <div class="ts-toast">
                    <i class="bi bi-check-circle-fill text-success fs-5 mt-0.5"></i>
                    <div class="flex-grow-1 small">
                        <strong class="d-block ts-toast-title">Operación completada</strong>
                        <span class="ts-toast-text">
                            <?php
                                if ($_GET['msg'] === 'creado') echo 'Nuevo usuario registrado correctamente.';
                                elseif ($_GET['msg'] === 'editado') echo 'Datos del usuario actualizados correctamente.';
                                elseif ($_GET['msg'] === 'estado') echo 'Estado de acceso del usuario actualizado.';
                                elseif ($_GET['msg'] === 'eliminado') echo 'Usuario eliminado permanentemente.';
                                elseif ($_GET['msg'] === 'clave_restablecida') echo 'Contraseña restablecida a los últimos 6 dígitos del RUT (' . htmlspecialchars($_GET['pass'] ?? '') . ').';
                                elseif ($_GET['msg'] === 'solicitud_rechazada') echo 'Solicitud de cambio de contraseña rechazada.';
                            ?>
                        </span>
                    </div>
                    <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                    <div class="ts-toast-progress"></div>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="ts-toast ts-toast-danger">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5 mt-0.5"></i>
                    <div class="flex-grow-1 small">
                        <strong class="d-block ts-toast-title">No se pudo completar</strong>
                        <span class="ts-toast-text">
                            <?php
                                if ($_GET['error'] === 'auto_accion') echo 'No puedes deshabilitar ni eliminar tu propia cuenta activa.';
                                elseif ($_GET['error'] === 'tiene_registros') echo 'El usuario tiene movimientos registrados. Deshabilítalo en su lugar.';
                            ?>
                        </span>
                    </div>
                    <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
                    <div class="ts-toast-progress"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal Crear Nuevo Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill text-success me-2"></i>Crear Nueva Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="operacion" value="crear">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">RUT <span class="text-danger">*</span></label>
                            <input type="text" name="rut" id="nuevoRut" class="form-control mono" placeholder="12.345.678-9" maxlength="12" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Rol / Perfil</label>
                            <select name="rol" class="form-select">
                                <option value="2" selected>Usuario</option>
                                <option value="1">Administrador</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="nuevoNombre" class="form-control" placeholder="Ej: Matías" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Paterno <span class="text-danger">*</span></label>
                            <input type="text" name="apellido_paterno" id="nuevoPaterno" class="form-control" placeholder="Ej: Valenzuela" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Materno <span class="text-danger">*</span></label>
                            <input type="text" name="apellido_materno" id="nuevoMaterno" class="form-control" placeholder="Ej: Rojas" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Correo Electrónico <span class="text-success fw-normal">(Autogenerado)</span></label>
                            <input type="email" name="correo" id="nuevoCorreo" class="form-control mono" placeholder="mvalenzuelar@tecnostock.cl" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contraseña Inicial <span class="text-success fw-normal">(Últimos 6 díg. RUT)</span></label>
                            <div class="input-group">
                                <input type="text" name="clave" id="nuevaClave" class="form-control mono fw-bold" placeholder="Automático desde RUT" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('nuevaClave', this)">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-emerald px-4">Crear Cuenta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Revisión de Solicitudes de Cambio de Contraseña -->
<?php if (!empty($solicitudesPendientes)): ?>
<div class="modal fade" id="modalSolicitudesClave" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-warning border-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-exclamation text-warning me-2"></i>Solicitudes de Recuperación de Contraseña
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Verifica que los datos ingresados coincidan con la cuenta real antes de restablecer la contraseña a los <strong>últimos 6 dígitos del RUT</strong> (incluido dígito verificador).
                </p>

                <div class="d-flex flex-column gap-3">
                    <?php foreach ($solicitudesPendientes as $sol): ?>
                        <?php
                            $nombreRealSol = trim($sol['real_nombre'] . ' ' . ($sol['real_paterno'] ?? '') . ' ' . ($sol['real_materno'] ?? ''));
                            $nombreIngSol  = trim($sol['nombre_ingresado'] . ' ' . ($sol['paterno_ingresado'] ?? '') . ' ' . ($sol['materno_ingresado'] ?? ''));
                            $claveNuevaPreview = claveDesdeRut($sol['real_rut'] ?? '');
                        ?>
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-3 gap-2">
                                <div>
                                    <span class="badge bg-warning text-dark mono me-1">SOLICITUD #<?= (int)$sol['id_solicitud'] ?></span>
                                    <span class="small text-muted mono"><?= date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])) ?></span>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ((int)$sol['coincide_rut'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">✓ RUT coincide</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">✕ RUT difiere</span>
                                    <?php endif; ?>

                                    <?php if ((int)$sol['coincide_nombre'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">✓ Nombre coincide</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">✕ Nombre difiere</span>
                                    <?php endif; ?>

                                    <?php if ((int)$sol['coincide_rol'] === 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">✓ Rol coincide</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">✕ Rol difiere</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row g-2 small mb-3">
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Cuenta Real en Sistema:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($nombreRealSol) ?></strong>
                                    <div class="mono text-muted">RUT: <?= htmlspecialchars($sol['real_rut'] ?? 'Sin RUT') ?> · <?= htmlspecialchars($sol['correo_ingresado']) ?></div>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted d-block">Datos Escritos en Solicitud:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($nombreIngSol) ?></strong>
                                    <div class="mono text-muted">RUT: <?= htmlspecialchars($sol['rut_ingresado'] ?? '') ?> · Rol: <?= obtenerNombreRol((int)$sol['rol_declarado']) ?></div>
                                </div>
                                <div class="col-12 mt-2">
                                    <span class="text-muted d-block">Dato de Verificación / Motivo:</span>
                                    <div class="p-2 bg-white rounded border text-dark fw-semibold">
                                        "<?= htmlspecialchars($sol['dato_verificacion']) ?>"
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id_solicitud" value="<?= (int)$sol['id_solicitud'] ?>">
                                    <input type="hidden" name="accion_solicitud" value="rechazar">
                                    <button type="submit" class="btn btn-outline-danger btn-sm px-3 fw-semibold">
                                        <i class="bi bi-x-circle me-1"></i> Rechazar
                                    </button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id_solicitud" value="<?= (int)$sol['id_solicitud'] ?>">
                                    <input type="hidden" name="accion_solicitud" value="aceptar">
                                    <button type="submit" class="btn btn-emerald btn-sm px-3 fw-semibold">
                                        <i class="bi bi-check2-circle me-1"></i> Aceptar (Clave: <?= htmlspecialchars($claveNuevaPreview) ?>)
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-success me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="operacion" value="editar">
                    <input type="hidden" name="id_usuario_edit" id="editUserId">

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">RUT</label>
                            <input type="text" name="rut" id="editUserRut" class="form-control mono" maxlength="12" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Rol / Perfil</label>
                            <select name="rol" id="editUserRol" class="form-select">
                                <option value="1">Administrador</option>
                                <option value="2">Usuario</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nombre</label>
                            <input type="text" name="nombre" id="editUserNombre" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Paterno</label>
                            <input type="text" name="apellido_paterno" id="editUserPaterno" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Materno</label>
                            <input type="text" name="apellido_materno" id="editUserMaterno" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Correo Electrónico</label>
                            <input type="email" name="correo" id="editUserCorreo" class="form-control mono" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nueva Contraseña <span class="text-muted fw-normal">(Opcional)</span></label>
                            <div class="input-group">
                                <input type="password" name="clave" id="editUserClave" class="form-control" placeholder="Dejar vacío para mantener">
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('editUserClave', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-emerald px-4">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Estado/Eliminar Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold" id="modalUserTitulo">Confirmar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted mb-1" id="modalUserDesc"></p>
                <h5 class="fw-bold text-dark" id="modalUserNombre"></h5>
            </div>
            <div class="modal-footer bg-light border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="modalUserBtn" class="btn px-4 fw-semibold">Confirmar</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const buscarUsuarioInput = document.getElementById('buscarUsuarioInput');
const filtroRolUsuario   = document.getElementById('filtroRolUsuario');

function filtrarTarjetasUsuarios() {
    const q   = buscarUsuarioInput.value.trim().toLowerCase();
    const rol = filtroRolUsuario.value;

    document.querySelectorAll('.item-usuario').forEach(card => {
        const texto   = card.getAttribute('data-busqueda') || '';
        const cardRol = card.getAttribute('data-rol') || '';

        const matchTexto = !q || texto.includes(q);
        const matchRol   = !rol || cardRol === rol;
        card.classList.toggle('d-none', !(matchTexto && matchRol));
    });
}

buscarUsuarioInput.addEventListener('input', filtrarTarjetasUsuarios);
filtroRolUsuario.addEventListener('change', filtrarTarjetasUsuarios);

// Formatear RUT chileno y generar clave automática con los últimos 6 caracteres (incluido dígito verificador)
function formatearRutChileno(valor) {
    let limpio = valor.replace(/[^0-9kK]/g, '').toUpperCase();
    if (limpio.length <= 1) return limpio;
    let cuerpo = limpio.slice(0, -1);
    let dv     = limpio.slice(-1);
    cuerpo = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${cuerpo}-${dv}`;
}

let claveEditadaManual = false;
document.getElementById('nuevaClave').addEventListener('input', () => {
    claveEditadaManual = true;
});

document.getElementById('nuevoRut').addEventListener('input', function() {
    this.value = formatearRutChileno(this.value);
    if (!claveEditadaManual) {
        const limpio = this.value.replace(/[^0-9kK]/g, '').toLowerCase();
        document.getElementById('nuevaClave').value = limpio.slice(-6);
    }
});

document.getElementById('editUserRut').addEventListener('input', function() {
    this.value = formatearRutChileno(this.value);
});

// Generador de correo estilo UTEM: inicial nombre + apellido paterno + inicial apellido materno
// Ej: Esteban + Curin + Paillan => ecurinp@tecnostock.cl
let correoEditadoManual = false;
document.getElementById('nuevoCorreo').addEventListener('input', () => {
    correoEditadoManual = true;
});

function limpiarTextoCorreo(txt) {
    return txt
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

function actualizarCorreoSugerido() {
    if (correoEditadoManual) return;
    const nom = limpiarTextoCorreo(document.getElementById('nuevoNombre').value.trim().split(/\s+/)[0] || '');
    const pat = limpiarTextoCorreo(document.getElementById('nuevoPaterno').value.trim().split(/\s+/)[0] || '');
    const mat = limpiarTextoCorreo(document.getElementById('nuevoMaterno').value.trim().split(/\s+/)[0] || '');

    if (!nom && !pat) {
        document.getElementById('nuevoCorreo').value = '';
        return;
    }

    const iniNom = nom.charAt(0);
    const iniMat = mat.charAt(0);
    document.getElementById('nuevoCorreo').value = `${iniNom}${pat}${iniMat}@tecnostock.cl`;
}

['nuevoNombre', 'nuevoPaterno', 'nuevoMaterno'].forEach(id => {
    document.getElementById(id).addEventListener('input', actualizarCorreoSugerido);
});

function toggleClave(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function abrirModalSolicitudes() {
    const modalEl = document.getElementById('modalSolicitudesClave');
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

function abrirEditarUsuario(u) {
    document.getElementById('editUserId').value      = u.id;
    document.getElementById('editUserRut').value     = u.rut;
    document.getElementById('editUserNombre').value  = u.nombre;
    document.getElementById('editUserPaterno').value = u.paterno;
    document.getElementById('editUserMaterno').value = u.materno;
    document.getElementById('editUserCorreo').value  = u.correo;
    document.getElementById('editUserRol').value     = u.rol;
    document.getElementById('editUserClave').value   = '';
    new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
}

function confirmarUsuario(accion, id, nombre, val) {
    const titulo = document.getElementById('modalUserTitulo');
    const desc   = document.getElementById('modalUserDesc');
    const btn    = document.getElementById('modalUserBtn');
    document.getElementById('modalUserNombre').textContent = nombre;

    if (accion === 'eliminar') {
        titulo.textContent = 'Eliminar Usuario';
        desc.textContent = '¿Estás seguro de eliminar permanentemente a este usuario?';
        btn.className = 'btn btn-danger px-4 fw-semibold';
        btn.href = `usuarios.php?accion=eliminar&id=${id}`;
    } else {
        const deshab = (val === 0);
        titulo.textContent = deshab ? 'Deshabilitar Acceso' : 'Habilitar Acceso';
        desc.textContent = deshab ? 'El usuario no podrá iniciar sesión hasta ser reactivado:' : 'Se restaurará el acceso al sistema para:';
        btn.className = deshab ? 'btn btn-warning px-4 fw-semibold' : 'btn btn-emerald px-4 fw-semibold';
        btn.href = `usuarios.php?accion=estado&id=${id}&val=${val}`;
    }
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}
</script>
</body>
</html>