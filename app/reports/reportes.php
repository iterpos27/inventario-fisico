<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/toma_summary.php';
require_once APP_INCLUDES_PATH . '/toma_lifecycle.php';
require_permission('reports');
$reportStartedAt = microtime(true);
cerrar_tomas_vencidas($pdo);

$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
    $fechaDesde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
    $fechaHasta = date('Y-m-d');
}
if ($fechaDesde > $fechaHasta) {
    [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
}

$tomas = [];
if (current_user_can('reports')) {
    $stmt = $pdo->query(
        "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion, t.archivo_excel,
                r.updated_at AS resumen_actualizado,
                COALESCE(r.usuarios_asignados, 0) AS asignados,
                COALESCE(r.usuarios_finalizados, 0) AS finalizados,
                COALESCE(r.conteos_creados, 0) AS conteos_creados,
                COALESCE(r.conteos_con_detalle, 0) AS conteos_con_detalle
         FROM tomas_fisicas t
         LEFT JOIN toma_resumen r ON r.toma_id = t.id
         ORDER BY t.id DESC"
    );
    $tomas = $stmt->fetchAll();
    $missingSummaries = array_filter($tomas, static fn($toma) => $toma['resumen_actualizado'] === null);
    if ($missingSummaries) {
        foreach ($missingSummaries as $toma) {
            refresh_toma_summary($pdo, (int) $toma['id']);
        }
        $stmt = $pdo->query(
            "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion, t.archivo_excel,
                    r.updated_at AS resumen_actualizado,
                    COALESCE(r.usuarios_asignados, 0) AS asignados,
                    COALESCE(r.usuarios_finalizados, 0) AS finalizados,
                    COALESCE(r.conteos_creados, 0) AS conteos_creados,
                    COALESCE(r.conteos_con_detalle, 0) AS conteos_con_detalle
             FROM tomas_fisicas t
             LEFT JOIN toma_resumen r ON r.toma_id = t.id
             ORDER BY t.id DESC"
        );
        $tomas = $stmt->fetchAll();
    }
}

$rangeSql = "SELECT DATE(fecha_inicio) AS dia,
                    COUNT(*) AS conteos,
                    SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados,
                    SUM(CASE WHEN estado = 'borrador' THEN 1 ELSE 0 END) AS borradores
             FROM conteos
             WHERE fecha_inicio >= ? AND fecha_inicio < DATE_ADD(?, INTERVAL 1 DAY)";
$rangeParams = [$fechaDesde . ' 00:00:00', $fechaHasta . ' 00:00:00'];
if (!role_can(current_user_role(), 'admin')) {
    $rangeSql .= ' AND usuario_id = ?';
    $rangeParams[] = (int) $_SESSION['usuario_id'];
}
$rangeSql .= ' GROUP BY DATE(fecha_inicio) ORDER BY dia DESC';
$stmt = $pdo->prepare($rangeSql);
$stmt->execute($rangeParams);
$diasRango = $stmt->fetchAll();
monitor_duration($pdo, 'reportes_generales', $reportStartedAt, 1000, ['desde' => $fechaDesde, 'hasta' => $fechaHasta]);

$pageTitle = 'Reportes - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Auditoria</p>
            <h1>Reportes</h1>
        </div>
    </div>

    <section class="content-panel mb-4">
        <div class="section-title"><h2>Reporte por rango de fechas</h2></div>
        <form class="row g-3 mb-3" method="get" action="<?= page_url('reportes') ?>">
            <div class="col-12 col-md-4">
                <label class="form-label" for="fecha_desde">Desde</label>
                <input class="form-control" id="fecha_desde" name="fecha_desde" type="date" value="<?= e($fechaDesde) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="fecha_hasta">Hasta</label>
                <input class="form-control" id="fecha_hasta" name="fecha_hasta" type="date" value="<?= e($fechaHasta) ?>">
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-search"></i> Consultar</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Dia</th><th>Conteos</th><th>Borradores</th><th>Finalizados</th></tr></thead>
                <tbody>
                    <?php foreach ($diasRango as $dia): ?>
                        <tr>
                            <td><?= e($dia['dia']) ?></td>
                            <td><?= (int) $dia['conteos'] ?></td>
                            <td><?= (int) $dia['borradores'] ?></td>
                            <td><?= (int) $dia['finalizados'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$diasRango): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">Sin movimientos para el rango seleccionado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (role_can(current_user_role(), 'admin')): ?>
    <section class="content-panel mb-4">
        <div class="section-title"><h2>Exportaciones por toma fisica</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Toma fisica</th><th>Estado</th><th>Usuarios</th><th>Finalizados</th><th>Fin</th><th>Excel</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($tomas as $toma): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                            <td><span class="badge text-bg-<?= $toma['estado'] === 'finalizada' ? 'success' : 'warning' ?>"><?= e($toma['estado']) ?></span></td>
                            <td><?= (int) $toma['asignados'] ?></td>
                            <td><?= (int) $toma['finalizados'] ?></td>
                            <td><?= e($toma['fecha_finalizacion'] ?? '-') ?></td>
                            <td>
                                <?php if ((int) $toma['conteos_con_detalle'] > 0): ?>
                                    <form method="post" action="<?= action_url('generar_consolidado') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                                        <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-download"></i> Descargar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= page_url('toma_detalle') ?>?id=<?= (int) $toma['id'] ?>">Ver detalle</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tomas): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-4">No hay tomas para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

</main>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>


