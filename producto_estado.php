<?php
// producto_estado.php
require_once 'config/conexion.php';
require_once 'config/auth.php';

// Solo el Administrador (rol 1) puede activar o desactivar productos
verificarRol([1]);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$nuevoEstado = isset($_GET['estado']) ? (int)$_GET['estado'] : 0;

// Asegurar que el estado solo sea 0 o 1
$nuevoEstado = ($nuevoEstado === 1) ? 1 : 0;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE producto SET estado = :estado WHERE id_producto = :id");
        $stmt->execute([
            'estado' => $nuevoEstado,
            'id'     => $id
        ]);
    } catch (PDOException $e) {
        header('Location: index.php?error=fallo_actualizacion');
        exit;
    }
}

header('Location: index.php?msg=estado_actualizado');
exit;