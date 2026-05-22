<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$dias = $pdo->query(
    "SELECT DATE(fecha_inicio) AS dia, COUNT(*) AS conteos
     FROM conteos
     GROUP BY DATE(fecha_inicio)
     ORDER BY dia DESC
     LIMIT 15"
)->fetchAll();

$pageTitle = 'Tendencias - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Panel</p><h1>Tendencias</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Dia</th><th>Conteos iniciados</th></tr></thead>
                <tbody>
                    <?php foreach ($dias as $dia): ?><tr><td><?= e($dia['dia']) ?></td><td><?= (int) $dia['conteos'] ?></td></tr><?php endforeach; ?>
                    <?php if (!$dias): ?><tr><td colspan="2" class="text-center text-secondary py-4">No hay datos suficientes.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
