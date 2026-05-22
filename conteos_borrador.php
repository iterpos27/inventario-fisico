<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if (current_user_role() === 'admin') {
    $stmt = $pdo->query(
        "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion,
                COUNT(tu.id) AS asignados,
                SUM(CASE WHEN tu.estado = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
                SUM(CASE WHEN tu.estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
         FROM tomas_fisicas t
         LEFT JOIN toma_usuarios tu ON tu.toma_id = t.id
         WHERE t.estado = 'abierta'
         GROUP BY t.id, t.nombre_toma, t.estado, t.fecha_creacion
         ORDER BY t.id DESC"
    );
    $tomas = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT t.id AS toma_id, t.nombre_toma, t.fecha_creacion, tu.estado AS asignacion_estado,
                c.id AS conteo_id, c.estado AS conteo_estado, COUNT(d.id) AS lineas
         FROM toma_usuarios tu
         INNER JOIN tomas_fisicas t ON t.id = tu.toma_id
         LEFT JOIN conteos c ON c.toma_id = t.id AND c.usuario_id = tu.usuario_id
         LEFT JOIN conteo_detalle d ON d.conteo_id = c.id
         WHERE tu.usuario_id = ? AND t.estado = 'abierta'
         GROUP BY t.id, t.nombre_toma, t.fecha_creacion, tu.estado, c.id, c.estado
         ORDER BY t.id DESC"
    );
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $tomas = $stmt->fetchAll();
}

$pageTitle = 'Borradores - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Conteos pendientes</p>
            <h1><?= current_user_role() === 'admin' ? 'Tomas abiertas' : 'Mis borradores' ?></h1>
        </div>
        <?php if (current_user_role() === 'admin'): ?>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-plus-circle"></i> Crear toma</a>
        <?php endif; ?>
    </div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <?php if (current_user_role() === 'admin'): ?>
                        <tr><th>Toma fisica</th><th>Asignados</th><th>En proceso</th><th>Finalizados</th><th>Creacion</th></tr>
                    <?php else: ?>
                        <tr><th>Toma fisica</th><th>Estado</th><th>Lineas</th><th>Creacion</th><th></th></tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($tomas as $toma): ?>
                        <?php if (current_user_role() === 'admin'): ?>
                            <tr>
                                <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                                <td><?= (int) $toma['asignados'] ?></td>
                                <td><?= (int) $toma['en_proceso'] ?></td>
                                <td><?= (int) $toma['finalizados'] ?></td>
                                <td><?= e($toma['fecha_creacion']) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                                <td><span class="badge text-bg-warning"><?= e($toma['asignacion_estado']) ?></span></td>
                                <td><?= (int) $toma['lineas'] ?></td>
                                <td><?= e($toma['fecha_creacion']) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/actions/iniciar_conteo.php?toma_id=<?= (int) $toma['toma_id'] ?>"><?= (int) $toma['lineas'] > 0 ? 'Continuar' : 'Empezar' ?></a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$tomas): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay borradores disponibles.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
