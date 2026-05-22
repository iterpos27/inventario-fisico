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
    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= e($_GET['msg']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>
    <section class="content-panel">
        <form class="row g-3" action="<?= BASE_URL ?>/actions/agregar_producto.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="col-md-4">
                <label class="form-label" for="codigo">Codigo</label>
                <input class="form-control form-control-lg" id="codigo" name="codigo" required>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="descripcion">Descripcion</label>
                <input class="form-control form-control-lg" id="descripcion" name="descripcion" required>
            </div>
            <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-plus-circle"></i> Guardar producto</button></div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
