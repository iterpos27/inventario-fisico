<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Location: ' . (is_logged_in() ? BASE_URL . '/dashboard.php' : BASE_URL . '/login.php'));
exit;
