<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------------
 * Shared helper functions used across the whole system:
 *  - input sanitization
 *  - CSRF token generation / validation
 *  - flash messages (toast notifications after redirect)
 *  - notification + audit log writers
 *  - file upload validation
 *  - small formatting helpers
 *
 * Include AFTER database.php and AFTER session_start() (auth.php does
 * both), e.g.:
 *      require_once __DIR__ . '/../includes/functions.php';
 * -----------------------------------------------------------------
 */

// ===========================================================
// INPUT / OUTPUT SAFETY
// ===========================================================

/** Trim + strip tags + convert to safe HTML for output (XSS prevention). */
function clean($value)
{
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Sanitize raw POST/GET input before using it in queries/logic. */
function sanitize_input($value)
{
    return trim(strip_tags($value ?? ''));
}

// ===========================================================
// CSRF PROTECTION
// ===========================================================

/** Generate (or reuse) a CSRF token for the current session. */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Echo a hidden CSRF field to embed inside a <form>. */
function csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Verify a submitted CSRF token; stops the request if it does not match. */
function verify_csrf($token)
{
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
    return true;
}

// ===========================================================
// FLASH MESSAGES (toast notifications after a redirect)
// ===========================================================

/** Queue a one-time flash message. $type: success | error | info | warning */
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Pop the queued flash message (returns null if none). Call once per page. */
function get_flash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ===========================================================
// REDIRECT HELPER
// ===========================================================

function redirect($path)
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

// ===========================================================
// NOTIFICATIONS
// ===========================================================

/** Insert a notification for a specific user. */
function create_notification($conn, $user_id, $title, $message, $link = null)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isss', $user_id, $title, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Count unread notifications for the topbar badge. */
function count_unread_notifications($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (int) ($row['total'] ?? 0);
}

// ===========================================================
// AUDIT LOG
// ===========================================================

function log_audit($conn, $user_id, $action, $details = '')
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isss', $user_id, $action, $details, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ===========================================================
// SYSTEM SETTINGS & PROFILE HELPERS
// ===========================================================

/**
 * Retrieve system upload configuration from system_settings or default fallbacks
 */
function get_upload_config($conn)
{
    static $config = null;
    if ($config !== null) return $config;

    $maxMb = 10;
    $types = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];

    if ($conn) {
        $res = @mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('max_upload_mb', 'allowed_file_types')");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if ($row['setting_key'] === 'max_upload_mb' && is_numeric($row['setting_value'])) {
                    $maxMb = (int) $row['setting_value'];
                }
                if ($row['setting_key'] === 'allowed_file_types' && !empty($row['setting_value'])) {
                    $types = array_values(array_filter(array_map('trim', explode(',', strtolower($row['setting_value'])))));
                }
            }
        }
    }

    $config = ['max_mb' => $maxMb, 'allowed_types' => $types];
    return $config;
}

/**
 * Safely update user profile including recovery_email if supported by DB schema
 */
function update_user_profile($conn, $userId, $firstName, $lastName, $contactNumber, $recoveryEmail = '')
{
    static $hasRecovery = null;
    if ($hasRecovery === null) {
        $check = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'recovery_email'");
        $hasRecovery = ($check && mysqli_num_rows($check) > 0);
        if (!$hasRecovery) {
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN recovery_email VARCHAR(150) DEFAULT NULL AFTER contact_number");
            $recheck = @mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'recovery_email'");
            $hasRecovery = ($recheck && mysqli_num_rows($recheck) > 0);
        }
    }

    if ($hasRecovery) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, contact_number = ?, recovery_email = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $firstName, $lastName, $contactNumber, $recoveryEmail, $userId);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, contact_number = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $firstName, $lastName, $contactNumber, $userId);
    }
    $res = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $res;
}

// ===========================================================
// FILE UPLOAD HELPERS
// ===========================================================

/**
 * Validate an uploaded file against allowed extensions and max size.
 * Returns ['ok' => bool, 'error' => string|null, 'ext' => string]
 */
function validate_upload(array $file, array $allowed_ext = ['pdf', 'docx', 'jpg', 'jpeg', 'png'], $max_mb = 10)
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $max_mb * 1024 * 1024) {
        return ['ok' => false, 'error' => "File exceeds the {$max_mb}MB size limit."];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        return ['ok' => false, 'error' => 'File type not allowed. Allowed: ' . implode(', ', $allowed_ext)];
    }
    return ['ok' => true, 'error' => null, 'ext' => $ext];
}

/** Move an already-validated upload into UPLOAD_DIR with a random safe name. */
function store_upload(array $file, $ext, $subfolder = 'documents')
{
    $targetDir = UPLOAD_DIR . trim($subfolder, '/') . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $targetDir . $storedName;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'stored_name' => $storedName,
            'file_path'   => 'uploads/' . trim($subfolder, '/') . '/' . $storedName,
        ];
    }
    return false;
}

// ===========================================================
// DYNAMIC QUERY HELPER (for filterable list pages)
// ===========================================================

/**
 * Bind a dynamic list of params to a prepared statement and execute it.
 * $types  = e.g. "isi"
 * $params = array of values in the same order as $types
 * Returns the mysqli_result (or true/false for non-SELECT statements).
 */
function bind_execute($stmt, $types, $params)
{
    if ($types !== '') {
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// ===========================================================
// DOCUMENT VERSIONING
// ===========================================================

/**
 * Work out the next version number for a document upload. If a
 * document with the same file name already exists on this
 * application, the new upload becomes the next version in that
 * lineage (v2, v3, ...); otherwise it starts at v1.
 */
function next_document_version($conn, $applicationId, $fileName)
{
    $stmt = mysqli_prepare($conn, "SELECT MAX(version) AS v FROM documents WHERE application_id = ? AND file_name = ?");
    mysqli_stmt_bind_param($stmt, 'is', $applicationId, $fileName);
    mysqli_stmt_execute($stmt);
    $max = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['v'] ?? null;
    mysqli_stmt_close($stmt);
    return $max ? ((int) $max + 1) : 1;
}

/** Delete every physical file attached to an application. Call this
 *  BEFORE deleting the application row, since the cascade delete will
 *  remove the `documents` rows (and therefore this data) immediately. */
function delete_application_files($conn, $applicationId)
{
    $stmt = mysqli_prepare($conn, "SELECT file_path FROM documents WHERE application_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $applicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $fullPath = __DIR__ . '/../' . $row['file_path'];
        if (is_file($fullPath)) { @unlink($fullPath); }
    }
    mysqli_stmt_close($stmt);
}

/** Delete every physical file across every application belonging to one
 *  applicant. Call this BEFORE deleting the applicant's user account,
 *  since the cascade delete will remove the related rows immediately. */
function delete_applicant_files($conn, $applicantId)
{
    $stmt = mysqli_prepare($conn, "SELECT application_id FROM applications WHERE applicant_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $applicantId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appIds = [];
    while ($row = mysqli_fetch_assoc($result)) { $appIds[] = $row['application_id']; }
    mysqli_stmt_close($stmt);

    foreach ($appIds as $appId) {
        delete_application_files($conn, $appId);
    }
}

// ===========================================================
// FORMATTING HELPERS
// ===========================================================

function format_date($datetime, $format = 'M d, Y h:i A')
{
    if (empty($datetime)) return '—';
    return date($format, strtotime($datetime));
}

function status_badge_class($status)
{
    return match ($status) {
        'pending'      => 'badge-warning',
        'under_review' => 'badge-info',
        'evaluated'    => 'badge-info',
        'returned'     => 'badge-warning',
        'approved'     => 'badge-success',
        'rejected'     => 'badge-danger',
        default        => 'badge-secondary',
    };
}

function status_label($status)
{
    return ucwords(str_replace('_', ' ', $status));
}

/** Generate a unique, human-readable tracking code, e.g. IPO-2026-000123 */
function generate_tracking_code($conn)
{
    $year = date('Y');
    do {
        $code = 'IPO-' . $year . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = mysqli_prepare($conn, "SELECT application_id FROM applications WHERE tracking_code = ?");
        mysqli_stmt_bind_param($stmt, 's', $code);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
        mysqli_stmt_close($stmt);
    } while ($exists);
    return $code;
}

/** Simple pagination math shared by every list page. */
function paginate($totalRows, $perPage = 10, $currentPage = 1)
{
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return compact('totalPages', 'currentPage', 'offset', 'perPage');
}
