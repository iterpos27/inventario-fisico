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
    'conteo.php', 'toma_detalle.php' => 'Conteo',
    'reportes.php' => 'Reportes',
    'productos.php' => 'Productos',
    'importar_productos.php' => 'Administracion',
    default => 'Inventario',
};

function nav_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? ' active' : '';
}

function nav_section_open(array $pages, string $currentPage): string
{
    return in_array($currentPage, $pages, true) ? ' open' : '';
}

function nav_section_class(array $pages, string $currentPage): string
{
    return in_array($currentPage, $pages, true) ? ' is-active' : '';
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
            <details class="nav-section nav-panel<?= nav_section_class(['dashboard.php'], $currentPage) ?>"<?= nav_section_open(['dashboard.php'], $currentPage) ?>>
                <summary><i class="bi bi-speedometer2 icon-panel"></i><span>Panel</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('dashboard.php', $currentPage) ?>" href="<?= BASE_URL ?>/dashboard.php">Dashboard general</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/dashboard.php">Estadisticas rapidas</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/dashboard.php">Tendencias</a>
                </div>
            </details>

            <details class="nav-section nav-count<?= nav_section_class(['conteo.php', 'toma_detalle.php'], $currentPage) ?>"<?= nav_section_open(['conteo.php', 'toma_detalle.php'], $currentPage) ?>>
                <summary><i class="bi bi-clipboard-check icon-count"></i><span>Conteo y Borradores</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('conteo.php', $currentPage) ?>" href="<?= BASE_URL ?>/conteo.php"><?= current_user_role() === 'admin' ? 'Nuevo conteo' : 'Conteos disponibles' ?></a>
                    <a class="sidebar-sublink<?= nav_active('toma_detalle.php', $currentPage) ?>" href="<?= BASE_URL ?>/conteo.php">Historial de conteos</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/conteo.php">Nuevo borrador</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/conteo.php">Lista de borradores</a>
                    <a class="sidebar-sublink is-muted" href="<?= BASE_URL ?>/conteo.php">Plantillas guardadas</a>
                </div>
            </details>

            <details class="nav-section nav-reports<?= nav_section_class(['reportes.php'], $currentPage) ?>"<?= nav_section_open(['reportes.php'], $currentPage) ?>>
                <summary><i class="bi bi-file-earmark-spreadsheet icon-reports"></i><span>Reportes</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('reportes.php', $currentPage) ?>" href="<?= BASE_URL ?>/reportes.php">Reportes diarios</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/reportes.php">Reportes mensuales</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/reportes.php">Exportar PDF/Excel</a>
                </div>
            </details>

            <?php if (current_user_role() === 'admin'): ?>
            <details class="nav-section nav-products<?= nav_section_class(['productos.php'], $currentPage) ?>"<?= nav_section_open(['productos.php'], $currentPage) ?>>
                <summary><i class="bi bi-box-seam icon-products"></i><span>Productos</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/productos.php">Inventario</a>
                    <a class="sidebar-sublink is-muted" href="<?= BASE_URL ?>/productos.php">Categorias</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/importar_productos.php">Agregar producto</a>
                    <a class="sidebar-sublink is-muted" href="<?= BASE_URL ?>/productos.php">Gestion de precios</a>
                </div>
            </details>

            <details class="nav-section nav-admin<?= nav_section_class(['importar_productos.php'], $currentPage) ?>"<?= nav_section_open(['importar_productos.php'], $currentPage) ?>>
                <summary><i class="bi bi-gear icon-admin"></i><span>Administracion</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/importar_productos.php">Usuarios</a>
                    <a class="sidebar-sublink is-muted" href="<?= BASE_URL ?>/importar_productos.php">Roles y permisos</a>
                    <a class="sidebar-sublink<?= nav_active('importar_productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/importar_productos.php">Configuracion del sistema</a>
                    <a class="sidebar-sublink is-muted" href="<?= BASE_URL ?>/importar_productos.php">Respaldos y seguridad</a>
                </div>
            </details>
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
