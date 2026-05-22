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
    'conteo.php', 'toma_detalle.php', 'historial_conteos.php', 'plantillas.php' => 'Conteo',
    'reportes.php', 'reportes_diarios.php', 'reportes_mensuales.php', 'exportar.php' => 'Reportes',
    'productos.php', 'categorias.php', 'agregar_producto.php', 'precios.php' => 'Productos',
    'importar_productos.php', 'usuarios.php', 'roles.php', 'configuracion.php', 'respaldos.php' => 'Administracion',
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

            <details class="nav-section nav-count<?= nav_section_class(['conteo.php', 'toma_detalle.php', 'historial_conteos.php', 'plantillas.php'], $currentPage) ?>"<?= nav_section_open(['conteo.php', 'toma_detalle.php', 'historial_conteos.php', 'plantillas.php'], $currentPage) ?>>
                <summary><i class="bi bi-clipboard-check icon-count"></i><span>Conteo y Borradores</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('conteo.php', $currentPage) ?>" href="<?= BASE_URL ?>/conteo.php"><?= current_user_role() === 'admin' ? 'Nuevo conteo' : 'Conteos disponibles' ?></a>
                    <a class="sidebar-sublink<?= nav_active('historial_conteos.php', $currentPage) ?>" href="<?= BASE_URL ?>/historial_conteos.php">Historial de conteos</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/conteo.php">Nuevo borrador</a>
                    <a class="sidebar-sublink" href="<?= BASE_URL ?>/conteo.php">Lista de borradores</a>
                    <a class="sidebar-sublink<?= nav_active('plantillas.php', $currentPage) ?>" href="<?= BASE_URL ?>/plantillas.php">Plantillas guardadas</a>
                </div>
            </details>

            <details class="nav-section nav-reports<?= nav_section_class(['reportes.php', 'reportes_diarios.php', 'reportes_mensuales.php', 'exportar.php'], $currentPage) ?>"<?= nav_section_open(['reportes.php', 'reportes_diarios.php', 'reportes_mensuales.php', 'exportar.php'], $currentPage) ?>>
                <summary><i class="bi bi-file-earmark-spreadsheet icon-reports"></i><span>Reportes</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('reportes_diarios.php', $currentPage) ?>" href="<?= BASE_URL ?>/reportes_diarios.php">Reportes diarios</a>
                    <a class="sidebar-sublink<?= nav_active('reportes_mensuales.php', $currentPage) ?>" href="<?= BASE_URL ?>/reportes_mensuales.php">Reportes mensuales</a>
                    <a class="sidebar-sublink<?= nav_active('exportar.php', $currentPage) ?>" href="<?= BASE_URL ?>/exportar.php">Exportar PDF/Excel</a>
                </div>
            </details>

            <?php if (current_user_role() === 'admin'): ?>
            <details class="nav-section nav-products<?= nav_section_class(['productos.php', 'categorias.php', 'agregar_producto.php', 'precios.php'], $currentPage) ?>"<?= nav_section_open(['productos.php', 'categorias.php', 'agregar_producto.php', 'precios.php'], $currentPage) ?>>
                <summary><i class="bi bi-box-seam icon-products"></i><span>Productos</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('productos.php', $currentPage) ?>" href="<?= BASE_URL ?>/productos.php">Inventario</a>
                    <a class="sidebar-sublink<?= nav_active('categorias.php', $currentPage) ?>" href="<?= BASE_URL ?>/categorias.php">Categorias</a>
                    <a class="sidebar-sublink<?= nav_active('agregar_producto.php', $currentPage) ?>" href="<?= BASE_URL ?>/agregar_producto.php">Agregar producto</a>
                    <a class="sidebar-sublink<?= nav_active('precios.php', $currentPage) ?>" href="<?= BASE_URL ?>/precios.php">Gestion de precios</a>
                </div>
            </details>

            <details class="nav-section nav-admin<?= nav_section_class(['importar_productos.php', 'usuarios.php', 'roles.php', 'configuracion.php', 'respaldos.php'], $currentPage) ?>"<?= nav_section_open(['importar_productos.php', 'usuarios.php', 'roles.php', 'configuracion.php', 'respaldos.php'], $currentPage) ?>>
                <summary><i class="bi bi-gear icon-admin"></i><span>Administracion</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('usuarios.php', $currentPage) ?>" href="<?= BASE_URL ?>/usuarios.php">Usuarios</a>
                    <a class="sidebar-sublink<?= nav_active('roles.php', $currentPage) ?>" href="<?= BASE_URL ?>/roles.php">Roles y permisos</a>
                    <a class="sidebar-sublink<?= nav_active('configuracion.php', $currentPage) ?>" href="<?= BASE_URL ?>/configuracion.php">Configuracion del sistema</a>
                    <a class="sidebar-sublink<?= nav_active('respaldos.php', $currentPage) ?>" href="<?= BASE_URL ?>/respaldos.php">Respaldos y seguridad</a>
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
