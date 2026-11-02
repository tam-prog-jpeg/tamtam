<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------
 * Session bootstrap + role-based access control (RBAC).
 * Every protected page must start with:
 *
 *      require_once __DIR__ . '/../database.php';
 *      require_once __DIR__ . '/../includes/functions.php';
 *      require_once __DIR__ . '/../includes/auth.php';
 *      require_role(['Administrator']);   // roles allowed on this page
 *
 * This file starts the session, exposes the current user in
 * $_SESSION, and provides require_login()/require_role() guards used
 * by every dashboard/module page.
 * -----------------------------------------------------------------
 */

// ---- Secure session bootstrap -------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie before starting the session.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Regenerate the session id periodically to reduce session fixation risk.
if (empty($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} elseif (time() - $_SESSION['last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

/** True if a user is currently logged in. */
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

/** Redirect to login if not authenticated. Call at the top of every protected page. */
function require_login()
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/**
 * Redirect to login (or a "not authorized" state) unless the logged-in
 * user's role is in $allowedRoles, e.g. require_role(['Administrator','Manager']).
 */
function require_role(array $allowedRoles)
{
    require_login();
    if (!in_array($_SESSION['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        die('<h2 style="font-family:sans-serif;color:#DC2626;">403 — You are not authorized to view this page.</h2>');
    }
}

/** Map a role name to its own dashboard folder for post-login redirects. */
function role_home($roleName)
{
    return match ($roleName) {
        'Administrator' => 'admin/dashboard.php',
        'Staff'         => 'staff/dashboard.php',
        'Evaluator'     => 'evaluator/dashboard.php',
        'Manager'       => 'manager/dashboard.php',
        'Applicant'     => 'applicant/dashboard.php',
        default         => 'login.php',
    };
}
