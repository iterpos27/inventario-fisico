<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$roles = $pdo->query('SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol ORDER BY rol')->fetchAll();

$pageTitle = 'Roles y permisos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Administracion</p><h1>Roles y permisos</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Rol</th><th>Usuarios</th><th>Permisos</th></tr></thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?><tr><td><?= e($rol['rol']) ?></td><td><?= (int) $rol['total'] ?></td><td><?= $rol['rol'] === 'admin' ? 'Administracion completa' : 'Conteo asignado y reportes propios' ?></td></tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
