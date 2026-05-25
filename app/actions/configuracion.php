<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$pageTitle = 'Configuracion - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Administracion</p><h1>Configuracion del sistema</h1></div></div>
    <div class="row g-4">
        <div class="col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Logo del sistema</h2></div>
                <form action="<?= action_url('logo_procesar') ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input class="form-control form-control-lg mb-3" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required>
                    <button class="btn btn-outline-primary btn-lg" type="submit"><i class="bi bi-image"></i> Guardar logo</button>
                </form>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Version</h2></div>
                <p class="text-secondary mb-0">Version instalada: <?= e(APP_VERSION) ?></p>
            </section>
        </div>
    </div>
</main>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>


