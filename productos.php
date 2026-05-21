<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$productosJsVersion = file_exists(__DIR__ . '/assets/js/productos.js')
    ? (string) filemtime(__DIR__ . '/assets/js/productos.js')
    : APP_VERSION;

$pageTitle = 'Productos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalogo</p>
            <h1>Productos</h1>
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Importar</a>
    </div>
    <section class="count-tool mb-3">
        <label class="form-label" for="buscarProductoAdmin">Buscar producto</label>
        <div class="position-relative">
            <input class="form-control form-control-lg search-input" id="buscarProductoAdmin" placeholder="Codigo o descripcion" autocomplete="off">
        </div>
    </section>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Codigo</th><th>Descripcion</th></tr></thead>
                <tbody id="productosResultados">
                    <tr><td colspan="2" class="text-center text-secondary py-4">Busque por codigo o descripcion.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/productos.js?v=<?= e($productosJsVersion) ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
