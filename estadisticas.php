<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$stats = [
    'Productos activos' => (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn(),
    'Tomas abiertas' => (int) $pdo->query("SELECT COUNT(*) FROM tomas_fisicas WHERE estado = 'abierta'")->fetchColumn(),
    'Conteos borrador' => (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'borrador'")->fetchColumn(),
    'Conteos finalizados' => (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'finalizado'")->fetchColumn(),
];

$pageTitle = 'Estadisticas rapidas - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Panel</p><h1>Estadisticas rapidas</h1></div></div>
    <div class="row g-3">
        <?php foreach ($stats as $label => $value): ?>
            <div class="col-6 col-lg-4"><div class="metric-card"><span><?= e($label) ?></span><strong><?= $value ?></strong></div></div>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
