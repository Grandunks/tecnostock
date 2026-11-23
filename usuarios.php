<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarRol([1]);

$idAdminActual = (int)$_SESSION['id_usuario'];
$rolUsuario    = (int)$_SESSION['rol'];
$nombreUsuario = $_SESSION['nombre'];
$errores = [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operacion = $_POST['operacion'] ?? 'crear';
    $idEdit    = (int)($_POST['id_usuario_edit'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $clave     = trim($_POST['clave'] ?? '');
    $rol       = (int)($_POST['rol'] ?? 2);

    if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Ingrese un correo válido.';
    if (!in_array($rol, [1, 2])) $errores[] = 'Rol inválido.';

    if ($operacion === 'crear') {
        if (strlen($clave) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
        $check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :c LIMIT 1");
        $check->execute(['c' => $correo]);
        if ($check->fetch()) $errores[] = 'Ese correo electrónico ya está registrado.';

        if (empty($errores)) {
            $hash = password_hash($clave, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO usuario (nombre, correo, clave, rol, estado) VALUES (:n, :c, :cl, :r, 1)")
                ->execute(['n' => $nombre, 'c' => $correo, 'cl' => $hash, 'r' => $rol]);
            header('Location: usuarios.php?msg=creado');
            exit;
        }
    } elseif ($operacion === 'editar' && $idEdit > 0) {
        $check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :c AND id_usuario != :id LIMIT 1");
        $check->execute(['c' => $correo, 'id' => $idEdit]);
        if ($check->fetch()) $errores[] = 'Ese correo pertenece a otro usuario.';

        if (empty($errores)) {
            if (!empty($clave)) {
                if (strlen($clave) < 6) {
                    $errores[] = 'Si cambias la contraseña, debe tener al menos 6 caracteres.';
                } else {
                    $hash = password_hash($clave, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE usuario SET nombre = :n, correo = :c, clave = :cl, rol = :r WHERE id_usuario = :id")
                        ->execute(['n' => $nombre, 'c' => $correo, 'cl' => $hash, 'r' => $rol, 'id' => $idEdit]);
                }
            } else {
                $pdo->prepare("UPDATE usuario SET nombre = :n, correo = :c, rol = :r WHERE id_usuario = :id")
                    ->execute(['n' => $nombre, 'c' => $correo, 'r' => $rol, 'id' => $idEdit]);
            }

            if (empty($errores)) {
                if ($idEdit === $idAdminActual) {
                    $_SESSION['nombre'] = $nombre;
                }
                header('Location: usuarios.php?msg=editado');
                exit;
            }
        }
    }
}

$usuarios = $pdo->query("SELECT * FROM usuario ORDER BY id_usuario ASC")->fetchAll();
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
        .panel-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .table thead th { background-color: #1f2937; color: #f9fafb; font-size: 0.78rem; text-transform: uppercase; padding: 0.9rem 1rem; border: none; }
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
                <a href="usuarios.php" class="nav-pill-custom active"><i class="bi bi-people-fill me-1"></i> Usuarios</a>
                <a href="producto_form.php" class="nav-pill-custom"><i class="bi bi-plus-circle-fill me-1 text-success"></i> Registrar Producto</a>
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

<main class="container-fluid px-4 py-4" style="max-width: 1300px;">

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-dark border-start border-4 border-success shadow-sm mb-4">
            <i class="bi bi-check-circle-fill text-success me-2"></i>
            <?php
                if ($_GET['msg'] === 'creado') echo 'Nuevo usuario registrado correctamente.';
                elseif ($_GET['msg'] === 'editado') echo 'Datos del usuario actualizados correctamente.';
                elseif ($_GET['msg'] === 'estado') echo 'Estado de acceso del usuario actualizado.';
                elseif ($_GET['msg'] === 'eliminado') echo 'Usuario eliminado permanentemente.';
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger border-start border-4 border-danger shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php
                if ($_GET['error'] === 'auto_accion') echo 'No puedes deshabilitar ni eliminar tu propia cuenta activa.';
                elseif ($_GET['error'] === 'tiene_registros') echo 'No se puede eliminar el usuario porque tiene movimientos registrados. Deshabilítalo en su lugar.';
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger mb-4">
            <ul class="mb-0 small"><?php foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="panel-card">
                <div class="p-3 bg-dark text-white fw-bold">
                    <i class="bi bi-person-plus-fill text-success me-2"></i>Nuevo Usuario
                </div>
                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="operacion" value="crear">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nombre Completo</label>
                            <input type="text" name="nombre" id="nuevoNombre" class="form-control" placeholder="Ej: Camila Soto" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Correo Electrónico <span class="text-success fw-normal">(Sugerido)</span></label>
                            <input type="email" name="correo" id="nuevoCorreo" class="form-control" placeholder="camila.soto@tecnostock.cl" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Contraseña Inicial</label>
                            <div class="input-group">
                                <input type="password" name="clave" id="nuevaClave" class="form-control" placeholder="Mínimo 6 caracteres" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('nuevaClave', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Rol / Perfil</label>
                            <select name="rol" class="form-select">
                                <option value="2" selected>Usuario</option>
                                <option value="1">Administrador</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-emerald w-100 py-2">
                            <i class="bi bi-save me-1"></i> Crear Cuenta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel-card">
                <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people text-success me-2"></i>Cuentas del Sistema</h6>
                    <span class="badge bg-dark mono"><?= count($usuarios) ?> usuarios</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Usuario</th>
                                <th>Rol</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <?php $activo = ((int)$u['estado'] === 1); ?>
                                <tr class="<?= !$activo ? 'opacity-50 bg-light' : '' ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= htmlspecialchars($u['nombre']) ?></div>
                                        <div class="text-muted small mono"><?= htmlspecialchars($u['correo']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= (int)$u['rol'] === 1 ? 'bg-dark' : 'bg-secondary' ?>">
                                            <?= obtenerNombreRol((int)$u['rol']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($activo): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">Deshabilitado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-dark" title="Editar usuario"
                                                    onclick='abrirEditarUsuario(<?= json_encode([
                                                        "id" => $u["id_usuario"],
                                                        "nombre" => $u["nombre"],
                                                        "correo" => $u["correo"],
                                                        "rol" => (int)$u["rol"]
                                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ((int)$u['id_usuario'] !== $idAdminActual): ?>
                                                <button type="button" class="btn <?= $activo ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                                        title="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?>"
                                                        onclick="confirmarUsuario('estado', <?= $u['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($u['nombre'])) ?>', <?= $activo ? 0 : 1 ?>)">
                                                    <i class="bi <?= $activo ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" title="Eliminar"
                                                        onclick="confirmarUsuario('eliminar', <?= $u['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($u['nombre'])) ?>', 0)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear text-success me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="operacion" value="editar">
                    <input type="hidden" name="id_usuario_edit" id="editUserId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nombre Completo</label>
                        <input type="text" name="nombre" id="editUserNombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Correo Electrónico</label>
                        <input type="email" name="correo" id="editUserCorreo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Rol / Perfil</label>
                        <select name="rol" id="editUserRol" class="form-select">
                            <option value="1">Administrador</option>
                            <option value="2">Usuario</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small">Nueva Contraseña <span class="text-muted fw-normal">(Opcional - dejar vacío para no cambiar)</span></label>
                        <div class="input-group">
                            <input type="password" name="clave" id="editUserClave" class="form-control" placeholder="••••••••">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('editUserClave', this)">
                                <i class="bi bi-eye"></i>
                            </button>
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
let correoEditadoManual = false;
document.getElementById('nuevoCorreo').addEventListener('input', () => {
    correoEditadoManual = true;
});

document.getElementById('nuevoNombre').addEventListener('input', function() {
    if (correoEditadoManual) return;
    const texto = this.value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '')
        .trim()
        .replace(/\s+/g, '.');
    document.getElementById('nuevoCorreo').value = texto ? `${texto}@tecnostock.cl` : '';
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

function abrirEditarUsuario(u) {
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUserNombre').value = u.nombre;
    document.getElementById('editUserCorreo').value = u.correo;
    document.getElementById('editUserRol').value = u.rol;
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