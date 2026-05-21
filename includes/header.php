<?php
$pageTitle = $pageTitle ?? APP_NAME;
$logoPath = BASE_URL . '/assets/img/logo.png';
foreach (['png', 'jpg', 'jpeg', 'webp'] as $logoExt) {
    if (file_exists(dirname(__DIR__) . "/assets/img/logo.{$logoExt}")) {
        $logoPath = BASE_URL . "/assets/img/logo.{$logoExt}";
        break;
    }
}
$styleVersion = file_exists(dirname(__DIR__) . '/assets/css/style.css')
    ? (string) filemtime(dirname(__DIR__) . '/assets/css/style.css')
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
    <link href="<?= BASE_URL ?>/assets/css/style.css?v=<?= e($styleVersion) ?>" rel="stylesheet">
</head>
<body>
