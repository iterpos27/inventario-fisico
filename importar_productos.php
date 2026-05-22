<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

$autoloadPath = __DIR__ . '/vendor/autoload.php';
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

$errorMessage = trim((string) ($_GET['error'] ?? ''));
if ($excelReady && str_contains($errorMessage, 'composer require phpoffice/phpspreadsheet')) {
    $errorMessage = '';
}

$pageTitle = 'Importar productos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Productos</p>
            <h1>Importar productos</h1>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?= e($errorMessage) ?></div>
    <?php endif; ?>
    <?php if ($excelReady): ?>
        <div class="alert alert-success">PhpSpreadsheet instalado y listo para importar Excel.</div>
    <?php elseif ($phpspreadsheetReady && $missingPhpExtensions): ?>
        <div class="alert alert-warning">
            PhpSpreadsheet esta instalado, pero Apache no tiene activas estas extensiones PHP:
            <strong><?= e(implode(', ', $missingPhpExtensions)) ?></strong>.
            Active las extensiones en XAMPP y reinicie Apache.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">Falta instalar PhpSpreadsheet. Ejecute <strong>composer install</strong> en la carpeta del proyecto.</div>
    <?php endif; ?>

    <section class="content-panel">
        <div class="section-title"><h2>Excel de productos</h2></div>
        <form action="<?= BASE_URL ?>/actions/importar_productos_procesar.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label" for="archivo">Archivo .xlsx, .xls o .csv</label>
                <input class="form-control form-control-lg" type="file" id="archivo" name="archivo" accept=".xlsx,.xls,.csv" required>
            </div>
            <p class="text-secondary small">El archivo debe tener las columnas codigo y descripcion en la primera fila.</p>
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-upload"></i> Importar productos</button>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
