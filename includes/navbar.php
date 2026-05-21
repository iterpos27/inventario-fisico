<?php
$navLogoPath = BASE_URL . '/assets/img/logo.png';
foreach (['png', 'jpg', 'jpeg', 'webp'] as $logoExt) {
    if (file_exists(dirname(__DIR__) . "/assets/img/logo.{$logoExt}")) {
        $navLogoPath = BASE_URL . "/assets/img/logo.{$logoExt}";
        break;
    }
}
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/dashboard.php">
            <img src="<?= $navLogoPath ?>" alt="Logo" class="brand-logo" onerror="this.style.display='none'">
            <span><?= APP_NAME ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-speedometer2"></i> Panel</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-phone"></i> Conteo</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/conteos_borrador.php"><i class="bi bi-journal-text"></i> Borradores</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/reportes.php"><i class="bi bi-file-earmark-excel"></i> Reportes</a></li>
                <?php if (current_user_role() === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/productos.php"><i class="bi bi-box-seam"></i> Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Importar</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="btn btn-sm btn-light ms-lg-2 mt-2 mt-lg-0" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
            </ul>
        </div>
    </div>
</nav>
