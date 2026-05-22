<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$stmt = $pdo->prepare(
    "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, u.nombre
     FROM conteos c
     INNER JOIN usuarios u ON u.id = c.usuario_id
     WHERE DATE(c.fecha_inicio) = ?
     ORDER BY c.id DESC"
);
$stmt->execute([$fecha]);
$conteos = $stmt->fetchAll();

$pageTitle = 'Reportes diarios - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Reportes</p><h1>Reportes diarios</h1></div></div>
    <form class="content-panel mb-3" method="get">
        <label class="form-label" for="fecha">Fecha</label>
        <div class="search-row">
            <input class="form-control" id="fecha" name="fecha" type="date" value="<?= e($fecha) ?>">
            <button class="btn btn-primary" type="submit">Consultar</button>
        </div>
    </form>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Conteo</th><th>Usuario</th><th>Estado</th><th>Inicio</th><th>Fin</th></tr></thead>
                <tbody>
                    <?php foreach ($conteos as $conteo): ?>
                        <tr><td class="count-name"><?= nl2br(e($conteo['nombre_conteo'])) ?></td><td><?= e($conteo['nombre']) ?></td><td><?= e($conteo['estado']) ?></td><td><?= e($conteo['fecha_inicio']) ?></td><td><?= e($conteo['fecha_finalizacion'] ?? '-') ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$conteos): ?><tr><td colspan="5" class="text-center text-secondary py-4">Sin movimientos para la fecha.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
