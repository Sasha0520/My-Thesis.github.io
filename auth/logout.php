<?php
// auth/logout.php
require_once __DIR__ . '/../includes/auth_guard.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /peer-tutoring/auth/login.php'); exit;
