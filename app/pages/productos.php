<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_PATH . '/repositories/ProductRepository.php';
require_admin();

$productosJsVersion = file_exists(PUBLIC_PATH . '/assets/js/productos.js')
    ? (string) filemtime(PUBLIC_PATH . '/assets/js/productos.js')
    : APP_VERSION;

$autoloadPath = ROOT_PATH . '/vendor/autoload.php';
$phpspreadsheetReady = file_exists($autoloadPath);
if ($phpspreadsheetReady) {
    require_once $autoloadPath;
    $phpspreadsheetReady = class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory');
}
$missingPhpExtensions = [];
foreach (['zip', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        $missingPhpExtensions[] = $extension;
    }
}
$excelReady = $phpspreadsheetReady && $missingPhpExtensions === [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '' && mb_strlen($q) < 3) {
    $q = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$sort = in_array(($_GET['sort'] ?? ''), ['codigo', 'descripcion'], true) ? (string) $_GET['sort'] : 'descripcion';
$direction = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$productosPage = (new ProductRepository($pdo))->paginateActive($q, $page, $perPage, $sort, $direction);
$productos = $productosPage['items'];
$totalProductos = $productosPage['total'];
$totalPages = max(1, (int) ceil($totalProductos / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$queryBase = [];
if ($q !== '') {
    $queryBase['q'] = $q;
}
if ($sort !== 'descripcion' || $direction !== 'asc') {
    $queryBase['sort'] = $sort;
    $queryBase['dir'] = $direction;
}
$prevUrl = page_url('productos', $queryBase + ['page' => max(1, $page - 1)]);
$nextUrl = page_url('productos', $queryBase + ['page' => min($totalPages, $page + 1)]);

$sortUrl = static function (string $column) use ($q, $sort, $direction): string {
    $nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
    $params = [
        'sort' => $column,
        'dir' => $nextDirection,
    ];
    if ($q !== '') {
        $params['q'] = $q;
    }

    return page_url('productos', $params);
};
$sortIcon = static function (string $column) use ($sort, $direction): string {
    if ($sort !== $column) {
        return 'bi-arrow-down-up';
    }

    return $direction === 'asc' ? 'bi-sort-down' : 'bi-sort-up';
};

$errorMessage = trim((string) ($_GET['error'] ?? ''));
if ($excelReady && str_contains($errorMessage, 'composer require phpoffice/phpspreadsheet')) {
    $errorMessage = '';
}

$pageTitle = 'Productos - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
require_once APP_INCLUDES_PATH . '/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Catalogo</p>
            <h1>Productos</h1>
        </div>
        <div class="quick-actions">
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalImportarProducto"><i class="bi bi-upload"></i> Importar Excel</button>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto"><i class="bi bi-plus-circle"></i> Agregar</button>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?= e($errorMessage) ?></div>
    <?php endif; ?>
    <?php if (!$excelReady): ?>
        <div class="alert alert-warning">
            <?php if ($phpspreadsheetReady && $missingPhpExtensions): ?>
                PhpSpreadsheet esta instalado, pero faltan extensiones PHP en Apache: <strong><?= e(implode(', ', $missingPhpExtensions)) ?></strong>.
            <?php else: ?>
                Falta instalar PhpSpreadsheet. Ejecute <strong>composer install</strong> en la carpeta del proyecto.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="count-tool mb-3">
        <form action="<?= page_url('productos') ?>" method="get" id="formBuscarProductoAdmin">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">
            <input type="hidden" name="dir" value="<?= e($direction) ?>">
            <label class="form-label" for="buscarProductoAdmin">Buscar producto</label>
            <div class="search-row">
                <input class="form-control form-control-lg search-input" id="buscarProductoAdmin" name="q" value="<?= e($q) ?>" placeholder="Codigo o descripcion" autocomplete="off">
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search"></i> Buscar</button>
            </div>
        </form>
    </section>

    <section class="content-panel">
        <div class="section-title">
            <h2>Inventario</h2>
            <span class="text-secondary"><?= $totalProductos ?> productos</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>
                            <a class="table-sort-link" href="<?= e($sortUrl('codigo')) ?>">
                                Codigo <i class="bi <?= e($sortIcon('codigo')) ?>"></i>
                            </a>
                        </th>
                        <th>
                            <a class="table-sort-link" href="<?= e($sortUrl('descripcion')) ?>">
                                Descripcion <i class="bi <?= e($sortIcon('descripcion')) ?>"></i>
                            </a>
                        </th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$productos): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-4">No hay productos para mostrar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($producto['codigo']) ?></td>
                            <td><?= e($producto['descripcion']) ?></td>
                            <td class="text-end">
                                <div class="table-actions">
                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditarProducto"
                                        data-product-id="<?= (int) $producto['id'] ?>"
                                        data-product-code="<?= e($producto['codigo']) ?>"
                                        data-product-description="<?= e($producto['descripcion']) ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                    <form action="<?= action_url('eliminar_producto') ?>" method="post" data-delete-product>
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span>Pagina <?= $page ?> de <?= $totalPages ?></span>
            <div class="quick-actions">
                <a class="btn btn-outline-primary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= e($prevUrl) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
                <a class="btn btn-outline-primary<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= e($nextUrl) ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </section>
</main>

<div class="modal fade" id="modalAgregarProducto" tabindex="-1" aria-labelledby="modalAgregarProductoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('agregar_producto') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalAgregarProductoTitulo">Agregar producto</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label" for="codigo">Codigo</label>
                        <input class="form-control form-control-lg" id="codigo" name="codigo" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="descripcion">Descripcion</label>
                        <input class="form-control form-control-lg" id="descripcion" name="descripcion" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportarProducto" tabindex="-1" aria-labelledby="modalImportarProductoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('importar_productos_procesar') ?>" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalImportarProductoTitulo">Importar productos</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label" for="archivo">Archivo .xlsx, .xls o .csv</label>
                        <input class="form-control form-control-lg" type="file" id="archivo" name="archivo" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <p class="text-secondary small mb-0">El archivo debe tener las columnas codigo y descripcion en la primera fila.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i> Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-labelledby="modalEditarProductoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= action_url('editar_producto') ?>" method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalEditarProductoTitulo">Editar producto</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" id="editarProductoId" name="id">
                    <div class="mb-3">
                        <label class="form-label" for="editarProductoCodigo">Codigo</label>
                        <input class="form-control form-control-lg" id="editarProductoCodigo" name="codigo" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="editarProductoDescripcion">Descripcion</label>
                        <input class="form-control form-control-lg" id="editarProductoDescripcion" name="descripcion" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-primary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url('js/productos.js') ?>?v=<?= e($productosJsVersion) ?>"></script>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>



