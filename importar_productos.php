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

$errorMessage = trim((string) ($_GET['error'] ?? ''));
if ($phpspreadsheetReady && str_contains($errorMessage, 'composer require phpoffice/phpspreadsheet')) {
    $errorMessage = '';
}

$usuarios = $pdo->query('SELECT nombre, usuario, rol, estado, fecha_creacion FROM usuarios ORDER BY id DESC LIMIT 100')->fetchAll();
$pageTitle = 'Importar productos - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-4">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administracion</p>
            <h1>Importar productos</h1>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?= e($errorMessage) ?></div>
    <?php endif; ?>
    <?php if ($phpspreadsheetReady): ?>
        <div class="alert alert-success">PhpSpreadsheet instalado y listo para importar Excel.</div>
    <?php else: ?>
        <div class="alert alert-warning">Falta instalar PhpSpreadsheet. Ejecute <strong>composer install</strong> en la carpeta del proyecto.</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Excel de productos</h2></div>
                <form action="<?= BASE_URL ?>/actions/importar_productos_procesar.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label" for="archivo">Archivo .xlsx, .xls o .csv</label>
                        <input class="form-control form-control-lg" type="file" id="archivo" name="archivo" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <p class="text-secondary small">El archivo debe tener las columnas codigo y descripcion en la primera fila.</p>
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-upload"></i> Importar productos</button>
                </form>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="content-panel h-100">
                <div class="section-title"><h2>Logo del sistema</h2></div>
                <form action="<?= BASE_URL ?>/actions/logo_procesar.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label" for="logo">Imagen PNG, JPG o WEBP</label>
                        <input class="form-control form-control-lg" type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp" required>
                    </div>
                    <button class="btn btn-outline-primary btn-lg w-100" type="submit"><i class="bi bi-image"></i> Guardar logo</button>
                </form>
            </section>
        </div>
    </div>

    <section class="content-panel mt-4">
        <div class="section-title"><h2>Crear usuario</h2></div>
        <form class="row g-3" action="<?= BASE_URL ?>/actions/crear_usuario.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="col-md-4">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <input class="form-control" name="usuario" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contraseña</label>
                <input class="form-control" type="password" name="password" required minlength="6">
            </div>
            <div class="col-md-2">
                <label class="form-label">Rol</label>
                <select class="form-select" name="rol">
                    <option value="usuario">Usuario</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> Crear usuario</button>
            </div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= e($usuario['nombre']) ?></td>
                            <td><?= e($usuario['usuario']) ?></td>
                            <td><?= e($usuario['rol']) ?></td>
                            <td><?= $usuario['estado'] ? 'Activo' : 'Inactivo' ?></td>
                            <td><?= e($usuario['fecha_creacion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
