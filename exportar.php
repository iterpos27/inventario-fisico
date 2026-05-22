<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$tomas = $pdo->query("SELECT id, nombre_toma, estado FROM tomas_fisicas ORDER BY id DESC LIMIT 100")->fetchAll();

$pageTitle = 'Exportar PDF/Excel - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Reportes</p><h1>Exportar PDF/Excel</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Toma fisica</th><th>Estado</th><th>Excel</th></tr></thead>
                <tbody>
                    <?php foreach ($tomas as $toma): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                            <td><?= e($toma['estado']) ?></td>
                            <td><a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/actions/descargar_consolidado.php?toma_id=<?= (int) $toma['id'] ?>">Excel consolidado</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tomas): ?><tr><td colspan="3" class="text-center text-secondary py-4">No hay tomas para exportar.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
