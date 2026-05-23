<?php
$pageTitle = $pageTitle ?? APP_NAME;
$logoPath = asset_url('img/logo.png');
foreach (['png', 'jpg', 'jpeg', 'webp'] as $logoExt) {
    if (file_exists(PUBLIC_PATH . "/assets/img/logo.{$logoExt}")) {
        $logoPath = asset_url("img/logo.{$logoExt}");
        break;
    }
}
$styleVersion = file_exists(PUBLIC_PATH . '/assets/css/style.css')
    ? (string) filemtime(PUBLIC_PATH . '/assets/css/style.css')
    : APP_VERSION;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('css/style.css') ?>?v=<?= e($styleVersion) ?>" rel="stylesheet">
</head>
<body>
