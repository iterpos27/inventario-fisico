<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_login();

header('Location: ' . BASE_URL . '/reportes.php');
exit;

