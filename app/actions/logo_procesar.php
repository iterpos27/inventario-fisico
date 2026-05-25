<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('configuracion', ['error' => 'Solicitud invalida']));
    exit;
}

if (empty($_FILES['logo']['tmp_name']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . page_url('configuracion', ['error' => 'Seleccione un logo valido']));
    exit;
}
if ((int) ($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
    header('Location: ' . page_url('configuracion', ['error' => 'El logo no puede superar 2 MB']));
    exit;
}

$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
$mime = mime_content_type($_FILES['logo']['tmp_name']);
$imageInfo = getimagesize($_FILES['logo']['tmp_name']);
if (!isset($allowed[$mime]) || $imageInfo === false || !isset($allowed[$imageInfo['mime'] ?? ''])) {
    header('Location: ' . page_url('configuracion', ['error' => 'Formato de logo no permitido']));
    exit;
}
if (!extension_loaded('gd')) {
    header('Location: ' . page_url('configuracion', ['error' => 'Active la extension GD para procesar logos']));
    exit;
}

$targetDir = PUBLIC_PATH . '/assets/img';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

foreach (glob($targetDir . '/logo.*') ?: [] as $oldLogo) {
    unlink($oldLogo);
}

$targetFile = $targetDir . '/logo.' . $allowed[$mime];
$sourceImage = match ($mime) {
    'image/png' => imagecreatefrompng($_FILES['logo']['tmp_name']),
    'image/jpeg' => imagecreatefromjpeg($_FILES['logo']['tmp_name']),
    'image/webp' => imagecreatefromwebp($_FILES['logo']['tmp_name']),
    default => false,
};
if (!$sourceImage) {
    header('Location: ' . page_url('configuracion', ['error' => 'No se pudo procesar el logo']));
    exit;
}

if ($mime === 'image/png') {
    imagealphablending($sourceImage, false);
    imagesavealpha($sourceImage, true);
}

$saved = match ($mime) {
    'image/png' => imagepng($sourceImage, $targetFile, 6),
    'image/jpeg' => imagejpeg($sourceImage, $targetFile, 85),
    'image/webp' => imagewebp($sourceImage, $targetFile, 85),
    default => false,
};
imagedestroy($sourceImage);
if (!$saved) {
    header('Location: ' . page_url('configuracion', ['error' => 'No se pudo guardar el logo']));
    exit;
}

header('Location: ' . page_url('configuracion', ['msg' => 'Logo actualizado']));
exit;


