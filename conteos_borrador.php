<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$sql = "SELECT c.id, c.nombre_conteo, c.fecha_inicio, u.nombre, COUNT(d.id) AS lineas
        FROM conteos c
        INNER JOIN usuarios u ON u.id = c.usuario_id
        LEFT JOIN conteo_detalle d ON d.conteo_id = c.id
        WHERE c.estado = 'borrador'";
$params = [];
if (current_user_role() !== 'admin') {
    $sql .= ' AND c.usuario_id = ?';
    $params[] = (int) $_SESSION['usuario_id'];
}
$sql .= ' GROUP BY c.id, c.nombre_conteo, c.fecha_inicio, u.nombre ORDER BY c.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conteos = $stmt->fetchAll();

$pageTitle = 'Borradores - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Conteos pendientes</p>
            <h1>Borradores</h1>
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/conteo.php"><i class="bi bi-plus-circle"></i> Nuevo</a>
    </div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Lineas</th><th>Inicio</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($conteos as $conteo): ?>
                        <tr>
                            <td><?= e($conteo['nombre_conteo']) ?></td>
                            <td><?= e($conteo['nombre']) ?></td>
                            <td><?= (int) $conteo['lineas'] ?></td>
                            <td><?= e($conteo['fecha_inicio']) ?></td>
                            <td><a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/conteo.php?id=<?= (int) $conteo['id'] ?>">Continuar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$conteos): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No hay borradores disponibles.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
