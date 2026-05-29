<?php
$navLogoPath = asset_url('img/logo.png');
foreach (['png', 'jpg', 'jpeg', 'webp'] as $logoExt) {
    if (file_exists(PUBLIC_PATH . "/assets/img/logo.{$logoExt}")) {
        $navLogoPath = asset_url("img/logo.{$logoExt}");
        break;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$pageLabel = match ($currentPage) {
    'dashboard.php' => 'Panel',
    'conteo.php', 'toma_detalle.php' => 'Conteo',
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
        <a class="sidebar-brand" href="<?= page_url('dashboard') ?>">
            <img src="<?= $navLogoPath ?>" alt="Logo" class="sidebar-logo" width="54" height="54" onerror="this.style.display='none'">
            <span>
                <strong><?= APP_NAME ?></strong>
                <small>SISTEMA DE INVENTARIO</small>
            </span>
        </a>

        <nav class="sidebar-nav" aria-label="Menu principal">
            <?php if (role_can(current_user_role(), 'admin')): ?>
            <a class="sidebar-link<?= nav_active('dashboard.php', $currentPage) ?>" href="<?= page_url('dashboard') ?>"><i class="bi bi-speedometer2 icon-panel"></i><span>Dashboard</span></a>
            <?php endif; ?>

            <?php if (role_can(current_user_role(), 'admin')): ?>
            <details class="nav-section nav-count<?= nav_section_class(['conteo.php', 'toma_detalle.php'], $currentPage) ?>"<?= nav_section_open(['conteo.php', 'toma_detalle.php'], $currentPage) ?>>
                <summary><i class="bi bi-clipboard-check icon-count"></i><span>Conteo y Borradores</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('conteo.php', $currentPage) ?>" href="<?= page_url('conteo') ?>">Nuevo conteo</a>
                </div>
            </details>
            <?php elseif (current_user_can('count')): ?>
            <a class="sidebar-link<?= nav_active('conteo.php', $currentPage) ?>" href="<?= page_url('conteo') ?>"><i class="bi bi-clipboard-check icon-count"></i><span>Conteos disponibles</span></a>
            <?php endif; ?>

            <?php if (current_user_can('reports')): ?>
            <details class="nav-section nav-reports<?= nav_section_class(['reportes.php'], $currentPage) ?>"<?= nav_section_open(['reportes.php'], $currentPage) ?>>
                <summary><i class="bi bi-file-earmark-spreadsheet icon-reports"></i><span>Reportes</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('reportes.php', $currentPage) ?>" href="<?= page_url('reportes') ?>">Reportes generales</a>
                </div>
            </details>
            <?php endif; ?>

            <?php if (role_can(current_user_role(), 'admin')): ?>
            <a class="sidebar-link<?= nav_active('productos.php', $currentPage) ?>" href="<?= page_url('productos') ?>"><i class="bi bi-box-seam icon-products"></i><span>Productos</span></a>

            <details class="nav-section nav-admin<?= nav_section_class(['usuarios.php', 'agencias.php', 'configuracion.php'], $currentPage) ?>"<?= nav_section_open(['usuarios.php', 'agencias.php', 'configuracion.php'], $currentPage) ?>>
                <summary><i class="bi bi-gear icon-admin"></i><span>Administracion</span><i class="bi bi-chevron-down nav-chevron"></i></summary>
                <div class="nav-submenu">
                    <a class="sidebar-sublink<?= nav_active('usuarios.php', $currentPage) ?>" href="<?= page_url('usuarios') ?>">Usuarios</a>
                    <a class="sidebar-sublink<?= nav_active('agencias.php', $currentPage) ?>" href="<?= page_url('agencias') ?>">Agencias</a>
                    <a class="sidebar-sublink<?= nav_active('configuracion.php', $currentPage) ?>" href="<?= page_url('configuracion') ?>">Configuracion del sistema</a>
                </div>
            </details>
            <?php endif; ?>
        </nav>

    </aside>

    <div class="mobile-menu-backdrop" data-mobile-menu-close></div>
    <nav class="mobile-slide-nav<?= role_can(current_user_role(), 'admin') ? ' mobile-slide-nav-admin' : ' mobile-slide-nav-user' ?>" id="mobileMenu" aria-label="Menu movil">
        <div class="mobile-slide-head">
            <strong>Menu</strong>
            <button type="button" data-mobile-menu-close aria-label="Cerrar menu"><i class="bi bi-list"></i></button>
        </div>
        <?php if (role_can(current_user_role(), 'admin')): ?>
            <a class="<?= nav_active('dashboard.php', $currentPage) ?>" href="<?= page_url('dashboard') ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            <details class="mobile-nav-section"<?= nav_section_open(['conteo.php', 'toma_detalle.php'], $currentPage) ?>>
                <summary><i class="bi bi-clipboard-check"></i><span>Conteo y Borradores</span><i class="bi bi-chevron-down"></i></summary>
                <a class="<?= nav_active('conteo.php', $currentPage) ?>" href="<?= page_url('conteo') ?>"><i class="bi bi-plus-circle"></i><span>Nuevo conteo</span></a>
            </details>
            <?php if (current_user_can('reports')): ?>
            <details class="mobile-nav-section"<?= nav_section_open(['reportes.php'], $currentPage) ?>>
                <summary><i class="bi bi-file-earmark-spreadsheet"></i><span>Reportes</span><i class="bi bi-chevron-down"></i></summary>
                <a class="<?= nav_active('reportes.php', $currentPage) ?>" href="<?= page_url('reportes') ?>"><i class="bi bi-graph-up"></i><span>Reportes generales</span></a>
            </details>
            <?php endif; ?>
            <a class="<?= nav_active('productos.php', $currentPage) ?>" href="<?= page_url('productos') ?>"><i class="bi bi-box-seam"></i><span>Productos</span></a>
            <details class="mobile-nav-section"<?= nav_section_open(['usuarios.php', 'agencias.php', 'configuracion.php'], $currentPage) ?>>
                <summary><i class="bi bi-gear"></i><span>Administracion</span><i class="bi bi-chevron-down"></i></summary>
                <a class="<?= nav_active('usuarios.php', $currentPage) ?>" href="<?= page_url('usuarios') ?>"><i class="bi bi-people"></i><span>Usuarios</span></a>
                <a class="<?= nav_active('agencias.php', $currentPage) ?>" href="<?= page_url('agencias') ?>"><i class="bi bi-building"></i><span>Agencias</span></a>
                <a class="<?= nav_active('configuracion.php', $currentPage) ?>" href="<?= page_url('configuracion') ?>"><i class="bi bi-sliders"></i><span>Configuracion</span></a>
            </details>
            <a href="<?= page_url('logout') ?>" class="mobile-logout-link"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesion</span></a>
        <?php else: ?>
            <?php if (current_user_can('reports')): ?>
            <a class="<?= nav_active('reportes.php', $currentPage) ?>" href="<?= page_url('reportes') ?>"><i class="bi bi-graph-up"></i><span>Reportes generales</span></a>
            <?php endif; ?>
            <?php if (current_user_can('count')): ?>
            <a class="<?= nav_active('conteo.php', $currentPage) ?>" href="<?= page_url('conteo') ?>"><i class="bi bi-clipboard-check"></i><span>Conteo inventario</span></a>
            <?php endif; ?>
            <a href="<?= page_url('logout') ?>" class="mobile-logout-link"><i class="bi bi-box-arrow-right"></i><span>Cerrar sesion</span></a>
        <?php endif; ?>
    </nav>

    <div class="app-main">
        <header class="app-topbar">
            <button class="mobile-menu-toggle" type="button" data-mobile-menu-toggle aria-controls="mobileMenu" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-title">
                <span><?= current_user_name() ?></span>
                <h1><?= e($pageLabel) ?></h1>
            </div>
            <div class="topbar-actions">
                <details class="topbar-user-menu">
                    <summary>
                        <i class="bi bi-person-circle"></i>
                        <span>
                            <strong><?= current_user_name() ?></strong>
                            <small><?= role_can(current_user_role(), 'admin') ? 'Administrador' : (current_user_can('reports') ? 'Reportes' : 'Usuario') ?></small>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </summary>
                    <div class="topbar-user-dropdown">
                        <a href="<?= page_url('logout') ?>"><i class="bi bi-box-arrow-right"></i> Salir</a>
                    </div>
                </details>
            </div>
        </header>
        <script>
        (() => {
            const body = document.body;
            const menu = document.getElementById('mobileMenu');
            const toggle = document.querySelector('[data-mobile-menu-toggle]');
            const closers = document.querySelectorAll('[data-mobile-menu-close]');
            if (!menu || !toggle) return;

            const setOpen = (open) => {
                body.classList.toggle('mobile-menu-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            toggle.addEventListener('click', () => setOpen(!body.classList.contains('mobile-menu-open')));
            closers.forEach((closer) => closer.addEventListener('click', () => setOpen(false)));
            menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });
        })();
        </script>



