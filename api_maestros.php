<?php
// api_maestros.php
require_once 'config/conexion.php';
require_once 'config/auth.php';

verificarRol([1]);
header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if ($accion === 'siguiente_codigo') {
    $idCat = (int)($_GET['id_categoria'] ?? 0);
    $stmtCat = $pdo->prepare("SELECT nombre FROM categoria WHERE id_categoria = :id LIMIT 1");
    $stmtCat->execute(['id' => $idCat]);
    $cat = $stmtCat->fetch();

    if (!$cat) {
        echo json_encode(['ok' => false]);
        exit;
    }

    // Generar prefijo de 3 letras limpiando tildes
    $limpio = iconv('UTF-8', 'ASCII//TRANSLIT', $cat['nombre']);
    $soloLetras = preg_replace('/[^A-Za-z]/', '', $limpio);
    $prefijo = strtoupper(substr($soloLetras, 0, 3));
    if (strlen($prefijo) < 3) $prefijo = str_pad($prefijo, 3, 'X');

    // Buscar el número más alto existente con ese prefijo
    $stmtCod = $pdo->prepare("SELECT codigo FROM producto WHERE codigo LIKE :pref");
    $stmtCod->execute(['pref' => $prefijo . '-%']);
    $codigos = $stmtCod->fetchAll(PDO::FETCH_COLUMN);

    $maxNum = 0;
    foreach ($codigos as $c) {
        if (preg_match('/^' . preg_quote($prefijo, '/') . '-(\d+)$/', $c, $m)) {
            $num = (int)$m[1];
            if ($num > $maxNum) $maxNum = $num;
        }
    }

    $siguiente = $prefijo . '-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
    echo json_encode(['ok' => true, 'codigo' => $siguiente]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_categoria') {
    $nombre = trim($_POST['nombre'] ?? '');
    $desc   = trim($_POST['descripcion'] ?? '');

    if (empty($nombre)) {
        echo json_encode(['ok' => false, 'error' => 'El nombre de la categoría es obligatorio.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO categoria (nombre, descripcion, estado) VALUES (:n, :d, 1)");
        $stmt->execute(['n' => $nombre, 'd' => $desc ?: null]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'nombre' => $nombre]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Esa categoría ya existe.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_proveedor') {
    $rut         = strtoupper(trim($_POST['rut'] ?? ''));
    $razonSocial = trim($_POST['razon_social'] ?? '');
    $contacto    = trim($_POST['contacto'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $correo      = trim($_POST['correo'] ?? '');

    if (empty($rut) || empty($razonSocial)) {
        echo json_encode(['ok' => false, 'error' => 'El RUT y la Razón Social son obligatorios.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO proveedor (rut, razon_social, contacto, telefono, correo, estado) VALUES (:r, :rs, :c, :t, :co, 1)");
        $stmt->execute([
            'r'  => $rut,
            'rs' => $razonSocial,
            'c'  => $contacto ?: null,
            't'  => $telefono ?: null,
            'co' => $correo ?: null
        ]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'nombre' => $razonSocial]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'El RUT del proveedor ya está registrado.']);
    }
    exit;
}