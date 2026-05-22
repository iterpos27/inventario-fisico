<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$estado = $_GET['estado'] ?? '';
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$mes = $_GET['mes'] ?? date('Y-m');

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

$dailySql = "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel, u.nombre
             FROM conteos c
             INNER JOIN usuarios u ON u.id = c.usuario_id
             WHERE DATE(c.fecha_inicio) = ?";
$dailyParams = [$fecha];
if (current_user_role() !== 'admin') {
    $dailySql .= ' AND c.usuario_id = ?';
    $dailyParams[] = (int) $_SESSION['usuario_id'];
}
$dailySql .= ' ORDER BY c.id DESC';
$stmt = $pdo->prepare($dailySql);
$stmt->execute($dailyParams);
$conteosDiarios = $stmt->fetchAll();

$monthlySql = "SELECT DATE(fecha_inicio) AS dia,
                      COUNT(*) AS conteos,
                      SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
               FROM conteos
               WHERE DATE_FORMAT(fecha_inicio, '%Y-%m') = ?";
$monthlyParams = [$mes];
if (current_user_role() !== 'admin') {
    $monthlySql .= ' AND usuario_id = ?';
    $monthlyParams[] = (int) $_SESSION['usuario_id'];
}
$monthlySql .= ' GROUP BY DATE(fecha_inicio) ORDER BY dia DESC';
$stmt = $pdo->prepare($monthlySql);
$stmt->execute($monthlyParams);
$diasMes = $stmt->fetchAll();

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

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Reporte diario</h2></div>
                <form class="search-row mb-3" method="get" action="<?= BASE_URL ?>/reportes.php">
                    <input type="hidden" name="estado" value="<?= e($estado) ?>">
                    <input type="hidden" name="mes" value="<?= e($mes) ?>">
                    <input class="form-control" id="fecha" name="fecha" type="date" value="<?= e($fecha) ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Consultar</button>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle compact-table">
                        <thead><tr><th>Conteo</th><th>Usuario</th><th>Estado</th><th>Inicio</th></tr></thead>
                        <tbody>
                            <?php foreach ($conteosDiarios as $conteo): ?>
                                <tr>
                                    <td class="count-name"><?= nl2br(e($conteo['nombre_conteo'])) ?></td>
                                    <td><?= e($conteo['nombre']) ?></td>
                                    <td><span class="badge text-bg-<?= $conteo['estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($conteo['estado']) ?></span></td>
                                    <td><?= e($conteo['fecha_inicio']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$conteosDiarios): ?>
                                <tr><td colspan="4" class="text-center text-secondary py-4">Sin movimientos para la fecha.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Reporte mensual</h2></div>
                <form class="search-row mb-3" method="get" action="<?= BASE_URL ?>/reportes.php">
                    <input type="hidden" name="estado" value="<?= e($estado) ?>">
                    <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                    <input class="form-control" id="mes" name="mes" type="month" value="<?= e($mes) ?>">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Consultar</button>
                </form>
                <div class="table-responsive">
                    <table class="table align-middle compact-table">
                        <thead><tr><th>Dia</th><th>Conteos</th><th>Finalizados</th></tr></thead>
                        <tbody>
                            <?php foreach ($diasMes as $dia): ?>
                                <tr>
                                    <td><?= e($dia['dia']) ?></td>
                                    <td><?= (int) $dia['conteos'] ?></td>
                                    <td><?= (int) $dia['finalizados'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$diasMes): ?>
                                <tr><td colspan="3" class="text-center text-secondary py-4">Sin movimientos para el mes.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <?php if (current_user_role() === 'admin'): ?>
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
                                <?php if ((int) $toma['conteos_creados'] > 0): ?>
                                    <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/actions/descargar_consolidado.php?toma_id=<?= (int) $toma['id'] ?>"><i class="bi bi-download"></i> Consolidado</a>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/toma_detalle.php?id=<?= (int) $toma['id'] ?>">Ver detalle</a></td>
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

    <form class="filter-tabs mb-3" method="get" action="<?= BASE_URL ?>/reportes.php">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <input type="hidden" name="mes" value="<?= e($mes) ?>">
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
