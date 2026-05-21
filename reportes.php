<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$estado = $_GET['estado'] ?? '';
$params = [];
$sql = "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel, u.nombre
        FROM conteos c
        INNER JOIN usuarios u ON u.id = c.usuario_id
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
    <form class="filter-tabs mb-3" method="get">
        <button class="btn <?= $estado === '' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="" type="submit">Todos</button>
        <button class="btn <?= $estado === 'borrador' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="borrador" type="submit">Borradores</button>
        <button class="btn <?= $estado === 'finalizado' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="finalizado" type="submit">Finalizados</button>
    </form>
    <section class="content-panel">
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
                            <td><?= e($conteo['nombre_conteo']) ?></td>
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
