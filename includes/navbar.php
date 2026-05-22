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
    'dashboard.php', 'estadisticas.php', 'tendencias.php' => 'Panel',
    'conteo.php', 'toma_detalle.php', 'historial_conteos.php' => 'Conteo',
    'reportes.php' => 'Reportes',
    'productos.php' => 'Productos',
    'usuarios.php', 'agencias.php', 'configuracion.php' => 'Administracion',
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

        <nav class="sidebar-nav" aria-label="Menu principal">
            <details class="nav-section nav-panel<?= nav_section_class(['dashboard.php', 'estadisticas.php', 'tendencias.php'], $currentPage) ?>"<?= nav_section_open(['dashboard.php', 'estadisticas.php', 'tendencias.php'], $currentPage) ?>>
                <summary><i class="bi bi-speedometer2 icon-panel"></i><span>Panel</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('dashboard.php', $currentPage) ?>" href="<?= BASE_URL ?>/dashboard.php">Dashboard general</a>
                    <a class="sidebar-sublink<?= nav_active('estadisticas.php', $currentPage) ?>" href="<?= BASE_URL ?>/estadisticas.php">Estadisticas rapidas</a>
                    <a class="sidebar-sublink<?= nav_active('tendencias.php', $currentPage) ?>" href="<?= BASE_URL ?>/tendencias.php">Tendencias</a>
                </div>
            </details>

            <details class="nav-section nav-count<?= nav_section_class(['conteo.php', 'toma_detalle.php', 'historial_conteos.php'], $currentPage) ?>"<?= nav_section_open(['conteo.php', 'toma_detalle.php', 'historial_conteos.php'], $currentPage) ?>>
                <summary><i class="bi bi-clipboard-check icon-count"></i><span>Conteo y Borradores</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('conteo.php', $currentPage) ?>" href="<?= BASE_URL ?>/conteo.php"><?= current_user_role() === 'admin' ? 'Nuevo conteo' : 'Conteos disponibles' ?></a>
                    <a class="sidebar-sublink<?= nav_active('historial_conteos.php', $currentPage) ?>" href="<?= BASE_URL ?>/historial_conteos.php">Historial de conteos</a>
                </div>
            </details>

            <a class="sidebar-link<?= nav_active('reportes.php', $currentPage) ?>" href="<?= BASE_URL ?>/reportes.php"><i class="bi bi-file-earmark-spreadsheet icon-reports"></i><span>Reportes</span></a>

            <?php if (current_user_role() === 'admin'): ?>
            <a class="sidebar-link<?= nav_active('productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/productos.php"><i class="bi bi-box-seam icon-products"></i><span>Productos</span></a>

            <details class="nav-section nav-admin<?= nav_section_class(['usuarios.php', 'agencias.php', 'configuracion.php'], $currentPage) ?>"<?= nav_section_open(['usuarios.php', 'agencias.php', 'configuracion.php'], $currentPage) ?>>
                <summary><i class="bi bi-gear icon-admin"></i><span>Administracion</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('usuarios.php', $currentPage) ?>" href="<?= BASE_URL ?>/usuarios.php">Usuarios</a>
                    <a class="sidebar-sublink<?= nav_active('agencias.php', $currentPage) ?>" href="<?= BASE_URL ?>/agencias.php">Agencias</a>
                    <a class="sidebar-sublink<?= nav_active('configuracion.php', $currentPage) ?>" href="<?= BASE_URL ?>/configuracion.php">Configuracion del sistema</a>
                </div>
            </details>
            <?php endif; ?>
        </nav>

    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div>
                <span><?= current_user_name() ?></span>
                <h1><?= e($pageLabel) ?></h1>
            </div>
            <details class="topbar-user-menu">
                <summary>
                    <i class="bi bi-person-circle"></i>
                    <span>
                        <strong><?= current_user_name() ?></strong>
                        <small><?= current_user_role() === 'admin' ? 'Administrador' : 'Usuario' ?></small>
                    </span>
                    <i class="bi bi-chevron-down"></i>
                </summary>
                <div class="topbar-user-dropdown">
                    <a href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>
                </div>
            </details>
        </header>
