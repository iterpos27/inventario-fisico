<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

if (current_user_role() !== 'admin') {
    header('Location: ' . BASE_URL . '/conteo.php');
    exit;
}

$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();
$tomasAbiertas = (int) $pdo->query("SELECT COUNT(*) FROM tomas_fisicas WHERE estado = 'abierta'")->fetchColumn();
$tomasFinalizadas = (int) $pdo->query("SELECT COUNT(*) FROM tomas_fisicas WHERE estado = 'finalizada'")->fetchColumn();
$conteosBorrador = (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'borrador'")->fetchColumn();
$conteosFinalizados = (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'finalizado'")->fetchColumn();
$usuariosActivos = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 1")->fetchColumn();
$totalTomas = $tomasAbiertas + $tomasFinalizadas;
$totalConteos = $conteosBorrador + $conteosFinalizados;
$porcentajeTomasFinalizadas = $totalTomas > 0 ? round(($tomasFinalizadas / $totalTomas) * 100) : 0;
$porcentajeConteosFinalizados = $totalConteos > 0 ? round(($conteosFinalizados / $totalConteos) * 100) : 0;

$stmt = $pdo->query(
    "SELECT DATE(fecha_inicio) AS dia, COUNT(*) AS conteos
     FROM conteos
     WHERE fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(fecha_inicio)
     ORDER BY dia"
);
$tendenciaConteos = $stmt->fetchAll();
$maxConteosDia = max(1, ...array_map(static fn (array $row): int => (int) $row['conteos'], $tendenciaConteos ?: [['conteos' => 1]]));

$stmt = $pdo->query(
    "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion,
            COUNT(tu.id) AS asignados,
            SUM(CASE WHEN tu.estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
     FROM tomas_fisicas t
     LEFT JOIN toma_usuarios tu ON tu.toma_id = t.id
     GROUP BY t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_finalizacion
     ORDER BY t.id DESC
     LIMIT 8"
);
$ultimasTomas = $stmt->fetchAll();

$pageTitle = 'Panel - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Inventario fisico</p>
            <h1>Panel de control</h1>
        </div>
        <div class="user-pill"><i class="bi bi-person-circle"></i> <?= current_user_name() ?></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <span>Productos</span>
                <strong><?= $totalProductos ?></strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card warning">
                <span>Tomas abiertas</span>
                <strong><?= $tomasAbiertas ?></strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card success">
                <span>Conteos finalizados</span>
                <strong><?= $conteosFinalizados ?></strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <span>Usuarios activos</span>
                <strong><?= $usuariosActivos ?></strong>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Estado de tomas</h2></div>
                <div class="donut-card">
                    <div class="donut-chart" style="--value: <?= $porcentajeTomasFinalizadas ?>;">
                        <span><?= $porcentajeTomasFinalizadas ?>%</span>
                    </div>
                    <div class="chart-legend">
                        <span><i class="legend-dot success"></i> Finalizadas: <?= $tomasFinalizadas ?></span>
                        <span><i class="legend-dot warning"></i> Abiertas: <?= $tomasAbiertas ?></span>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-4">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Avance de conteos</h2></div>
                <div class="donut-card">
                    <div class="donut-chart chart-blue" style="--value: <?= $porcentajeConteosFinalizados ?>;">
                        <span><?= $porcentajeConteosFinalizados ?>%</span>
                    </div>
                    <div class="chart-legend">
                        <span><i class="legend-dot success"></i> Finalizados: <?= $conteosFinalizados ?></span>
                        <span><i class="legend-dot warning"></i> Borradores: <?= $conteosBorrador ?></span>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-4">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Tendencia de conteos</h2></div>
                <div class="bar-chart">
                    <?php foreach ($tendenciaConteos as $dia): ?>
                        <?php $height = max(10, round(((int) $dia['conteos'] / $maxConteosDia) * 100)); ?>
                        <div class="bar-item">
                            <span class="bar-value"><?= (int) $dia['conteos'] ?></span>
                            <div class="bar-track"><div class="bar-fill" style="height: <?= $height ?>%;"></div></div>
                            <small><?= e(date('d/m', strtotime((string) $dia['dia']))) ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$tendenciaConteos): ?>
                        <div class="empty-state">No hay datos suficientes.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="quick-actions">
        <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-plus-circle"></i> <?= current_user_role() === 'admin' ? 'Crear toma' : 'Seleccionar conteo' ?></a>
        <?php if (current_user_role() === 'admin'): ?>
            <a class="btn btn-outline-primary btn-lg" href="<?= BASE_URL ?>/productos.php"><i class="bi bi-box-seam"></i> Productos</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-lg" href="<?= BASE_URL ?>/reportes.php"><i class="bi bi-file-earmark-excel"></i> Reportes</a>
    </div>

    <section class="content-panel mt-4">
        <div class="section-title">
            <h2>Ultimas tomas fisicas</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Toma fisica</th>
                        <th>Usuarios</th>
                        <th>Estado</th>
                        <th>Creacion</th>
                        <th>Fin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimasTomas as $toma): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                            <td><?= (int) $toma['finalizados'] ?> / <?= (int) $toma['asignados'] ?></td>
                            <td><span class="badge text-bg-<?= $toma['estado'] === 'finalizada' ? 'success' : 'warning' ?>"><?= e($toma['estado']) ?></span></td>
                            <td><?= e($toma['fecha_creacion']) ?></td>
                            <td><?= e($toma['fecha_finalizacion'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ultimasTomas): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">Aun no hay tomas registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
