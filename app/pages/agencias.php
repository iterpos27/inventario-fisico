<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$agencias = $pdo->query('SELECT id, nombre, estado, fecha_creacion FROM agencias ORDER BY estado DESC, nombre')->fetchAll();

$pageTitle = 'Agencias - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administracion</p>
            <h1>Agencias</h1>
        </div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearAgencia"><i class="bi bi-building-add"></i> Crear agencia</button>
    </div>

    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= e($_GET['msg']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>

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
                                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarAgencia<?= (int) $agencia['id'] ?>"><i class="bi bi-pencil"></i> Editar</button>
                                <form action="<?= action_url('cambiar_estado_agencia') ?>" method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= (int) $agencia['id'] ?>">
                                    <input type="hidden" name="estado" value="<?= (int) $agencia['estado'] === 1 ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit"><?= (int) $agencia['estado'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalEliminarAgencia<?= (int) $agencia['id'] ?>"><i class="bi bi-trash"></i> Eliminar</button>
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

<div class="modal fade" id="modalCrearAgencia" tabindex="-1" aria-labelledby="modalCrearAgenciaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('guardar_agencia') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalCrearAgenciaTitulo">Crear agencia</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <label class="form-label" for="nombreAgencia">Nombre de agencia</label>
                    <input class="form-control" id="nombreAgencia" name="nombre" placeholder="PORTOVIEJO 01" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($agencias as $agencia): ?>
<div class="modal fade" id="modalEditarAgencia<?= (int) $agencia['id'] ?>" tabindex="-1" aria-labelledby="modalEditarAgenciaTitulo<?= (int) $agencia['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('actualizar_agencia') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEditarAgenciaTitulo<?= (int) $agencia['id'] ?>">Editar agencia</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int) $agencia['id'] ?>">
                    <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= e($agencia['nombre']) ?>" required></div>
                    <div><label class="form-label">Estado</label><select class="form-select" name="estado"><option value="1" <?= (int) $agencia['estado'] === 1 ? 'selected' : '' ?>>Activa</option><option value="0" <?= (int) $agencia['estado'] === 0 ? 'selected' : '' ?>>Inactiva</option></select></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEliminarAgencia<?= (int) $agencia['id'] ?>" tabindex="-1" aria-labelledby="modalEliminarAgenciaTitulo<?= (int) $agencia['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('eliminar_agencia') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEliminarAgenciaTitulo<?= (int) $agencia['id'] ?>">Eliminar agencia</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int) $agencia['id'] ?>">
                    <p class="mb-0">Desea eliminar/desactivar la agencia <strong><?= e($agencia['nombre']) ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger" type="submit">Si, eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>
