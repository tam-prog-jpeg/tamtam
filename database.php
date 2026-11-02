<?php
/**
 * database.php
 * -----------------------------------------------------------------
 * Central MySQLi connection used by every page in the system.
 * Include this file with require_once at the top of any script that
 * needs database access:
 *      require_once __DIR__ . '/database.php';   (from root)
 *      require_once __DIR__ . '/../database.php'; (from a subfolder)
 * -----------------------------------------------------------------
 */

// ---- Connection settings -------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'zppsu_ipo');

// ---- Open connection -------------------------------------------------
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Never leak connection details to the browser in production.
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('Database connection failed. Please contact the system administrator.');
}

mysqli_set_charset($conn, 'utf8mb4');

// ---- Base URL / paths used across the app -------------------------------
// Adjust BASE_URL if the project folder name changes.
define('BASE_URL', '/ZPPSU_IPO/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
