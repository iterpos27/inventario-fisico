<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$totalProductos = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();
$conteosBorrador = (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'borrador'")->fetchColumn();
$conteosFinalizados = (int) $pdo->query("SELECT COUNT(*) FROM conteos WHERE estado = 'finalizado'")->fetchColumn();

$stmt = $pdo->query(
    "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, u.nombre
     FROM conteos c
     INNER JOIN usuarios u ON u.id = c.usuario_id
     ORDER BY c.id DESC
     LIMIT 8"
);
$ultimosConteos = $stmt->fetchAll();

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
                <span>Borradores</span>
                <strong><?= $conteosBorrador ?></strong>
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
        <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-plus-circle"></i> Nuevo conteo</a>
        <?php if (current_user_role() === 'admin'): ?>
            <a class="btn btn-outline-primary btn-lg" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Importar productos</a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-lg" href="<?= BASE_URL ?>/reportes.php"><i class="bi bi-file-earmark-excel"></i> Reportes</a>
    </div>

    <section class="content-panel mt-4">
        <div class="section-title">
            <h2>Ultimos conteos</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Conteo</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimosConteos as $conteo): ?>
                        <tr>
                            <td><?= e($conteo['nombre_conteo']) ?></td>
                            <td><?= e($conteo['nombre']) ?></td>
                            <td><span class="badge text-bg-<?= $conteo['estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($conteo['estado']) ?></span></td>
                            <td><?= e($conteo['fecha_inicio']) ?></td>
                            <td><?= e($conteo['fecha_finalizacion'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ultimosConteos): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">Aun no hay conteos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
