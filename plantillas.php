<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$tomas = $pdo->query(
    "SELECT numero_toma, agencia, fecha_toma, nombre_toma
     FROM tomas_fisicas
     ORDER BY id DESC
     LIMIT 20"
)->fetchAll();

$pageTitle = 'Plantillas guardadas - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Conteo y borradores</p><h1>Plantillas guardadas</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Numero</th><th>Agencia</th><th>Fecha</th><th>Base</th></tr></thead>
                <tbody>
                    <?php foreach ($tomas as $toma): ?>
                        <tr>
                            <td><?= e($toma['numero_toma']) ?></td>
                            <td><?= e($toma['agencia']) ?></td>
                            <td><?= e($toma['fecha_toma']) ?></td>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tomas): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No hay plantillas generadas todavia.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
