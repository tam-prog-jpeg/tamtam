<?php
/**
 * database.example.php
 * -----------------------------------------------------------------
 * Example database connection configuration.
 * Copy this file to `database.php` and update with your local
 * MySQL/MariaDB database credentials:
 *      cp database.example.php database.php
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
// Adjust BASE_URL if your local web folder name is different.
define('BASE_URL', '/zppsu_ipo/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
