<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . (current_user_role() === 'admin' ? '/dashboard.php' : '/conteo.php'));
    exit;
}

$pageTitle = 'Ingresar - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>
<main class="login-page">
    <section class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo mx-auto">CR</div>
            <h1 class="h4 mt-3 mb-1"><?= APP_NAME ?></h1>
            <p class="text-secondary mb-0">Sistema de Conteo e Inventario</p>
        </div>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger">Usuario o contraseña incorrectos.</div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/actions/login_procesar.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label" for="usuario">Usuario</label>
                <input class="form-control form-control-lg" type="text" id="usuario" name="usuario" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-control form-control-lg" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Ingresar</button>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
