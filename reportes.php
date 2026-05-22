<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$estado = $_GET['estado'] ?? '';
$params = [];
$sql = "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel,
               u.nombre, t.numero_toma, t.estado AS toma_estado
        FROM conteos c
        INNER JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN tomas_fisicas t ON t.id = c.toma_id
        WHERE 1 = 1";
if (in_array($estado, ['borrador', 'finalizado'], true)) {
    $sql .= ' AND c.estado = ?';
    $params[] = $estado;
}
if (current_user_role() !== 'admin') {
    $sql .= ' AND c.usuario_id = ?';
    $params[] = (int) $_SESSION['usuario_id'];
}
$sql .= ' ORDER BY c.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteos = $stmt->fetchAll();

$tomas = [];
if (current_user_role() === 'admin') {
    $stmt = $pdo->query(
        "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion,
                COUNT(DISTINCT tu.id) AS asignados,
                COUNT(DISTINCT CASE WHEN tu.estado = 'finalizado' THEN tu.id END) AS finalizados,
                COUNT(DISTINCT c.id) AS conteos_creados
         FROM tomas_fisicas t
         LEFT JOIN toma_usuarios tu ON tu.toma_id = t.id
         LEFT JOIN conteos c ON c.toma_id = t.id
         GROUP BY t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion
         ORDER BY t.id DESC"
    );
    $tomas = $stmt->fetchAll();
}

$pageTitle = 'Reportes - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Auditoria</p>
            <h1>Reportes</h1>
        </div>
    </div>

    <?php if (current_user_role() === 'admin'): ?>
    <section class="content-panel mb-4">
        <div class="section-title"><h2>Consolidado por toma fisica</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Toma fisica</th><th>Estado</th><th>Usuarios</th><th>Finalizados</th><th>Fin</th><th>Excel</th></tr></thead>
                <tbody>
                    <?php foreach ($tomas as $toma): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                            <td><span class="badge text-bg-<?= $toma['estado'] === 'finalizada' ? 'success' : 'warning' ?>"><?= e($toma['estado']) ?></span></td>
                            <td><?= (int) $toma['asignados'] ?></td>
                            <td><?= (int) $toma['finalizados'] ?></td>
                            <td><?= e($toma['fecha_finalizacion'] ?? '-') ?></td>
                            <td>
                                <?php if ((int) $toma['conteos_creados'] > 0): ?>
                                    <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/actions/descargar_consolidado.php?toma_id=<?= (int) $toma['id'] ?>"><i class="bi bi-download"></i> Consolidado</a>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tomas): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">No hay tomas para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <form class="filter-tabs mb-3" method="get">
        <button class="btn <?= $estado === '' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="" type="submit">Todos</button>
        <button class="btn <?= $estado === 'borrador' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="borrador" type="submit">Borradores</button>
        <button class="btn <?= $estado === 'finalizado' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="finalizado" type="submit">Finalizados</button>
    </form>
    <section class="content-panel">
        <div class="section-title"><h2>Conteos individuales</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Conteo</th>
                        <th>Estado</th>
                        <th>Usuario</th>
                        <th>Fecha inicio</th>
                        <th>Fecha finalizacion</th>
                        <th>Excel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conteos as $conteo): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($conteo['nombre_conteo'])) ?></td>
                            <td><span class="badge text-bg-<?= $conteo['estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($conteo['estado']) ?></span></td>
                            <td><?= e($conteo['nombre']) ?></td>
                            <td><?= e($conteo['fecha_inicio']) ?></td>
                            <td><?= e($conteo['fecha_finalizacion'] ?? '-') ?></td>
                            <td>
                                <?php if ($conteo['estado'] === 'finalizado' && $conteo['archivo_excel']): ?>
                                    <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/actions/descargar_excel.php?id=<?= (int) $conteo['id'] ?>"><i class="bi bi-download"></i> Descargar</a>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$conteos): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">No hay conteos para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
