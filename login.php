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

        if ($usuario && password_verify($clave, $usuario['clave'])) {
            if ($usuario['estado'] == 1) {
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
    <title>Iniciar Sesión - TecnoStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-login {
            max-width: 420px;
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="card card-login mx-auto p-4 bg-white border-0">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">TecnoStock</h3>
            <p class="text-muted small">Sistema de Control de Inventario</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'debe_iniciar_sesion'): ?>
            <div class="alert alert-warning py-2 small" role="alert">
                Debes iniciar sesión para acceder al sistema.
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="formLogin" novalidate>
            <div class="mb-3">
                <label for="correo" class="form-label fw-semibold">Correo Electrónico</label>
                <input type="email" class="form-control" id="correo" name="correo" placeholder="ejemplo@tecnostock.cl" required>
                <div class="invalid-feedback" id="feedbackCorreo">Ingrese un correo válido.</div>
            </div>

            <div class="mb-3">
                <label for="clave" class="form-label fw-semibold">Contraseña</label>
                <input type="password" class="form-control" id="clave" name="clave" placeholder="••••••••" required>
                <div class="invalid-feedback">La contraseña no puede estar vacía.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-semibold mt-2">Ingresar al Sistema</button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <p class="text-muted small mb-1"><strong>Cuentas de prueba:</strong></p>
            <p class="text-muted small mb-0"><code>admin@tecnostock.cl</code> | Clave: <code>admin123</code></p>
            <p class="text-muted small mb-0"><code>bodega@tecnostock.cl</code> | Clave: <code>admin123</code></p>
            <p class="text-muted small mb-0"><code>vendedor@tecnostock.cl</code> | Clave: <code>admin123</code></p>
        </div>
    </div>
</div>

<script>
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