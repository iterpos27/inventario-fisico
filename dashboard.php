<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();
$tomasAbiertas = (int) $pdo->query("SELECT COUNT(*) FROM tomas_fisicas WHERE estado = 'abierta'")->fetchColumn();
$conteosFinalizados = (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'finalizado'")->fetchColumn();

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
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Productos</span>
                <strong><?= $totalProductos ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card warning">
                <span>Tomas abiertas</span>
                <strong><?= $tomasAbiertas ?></strong>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="metric-card success">
                <span>Finalizados</span>
                <strong><?= $conteosFinalizados ?></strong>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-plus-circle"></i> <?= current_user_role() === 'admin' ? 'Crear toma' : 'Seleccionar conteo' ?></a>
        <?php if (current_user_role() === 'admin'): ?>
            <a class="btn btn-outline-primary btn-lg" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Importar productos</a>
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
