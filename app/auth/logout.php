<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

$_SESSION = [];
session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;

