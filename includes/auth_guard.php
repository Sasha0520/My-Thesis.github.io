<?php
// includes/auth_guard.php
// Usage: require_once __DIR__ . '/../includes/auth_guard.php';
//        auth_require();               // any logged-in user
//        auth_require('tutor');        // specific role
//        auth_require(['tutor','admin']); // multiple roles allowed

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_require($roles = null): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: /peer-tutoring/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if ($roles !== null) {
        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($_SESSION['role'], $allowed, true)) {
            http_response_code(403);
            include __DIR__ . '/403.php';
            exit;
        }
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['role']      ?? '',
        'email'=> $_SESSION['email']     ?? '',
    ];
}

function flash(string $key, string $msg = ''): string {
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}
