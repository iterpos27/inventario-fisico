<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . page_url(current_user_role() === 'admin' ? 'dashboard' : 'conteo'));
    exit;
}

$pageTitle = 'Ingresar - ' . APP_NAME;
require_once APP_INCLUDES_PATH . '/header.php';
?>
<main class="login-page">
    <section class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo mx-auto">CR</div>
            <h1 class="h4 mt-3 mb-1"><?= APP_NAME ?></h1>
            <p class="text-secondary mb-0">Sistema de Conteo e Inventario</p>
        </div>

        <?php if (!empty($_GET['error']) && $_GET['error'] === 'bloqueado'): ?>
            <div class="alert alert-danger">Demasiados intentos. Espere 15 minutos e intente nuevamente.</div>
        <?php elseif (!empty($_GET['error']) && $_GET['error'] === 'sesion'): ?>
            <div class="alert alert-warning">La sesion expiro por inactividad.</div>
        <?php elseif (!empty($_GET['error'])): ?>
            <div class="alert alert-danger">Usuario o contrasena incorrectos.</div>
        <?php endif; ?>

        <form action="<?= action_url('login_procesar') ?>" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label" for="usuario">Usuario</label>
                <input class="form-control form-control-lg" type="text" id="usuario" name="usuario" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contrasena</label>
                <input class="form-control form-control-lg" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Ingresar</button>
        </form>
    </section>
</main>
<?php require_once APP_INCLUDES_PATH . '/footer.php'; ?>
