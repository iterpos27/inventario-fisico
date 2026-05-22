<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$estado = $_GET['estado'] ?? '';
$params = [];
$sql = "SELECT c.id, c.nombre_conteo, c.estado, c.fecha_inicio, c.fecha_finalizacion, u.nombre
        FROM conteos c
        INNER JOIN usuarios u ON u.id = c.usuario_id";
$where = [];
if (in_array($estado, ['borrador', 'finalizado'], true)) {
    $where[] = 'c.estado = ?';
    $params[] = $estado;
}
if (current_user_role() !== 'admin') {
    $where[] = 'c.usuario_id = ?';
    $params[] = (int) $_SESSION['usuario_id'];
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY c.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteos = $stmt->fetchAll();

$pageTitle = 'Historial de conteos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Conteo y borradores</p><h1><?= $estado === 'borrador' ? 'Borradores' : 'Historial de conteos' ?></h1></div></div>
    <form class="filter-tabs mb-3" method="get" action="<?= BASE_URL ?>/historial_conteos.php">
        <button class="btn <?= $estado === '' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="" type="submit">Todos</button>
        <button class="btn <?= $estado === 'borrador' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="borrador" type="submit">Borradores</button>
        <button class="btn <?= $estado === 'finalizado' ? 'btn-primary' : 'btn-outline-primary' ?>" name="estado" value="finalizado" type="submit">Finalizados</button>
    </form>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Conteo</th><th>Usuario</th><th>Estado</th><th>Inicio</th><th>Fin</th></tr></thead>
                <tbody>
                    <?php foreach ($conteos as $conteo): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($conteo['nombre_conteo'])) ?></td>
                            <td><?= e($conteo['nombre']) ?></td>
                            <td><span class="badge text-bg-<?= $conteo['estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($conteo['estado']) ?></span></td>
                            <td><?= e($conteo['fecha_inicio']) ?></td>
                            <td><?= e($conteo['fecha_finalizacion'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$conteos): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay conteos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
