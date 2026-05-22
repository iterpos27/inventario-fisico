<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);

$pageTitle = 'Respaldos y seguridad - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Administracion</p><h1>Respaldos y seguridad</h1></div></div>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Tabla</th><th>Estado</th></tr></thead>
                <tbody><?php foreach ($tablas as $tabla): ?><tr><td><?= e($tabla[0]) ?></td><td>Disponible para respaldo desde MySQL Workbench o phpMyAdmin</td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
