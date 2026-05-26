<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$usuarios = $pdo->query('SELECT id, nombre, usuario, rol, estado, fecha_creacion FROM usuarios ORDER BY id DESC')->fetchAll();

$pageTitle = 'Usuarios - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div><p class="eyebrow">Administracion</p><h1>Usuarios</h1></div>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario"><i class="bi bi-person-plus"></i> Crear usuario</button>
    </div>

    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= e($_GET['msg']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>

    <section class="content-panel">
        <div class="section-title"><h2>Usuarios registrados</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Fecha</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($usuario['nombre']) ?></td>
                        <td><?= e($usuario['usuario']) ?></td>
                        <td><?= e($usuario['rol']) ?></td>
                        <td><span class="badge text-bg-<?= (int) $usuario['estado'] === 1 ? 'success' : 'warning' ?>"><?= (int) $usuario['estado'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td><?= e($usuario['fecha_creacion']) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= (int) $usuario['id'] ?>"><i class="bi bi-pencil"></i> Editar</button>
                            <?php if ((int) $usuario['id'] !== (int) ($_SESSION['usuario_id'] ?? 0)): ?>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalEliminarUsuario<?= (int) $usuario['id'] ?>"><i class="bi bi-trash"></i> Eliminar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$usuarios): ?>
                    <tr><td colspan="6" class="text-center text-secondary py-4">No hay usuarios registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('crear_usuario') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalCrearUsuarioTitulo">Crear usuario</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
                    <div class="mb-3"><label class="form-label">Usuario</label><input class="form-control" name="usuario" required></div>
                    <div class="mb-3"><label class="form-label">Contrasena</label><input class="form-control" type="password" name="password" required minlength="6"></div>
                    <div><label class="form-label">Rol</label><select class="form-select" name="rol"><option value="usuario">Usuario</option><option value="admin">Admin</option></select></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($usuarios as $usuario): ?>
<div class="modal fade" id="modalEditarUsuario<?= (int) $usuario['id'] ?>" tabindex="-1" aria-labelledby="modalEditarUsuarioTitulo<?= (int) $usuario['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('actualizar_usuario') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEditarUsuarioTitulo<?= (int) $usuario['id'] ?>">Editar usuario</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                    <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="<?= e($usuario['nombre']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Usuario</label><input class="form-control" name="usuario" value="<?= e($usuario['usuario']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Clave nueva</label><input class="form-control" type="password" name="password" minlength="6" placeholder="Dejar vacio para no cambiar"></div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Rol</label>
                            <select class="form-select" name="rol" <?= (int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0) ? 'disabled' : '' ?>>
                                <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                                <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ((int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0)): ?><input type="hidden" name="rol" value="admin"><?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado" <?= (int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0) ? 'disabled' : '' ?>>
                                <option value="1" <?= (int) $usuario['estado'] === 1 ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= (int) $usuario['estado'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <?php if ((int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0)): ?><input type="hidden" name="estado" value="1"><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ((int) $usuario['id'] !== (int) ($_SESSION['usuario_id'] ?? 0)): ?>
<div class="modal fade" id="modalEliminarUsuario<?= (int) $usuario['id'] ?>" tabindex="-1" aria-labelledby="modalEliminarUsuarioTitulo<?= (int) $usuario['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('eliminar_usuario') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEliminarUsuarioTitulo<?= (int) $usuario['id'] ?>">Eliminar usuario</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                    <p class="mb-0">Desea eliminar/desactivar el usuario <strong><?= e($usuario['nombre']) ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger" type="submit">Si, eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>
