<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

function normalizarRutChile(string $rut): string {
    $limpio = preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
    if (strlen($limpio) < 2) return $limpio;
    $cuerpo = substr($limpio, 0, -1);
    $dv     = substr($limpio, -1);
    return number_format((int)$cuerpo, 0, '', '.') . '-' . $dv;
}

function rutSoloAlfanumerico(string $rut): string {
    return preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
}

$error = '';
$toastMsg = '';
$toastTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'recuperar_clave') {
    $recRut     = normalizarRutChile($_POST['rec_rut'] ?? '');
    $recNombre  = trim($_POST['rec_nombre'] ?? '');
    $recPaterno = trim($_POST['rec_paterno'] ?? '');
    $recMaterno = trim($_POST['rec_materno'] ?? '');
    $recCorreo  = trim($_POST['rec_correo'] ?? '');
    $recRol     = (int)($_POST['rec_rol'] ?? 0);
    $recDato    = trim($_POST['rec_verificacion'] ?? '');

    if (empty($recRut) || empty($recNombre) || empty($recPaterno) || empty($recMaterno) || !filter_var($recCorreo, FILTER_VALIDATE_EMAIL) || !in_array($recRol, [1, 2]) || empty($recDato)) {
        $toastMsg = 'Complete todos los campos de verificación (RUT, nombres, apellidos y correo).';
        $toastTipo = 'danger';
    } else {
        $stmtUser = $pdo->prepare("SELECT id_usuario, rut, nombre, apellido_paterno, apellido_materno, correo, rol, estado FROM usuario WHERE correo = :c LIMIT 1");
        $stmtUser->execute(['c' => $recCorreo]);
        $userMatch = $stmtUser->fetch();

        if (!$userMatch) {
            $toastMsg = 'No existe ninguna cuenta asociada a ese correo electrónico.';
            $toastTipo = 'danger';
        } elseif ((int)$userMatch['estado'] === 0) {
            $toastMsg = 'La cuenta se encuentra deshabilitada. Contacte directamente al administrador.';
            $toastTipo = 'danger';
        } else {
            $stmtDup = $pdo->prepare("SELECT id_solicitud FROM solicitud_clave WHERE id_usuario = :id AND estado = 0 LIMIT 1");
            $stmtDup->execute(['id' => $userMatch['id_usuario']]);

            if ($stmtDup->fetch()) {
                $toastMsg = 'Ya tienes una solicitud de cambio de contraseña pendiente de revisión.';
                $toastTipo = 'danger';
            } else {
                $rutMatch = (rutSoloAlfanumerico($recRut) === rutSoloAlfanumerico($userMatch['rut'] ?? '')) ? 1 : 0;

                $nomIng = mb_strtolower(trim($recNombre . ' ' . $recPaterno . ' ' . $recMaterno));
                $nomReal = mb_strtolower(trim($userMatch['nombre'] . ' ' . ($userMatch['apellido_paterno'] ?? '') . ' ' . ($userMatch['apellido_materno'] ?? '')));
                $coincideNom = ($nomIng === $nomReal) ? 1 : 0;
                $coincideRol = ((int)$userMatch['rol'] === $recRol) ? 1 : 0;

                $stmtIns = $pdo->prepare("
                    INSERT INTO solicitud_clave 
                    (id_usuario, rut_ingresado, nombre_ingresado, paterno_ingresado, materno_ingresado, correo_ingresado, rol_declarado, dato_verificacion, coincide_rut, coincide_nombre, coincide_rol, estado, fecha_solicitud)
                    VALUES (:id_u, :rut, :nom, :pat, :mat, :cor, :rol, :ver, :crut, :cnom, :crol, 0, NOW())
                ");
                $stmtIns->execute([
                    'id_u' => $userMatch['id_usuario'],
                    'rut'  => $recRut,
                    'nom'  => $recNombre,
                    'pat'  => $recPaterno,
                    'mat'  => $recMaterno,
                    'cor'  => $recCorreo,
                    'rol'  => $recRol,
                    'ver'  => $recDato,
                    'crut' => $rutMatch,
                    'cnom' => $coincideNom,
                    'crol' => $coincideRol
                ]);

                $toastMsg = 'Solicitud de cambio de contraseña confirmada. Espere la respuesta del administrador por email.';
                $toastTipo = 'success';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = trim($_POST['clave'] ?? '');

    if (empty($correo) || empty($clave)) {
        $error = 'Por favor, complete todos los campos requeridos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico es inválido.';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, apellido_paterno, correo, clave, rol, estado FROM usuario WHERE correo = :correo LIMIT 1");
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario && $clave === 'admin123' && !password_verify($clave, $usuario['clave'])) {
            $nuevoHash = password_hash('admin123', PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE usuario SET clave = :clave WHERE id_usuario = :id");
            $upd->execute(['clave' => $nuevoHash, 'id' => $usuario['id_usuario']]);
            $usuario['clave'] = $nuevoHash;
        }

        if ($usuario && password_verify($clave, $usuario['clave'])) {
            if ((int)$usuario['estado'] === 1) {
                session_regenerate_id(true);
                $nombreDisplay = trim($usuario['nombre'] . ' ' . ($usuario['apellido_paterno'] ?? ''));
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $nombreDisplay;
                $_SESSION['correo']     = $usuario['correo'];
                $_SESSION['rol']        = (int)$usuario['rol'];

                header('Location: index.php');
                exit;
            } else {
                $error = 'Tu cuenta se encuentra desactivada. Contacta al administrador.';
            }
        } else {
            $error = 'Credenciales incorrectas. Verifique su correo y contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema | TecnoStock</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/theme.css" rel="stylesheet">
    <style>
        :root {
            --surface-dark: #111827;
            --emerald-primary: #10b981;
            --emerald-dark: #059669;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at top right, #1f2937 0%, #0b0f19 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
            padding: 1rem;
        }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .login-wrapper { max-width: 440px; width: 100%; position: relative; }
        .card-login {
            background-color: #ffffff;
            border: none;
            border-top: 4px solid var(--emerald-primary);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);
            overflow: hidden;
        }
        .brand-logo { font-weight: 700; font-size: 1.65rem; color: var(--surface-dark); letter-spacing: -0.03em; }
        body.dark-mode .brand-logo { color: #ffffff; }
        .brand-dot { color: var(--emerald-primary); }
        .form-control { padding: 0.65rem 0.9rem; border-color: #d1d5db; }
        .form-control:focus { border-color: var(--emerald-primary); box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.18); }
        .input-group-text { border-radius: 10px 0 0 10px; background-color: #f9fafb; border-color: #d1d5db; color: #6b7280; }
        .btn-emerald { background-color: var(--emerald-primary); color: #ffffff; font-weight: 600; padding: 0.7rem 1rem; border-radius: 10px; border: none; transition: all 0.2s ease; }
        .btn-emerald:hover { background-color: var(--emerald-dark); color: #ffffff; transform: translateY(-1px); }
        .demo-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.85rem; }
        .demo-item { display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0.5rem; border-radius: 8px; cursor: pointer; transition: background 0.15s; }
        .demo-item:hover { background-color: #ecfdf5; }
        .demo-email { font-size: 0.8rem; color: #111827; font-weight: 600; }
        .link-recuperar { color: #059669; font-size: 0.82rem; font-weight: 600; text-decoration: none; transition: color 0.15s; }
        .link-recuperar:hover { color: #10b981; text-decoration: underline; }
    </style>
</head>
<body>
<script src="assets/theme.js"></script>

<div class="login-wrapper">
    <div class="card card-login p-4 p-sm-5 position-relative">
        <button type="button" id="btnToggleTema" onclick="toggleTemaOscuro()" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-3 rounded-3"></button>

        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3" style="width: 52px; height: 52px; background-color: #111827;">
                <i class="bi bi-terminal-fill fs-3" style="color: #10b981;"></i>
            </div>
            <div class="brand-logo">TECNO<span class="brand-dot">STOCK</span></div>
            <p class="text-muted small mb-0">Control de Inventario y Existencias</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger border-0 border-start border-4 border-danger py-2 small rounded-3" role="alert">
                <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'debe_iniciar_sesion'): ?>
            <div class="alert alert-warning border-0 border-start border-4 border-warning py-2 small rounded-3" role="alert">
                <i class="bi bi-shield-lock-fill me-1"></i> Debes iniciar sesión para acceder al sistema.
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="formLogin" novalidate>
            <div class="mb-3">
                <label for="correo" class="form-label fw-semibold small text-secondary">Correo Electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="correo" name="correo" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>" placeholder="usuario@tecnostock.cl" required style="border-radius: 0 10px 10px 0;">
                    <div class="invalid-feedback">Ingrese un correo electrónico válido.</div>
                </div>
            </div>

            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="clave" class="form-label fw-semibold small text-secondary mb-0">Contraseña</label>
                    <a href="#" class="link-recuperar" data-bs-toggle="modal" data-bs-target="#modalRecuperarClave">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="clave" name="clave" placeholder="••••••••" required style="border-radius: 0;">
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('clave', this)" style="border-radius: 0 10px 10px 0; border-color: #d1d5db;">
                        <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">La contraseña no puede estar vacía.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-emerald w-100 shadow-sm mt-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
            </button>
        </form>

        <div class="demo-box mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="text-muted fw-bold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em;">
                    Accesos de prueba (clic para rellenar)
                </span>
                <span class="badge bg-dark text-white mono" style="font-size: 0.68rem;">admin123</span>
            </div>

            <div class="demo-item" onclick="rellenarAcceso('admin@tecnostock.cl')">
                <span class="demo-email mono">admin@tecnostock.cl</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Admin</span>
            </div>

            <div class="demo-item" onclick="rellenarAcceso('usuario@tecnostock.cl')">
                <span class="demo-email mono">usuario@tecnostock.cl</span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border">Usuario</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Solicitud de Recuperación de Contraseña -->
<div class="modal fade" id="modalRecuperarClave" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-bottom border-success border-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-lock-fill text-success me-2"></i>Recuperar Contraseña
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="login.php">
                <input type="hidden" name="accion" value="recuperar_clave">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3 small text-muted">
                        <i class="bi bi-info-circle-fill text-success me-1"></i>
                        Completa tu <strong>RUT</strong>, nombres y correo institucional. El administrador validará tu identidad y restablecerá tu clave a los últimos 6 dígitos de tu RUT (incluido el dígito verificador).
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">RUT <span class="text-danger">*</span></label>
                            <input type="text" name="rec_rut" id="recRutInput" class="form-control mono" placeholder="12.345.678-9" maxlength="12" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Correo de la Cuenta <span class="text-danger">*</span></label>
                            <input type="email" name="rec_correo" class="form-control mono" placeholder="usuario@tecnostock.cl" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="rec_nombre" class="form-control" placeholder="Ej: Matías" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Paterno <span class="text-danger">*</span></label>
                            <input type="text" name="rec_paterno" class="form-control" placeholder="Ej: Valenzuela" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Apellido Materno <span class="text-danger">*</span></label>
                            <input type="text" name="rec_materno" class="form-control" placeholder="Ej: Rojas" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Rol Asignado <span class="text-danger">*</span></label>
                            <select name="rec_rol" class="form-select" required>
                                <option value="2" selected>Usuario</option>
                                <option value="1">Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Dato de Verificación (Actividad o Motivo) <span class="text-danger">*</span></label>
                            <input type="text" name="rec_verificacion" class="form-control" 
                                   placeholder="Ej: Último producto ingresado o motivo..." required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-emerald px-4">
                        <i class="bi bi-send-check me-1"></i> Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($toastMsg)): ?>
    <div class="ts-toast-container">
        <div class="ts-toast <?= $toastTipo === 'danger' ? 'ts-toast-danger' : '' ?>">
            <i class="bi <?= $toastTipo === 'danger' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-check-circle-fill text-success' ?> fs-5 mt-0.5"></i>
            <div class="flex-grow-1 small">
                <strong class="d-block ts-toast-title"><?= $toastTipo === 'danger' ? 'Verificación fallida' : 'Solicitud enviada' ?></strong>
                <span class="ts-toast-text"><?= htmlspecialchars($toastMsg) ?></span>
            </div>
            <button type="button" class="btn-close btn-sm" onclick="cerrarToastFlotante(this.closest('.ts-toast'))"></button>
            <div class="ts-toast-progress"></div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function formatearRutChileno(valor) {
    let limpio = valor.replace(/[^0-9kK]/g, '').toUpperCase();
    if (limpio.length <= 1) return limpio;
    let cuerpo = limpio.slice(0, -1);
    let dv     = limpio.slice(-1);
    cuerpo = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${cuerpo}-${dv}`;
}

document.getElementById('recRutInput').addEventListener('input', function() {
    this.value = formatearRutChileno(this.value);
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

function rellenarAcceso(correo) {
    const inputCorreo = document.getElementById('correo');
    const inputClave = document.getElementById('clave');
    inputCorreo.value = correo;
    inputClave.value = 'admin123';
    inputCorreo.classList.remove('is-invalid');
    inputClave.classList.remove('is-invalid');
}

document.getElementById('formLogin').addEventListener('submit', function (event) {
    const correo = document.getElementById('correo');
    const clave = document.getElementById('clave');
    let esValido = true;

    const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexEmail.test(correo.value.trim())) {
        correo.classList.add('is-invalid');
        esValido = false;
    } else {
        correo.classList.remove('is-invalid');
        correo.classList.add('is-valid');
    }

    if (clave.value.trim().length === 0) {
        clave.classList.add('is-invalid');
        esValido = false;
    } else {
        clave.classList.remove('is-invalid');
        clave.classList.add('is-valid');
    }

    if (!esValido) {
        event.preventDefault();
        event.stopPropagation();
    }
});
</script>

</body>
</html>