<?php
$navLogoPath = BASE_URL . '/assets/img/logo.png';
foreach (['png', 'jpg', 'jpeg', 'webp'] as $logoExt) {
    if (file_exists(dirname(__DIR__) . "/assets/img/logo.{$logoExt}")) {
        $navLogoPath = BASE_URL . "/assets/img/logo.{$logoExt}";
        break;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$pageLabel = match ($currentPage) {
    'dashboard.php' => 'Panel',
    'conteo.php' => 'Conteo',
    'reportes.php' => 'Reportes',
    'productos.php' => 'Productos',
    'importar_productos.php' => 'Administracion',
    default => 'Inventario',
};

function nav_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? ' active' : '';
}

$layoutStarted = true;
?>
<div class="app-layout">
    <aside class="app-sidebar">
        <a class="sidebar-brand" href="<?= BASE_URL ?>/dashboard.php">
            <img src="<?= $navLogoPath ?>" alt="Logo" class="sidebar-logo" width="54" height="54" onerror="this.style.display='none'">
            <span>
                <strong><?= APP_NAME ?></strong>
                <small>SISTEMA DE INVENTARIO</small>
            </span>
        </a>

        <div class="sidebar-user">
            <strong><?= current_user_role() === 'admin' ? 'Administrador' : 'Usuario' ?></strong>
            <span><?= current_user_name() ?></span>
        </div>

        <nav class="sidebar-nav" aria-label="Menu principal">
            <a class="sidebar-link<?= nav_active('dashboard.php', $currentPage) ?>" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-bar-chart-line"></i> Panel</a>
            <a class="sidebar-link<?= nav_active('conteo.php', $currentPage) ?>" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-phone"></i> Conteo</a>
            <a class="sidebar-link<?= nav_active('reportes.php', $currentPage) ?>" href="<?= BASE_URL ?>/reportes.php"><i class="bi bi-file-earmark-spreadsheet"></i> Reportes</a>
            <?php if (current_user_role() === 'admin'): ?>
                <a class="sidebar-link<?= nav_active('productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/productos.php"><i class="bi bi-box-seam"></i> Productos</a>
                <a class="sidebar-link<?= nav_active('importar_productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Administracion</a>
            <?php endif; ?>
        </nav>

        <a class="sidebar-logout" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Salir</a>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div>
                <span><?= current_user_name() ?></span>
                <h1><?= e($pageLabel) ?></h1>
            </div>
        </header>
