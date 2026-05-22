<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$agencias = $pdo->query('SELECT id, nombre, estado, fecha_creacion FROM agencias ORDER BY estado DESC, nombre')->fetchAll();

$pageTitle = 'Agencias - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administracion</p>
            <h1>Agencias</h1>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= e($_GET['msg']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>

    <section class="content-panel mb-4">
        <div class="section-title"><h2>Crear agencia</h2></div>
        <form class="row g-3" action="<?= BASE_URL ?>/actions/guardar_agencia.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="col-12 col-md-4">
                <label class="form-label" for="nombre">Nombre de agencia</label>
                <input class="form-control form-control-lg" id="nombre" name="nombre" placeholder="PORTOVIEJO 01" required>
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-building-add"></i> Guardar agencia</button>
            </div>
        </form>
    </section>

    <section class="content-panel">
        <div class="section-title"><h2>Agencias registradas</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Agencia</th><th>Estado</th><th>Creacion</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($agencias as $agencia): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($agencia['nombre']) ?></td>
                            <td><span class="badge text-bg-<?= (int) $agencia['estado'] === 1 ? 'success' : 'warning' ?>"><?= (int) $agencia['estado'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                            <td><?= e($agencia['fecha_creacion']) ?></td>
                            <td class="text-end">
                                <form action="<?= BASE_URL ?>/actions/cambiar_estado_agencia.php" method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= (int) $agencia['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= (int) $agencia['estado'] === 1 ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">
                                        <?= (int) $agencia['estado'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$agencias): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No hay agencias registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
