<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$tomaId = (int) ($_GET['id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . page_url('reportes'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*, u.nombre AS creado_por_nombre
     FROM tomas_fisicas t
     INNER JOIN usuarios u ON u.id = t.creado_por
     WHERE t.id = ?"
);
$stmt->execute([$tomaId]);
$toma = $stmt->fetch();
if (!$toma) {
    header('Location: ' . page_url('reportes'));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT tu.estado AS asignacion_estado, tu.fecha_asignacion,
            u.id AS usuario_id, u.nombre, u.usuario,
            c.id AS conteo_id, c.estado AS conteo_estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel,
            COUNT(d.id) AS lineas,
            COALESCE(SUM(d.cantidad), 0) AS unidades
     FROM toma_usuarios tu
     INNER JOIN usuarios u ON u.id = tu.usuario_id
     LEFT JOIN conteos c ON c.toma_id = tu.toma_id AND c.usuario_id = tu.usuario_id
     LEFT JOIN conteo_detalle d ON d.conteo_id = c.id
     WHERE tu.toma_id = ?
     GROUP BY tu.estado, tu.fecha_asignacion, u.id, u.nombre, u.usuario, c.id, c.estado, c.fecha_inicio, c.fecha_finalizacion, c.archivo_excel
     ORDER BY u.nombre"
);
$stmt->execute([$tomaId]);
$participantes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT id, nombre, usuario
     FROM usuarios
     WHERE rol = 'usuario'
       AND estado = 1
       AND id NOT IN (
           SELECT usuario_id FROM toma_usuarios WHERE toma_id = ?
       )
     ORDER BY nombre"
);
$stmt->execute([$tomaId]);
$usuariosDisponibles = $stmt->fetchAll();

$agenciasActivas = $pdo->query(
    "SELECT nombre
     FROM agencias
     WHERE estado = 1
     ORDER BY nombre"
)->fetchAll();

$asignados = count($participantes);
$finalizados = 0;
$enProceso = 0;
$lineas = 0;
$unidades = 0.0;
foreach ($participantes as $participante) {
    if ($participante['asignacion_estado'] === 'finalizado') {
        $finalizados++;
    }
    if ($participante['asignacion_estado'] === 'en_proceso') {
        $enProceso++;
    }
    $lineas += (int) $participante['lineas'];
    $unidades += (float) $participante['unidades'];
}
$pendientes = max(0, $asignados - $finalizados - $enProceso);

$pageTitle = 'Detalle de toma - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'edicion'): ?>
        <div class="alert alert-success">Conteo habilitado nuevamente para edicion.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'asignacion'): ?>
        <div class="alert alert-success">Usuario asignado correctamente a la toma.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'toma_actualizada'): ?>
        <div class="alert alert-success">Toma actualizada correctamente.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'edicion'): ?>
        <div class="alert alert-danger">No se pudo habilitar el conteo.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'asignacion'): ?>
        <div class="alert alert-danger">No se pudo asignar el usuario a la toma. Revise que la toma este abierta y que el usuario no este asignado.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'consolidado'): ?>
        <div class="alert alert-danger">No se pudo generar el consolidado.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'edicion_toma'): ?>
        <div class="alert alert-danger">No se pudo actualizar la toma. Revise fechas y horas.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'eliminar_toma'): ?>
        <div class="alert alert-danger">No se pudo eliminar la toma. Solo se eliminan tomas sin productos contados.</div>
    <?php endif; ?>

    <div class="page-heading">
        <div>
            <p class="eyebrow">Toma fisica</p>
            <h1>Detalle de toma</h1>
        </div>
        <div class="quick-actions">
            <a class="btn btn-outline-primary" href="<?= page_url('conteo') ?>"><i class="bi bi-arrow-left"></i> Volver</a>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalEditarToma"><i class="bi bi-pencil"></i> Editar</button>
            <form method="post" action="<?= action_url('cambiar_estado_toma') ?>" onsubmit="return confirm('<?= $toma['estado'] === 'abierta' ? 'Cerrar esta toma? Los usuarios no podran seguir editando.' : 'Reabrir esta toma para permitir edicion?' ?>');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                <input type="hidden" name="accion" value="<?= $toma['estado'] === 'abierta' ? 'cerrar' : 'reabrir' ?>">
                <button class="btn <?= $toma['estado'] === 'abierta' ? 'btn-outline-danger' : 'btn-outline-primary' ?>" type="submit">
                    <i class="bi <?= $toma['estado'] === 'abierta' ? 'bi-lock' : 'bi-unlock' ?>"></i>
                    <?= $toma['estado'] === 'abierta' ? 'Cerrar toma' : 'Reabrir toma' ?>
                </button>
            </form>
            <?php if ($lineas > 0): ?>
                <form method="post" action="<?= action_url('generar_consolidado') ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                    <button class="btn btn-success" type="submit"><i class="bi bi-download"></i> Consolidado</button>
                </form>
            <?php endif; ?>
            <?php if ($lineas === 0): ?>
                <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalEliminarToma"><i class="bi bi-trash"></i> Eliminar</button>
            <?php endif; ?>
        </div>
    </div>

    <section class="content-panel mb-4">
        <div class="detail-header">
            <div>
                <span class="badge text-bg-<?= $toma['estado'] === 'finalizada' ? 'success' : 'warning' ?>"><?= e($toma['estado']) ?></span>
                <h2><?= nl2br(e($toma['nombre_toma'])) ?></h2>
            </div>
            <div class="detail-meta">
                <span>Creada por <?= e($toma['creado_por_nombre']) ?></span>
                <span><?= e($toma['fecha_creacion']) ?></span>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Participantes</span>
                <strong><?= $asignados ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card warning">
                <span>En proceso</span>
                <strong><?= $enProceso ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card success">
                <span>Finalizados</span>
                <strong><?= $finalizados ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Pendientes</span>
                <strong><?= $pendientes ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Lineas</span>
                <strong><?= $lineas ?></strong>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="metric-card">
                <span>Unidades</span>
                <strong><?= number_format($unidades, 2, '.', ',') ?></strong>
            </div>
        </div>
    </div>

    <?php if ($toma['estado'] === 'abierta' && $usuariosDisponibles): ?>
    <section class="content-panel mb-4">
        <div class="section-title"><h2>Agregar usuario a esta toma</h2></div>
        <form method="post" action="<?= action_url('asignar_usuarios_toma') ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
            <div class="row g-3">
                <div class="col-12">
                    <div class="participants-grid">
                    <?php foreach ($usuariosDisponibles as $usuarioDisponible): ?>
                        <label class="participant-check">
                            <input type="checkbox" name="usuarios[]" value="<?= (int) $usuarioDisponible['id'] ?>">
                            <span>
                                <strong><?= e($usuarioDisponible['nombre']) ?></strong>
                                <small><?= e($usuarioDisponible['usuario']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus"></i> Agregar seleccionados</button>
                </div>
            </div>
        </form>
    </section>
    <?php elseif ($toma['estado'] !== 'abierta'): ?>
        <div class="alert alert-warning">Para agregar usuarios nuevos, primero reabra la toma.</div>
    <?php endif; ?>

    <section class="content-panel">
        <div class="section-title"><h2>Participantes</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Asignacion</th>
                        <th>Conteo</th>
                        <th>Lineas</th>
                        <th>Unidades</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participantes as $participante): ?>
                        <tr>
                            <td>
                                <strong><?= e($participante['nombre']) ?></strong><br>
                                <span class="text-secondary"><?= e($participante['usuario']) ?></span>
                            </td>
                            <td><span class="badge text-bg-<?= $participante['asignacion_estado'] === 'finalizado' ? 'success' : 'warning' ?>"><?= e($participante['asignacion_estado']) ?></span></td>
                            <td><?= e($participante['conteo_estado'] ?? 'sin iniciar') ?></td>
                            <td><?= (int) $participante['lineas'] ?></td>
                            <td><?= number_format((float) $participante['unidades'], 2, '.', ',') ?></td>
                            <td><?= e($participante['fecha_inicio'] ?? '-') ?></td>
                            <td><?= e($participante['fecha_finalizacion'] ?? '-') ?></td>
                            <td>
                                <?php if ($participante['conteo_estado'] === 'finalizado' && $participante['archivo_excel']): ?>
                                    <a class="btn btn-sm btn-success" href="<?= action_url('descargar_excel') ?>?id=<?= (int) $participante['conteo_id'] ?>"><i class="bi bi-download"></i> Descargar</a>
                                    <form class="mt-2" method="post" action="<?= action_url('habilitar_conteo_usuario') ?>" onsubmit="return confirm('Habilitar nuevamente este conteo para edicion?');">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                                        <input type="hidden" name="usuario_id" value="<?= (int) $participante['usuario_id'] ?>">
                                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-unlock"></i> Habilitar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$participantes): ?>
                        <tr><td colspan="8" class="text-center text-secondary py-4">No hay participantes asignados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="modalEditarToma" tabindex="-1" aria-labelledby="modalEditarTomaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= action_url('editar_toma') ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEditarTomaTitulo">Editar toma</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editarAgencia">Agencia</label>
                            <select class="form-select" id="editarAgencia" name="agencia">
                                <option value="">Sin agencia</option>
                                <?php if ($toma['agencia'] && !in_array($toma['agencia'], array_column($agenciasActivas, 'nombre'), true)): ?>
                                    <option value="<?= e($toma['agencia']) ?>" selected><?= e($toma['agencia']) ?></option>
                                <?php endif; ?>
                                <?php foreach ($agenciasActivas as $agenciaActiva): ?>
                                    <option value="<?= e($agenciaActiva['nombre']) ?>" <?= $toma['agencia'] === $agenciaActiva['nombre'] ? 'selected' : '' ?>><?= e($agenciaActiva['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editarFechaHabilitacion">Fecha habilitacion</label>
                            <input class="form-control" id="editarFechaHabilitacion" type="date" name="fecha_habilitacion" value="<?= e($toma['fecha_habilitacion'] ?? $toma['fecha_toma']) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editarHoraInicio">Hora inicio</label>
                            <input class="form-control" id="editarHoraInicio" type="time" name="hora_inicio" value="<?= e(substr((string) ($toma['hora_inicio'] ?? '08:00'), 0, 5)) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editarFechaCierre">Fecha finalizacion</label>
                            <input class="form-control" id="editarFechaCierre" type="date" name="fecha_cierre" value="<?= e($toma['fecha_cierre'] ?? $toma['fecha_toma']) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="editarHoraFin">Hora fin</label>
                            <input class="form-control" id="editarHoraFin" type="time" name="hora_fin" value="<?= e(substr((string) ($toma['hora_fin'] ?? '18:00'), 0, 5)) ?>" required>
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

<?php if ($lineas === 0): ?>
<div class="modal fade" id="modalEliminarToma" tabindex="-1" aria-labelledby="modalEliminarTomaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= action_url('eliminar_toma') ?>">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEliminarTomaTitulo">Eliminar toma</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="toma_id" value="<?= (int) $toma['id'] ?>">
                    <p>Desea eliminar esta toma?</p>
                    <p class="mb-0 text-secondary">Esta accion solo se permite cuando no hay productos contados y no se puede deshacer.</p>
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

<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>


