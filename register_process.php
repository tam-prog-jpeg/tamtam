<?php
/**
 * register_process.php
 * -----------------------------------------------------------------
 * Handles the registration form POST from register.php.
 * Validates input, checks for duplicate username/email, hashes the
 * password, and inserts into `users` + `applicants` inside a single
 * transaction (role_id 5 = Applicant, see roles table).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

verify_csrf($_POST['csrf_token'] ?? '');

$first_name       = sanitize_input($_POST['first_name'] ?? '');
$last_name        = sanitize_input($_POST['last_name'] ?? '');
$username         = sanitize_input($_POST['username'] ?? '');
$email            = sanitize_input($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$contact_number   = sanitize_input($_POST['contact_number'] ?? '');
$applicant_type   = sanitize_input($_POST['applicant_type'] ?? 'External');
$college_office   = sanitize_input($_POST['college_office'] ?? '');
$college_id       = !empty($_POST['college_id']) ? (int) $_POST['college_id'] : null;
$department_id    = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
$address          = sanitize_input($_POST['address'] ?? '');

// Keep submitted values so the form can be re-filled on error.
$_SESSION['old_input'] = $_POST;

$errors = [];

if ($first_name === '' || $last_name === '') $errors[] = 'First and last name are required.';
if (strlen($username) < 4) $errors[] = 'Username must be at least 4 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
if (!in_array($applicant_type, ['Student', 'Faculty', 'Staff', 'External'], true)) $errors[] = 'Invalid applicant type.';
if ($college_office === '' && !$college_id) $errors[] = 'Please select your College or Office.';

// Resolve or auto-register college_id from college_office
if (!$college_id && !empty($college_office)) {
    $stmtCol = mysqli_prepare($conn, "SELECT college_id FROM colleges WHERE college_name = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtCol, 's', $college_office);
    mysqli_stmt_execute($stmtCol);
    $resCol = mysqli_stmt_get_result($stmtCol);
    if ($colRow = mysqli_fetch_assoc($resCol)) {
        $college_id = (int) $colRow['college_id'];
    }
    mysqli_stmt_close($stmtCol);

    if (!$college_id) {
        if (preg_match('/\(([^)]+)\)/', $college_office, $m)) {
            $code = strtoupper(trim($m[1]));
        } else {
            $words = preg_split('/[\s\/\-]+/', $college_office);
            $code = '';
            foreach ($words as $w) {
                if (strlen($w) > 0 && !in_array(strtolower($w), ['of', 'the', 'and', '&'])) {
                    $code .= strtoupper($w[0]);
                }
            }
            if (empty($code)) $code = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $college_office)), 0, 10);
        }
        $code = substr($code, 0, 20);

        $stmtChk = mysqli_prepare($conn, "SELECT college_id FROM colleges WHERE college_code = ?");
        mysqli_stmt_bind_param($stmtChk, 's', $code);
        mysqli_stmt_execute($stmtChk);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtChk))) {
            $code = substr($code, 0, 15) . '_' . rand(10, 99);
        }
        mysqli_stmt_close($stmtChk);

        $insCol = mysqli_prepare($conn, "INSERT INTO colleges (college_name, college_code) VALUES (?, ?)");
        mysqli_stmt_bind_param($insCol, 'ss', $college_office, $code);
        if (mysqli_stmt_execute($insCol)) {
            $college_id = mysqli_insert_id($conn);
        }
        mysqli_stmt_close($insCol);
    }
}

if (empty($errors)) {
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
    mysqli_stmt_execute($stmt);
    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
        $errors[] = 'That username or email is already registered.';
    }
    mysqli_stmt_close($stmt);
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('register.php');
}

// ---- Insert user + applicant profile in one transaction ----
mysqli_begin_transaction($conn);
try {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($conn, "INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, contact_number, status) VALUES (5, ?, ?, ?, ?, ?, ?, 'active')");
    mysqli_stmt_bind_param($stmt, 'ssssss', $username, $email, $hash, $first_name, $last_name, $contact_number);
    mysqli_stmt_execute($stmt);
    $userId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt2 = mysqli_prepare($conn, "INSERT INTO applicants (user_id, applicant_type, college_id, department_id, address) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt2, 'isiis', $userId, $applicant_type, $college_id, $department_id, $address);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    mysqli_commit($conn);
    log_audit($conn, $userId, 'register', 'New applicant account created');
    unset($_SESSION['old_input']);

    set_flash('success', 'Account created successfully. You may now log in.');
    redirect('login.php');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('Registration failed: ' . $e->getMessage());
    set_flash('error', 'Something went wrong while creating your account. Please try again.');
    redirect('register.php');
}
