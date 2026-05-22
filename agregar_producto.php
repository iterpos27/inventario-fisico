<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pageTitle = 'Agregar producto - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Productos</p><h1>Agregar producto</h1></div></div>
    <section class="content-panel">
        <form action="<?= BASE_URL ?>/actions/importar_productos_procesar.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label" for="archivo">Archivo .xlsx, .xls o .csv</label>
                <input class="form-control form-control-lg" type="file" id="archivo" name="archivo" accept=".xlsx,.xls,.csv" required>
            </div>
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-upload"></i> Importar productos</button>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
