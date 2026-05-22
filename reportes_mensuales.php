<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$mes = $_GET['mes'] ?? date('Y-m');
$stmt = $pdo->prepare(
    "SELECT DATE(fecha_inicio) AS dia,
            COUNT(*) AS conteos,
            SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
     FROM conteos
     WHERE DATE_FORMAT(fecha_inicio, '%Y-%m') = ?
     GROUP BY DATE(fecha_inicio)
     ORDER BY dia DESC"
);
$stmt->execute([$mes]);
$dias = $stmt->fetchAll();

$pageTitle = 'Reportes mensuales - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Reportes</p><h1>Reportes mensuales</h1></div></div>
    <form class="content-panel mb-3" method="get">
        <label class="form-label" for="mes">Mes</label>
        <div class="search-row">
            <input class="form-control" id="mes" name="mes" type="month" value="<?= e($mes) ?>">
            <button class="btn btn-primary" type="submit">Consultar</button>
        </div>
    </form>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Dia</th><th>Conteos</th><th>Finalizados</th></tr></thead>
                <tbody>
                    <?php foreach ($dias as $dia): ?>
                        <tr><td><?= e($dia['dia']) ?></td><td><?= (int) $dia['conteos'] ?></td><td><?= (int) $dia['finalizados'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$dias): ?><tr><td colspan="3" class="text-center text-secondary py-4">Sin movimientos para el mes.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
