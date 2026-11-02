<?php
/**
 * admin/settings.php
 * -----------------------------------------------------------------
 * Edits the key/value pairs in system_settings (site name, contact
 * email, max upload size, allowed file types, maintenance mode).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Settings';
$currentPage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $fields = ['site_name', 'site_email', 'max_upload_mb', 'allowed_file_types'];
    foreach ($fields as $key) {
        $value = sanitize_input($_POST[$key] ?? '');
        $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        mysqli_stmt_bind_param($stmt, 'sss', $key, $value, $value);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $maintenance, $maintenance);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    log_audit($conn, $_SESSION['user_id'], 'update_settings', 'System settings updated');
    set_flash('success', 'System settings saved successfully.');
    redirect('admin/settings.php');
}

$settingsResult = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = mysqli_fetch_assoc($settingsResult)) { $settings[$row['setting_key']] = $row['setting_value']; }

$isMaintenance = ($settings['maintenance_mode'] ?? '0') === '1';
$allowedTypesArr = array_filter(array_map('trim', explode(',', $settings['allowed_file_types'] ?? 'pdf,docx,jpg,jpeg,png')));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">System Configuration &amp; Policies</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Configure portal identity, institutional contact channels, file upload quotas, and maintenance controls.</div>
    </div>
    <div class="action-controls-group">
        <?php if ($isMaintenance): ?>
            <span class="badge badge-warning" style="padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
                Maintenance Active
            </span>
        <?php else: ?>
            <span class="badge badge-success" style="padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                All Systems Operational
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST">
    <?php csrf_field(); ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        <!-- Card 1: Branding & Contact -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="globe" style="width:16px;height:16px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;">Portal Identity &amp; Contact</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Institutional title and communication email.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                <div class="form-group" style="margin-bottom:18px;">
                    <label style="font-weight:600;font-size:13px;margin-bottom:6px;display:block;">Site / Office Name</label>
                    <input type="text" name="site_name" class="form-control" value="<?= clean($settings['site_name'] ?? '') ?>" required style="border-radius:var(--radius-md);">
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">Appears in the header, login page, and automated email notifications.</div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:600;font-size:13px;margin-bottom:6px;display:block;">Official Contact Email</label>
                    <input type="email" name="site_email" class="form-control" value="<?= clean($settings['site_email'] ?? '') ?>" required style="border-radius:var(--radius-md);">
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">Primary institutional inquiry and technical support address.</div>
                </div>
            </div>
        </div>

        <!-- Card 2: Document Intake & Quotas -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
                <div style="width:32px;height:32px;border-radius:8px;background:rgba(16,185,129,0.1);color:var(--color-success);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="file-up" style="width:16px;height:16px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;">Document Intake Quota &amp; Formats</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Control physical file upload restrictions.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                <div class="form-group" style="margin-bottom:18px;">
                    <label style="font-weight:600;font-size:13px;margin-bottom:6px;display:block;">Maximum File Upload Size (MB)</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" name="max_upload_mb" class="form-control" min="1" max="100" value="<?= clean($settings['max_upload_mb'] ?? '10') ?>" required style="border-radius:var(--radius-md);width:120px;">
                        <span style="font-size:13px;color:var(--color-text-muted);font-weight:500;">Megabytes (MB) per file</span>
                    </div>
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">Enforced dynamically on all applicant and staff upload forms.</div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:600;font-size:13px;margin-bottom:6px;display:block;">Allowed File Extensions</label>
                    <input type="text" name="allowed_file_types" class="form-control" value="<?= clean($settings['allowed_file_types'] ?? 'pdf,docx,jpg,jpeg,png') ?>" required style="border-radius:var(--radius-md);">
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:4px;margin-bottom:10px;">Comma-separated extensions without dots or spaces.</div>
                    
                    <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                        <span style="font-size:11.5px;color:var(--color-text-subtle);">Active Formats:</span>
                        <?php foreach ($allowedTypesArr as $type): ?>
                            <span class="badge badge-secondary" style="font-size:11px;padding:2px 8px;font-family:var(--font-mono);"><?= strtoupper($type) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: System Availability & Maintenance Mode -->
    <div class="card" style="border-radius:var(--radius-lg);margin-bottom:24px;">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:8px;background:rgba(245,158,11,0.12);color:var(--color-warning);display:flex;align-items:center;justify-content:center;">
                <i data-lucide="shield-alert" style="width:16px;height:16px;"></i>
            </div>
            <div>
                <h3 style="margin:0;font-size:15px;">System Availability &amp; Maintenance Mode</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Manage emergency maintenance access and operational uptime.</div>
            </div>
        </div>
        <div class="card-body" style="padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                <div style="max-width:650px;">
                    <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">Enable Maintenance Lockout</div>
                    <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;">
                        When active, only users with the <strong>Administrator</strong> role can log in. Applicants, Staff, Evaluators, and Managers will receive a maintenance notice during sign-in attempts.
                    </div>
                </div>
                <div>
                    <label class="switch" style="position:relative;display:inline-block;width:44px;height:24px;margin:0;">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?= $isMaintenance ? 'checked' : '' ?> style="opacity:0;width:0;height:0;">
                        <span class="slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--color-border);transition:.2s;border-radius:24px;"></span>
                    </label>
                </div>
            </div>

            <?php if ($isMaintenance): ?>
                <div style="margin-top:14px;background:var(--color-warning-bg);border:1px solid var(--color-warning);border-radius:var(--radius-md);padding:12px 16px;display:flex;align-items:center;gap:10px;">
                    <i data-lucide="alert-circle" style="width:18px;height:18px;color:var(--color-warning);flex-shrink:0;"></i>
                    <div style="font-size:12.5px;color:var(--color-warning);line-height:1.5;">
                        <strong>Warning:</strong> Maintenance mode is currently <strong>ACTIVE</strong>. Non-administrator users cannot access the portal. Uncheck the switch above and save to reopen public access.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer" style="padding:14px 20px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);display:flex;justify-content:flex-end;border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
            <button type="submit" class="btn btn-primary" style="padding:9px 24px;">
                <i data-lucide="save" style="width:15px;height:15px;"></i>
                Save Configuration Changes
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

