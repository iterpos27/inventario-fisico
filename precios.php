<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$productos = $pdo->query('SELECT codigo, descripcion FROM productos WHERE estado = 1 ORDER BY descripcion LIMIT 100')->fetchAll();

$pageTitle = 'Gestion de precios - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Productos</p><h1>Gestion de precios</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Codigo</th><th>Descripcion</th><th>Precio</th></tr></thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?><tr><td><?= e($producto['codigo']) ?></td><td><?= e($producto['descripcion']) ?></td><td class="text-secondary">Pendiente</td></tr><?php endforeach; ?>
                    <?php if (!$productos): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay productos cargados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
