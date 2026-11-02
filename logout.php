<?php
/**
 * logout.php
 * -----------------------------------------------------------------
 * Logs the current user out: records an audit entry, destroys the
 * session completely (including the session cookie), and redirects
 * to the login page.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    log_audit($conn, $_SESSION['user_id'], 'logout', 'User logged out');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// Start a fresh session just to carry the flash message through the redirect.
session_start();
set_flash('success', 'You have been logged out.');
redirect('login.php');
