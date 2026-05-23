<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$tomaId = (int) ($_GET['id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . BASE_URL . '/reportes.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*, u.nombre AS creado_por_nombre
     FROM tomas_fisicas t
     INNER JOIN usuarios u ON u.id = t.creado_por
     WHERE t.id = ?"
);
$stmt->execute([$tomaId]);
$toma = $stmt->fetch();
if (!$toma) {
    header('Location: ' . BASE_URL . '/reportes.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT tu.estado AS asignacion_estado, tu.fecha_asignacion,
            u.id AS usuario_id, u.nombre, u.usuario,
            c.id AS conteo_id, c.estado AS conteo_estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel,
            COUNT(d.id) AS lineas,
            COALESCE(SUM(d.cantidad), 0) AS unidades
     FROM toma_usuarios tu
     INNER JOIN usuarios u ON u.id = tu.usuario_id
     LEFT JOIN conteos c ON c.toma_id = tu.toma_id AND c.usuario_id = tu.usuario_id
     LEFT JOIN conteo_detalle d ON d.conteo_id = c.id
     WHERE tu.toma_id = ?
     GROUP BY tu.estado, tu.fecha_asignacion, u.id, u.nombre, u.usuario, c.id, c.estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel
     ORDER BY u.nombre"
);
$stmt->execute([$tomaId]);
$participantes = $stmt->fetchAll();

$asignados = count($participantes);
$finalizados = 0;
$enProceso = 0;
$lineas = 0;
$unidades = 0.0;
foreach ($participantes as $participante) {
    if ($participante['asignacion_estado'] === 'finalizado') {
        $finalizados++;
    }
    if ($participante['asignacion_estado'] === 'en_proceso') {
        $enProceso++;
    }
    $lineas += (int) $participante['lineas'];
    $unidades += (float) $participante['unidades'];
}
$pendientes = max(0, $asignados - $finalizados - $enProceso);

$pageTitle = 'Detalle de toma - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'edicion'): ?>
        <div class="alert alert-success">Conteo habilitado nuevamente para edicion.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'edicion'): ?>
        <div class="alert alert-danger">No se pudo habilitar el conteo.</div>
    <?php endif; ?>

    <div class="page-heading">
        <div>
            <p class="eyebrow">Toma fisica</p>
            <h1>Detalle de toma</h1>
        </div>
        <div class="quick-actions">
            <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-arrow-left"></i> Volver</a>
            <form method="post" action="<?= BASE_URL ?>/actions/cambiar_estado_toma.php" onsubmit="return confirm('<?= $toma['estado'] === 'abierta' ? 'Cerrar esta toma? Los usuarios no podran seguir editando.' : 'Reabrir esta toma para permitir edicion?' ?>');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                <input type="hidden" name="accion" value="<?= $toma['estado'] === 'abierta' ? 'cerrar' : 'reabrir' ?>">
                <button class="btn <?= $toma['estado'] === 'abierta' ? 'btn-outline-danger' : 'btn-outline-primary' ?>" type="submit">
                    <i class="bi <?= $toma['estado'] === 'abierta' ? 'bi-lock' : 'bi-unlock' ?>"></i>
                    <?= $toma['estado'] === 'abierta' ? 'Cerrar toma' : 'Reabrir toma' ?>
                </button>
            </form>
            <?php if ($lineas > 0): ?>
                <a class="btn btn-success" href="<?= BASE_URL ?>/actions/descargar_consolidado.php?toma_id=<?= (int) $toma['id'] ?>"><i class="bi bi-download"></i> Consolidado</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="content-panel mb-4">
        <div class="detail-header">
            <div>
                <span class="badge text-bg-<?= $toma['estado'] === 'finalizada' ? 'success' : 'warning' ?>"><?= e($toma['estado']) ?></span>
                <h2><?= nl2br(e($toma['nombre_toma'])) ?></h2>
            </div>
            <div class="detail-meta">
                <span>Creada por <?= e($toma['creado_por_nombre']) ?></span>
                <span><?= e($toma['fecha_creacion']) ?></span>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Participantes</span>
                <strong><?= $asignados ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card warning">
                <span>En proceso</span>
                <strong><?= $enProceso ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card success">
                <span>Finalizados</span>
                <strong><?= $finalizados ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Pendientes</span>
                <strong><?= $pendientes ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Lineas</span>
                <strong><?= $lineas ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Unidades</span>
                <strong><?= number_format($unidades, 2, '.', ',') ?></strong>
            </div>
        </div>
    </div>

    <section class="content-panel">
        <div class="section-title"><h2>Participantes</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Asignacion</th>
                        <th>Conteo</th>
                        <th>Lineas</th>
                        <th>Unidades</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participantes as $participante): ?>
                        <tr>
                            <td>
                                <strong><?= e($participante['nombre']) ?></strong><br>
                                <span class="text-secondary"><?= e($participante['usuario']) ?></span>
                            </td>
                            <td><span class="badge text-bg-<?= $participante['asignacion_estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($participante['asignacion_estado']) ?></span></td>
                            <td><?= e($participante['conteo_estado'] ?? 'sin iniciar') ?></td>
                            <td><?= (int) $participante['lineas'] ?></td>
                            <td><?= number_format((float) $participante['unidades'], 2, '.', ',') ?></td>
                            <td><?= e($participante['fecha_inicio'] ?? '-') ?></td>
                            <td><?= e($participante['fecha_finalizacion'] ?? '-') ?></td>
                            <td>
                                <?php if ($participante['conteo_estado'] === 'finalizado' && $participante['archivo_excel']): ?>
                                    <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/actions/descargar_excel.php?id=<?= (int) $participante['conteo_id'] ?>"><i class="bi bi-download"></i> Descargar</a>
                                    <form class="mt-2" method="post" action="<?= BASE_URL ?>/actions/habilitar_conteo_usuario.php" onsubmit="return confirm('Habilitar nuevamente este conteo para edicion?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                                        <input type="hidden" name="usuario_id" value="<?= (int) $participante['usuario_id'] ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-unlock"></i> Habilitar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$participantes): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-4">No hay participantes asignados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>

