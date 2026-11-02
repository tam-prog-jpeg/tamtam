<?php
/**
 * admin/backup.php
 * -----------------------------------------------------------------
 * Administrator backup tools:
 *  1. Download a full database backup (.sql) — generated in pure
 *     PHP/MySQLi, so it does not depend on mysqldump being installed.
 *  2. Download all uploaded documents as a single .zip file.
 *
 * To RESTORE a database backup: phpMyAdmin -> Import tab -> choose the
 * .sql file -> Go. To restore documents: extract the zip and copy its
 * "uploads" folder into the project root.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Backup';
$currentPage = 'backup';

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

/** Human-readable file size, e.g. 1.4 MB */
function fmt_bytes($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $bytes = (float) $bytes;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

/** Count files and total size inside the uploads folder. */
function get_upload_stats()
{
    $stats = ['files' => 0, 'bytes' => 0];
    $base = realpath(UPLOAD_DIR);
    if ($base === false || !is_dir($base)) return $stats;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getFilename() !== '.htaccess') {
            $stats['files']++;
            $stats['bytes'] += $file->getSize();
        }
    }
    return $stats;
}

/** Stream the whole database as SQL text straight to the browser. */
function dump_database($conn)
{
    echo "-- ZPPSU IPO Management System — database backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: " . DB_NAME . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    echo "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    echo "USE `" . DB_NAME . "`;\n\n";

    $tables = [];
    $res = mysqli_query($conn, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = mysqli_fetch_row($res)) { $tables[] = $row[0]; }
    mysqli_free_result($res);

    foreach ($tables as $table) {
        $q = '`' . str_replace('`', '``', $table) . '`';

        echo "-- ---------------------------------------------\n";
        echo "-- Table: $table\n";
        echo "-- ---------------------------------------------\n";
        echo "DROP TABLE IF EXISTS $q;\n";
        $createRes = mysqli_query($conn, "SHOW CREATE TABLE $q");
        $create = mysqli_fetch_row($createRes);
        mysqli_free_result($createRes);
        echo $create[1] . ";\n\n";

        // Unbuffered read so large tables don't exhaust memory.
        $data = mysqli_query($conn, "SELECT * FROM $q", MYSQLI_USE_RESULT);
        $cols = null;
        $batch = [];
        while ($r = mysqli_fetch_assoc($data)) {
            if ($cols === null) { $cols = '`' . implode('`, `', array_keys($r)) . '`'; }
            $vals = [];
            foreach ($r as $v) {
                $vals[] = $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) >= 100) {
                echo "INSERT INTO $q ($cols) VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        mysqli_free_result($data);
        if (!empty($batch)) {
            echo "INSERT INTO $q ($cols) VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
}

/** Remember when the last backup was taken (shown on this page). */
function record_backup_time($conn, $key)
{
    $now = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    mysqli_stmt_bind_param($stmt, 'sss', $key, $now, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ---------------------------------------------------------------
// Handle download requests (must run BEFORE any HTML is output)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    set_time_limit(0);

    if ($action === 'backup_database') {
        record_backup_time($conn, 'last_db_backup_at');
        log_audit($conn, $_SESSION['user_id'], 'backup_database', 'Downloaded a full database backup');

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="zppsu_ipo_backup_' . date('Ymd_His') . '.sql"');
        header('Cache-Control: no-store');
        dump_database($conn);
        exit;
    }

    if ($action === 'backup_files') {
        if (!class_exists('ZipArchive')) {
            set_flash('error', 'The PHP "zip" extension is not enabled on this server, so the documents backup is unavailable. Enable extension=zip in php.ini, or copy the uploads folder manually.');
            redirect('admin/backup.php');
        }
        $stats = get_upload_stats();
        if ($stats['files'] === 0) {
            set_flash('error', 'There are no uploaded documents to back up yet.');
            redirect('admin/backup.php');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ipo_zip_');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            set_flash('error', 'Could not create the backup file. Please try again.');
            redirect('admin/backup.php');
        }

        $base = realpath(UPLOAD_DIR);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $zip->addFile($file->getPathname(), 'uploads/' . $relative);
            }
        }
        $zip->close();

        record_backup_time($conn, 'last_files_backup_at');
        log_audit($conn, $_SESSION['user_id'], 'backup_files', 'Downloaded a backup of all uploaded documents');

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="zppsu_ipo_documents_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }
    redirect('admin/backup.php');
}

// ---------------------------------------------------------------
// Page data
// ---------------------------------------------------------------
$tableCount = 0;
$rowCount = 0;
$dbName = DB_NAME;
$infoStmt = mysqli_prepare($conn, "SELECT COUNT(*), COALESCE(SUM(data_length + index_length), 0), COALESCE(SUM(table_rows), 0) FROM information_schema.tables WHERE table_schema = ?");
mysqli_stmt_bind_param($infoStmt, 's', $dbName);
mysqli_stmt_execute($infoStmt);
mysqli_stmt_bind_result($infoStmt, $tableCount, $dbBytes, $rowCount);
mysqli_stmt_fetch($infoStmt);
mysqli_stmt_close($infoStmt);

$uploadStats = get_upload_stats();

$settingsResult = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('last_db_backup_at','last_files_backup_at')");
$lastBackup = [];
while ($row = mysqli_fetch_assoc($settingsResult)) { $lastBackup[$row['setting_key']] = $row['setting_value']; }

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Disaster Recovery &amp; System Backups</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Generate on-demand database SQL dumps and physical document archives for disaster recovery and audits.</div>
    </div>
    <div class="action-controls-group">
        <span class="badge badge-info" style="padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
            <i data-lucide="server" style="width:14px;height:14px;"></i>
            MySQL Engine: <?= clean($dbName) ?>
        </span>
    </div>
</div>

<!-- =====================================================================
     3 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
    <!-- Card 1: Database Storage -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Database Storage</span>
            <span class="info-icon" title="Calculated size of all data and index lengths in the active schema">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= fmt_bytes($dbBytes) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="bkpGradBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,24 50,14 T100,6 L100,40 L0,40 Z" fill="url(#bkpGradBlue)"/>
                <path d="M0,32 Q25,24 50,14 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="database" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span><?= (int)$tableCount ?> tables &bull; <?= number_format($rowCount) ?> total records</span>
        </div>
    </div>

    <!-- Card 2: Document Storage -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Document Attachments</span>
            <span class="info-icon" title="Cumulative size and count of physical application files on disk">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= fmt_bytes($uploadStats['bytes']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="bkpGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,35 Q25,25 50,15 T100,4 L100,40 L0,40 Z" fill="url(#bkpGradGreen)"/>
                <path d="M0,35 Q25,25 50,15 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="folder" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span><?= number_format($uploadStats['files']) ?> physical uploaded files</span>
        </div>
    </div>

    <!-- Card 3: Disaster Readiness -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Disaster Readiness</span>
            <span class="info-icon" title="Operational integrity state based on recent backup generation">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value" style="font-size:24px;letter-spacing:-0.02em;">
                <?= isset($lastBackup['last_db_backup_at']) ? 'Protected' : 'Pending' ?>
            </div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="bkpGradPurple" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8B5CF6" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,28 Q25,20 50,14 T100,8 L100,40 L0,40 Z" fill="url(#bkpGradPurple)"/>
                <path d="M0,28 Q25,20 50,14 T100,8" stroke="#8B5CF6" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="shield-check" style="width:13px;height:13px;color:var(--color-purple);"></i>
            <span>Last dump: <?= isset($lastBackup['last_db_backup_at']) ? format_date($lastBackup['last_db_backup_at'], 'M d, Y') : 'Never' ?></span>
        </div>
    </div>
</div>

<!-- =====================================================================
     BACKUP ACTION CARDS
     ===================================================================== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
    <!-- Card 1: Database SQL Backup -->
    <div class="card" style="border-radius:var(--radius-lg);display:flex;flex-direction:column;justify-content:space-between;">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="database" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;">Database SQL Dump</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Full relational database schema &amp; table records</div>
                </div>
            </div>
            <span class="badge badge-primary" style="font-family:var(--font-mono);font-size:11px;">.SQL</span>
        </div>
        <div class="card-body" style="padding:20px;">
            <p style="font-size:13px;color:var(--color-text-muted);line-height:1.6;margin:0 0 16px;">
                Exports every table including <code>users</code>, <code>applications</code>, <code>evaluations</code>, <code>documents</code>, <code>settings</code>, and <code>audit_logs</code>. Generated in native PHP without relying on external <code>mysqldump</code> binaries.
            </p>
            <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:20px;">
                <div style="font-size:12px;color:var(--color-text-muted);display:flex;justify-content:space-between;">
                    <span>Last Database Backup:</span>
                    <strong style="color:var(--color-text);">
                        <?= isset($lastBackup['last_db_backup_at']) ? format_date($lastBackup['last_db_backup_at']) : 'Never generated' ?>
                    </strong>
                </div>
            </div>
            <form method="POST" style="margin:0;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="backup_database">
                <button type="submit" class="btn btn-primary" style="width:100%;padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                    Download Database Backup (.sql)
                </button>
            </form>
        </div>
    </div>

    <!-- Card 2: Documents ZIP Backup -->
    <div class="card" style="border-radius:var(--radius-lg);display:flex;flex-direction:column;justify-content:space-between;">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:rgba(16,185,129,0.1);color:var(--color-success);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="archive" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;">Document Attachments Archive</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Compressed physical application files on disk</div>
                </div>
            </div>
            <span class="badge badge-success" style="font-family:var(--font-mono);font-size:11px;">.ZIP</span>
        </div>
        <div class="card-body" style="padding:20px;">
            <p style="font-size:13px;color:var(--color-text-muted);line-height:1.6;margin:0 0 16px;">
                Packages all supporting technical disclosures, patents, trademarks, and PDF documents uploaded by applicants. The SQL dump stores metadata; this archive preserves actual physical files.
            </p>
            <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:20px;">
                <div style="font-size:12px;color:var(--color-text-muted);display:flex;justify-content:space-between;">
                    <span>Last Files Backup:</span>
                    <strong style="color:var(--color-text);">
                        <?= isset($lastBackup['last_files_backup_at']) ? format_date($lastBackup['last_files_backup_at']) : 'Never generated' ?>
                    </strong>
                </div>
            </div>
            <form method="POST" style="margin:0;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="backup_files">
                <button type="submit" class="btn btn-primary" style="width:100%;padding:10px 20px;display:flex;align-items:center;justify-content:center;gap:8px;" <?= $uploadStats['files'] === 0 ? 'disabled' : '' ?>>
                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                    Download Documents Archive (.zip)
                </button>
            </form>
        </div>
    </div>
</div>

<!-- =====================================================================
     RESTORATION PROTOCOL & GUIDELINES
     ===================================================================== -->
<div class="card" style="border-radius:var(--radius-lg);">
    <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;border-radius:8px;background:var(--color-surface-subtle);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;">
            <i data-lucide="help-circle" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
        </div>
        <div>
            <h3 style="margin:0;font-size:15px;">Disaster Recovery &amp; Restoration Protocol</h3>
            <div style="font-size:12px;color:var(--color-text-muted);">Standard operating procedures for system disaster recovery</div>
        </div>
    </div>
    <div class="card-body" style="padding:20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;">
            <div style="border-left:2px solid var(--color-primary-600);padding-left:14px;">
                <div style="font-weight:600;font-size:13.5px;color:var(--color-text);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="database" style="width:14px;height:14px;color:var(--color-primary-600);"></i>
                    1. Restoring Database (.sql)
                </div>
                <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">
                    Open <code>http://localhost/phpmyadmin</code>, click the <strong>Import</strong> tab, select your downloaded <code>.sql</code> backup file, and click <strong>Go</strong>. Alternatively, execute via terminal:
                    <div style="margin-top:6px;background:var(--color-bg);padding:6px 10px;border-radius:6px;font-family:var(--font-mono);font-size:11.5px;border:1px solid var(--color-border);">
                        mysql -u root zppsu_ipo &lt; backup.sql
                    </div>
                </div>
            </div>

            <div style="border-left:2px solid var(--color-success);padding-left:14px;">
                <div style="font-weight:600;font-size:13.5px;color:var(--color-text);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="archive" style="width:14px;height:14px;color:var(--color-success);"></i>
                    2. Restoring Documents (.zip)
                </div>
                <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">
                    Extract the downloaded <code>.zip</code> file, then copy the extracted <code>uploads/</code> folder directly into the project root directory, allowing file replacement if prompted.
                </div>
            </div>

            <div style="border-left:2px solid var(--color-warning);padding-left:14px;">
                <div style="font-weight:600;font-size:13.5px;color:var(--color-text);margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="shield" style="width:14px;height:14px;color:var(--color-warning);"></i>
                    3. Off-Site Storage Protocol
                </div>
                <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">
                    Store critical backups on secure institutional external media or encrypted cloud repositories. Never store disaster recovery files exclusively on the local web server.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
