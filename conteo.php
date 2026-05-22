<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conteoId = (int) ($_GET['id'] ?? 0);
$conteo = null;
$detalles = [];
$tomasDisponibles = [];
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
        header('Location: ' . BASE_URL . '/conteos_borrador.php');
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

$pageTitle = 'Conteo movil - ' . APP_NAME;
$conteoJsVersion = file_exists(__DIR__ . '/assets/js/conteo.js')
    ? (string) filemtime(__DIR__ . '/assets/js/conteo.js')
    : APP_VERSION;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-3 conteo-shell">
    <div class="page-heading compact">
        <div>
            <p class="eyebrow">Conteo fisico</p>
            <h1><?= current_user_role() === 'admin' ? 'Crear toma fisica' : ($conteo ? 'Continuar conteo' : 'Seleccionar conteo') ?></h1>
        </div>
    </div>

    <input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">
    <input type="hidden" id="conteoId" value="<?= (int) ($conteo['id'] ?? 0) ?>">
    <input type="hidden" id="conteoCreado" value="<?= $conteo ? '1' : '0' ?>">
    <input type="hidden" id="nombreConteo" value="<?= e($conteo['nombre_conteo'] ?? '') ?>">

    <?php if (current_user_role() === 'admin' && !$conteo): ?>
    <section id="crearConteoPanel" class="content-panel mb-3">
        <div class="section-title"><h2>Crear toma fisica</h2></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="numeroToma">Toma fisica #</label>
                <input class="form-control form-control-lg" id="numeroToma" value="<?= e($defaultToma) ?>" placeholder="2026-001">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="agenciaConteo">Agencia</label>
                <input class="form-control form-control-lg" id="agenciaConteo" value="PORTOVIEJO 01" placeholder="PORTOVIEJO 01">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fechaConteo">Fecha</label>
                <input class="form-control form-control-lg" id="fechaConteo" type="date" value="<?= e(date('Y-m-d')) ?>">
            </div>
        </div>
        <div class="count-preview mt-3" id="vistaNombreConteo"></div>
        <button class="btn btn-primary btn-lg w-100 mt-3" id="crearConteo" type="button"><i class="bi bi-plus-circle"></i> Crear toma fisica</button>
    </section>
    <?php endif; ?>

    <?php if (current_user_role() !== 'admin' && !$conteo): ?>
        <section class="content-panel mb-3">
            <div class="section-title"><h2>Conteos disponibles</h2></div>
            <div class="count-list">
                <?php foreach ($tomasDisponibles as $disponible): ?>
                    <a class="available-count" href="<?= BASE_URL ?>/actions/iniciar_conteo.php?toma_id=<?= (int) $disponible['toma_id'] ?>">
                        <span><?= nl2br(e($disponible['nombre_toma'])) ?></span>
                        <small><?= (int) $disponible['lineas'] ?> lineas registradas · <?= $disponible['conteo_estado'] === 'borrador' ? 'Continuar' : 'Empezar' ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$tomasDisponibles): ?>
                    <div class="empty-state">No hay conteos disponibles. Solicite al administrador crear una toma fisica.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($conteo): ?>
        <div class="content-panel count-operation mb-3">
            <span>Operacion activa</span>
            <strong><?= nl2br(e($conteo['nombre_conteo'])) ?></strong>
        </div>
    <?php else: ?>
        <div id="operacionActiva" class="content-panel count-operation mb-3 d-none">
            <span>Operacion activa</span>
            <strong id="operacionNombre"></strong>
        </div>
    <?php endif; ?>

    <section id="conteoWorkspace" class="<?= ($conteo && current_user_role() !== 'admin') ? '' : 'd-none' ?>">
    <section class="count-tool">
        <label class="form-label" for="buscarProducto">Buscar producto</label>
        <div class="position-relative">
            <input class="form-control form-control-lg search-input" id="buscarProducto" placeholder="Codigo o descripcion" autocomplete="off">
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

    <div id="accionesConteo" class="mobile-actions <?= ($conteo && current_user_role() !== 'admin') ? '' : 'd-none' ?>">
        <button class="btn btn-outline-primary btn-lg" id="guardarBorrador" type="button"><i class="bi bi-save"></i> Guardar borrador</button>
        <button class="btn btn-success btn-lg" id="finalizarConteo" type="button"><i class="bi bi-check2-circle"></i> Finalizar conteo</button>
    </div>
</main>
<script>
window.CONTEO_INICIAL = <?= json_encode($detalles, JSON_UNESCAPED_UNICODE) ?>;
window.BASE_URL = '<?= BASE_URL ?>';
window.USER_ROLE = '<?= e(current_user_role()) ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/conteo.js?v=<?= e($conteoJsVersion) ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
