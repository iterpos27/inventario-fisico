<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

header('Location: ' . BASE_URL . '/reportes.php');
exit;
