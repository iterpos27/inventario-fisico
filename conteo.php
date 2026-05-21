<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$conteoId = (int) ($_GET['id'] ?? 0);
$conteo = null;
$detalles = [];

if ($conteoId > 0) {
    $sql = 'SELECT * FROM conteos WHERE id = ?';
    $params = [$conteoId];
    if (current_user_role() !== 'admin') {
        $sql .= ' AND usuario_id = ?';
        $params[] = (int) $_SESSION['usuario_id'];
    }
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

$pageTitle = 'Conteo movil - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-3 conteo-shell">
    <div class="page-heading compact">
        <div>
            <p class="eyebrow">Conteo fisico</p>
            <h1><?= $conteo ? 'Continuar conteo' : 'Nuevo conteo' ?></h1>
        </div>
    </div>

    <input type="hidden" id="csrfToken" value="<?= csrf_token() ?>">
    <input type="hidden" id="conteoId" value="<?= (int) ($conteo['id'] ?? 0) ?>">

    <div class="mb-3">
        <label class="form-label" for="nombreConteo">Nombre del conteo</label>
        <input class="form-control form-control-lg" id="nombreConteo" value="<?= e($conteo['nombre_conteo'] ?? ('Conteo ' . date('d/m/Y H:i'))) ?>" placeholder="Ej. Conteo bodega principal">
    </div>

    <section class="count-tool">
        <label class="form-label" for="buscarProducto">Buscar producto</label>
        <div class="position-relative">
            <input class="form-control form-control-lg search-input" id="buscarProducto" placeholder="Codigo o descripcion" autocomplete="off">
            <div id="resultadosBusqueda" class="search-results d-none"></div>
        </div>
    </section>

    <section id="productoSeleccionado" class="selected-product d-none">
        <div>
            <span id="selCodigo" class="product-code"></span>
            <strong id="selDescripcion"></strong>
        </div>
        <label class="form-label mt-3" for="cantidadProducto">Cantidad</label>
        <div class="quantity-row">
            <input class="form-control quantity-input" id="cantidadProducto" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0">
            <button class="btn btn-primary btn-lg" id="agregarProducto" type="button"><i class="bi bi-plus-lg"></i></button>
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

    <div id="mensajeEstado" class="save-message d-none"></div>

    <div class="mobile-actions">
        <button class="btn btn-outline-primary btn-lg" id="guardarBorrador" type="button"><i class="bi bi-save"></i> Guardar borrador</button>
        <button class="btn btn-success btn-lg" id="finalizarConteo" type="button"><i class="bi bi-check2-circle"></i> Finalizar conteo</button>
    </div>
</main>
<script>
window.CONTEO_INICIAL = <?= json_encode($detalles, JSON_UNESCAPED_UNICODE) ?>;
window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/conteo.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
