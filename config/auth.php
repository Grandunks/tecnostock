<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarAutenticacion(): void {
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: login.php?error=debe_iniciar_sesion');
        exit;
    }
}

function verificarRol(array $rolesPermitidos): void {
    verificarAutenticacion();
    if (!in_array((int)$_SESSION['rol'],$rolesPermitidos, true)) {
        header('Location: index.php?error=acceso_denegado');
        exit;
    }
}

function obtenerNombreRol(int $rol): string {
    return $rol === 1 ? 'Administrador' : 'Usuario';
}