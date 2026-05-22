<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$usuarios = $pdo->query('SELECT nombre, usuario, rol, estado, fecha_creacion FROM usuarios ORDER BY id DESC')->fetchAll();

$pageTitle = 'Usuarios - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading"><div><p class="eyebrow">Administracion</p><h1>Usuarios</h1></div></div>
    <section class="content-panel mb-4">
        <form class="row g-3" action="<?= BASE_URL ?>/actions/crear_usuario.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
            <div class="col-md-3"><label class="form-label">Usuario</label><input class="form-control" name="usuario" required></div>
            <div class="col-md-3"><label class="form-label">Contrasena</label><input class="form-control" type="password" name="password" required minlength="6"></div>
            <div class="col-md-2"><label class="form-label">Rol</label><select class="form-select" name="rol"><option value="usuario">Usuario</option><option value="admin">Admin</option></select></div>
            <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> Crear usuario</button></div>
        </form>
    </section>
    <section class="content-panel">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody><?php foreach ($usuarios as $usuario): ?><tr><td><?= e($usuario['nombre']) ?></td><td><?= e($usuario['usuario']) ?></td><td><?= e($usuario['rol']) ?></td><td><?= $usuario['estado'] ? 'Activo' : 'Inactivo' ?></td><td><?= e($usuario['fecha_creacion']) ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
