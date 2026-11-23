<?php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarAutenticacion();

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$estado = isset($_GET['estado']) ? (int)$_GET['estado'] : -1;

if ($id > 0 && in_array($estado, [0, 1], true)) {
    $stmt = $pdo->prepare("UPDATE producto SET estado = :estado WHERE id_producto = :id");
    $stmt->execute(['estado' => $estado, 'id' => $id]);
    header('Location: index.php?msg=estado_actualizado');
    exit;
}

header('Location: index.php');
exit;