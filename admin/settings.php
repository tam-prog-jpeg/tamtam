<?php
/**
 * admin/settings.php
 * -----------------------------------------------------------------
 * Professional System Configuration & Policy Governance:
 *  - Institutional identity, office acronym, and physical location
 *  - Official communication channels (email, hotlines)
 *  - Intake quotas, allowed file extensions, and code prefix
 *  - System availability and maintenance lockout controls
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

    $fields = [
        'site_name',
        'site_acronym',
        'site_email',
        'site_phone',
        'site_address',
        'app_code_prefix',
        'max_upload_mb',
        'allowed_file_types'
    ];
    
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

    log_audit($conn, $_SESSION['user_id'], 'update_settings', 'System configuration and policies updated');
    set_flash('success', 'System configuration saved successfully.');
    redirect('admin/settings.php');
}

$settingsResult = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = mysqli_fetch_assoc($settingsResult)) { 
    $settings[$row['setting_key']] = $row['setting_value']; 
}

$isMaintenance = ($settings['maintenance_mode'] ?? '0') === '1';
$allowedTypesRaw = $settings['allowed_file_types'] ?? 'pdf,docx,jpg,jpeg,png';
$allowedTypesArr = array_filter(array_map('trim', explode(',', strtolower($allowedTypesRaw))));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary-600);margin-bottom:2px;">Configuration Center</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">System Configuration &amp; Policies</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Manage institutional branding, intake quotas, file constraints, and maintenance access.</div>
    </div>
    <div class="action-controls-group">
        <?php if ($isMaintenance): ?>
            <span class="badge badge-warning" style="padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                <i data-lucide="alert-triangle" style="width:14px;height:14px;"></i>
                Maintenance Mode Active
            </span>
        <?php else: ?>
            <span class="badge badge-success" style="padding:6px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
                <span style="width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                All Systems Operational
            </span>
        <?php endif; ?>
    </div>
</div>

<form method="POST" id="settingsForm">
    <?php csrf_field(); ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        
        <!-- Card 1: Institutional Identity & Communication -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="building-2" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Portal Identity &amp; Contact</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Institutional title, acronym, and communication channels.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                
                <!-- Office Name -->
                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label" for="site_name">
                            Office / System Title
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <span class="field-counter" id="siteNameCounter">0 / 80</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="building" class="input-icon-left"></i>
                        <input type="text" id="site_name" name="site_name" class="form-control" 
                               value="<?= clean($settings['site_name'] ?? 'ZPPSU Intellectual Property Office') ?>" 
                               maxlength="80" data-char-counter="#siteNameCounter" required>
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Displayed across portal topbars, official certificates, and transactional receipts.</span>
                    </div>
                </div>

                <!-- Office Acronym & Code Prefix -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="site_acronym">Institutional Acronym</label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="shield" class="input-icon-left"></i>
                            <input type="text" id="site_acronym" name="site_acronym" class="form-control" 
                                   value="<?= clean($settings['site_acronym'] ?? 'ZPPSU-IPO') ?>" placeholder="e.g. ZPPSU-IPO">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="app_code_prefix">Tracking Code Prefix</label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="hash" class="input-icon-left"></i>
                            <input type="text" id="app_code_prefix" name="app_code_prefix" class="form-control" 
                                   value="<?= clean($settings['app_code_prefix'] ?? 'IPO-') ?>" placeholder="e.g. IPO-" style="font-family:var(--font-mono);">
                        </div>
                    </div>
                </div>

                <!-- Official Email -->
                <div class="form-group">
                    <label class="form-label" for="site_email">
                        Official Contact Email
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="mail" class="input-icon-left"></i>
                        <input type="email" id="site_email" name="site_email" class="form-control" 
                               value="<?= clean($settings['site_email'] ?? 'ipo@zppsu.edu.ph') ?>" required>
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Primary mailbox for university inquiries and system notification dispatch headers.</span>
                    </div>
                </div>

                <!-- Official Phone -->
                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label" for="site_phone">Contact Hotline / Phone</label>
                        <span class="optional-badge">Optional</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="phone" class="input-icon-left"></i>
                        <input type="text" id="site_phone" name="site_phone" class="form-control" 
                               value="<?= clean($settings['site_phone'] ?? '(062) 991-3876') ?>" placeholder="+63 (062) ...">
                    </div>
                </div>

                <!-- Office Physical Address -->
                <div class="form-group" style="margin-bottom:0;">
                    <div class="form-label-row">
                        <label class="form-label" for="site_address">Office Physical Location</label>
                        <span class="optional-badge">Optional</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="map-pin" class="input-icon-left"></i>
                        <input type="text" id="site_address" name="site_address" class="form-control" 
                               value="<?= clean($settings['site_address'] ?? 'R.T. Lim Boulevard, Baliwasan, Zamboanga City') ?>">
                    </div>
                </div>

            </div>
        </div>

        <!-- Card 2: Document Intake Quotas & Formats -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:var(--color-success-bg);color:var(--color-success);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="file-check-2" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Document Intake Quota &amp; Formats</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Manage physical file intake quotas, MIME limits, and accepted formats.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                
                <!-- Maximum File Upload Size -->
                <div class="form-group">
                    <label class="form-label" for="max_upload_mb">
                        Maximum Upload File Size
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group" style="max-width:280px;">
                        <input type="number" id="max_upload_mb" name="max_upload_mb" class="form-control" 
                               min="1" max="100" value="<?= clean($settings['max_upload_mb'] ?? '10') ?>" required style="font-family:var(--font-mono);font-weight:600;">
                        <span class="input-group-text">MB per file</span>
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Enforced on all applicant document uploads, specs, drawings, and supporting claims.</span>
                    </div>
                </div>

                <!-- Allowed File Extensions -->
                <div class="form-group">
                    <label class="form-label" for="allowed_file_types">
                        Allowed File Extensions
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon" style="margin-bottom:10px;">
                        <i data-lucide="file-code" class="input-icon-left"></i>
                        <input type="text" id="allowed_file_types" name="allowed_file_types" class="form-control" 
                               value="<?= clean($allowedTypesRaw) ?>" required style="font-family:var(--font-mono);font-size:13px;">
                    </div>
                    <div class="form-hint" style="margin-bottom:12px;">
                        <i data-lucide="info"></i>
                        <span>Comma-separated list without dots (e.g. <code>pdf,docx,jpg,jpeg,png</code>).</span>
                    </div>

                    <!-- Quick Preset Format Chips -->
                    <div>
                        <div style="font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text-muted);margin-bottom:8px;">Quick Format Presets (Click to Add / Remove):</div>
                        <div class="form-chip-group" id="formatChipGroup">
                            <?php 
                            $commonFormats = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'xlsx', 'zip'];
                            foreach ($commonFormats as $fmt): 
                                $isActive = in_array($fmt, $allowedTypesArr, true);
                            ?>
                                <button type="button" class="form-chip <?= $isActive ? 'active' : '' ?>" data-format="<?= $fmt ?>">
                                    <i data-lucide="<?= $isActive ? 'check' : 'plus' ?>" style="width:12px;height:12px;"></i>
                                    <span><?= strtoupper($fmt) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="background:var(--color-surface-subtle);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px;margin-top:20px;">
                    <div style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;color:var(--color-text);margin-bottom:4px;">
                        <i data-lucide="shield-check" style="width:15px;height:15px;color:var(--color-success);"></i>
                        Intake Security Architecture
                    </div>
                    <div style="font-size:12px;color:var(--color-text-muted);line-height:1.5;">
                        Executable files (<code>.exe, .sh, .php, .js</code>) are unconditionally rejected at the server level regardless of configured extension lists to protect university infrastructure.
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Card 3: System Availability & Emergency Maintenance Mode -->
    <div class="card" style="border-radius:var(--radius-lg);margin-bottom:24px;">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:8px;background:var(--color-warning-bg);color:var(--color-warning);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="shield-alert" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h3 style="margin:0;font-size:15px;font-weight:700;">System Availability &amp; Maintenance Lockout</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Control platform access for scheduled maintenance, database upgrades, or emergencies.</div>
            </div>
        </div>
        <div class="card-body" style="padding:20px;">
            
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);gap:16px;">
                <div style="max-width:700px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <span style="font-weight:700;font-size:14px;color:var(--color-text);">Emergency Maintenance Lockout</span>
                        <?php if ($isMaintenance): ?>
                            <span class="badge badge-warning" style="font-size:11px;padding:2px 8px;">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary" style="font-size:11px;padding:2px 8px;">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.55;">
                        When active, only users with the <strong>Administrator</strong> role can log in. Applicants, Staff, Evaluators, and Managers will receive a polite maintenance notice during authentication attempts.
                    </div>
                </div>
                <div>
                    <label class="form-switch form-switch-warning">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?= $isMaintenance ? 'checked' : '' ?>>
                        <span class="form-switch-track">
                            <span class="form-switch-thumb"></span>
                        </span>
                    </label>
                </div>
            </div>

            <?php if ($isMaintenance): ?>
                <div style="margin-top:16px;background:var(--color-warning-bg);border:1px solid var(--color-warning);border-radius:var(--radius-md);padding:14px 18px;display:flex;align-items:flex-start;gap:12px;">
                    <i data-lucide="alert-triangle" style="width:20px;height:20px;color:var(--color-warning);flex-shrink:0;margin-top:2px;"></i>
                    <div style="font-size:12.5px;color:var(--color-warning-text);line-height:1.55;">
                        <strong>Warning: Maintenance lockout is currently ACTIVE.</strong> Public access and applicant submissions are paused. Only administrators can use the system. Toggle the switch above to <strong>OFF</strong> and save changes to restore public operation.
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <div class="card-footer" style="padding:16px 20px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);display:flex;align-items:center;justify-content:space-between;border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
            <div style="font-size:12px;color:var(--color-text-muted);display:flex;align-items:center;gap:6px;">
                <i data-lucide="clock" style="width:14px;height:14px;"></i>
                <span>Changes are written immediately to active production configurations.</span>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="<?= BASE_URL ?>admin/settings.php" class="btn btn-outline btn-sm">Discard Changes</a>
                <button type="submit" class="btn btn-primary" style="padding:9px 24px;">
                    <i data-lucide="save" style="width:15px;height:15px;"></i>
                    Save Configuration
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format chips dynamic synchronization with allowed_file_types input
    const inputField = document.getElementById('allowed_file_types');
    const chipButtons = document.querySelectorAll('#formatChipGroup .form-chip');

    chipButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const format = this.dataset.format.toLowerCase();
            let current = inputField.value.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);

            if (current.includes(format)) {
                current = current.filter(f => f !== format);
                this.classList.remove('active');
                const icon = this.querySelector('i, svg');
                if (icon) {
                    icon.setAttribute('data-lucide', 'plus');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            } else {
                current.push(format);
                this.classList.add('active');
                const icon = this.querySelector('i, svg');
                if (icon) {
                    icon.setAttribute('data-lucide', 'check');
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            }
            inputField.value = current.join(',');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

