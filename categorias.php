<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();
$iniciales = $pdo->query(
    "SELECT UPPER(LEFT(descripcion, 1)) AS inicial, COUNT(*) AS total
     FROM productos
     WHERE estado = 1
     GROUP BY UPPER(LEFT(descripcion, 1))
     ORDER BY inicial"
)->fetchAll();

$pageTitle = 'Categorias - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Productos</p><h1>Categorias</h1></div></div>
    <section class="content-panel mb-4">
        <div class="metric-card"><span>Productos activos</span><strong><?= $totalProductos ?></strong></div>
    </section>
    <section class="content-panel">
        <div class="section-title"><h2>Agrupacion por inicial de descripcion</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Inicial</th><th>Productos</th></tr></thead>
                <tbody>
                    <?php foreach ($iniciales as $row): ?><tr><td><?= e($row['inicial']) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?>
                    <?php if (!$iniciales): ?><tr><td colspan="2" class="text-center text-secondary py-4">No hay productos cargados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
