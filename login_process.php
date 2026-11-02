<?php
/**
 * login_process.php
 * -----------------------------------------------------------------
 * Handles the login form POST from login.php:
 *  - verifies CSRF token
 *  - looks up the user by username OR email (prepared statement)
 *  - verifies the password with password_verify()
 *  - checks account status
 *  - stores identity in the session and logs the event
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

verify_csrf($_POST['csrf_token'] ?? '');

$identifier = sanitize_input($_POST['username'] ?? '');
$password   = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    set_flash('error', 'Please enter your username/email and password.');
    redirect('login.php');
}

$stmt = mysqli_prepare($conn, "
    SELECT u.user_id, u.username, u.email, u.password_hash, u.first_name, u.last_name,
           u.status, r.role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.username = ? OR u.email = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Generic error message on purpose: don't reveal whether the username exists.
$genericError = 'Invalid username/email or password.';

if (!$user || !password_verify($password, $user['password_hash'])) {
    set_flash('error', $genericError);
    redirect('login.php');
}

if ($user['status'] !== 'active') {
    set_flash('error', 'Your account is currently ' . $user['status'] . '. Please contact the IPO administrator.');
    redirect('login.php');
}

// ---- Maintenance mode: only Administrators may log in while enabled ----
$maintRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'"));
if (($maintRow['setting_value'] ?? '0') === '1' && $user['role_name'] !== 'Administrator') {
    set_flash('error', 'The system is currently under maintenance. Please try again later.');
    redirect('login.php');
}

// ---- Success: establish the session ----
session_regenerate_id(true);
$_SESSION['user_id']    = $user['user_id'];
$_SESSION['username']   = $user['username'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name']  = $user['last_name'];
$_SESSION['full_name']  = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['role_name']  = $user['role_name'];
$_SESSION['last_regen'] = time();

log_audit($conn, $user['user_id'], 'login', 'User logged in successfully');

set_flash('success', 'Welcome back, ' . $user['first_name'] . '!');
redirect(role_home($user['role_name']));
