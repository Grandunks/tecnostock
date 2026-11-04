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
    if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
        header('Location: index.php?error=acceso_denegado');
        exit;
    }
}

function obtenerNombreRol(int $rol): string {
    return match ($rol) {
        1 => 'Administrador',
        2 => 'Bodeguero',
        3 => 'Vendedor',
        default => 'Desconocido',
    };
}