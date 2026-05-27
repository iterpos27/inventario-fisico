<?php
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-src 'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

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
$bodyRoleClass = 'app-role-guest';
if (function_exists('is_logged_in') && is_logged_in()) {
    $bodyRoleClass = current_user_role() === 'admin' ? 'app-role-admin' : 'app-role-usuario';
}
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
<body class="<?= e($bodyRoleClass) ?>">

