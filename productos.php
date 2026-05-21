<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = 'SELECT codigo, descripcion, estado, fecha_creacion FROM productos';
if ($q !== '') {
    $sql .= ' WHERE codigo LIKE ? OR descripcion LIKE ?';
    $params = ["%{$q}%", "%{$q}%"];
}
$sql .= ' ORDER BY descripcion LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$pageTitle = 'Productos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalogo</p>
            <h1>Productos</h1>
        </div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/importar_productos.php"><i class="bi bi-upload"></i> Importar</a>
    </div>
    <form class="search-row mb-3" method="get">
        <input class="form-control form-control-lg" name="q" value="<?= e($q) ?>" placeholder="Buscar codigo o descripcion">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Codigo</th><th>Descripcion</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($producto['codigo']) ?></td>
                            <td><?= e($producto['descripcion']) ?></td>
                            <td><?= $producto['estado'] ? 'Activo' : 'Inactivo' ?></td>
                            <td><?= e($producto['fecha_creacion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$productos): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No se encontraron productos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
