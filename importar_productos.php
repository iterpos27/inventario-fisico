<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_admin();

header('Location: ' . BASE_URL . '/productos.php');
exit;
