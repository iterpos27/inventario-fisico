<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';

$_SESSION = [];
expire_session_cookie();
session_destroy();

header('Location: ' . page_url('login'));
exit;


