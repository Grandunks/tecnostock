<?php
$paginaActual    = basename($_SERVER['PHP_SELF']);
$navNombre       = $_SESSION['nombre'] ?? 'Usuario';
$navRol          = isset($_SESSION['rol']) ? (int)$_SESSION['rol'] : 2;
$totalSolicitudesNav = 0;

if ($navRol === 1 && isset($pdo)) {
    try {
        $totalSolicitudesNav = (int)$pdo->query("SELECT COUNT(*) FROM solicitud_clave WHERE estado = 0")->fetchColumn();
    } catch (PDOException $e) {
        $totalSolicitudesNav = 0;
    }
}
?>
<style>
    .btn-profile-pill {
        background-color: rgba(255, 255, 255, 0.06);
        color: #f9fafb;
        border: 1px solid rgba(16, 185, 129, 0.35);
        border-radius: 10px;
        padding: 0.35rem 0.75rem;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .btn-profile-pill:hover {
        background-color: rgba(16, 185, 129, 0.16);
        border-color: #10b981;
        color: #ffffff;
    }
    .btn-profile-pill.active-profile {
        background-color: #10b981;
        border-color: #10b981;
        color: #111827;
    }
</style>
<header class="top-header sticky-top shadow-sm">
    <div class="container-fluid px-4 d-flex flex-wrap align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="index.php" class="brand-logo me-2"><i class="bi bi-terminal-fill brand-dot me-1"></i>TECNO<span class="brand-dot">STOCK</span></a>
            <nav class="d-flex gap-1 flex-wrap align-items-center">
                <a href="index.php" class="nav-pill-custom <?= $paginaActual === 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-layers-fill me-1"></i> Catálogo
                </a>
                <a href="movimientos.php" class="nav-pill-custom <?= $paginaActual === 'movimientos.php' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right me-1"></i> Movimientos
                </a>
                <a href="historial_movimientos.php" class="nav-pill-custom <?= $paginaActual === 'historial_movimientos.php' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-1"></i> Registro Global
                </a>
                <a href="producto_form.php" class="nav-pill-custom <?= $paginaActual === 'producto_form.php' ? 'active' : '' ?>">
                    <i class="bi bi-plus-circle-fill me-1 <?= $paginaActual !== 'producto_form.php' ? 'text-success' : '' ?>"></i> Registrar Producto
                </a>
                <?php if ($navRol === 1): ?>
                    <a href="usuarios.php" class="nav-pill-custom <?= $paginaActual === 'usuarios.php' ? 'active' : '' ?>">
                        <i class="bi bi-people-fill me-1"></i> Usuarios
                        <?php if ($totalSolicitudesNav > 0): ?>
                            <span class="badge bg-warning text-dark ms-1 mono"><?= $totalSolicitudesNav ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </nav>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="perfil.php" class="btn-profile-pill <?= $paginaActual === 'perfil.php' ? 'active-profile' : '' ?> small fw-semibold d-flex align-items-center gap-2" title="Editar mis datos personales">
                <i class="bi bi-person-circle <?= $paginaActual === 'perfil.php' ? 'text-dark' : 'text-success' ?>"></i>
                <span class="d-none d-md-inline"><?= htmlspecialchars($navNombre) ?></span>
                <span class="badge <?= $paginaActual === 'perfil.php' ? 'bg-dark text-success' : 'bg-success bg-opacity-25 text-success border border-success border-opacity-50' ?>" style="font-size: 0.65rem;">
                    <?= strtoupper(obtenerNombreRol($navRol)) ?>
                </span>
            </a>
            <button type="button" id="btnToggleTema" onclick="toggleTemaOscuro()" class="btn btn-sm btn-outline-secondary text-white px-2"></button>
            <a href="logout.php" class="btn btn-sm btn-outline-danger px-3 fw-semibold">
                <i class="bi bi-box-arrow-right me-1"></i>Salir
            </a>
        </div>
    </div>
</header>