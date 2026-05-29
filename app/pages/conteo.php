<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_login();

$conteoId = (int) ($_GET['id'] ?? 0);
$conteo = null;
$detalles = [];
$tomasDisponibles = [];
$tomasAbiertasAdmin = [];
$usuariosParticipantes = [];
$agenciasActivas = [];
$defaultYear = date('Y');
$nextSequence = 1;
$stmt = $pdo->prepare('SELECT numero_toma FROM tomas_fisicas WHERE numero_toma LIKE ? ORDER BY id DESC LIMIT 100');
$stmt->execute(["{$defaultYear}-%"]);
foreach ($stmt->fetchAll() as $row) {
    if (preg_match('/^' . preg_quote($defaultYear, '/') . '-(\d{3})$/', (string) $row['numero_toma'], $matches)) {
        $nextSequence = max($nextSequence, (int) $matches[1] + 1);
    }
}
$defaultToma = $defaultYear . '-' . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);

if ($conteoId > 0) {
    $sql = 'SELECT c.*, t.numero_toma, t.agencia, t.fecha_toma
            FROM conteos c
            LEFT JOIN tomas_fisicas t ON t.id = c.toma_id
            WHERE c.id = ? AND c.usuario_id = ?';
    $params = [$conteoId, (int) $_SESSION['usuario_id']];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conteo = $stmt->fetch();

    if (!$conteo || $conteo['estado'] === 'finalizado') {
        header('Location: ' . page_url('conteo'));
        exit;
    }

    $stmt = $pdo->prepare('SELECT producto_id, codigo, descripcion, cantidad FROM conteo_detalle WHERE conteo_id = ? ORDER BY id');
    $stmt->execute([$conteoId]);
    $detalles = $stmt->fetchAll();
}

if (current_user_role() !== 'admin' && $conteoId === 0) {
    $stmt = $pdo->prepare(
        "SELECT t.id AS toma_id, t.nombre_toma, t.fecha_creacion, tu.estado AS asignacion_estado,
                c.id AS conteo_id, c.estado AS conteo_estado, COUNT(d.id) AS lineas
         FROM toma_usuarios tu
         INNER JOIN tomas_fisicas t ON t.id = tu.toma_id
         LEFT JOIN conteos c ON c.toma_id = t.id AND c.usuario_id = tu.usuario_id
         LEFT JOIN conteo_detalle d ON d.conteo_id = c.id
         WHERE tu.usuario_id = ? AND t.estado = 'abierta'
         GROUP BY t.id, t.nombre_toma, t.fecha_creacion, tu.estado, c.id, c.estado
         ORDER BY t.id DESC"
    );
    $stmt->execute([(int) $_SESSION['usuario_id']]);
    $tomasDisponibles = $stmt->fetchAll();
}

if (current_user_role() === 'admin' && $conteoId === 0) {
    $agenciasActivas = $pdo->query(
        "SELECT nombre
         FROM agencias
         WHERE estado = 1
         ORDER BY nombre"
    )->fetchAll();

    $usuariosParticipantes = $pdo->query(
        "SELECT id, nombre, usuario
         FROM usuarios
         WHERE rol = 'usuario' AND estado = 1
         ORDER BY nombre"
    )->fetchAll();

    $tomasAbiertasAdmin = $pdo->query(
        "SELECT t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_habilitacion, t.fecha_cierre, t.hora_inicio, t.hora_fin,
                COUNT(tu.id) AS asignados,
                SUM(CASE WHEN tu.estado = 'en_proceso' THEN 1 ELSE 0 END) AS en_proceso,
                SUM(CASE WHEN tu.estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
         FROM tomas_fisicas t
         LEFT JOIN toma_usuarios tu ON tu.toma_id = t.id
         WHERE t.estado = 'abierta'
         GROUP BY t.id, t.nombre_toma, t.estado, t.fecha_creacion, t.fecha_habilitacion, t.fecha_cierre, t.hora_inicio, t.hora_fin
         ORDER BY t.id DESC"
    )->fetchAll();
}

$pageTitle = 'Conteo movil - ' . APP_NAME;
$conteoJsVersion = file_exists(PUBLIC_PATH . '/assets/js/conteo.js')
    ? (string) filemtime(PUBLIC_PATH . '/assets/js/conteo.js')
    : APP_VERSION;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
$conteoShellClass = 'container py-3 conteo-shell';
if (current_user_role() !== 'admin') {
    $conteoShellClass .= $conteo ? ' user-conteo-shell user-conteo-workspace' : ' user-conteo-shell user-conteo-home';
}
?>
<main class="<?= e($conteoShellClass) ?>">
    <?php if (current_user_role() === 'admin' || !$conteo): ?>
    <div class="page-heading compact">
        <div>
            <p class="eyebrow">Conteo fisico</p>
            <h1><?= current_user_role() === 'admin' ? 'Crear toma fisica' : ($conteo ? 'Continuar conteo' : 'Seleccionar conteo') ?></h1>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success"><?= e($_GET['msg']) ?></div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>

    <input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">
    <input type="hidden" id="conteoId" value="<?= (int) ($conteo['id'] ?? 0) ?>">
    <input type="hidden" id="conteoVersion" value="<?= (int) ($conteo['version'] ?? 0) ?>">
    <input type="hidden" id="conteoCreado" value="<?= $conteo ? '1' : '0' ?>">
    <input type="hidden" id="nombreConteo" value="<?= e($conteo['nombre_conteo'] ?? '') ?>">

    <?php if (current_user_role() === 'admin' && !$conteo): ?>
    <section id="crearConteoPanel" class="content-panel mb-3">
        <div class="section-title"><h2>Crear toma fisica</h2></div>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="numeroToma">Toma fisica #</label>
                <input class="form-control form-control-lg" id="numeroToma" value="<?= e($defaultToma) ?>" placeholder="Automatico" readonly>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="agenciaConteo">Agencia</label>
                <select class="form-select form-control-lg" id="agenciaConteo">
                    <option value="">Sin agencia</option>
                    <?php foreach ($agenciasActivas as $agenciaActiva): ?>
                        <option value="<?= e($agenciaActiva['nombre']) ?>"><?= e($agenciaActiva['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="fechaHoraHabilitacion">Habilitacion</label>
                <input class="form-control form-control-lg" id="fechaHoraHabilitacion" type="datetime-local" value="<?= e(date('Y-m-d\T08:00')) ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="fechaHoraCierre">Finalizacion</label>
                <input class="form-control form-control-lg" id="fechaHoraCierre" type="datetime-local" value="<?= e(date('Y-m-d\T18:00')) ?>">
            </div>
        </div>
        <div class="count-preview mt-3" id="vistaNombreConteo"></div>
        <div class="participants-box mt-3">
            <div class="section-title split">
                <h2>Usuarios participantes</h2>
                <div class="participant-actions">
                    <button class="btn btn-sm btn-outline-primary" id="seleccionarUsuarios" type="button">Todos</button>
                    <button class="btn btn-sm btn-outline-secondary" id="limpiarUsuarios" type="button">Ninguno</button>
                </div>
            </div>
            <div class="participants-grid">
                <?php foreach ($usuariosParticipantes as $usuarioParticipante): ?>
                    <label class="participant-option">
                        <input class="participant-check" type="checkbox" value="<?= (int) $usuarioParticipante['id'] ?>" checked>
                        <span>
                            <strong><?= e($usuarioParticipante['nombre']) ?></strong>
                            <small><?= e($usuarioParticipante['usuario']) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$usuariosParticipantes): ?>
                    <div class="empty-state">No hay usuarios operativos activos para asignar.</div>
                <?php endif; ?>
            </div>
        </div>
        <button class="btn btn-primary btn-lg w-100 mt-3" id="crearConteo" type="button"><i class="bi bi-plus-circle"></i> Crear toma fisica</button>
    </section>

    <section class="content-panel mb-3">
        <div class="section-title"><h2>Tomas abiertas</h2></div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Toma fisica</th><th>Asignados</th><th>En proceso</th><th>Finalizados</th><th>Periodo</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($tomasAbiertasAdmin as $toma): ?>
                        <tr>
                            <td class="count-name"><?= nl2br(e($toma['nombre_toma'])) ?></td>
                            <td><?= (int) $toma['asignados'] ?></td>
                            <td><?= (int) $toma['en_proceso'] ?></td>
                            <td><?= (int) $toma['finalizados'] ?></td>
                            <td><?= e(($toma['fecha_habilitacion'] ?? '-') . ' ' . substr((string) ($toma['hora_inicio'] ?? ''), 0, 5) . ' / ' . ($toma['fecha_cierre'] ?? '-') . ' ' . substr((string) ($toma['hora_fin'] ?? ''), 0, 5)) ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="<?= page_url('toma_detalle') ?>?id=<?= (int) $toma['id'] ?>">Ver detalle</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tomasAbiertasAdmin): ?>
                        <tr><td colspan="6" class="text-center text-secondary py-4">No hay tomas abiertas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if (current_user_role() !== 'admin' && !$conteo): ?>
        <section class="content-panel mb-3 available-counts-panel">
            <div class="section-title"><h2>Conteos disponibles</h2></div>
            <div class="available-count-grid">
                <?php foreach ($tomasDisponibles as $disponible): ?>
                    <form method="post" action="<?= action_url('iniciar_conteo') ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="toma_id" value="<?= (int) $disponible['toma_id'] ?>">
                        <button class="available-count" type="submit">
                            <span class="available-count-title"><?= nl2br(e($disponible['nombre_toma'])) ?></span>
                            <small><?= (int) $disponible['lineas'] ?> lineas registradas - <?= $disponible['conteo_estado'] === 'borrador' ? 'Continuar' : 'Empezar' ?></small>
                            <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </form>
                <?php endforeach; ?>
                <?php if (!$tomasDisponibles): ?>
                    <div class="empty-state">No hay conteos disponibles. Solicite al administrador crear una toma fisica.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $nombreConteo = $conteo['nombre_conteo'] ?? '';
    $tomaFisica = '';
    $agencia = '';
    $habilitacion = '';
    $finalizacion = '';
    if ($nombreConteo) {
        $lines = explode("\n", $nombreConteo);
        $tomaFisica = trim($lines[0] ?? '');
        $agencia = isset($lines[1]) ? trim(str_replace('AGENCIA:', '', $lines[1])) : '';
        $habilitacion = isset($lines[2]) ? trim(str_replace('HABILITACION:', '', $lines[2])) : '';
        $finalizacion = isset($lines[3]) ? trim(str_replace('FINALIZACION:', '', $lines[3])) : '';
    }
    ?>
    <div id="operacionActiva" class="content-panel count-operation-card mb-3 <?= ($conteo && current_user_role() !== 'admin') ? '' : 'd-none' ?>">
        <div class="operation-card-body">
            <div class="operation-info">
                <span class="operation-tag"><i class="bi bi-play-circle-fill text-success me-1"></i> Operación Activa</span>
                <h3 id="operacionToma" class="operation-title"><?= e($tomaFisica) ?: 'Toma Física' ?></h3>
                <small id="estadoGuardado" class="save-status">Sin cambios recientes.</small>
                <div class="operation-meta">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1 px-2">
                        <i class="bi bi-building"></i> <span id="operacionAgencia"><?= e($agencia) ?: 'Agencia' ?></span>
                    </span>
                    <span class="badge bg-light text-secondary border py-1 px-2">
                        <i class="bi bi-calendar-event"></i> Habilitación: <span id="operacionHabilitacion"><?= e($habilitacion) ?: '-' ?></span>
                    </span>
                    <span class="badge bg-light text-secondary border py-1 px-2">
                        <i class="bi bi-calendar-check"></i> Cierre: <span id="operacionFinalizacion"><?= e($finalizacion) ?: '-' ?></span>
                    </span>
                </div>
            </div>
            <div id="accionesConteo" class="operation-actions <?= ($conteo && current_user_role() !== 'admin') ? '' : 'd-none' ?>">
                <button class="btn btn-outline-primary" id="guardarBorrador" type="button"><i class="bi bi-save"></i> Guardar borrador</button>
                <button class="btn btn-success" id="finalizarConteo" type="button"><i class="bi bi-check2-circle"></i> Finalizar conteo</button>
            </div>
        </div>
    </div>

    <section id="conteoWorkspace" class="<?= ($conteo && current_user_role() !== 'admin') ? '' : 'd-none' ?>">
    <section class="count-tool">
        <label class="form-label" for="buscarProducto">Buscar producto</label>
        <div class="position-relative">
            <input class="form-control form-control-lg search-input" id="buscarProducto" placeholder="Codigo o descripcion" autocomplete="off">
            <button class="search-clear d-none" id="limpiarBusqueda" type="button" aria-label="Limpiar busqueda"><i class="bi bi-x-circle-fill"></i></button>
            <button class="search-scan" id="abrirEscaner" type="button" aria-label="Escanear codigo de barras"><i class="bi bi-upc-scan"></i></button>
            <div id="resultadosBusqueda" class="search-results d-none"></div>
        </div>
    </section>

    <section class="content-panel mt-3">
        <div class="section-title split">
            <h2>Productos contados</h2>
            <span id="contadorLineas" class="badge text-bg-primary">0</span>
        </div>
        <div id="listaProductos" class="count-list"></div>
        <div id="listaVacia" class="empty-state">Agregue productos para iniciar el conteo.</div>
    </section>
    </section>

    <div id="mensajeEstado" class="save-message d-none"></div>
</main>

<div class="modal fade" id="modalEliminarProductoConteo" tabindex="-1" aria-labelledby="modalEliminarProductoConteoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content app-confirm-modal">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modalEliminarProductoConteoTitulo">Eliminar producto</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Desea eliminar este producto del conteo?</p>
                <p class="mb-0 text-secondary">La linea se quitara de la lista antes de guardar el borrador o finalizar.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" id="confirmarEliminarProductoConteo" type="button"><i class="bi bi-trash"></i> Eliminar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBorradorGuardado" tabindex="-1" aria-labelledby="modalBorradorGuardadoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content app-confirm-modal save-confirm-modal">
            <div class="modal-body text-center">
                <i class="bi bi-check2-circle"></i>
                <h2 class="modal-title fs-5" id="modalBorradorGuardadoTitulo">Proceso guardado</h2>
                <p class="mb-0 text-secondary">El borrador fue guardado correctamente.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEscanerProducto" tabindex="-1" aria-labelledby="modalEscanerProductoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content app-confirm-modal scanner-modal">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="modalEscanerProductoTitulo">Escanear codigo</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="scanner-view">
                    <video id="videoEscanerProducto" playsinline muted></video>
                    <div class="scanner-frame" aria-hidden="true"></div>
                </div>
                <p id="estadoEscanerProducto" class="scanner-status mb-0">Preparando camara...</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>
window.CONTEO_INICIAL = <?= json_encode($detalles, JSON_UNESCAPED_UNICODE) ?>;
window.BASE_URL = '<?= BASE_URL ?>';
window.USER_ROLE = '<?= e(current_user_role()) ?>';
</script>
<script src="<?= asset_url('js/conteo.js') ?>?v=<?= e($conteoJsVersion) ?>"></script>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>


