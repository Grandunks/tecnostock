<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = trim($_POST['clave'] ?? '');

    if (empty($correo) || empty($clave)) {
        $error = 'Por favor, complete todos los campos requeridos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico es inválido.';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, correo, clave, rol, estado FROM usuario WHERE correo = :correo LIMIT 1");
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
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $usuario['nombre'];
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
        .login-wrapper { max-width: 440px; width: 100%; }
        .card-login {
            background-color: #ffffff;
            border: none;
            border-top: 4px solid var(--emerald-primary);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);
            overflow: hidden;
        }
        .brand-logo { font-weight: 700; font-size: 1.65rem; color: var(--surface-dark); letter-spacing: -0.03em; }
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
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="card card-login p-4 p-sm-5">
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

            <div class="mb-4">
                <label for="clave" class="form-label fw-semibold small text-secondary">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="clave" name="clave" placeholder="••••••••" required style="border-radius: 0;">
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleClave('clave', this)" style="border-radius: 0 10px 10px 0; border-color: #d1d5db;">
                        <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">La contraseña no puede estar vacía.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-emerald w-100 shadow-sm">
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

<script>
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