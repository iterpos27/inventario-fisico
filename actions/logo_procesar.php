<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/configuracion.php?error=Solicitud invalida');
    exit;
}

if (empty($_FILES['logo']['tmp_name']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . BASE_URL . '/configuracion.php?error=Seleccione un logo valido');
    exit;
}

$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
$mime = mime_content_type($_FILES['logo']['tmp_name']);
if (!isset($allowed[$mime])) {
    header('Location: ' . BASE_URL . '/configuracion.php?error=Formato de logo no permitido');
    exit;
}

$targetDir = dirname(__DIR__) . '/assets/img';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

foreach (glob($targetDir . '/logo.*') ?: [] as $oldLogo) {
    unlink($oldLogo);
}

$targetFile = $targetDir . '/logo.' . $allowed[$mime];
if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
    header('Location: ' . BASE_URL . '/configuracion.php?error=No se pudo guardar el logo');
    exit;
}

header('Location: ' . BASE_URL . '/configuracion.php?msg=Logo actualizado');
exit;
