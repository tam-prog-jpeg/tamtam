<?php
/**
 * manager/profile.php
 * -----------------------------------------------------------------
 * Manager's own account settings: name/contact/recovery email,
 * and change password (current password required).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'My Profile';
$currentPage = 'profile';
$errors = [];

// ---- Active tab selection ----
$activeTab = sanitize_input($_GET['tab'] ?? 'account');
if (!in_array($activeTab, ['account', 'security'], true)) {
    $activeTab = 'account';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $activeTab = 'account';

    $first_name     = sanitize_input($_POST['first_name'] ?? '');
    $last_name      = sanitize_input($_POST['last_name'] ?? '');
    $contact_number = sanitize_input($_POST['contact_number'] ?? '');
    $recovery_email = sanitize_input($_POST['recovery_email'] ?? '');

    if ($first_name === '' || $last_name === '') {
        $errors[] = 'First and last name are required.';
    }
    if ($recovery_email !== '' && !filter_var($recovery_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid recovery email address.';
    }

    if (empty($errors)) {
        update_user_profile($conn, $_SESSION['user_id'], $first_name, $last_name, $contact_number, $recovery_email);

        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['full_name']  = $first_name . ' ' . $last_name;

        log_audit($conn, $_SESSION['user_id'], 'update_profile', 'Manager updated their profile');
        set_flash('success', 'Profile updated successfully.');
        redirect('manager/profile.php?tab=account');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $activeTab = 'security';

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $hash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['password_hash'] ?? '';
    mysqli_stmt_close($stmt);

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $newHash, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_audit($conn, $_SESSION['user_id'], 'change_password', 'Manager changed their password');
        set_flash('success', 'Password changed successfully.');
        redirect('manager/profile.php?tab=security');
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Centered Profile Layout Container -->
<div style="max-width:920px;margin:0 auto;width:100%;">

    <!-- 1. Deli-Prop Sub-Header Bar -->
    <div class="sub-header-bar">
        <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Account Center</div>
            <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Manager Profile &amp; Governance Identity</h2>
            <div style="font-size:13px;color:var(--color-text-muted);">Manage your executive sign-off authority details, emergency recovery email, and authentication keys.</div>
        </div>
        <div class="action-controls-group">
            <span class="badge badge-primary" style="font-size:12px;padding:6px 12px;">
                <i data-lucide="award" style="width:14px;height:14px;margin-right:4px;"></i>
                Executive Authority Verified
            </span>
        </div>
    </div>

    <!-- 2. Segmented Navigation Tabs (Personal Information vs Password & Security) -->
    <div class="filter-card" style="margin-bottom:20px;padding:6px;border-radius:var(--radius-lg);background:var(--color-surface);border:1px solid var(--color-border);">
        <div class="segmented-control" style="display:flex;gap:4px;border:none;background:transparent;padding:0;">
            <a href="?tab=account" class="segment-btn <?= $activeTab === 'account' ? 'active' : '' ?>" style="flex:1;justify-content:center;padding:10px 16px;font-size:13.5px;font-weight:600;display:inline-flex;align-items:center;gap:8px;">
                <i data-lucide="user" style="width:16px;height:16px;"></i>
                Personal Information
            </a>
            <a href="?tab=security" class="segment-btn <?= $activeTab === 'security' ? 'active' : '' ?>" style="flex:1;justify-content:center;padding:10px 16px;font-size:13.5px;font-weight:600;display:inline-flex;align-items:center;gap:8px;">
                <i data-lucide="key-round" style="width:16px;height:16px;"></i>
                Password &amp; Security
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom:18px;">
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'account'): ?>
    <!-- TAB 1: Profile Information Card -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="user" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Personal Information</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Manage your executive identity, primary contact, and recovery mailbox.</div>
            </div>
        </div>
        <div class="card-body" style="padding:24px;">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="profile">
                
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">
                            First Name
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="user" class="input-icon-left"></i>
                            <input type="text" name="first_name" class="form-control" value="<?= clean($me['first_name']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">
                            Last Name
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="user" class="input-icon-left"></i>
                            <input type="text" name="last_name" class="form-control" value="<?= clean($me['last_name']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label">Institutional Login Email</label>
                        <span class="optional-badge">System Key</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="lock" class="input-icon-left"></i>
                        <input type="email" class="form-control" value="<?= clean($me['email']) ?>" disabled>
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Primary executive login email. Cannot be changed without Administrator clearance.</span>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label">Account Recovery Email</label>
                        <span class="optional-badge">Optional</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="mail" class="input-icon-left"></i>
                        <input type="email" name="recovery_email" class="form-control" value="<?= clean($me['recovery_email'] ?? '') ?>" placeholder="executive.backup@gmail.com">
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Secondary contact for administrative password resets.</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:24px;">
                    <div class="form-label-row">
                        <label class="form-label">Contact Number / Mobile</label>
                        <span class="optional-badge">Optional</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="phone" class="input-icon-left"></i>
                        <input type="text" name="contact_number" class="form-control" value="<?= clean($me['contact_number']) ?>" placeholder="+63 9...">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="padding:9px 24px;">
                    <i data-lucide="check" style="width:14px;height:14px;"></i>
                    Save Profile Changes
                </button>
            </form>
        </div>
    </div>

    <?php elseif ($activeTab === 'security'): ?>
    <!-- TAB 2: Security & Password Update Card -->
    <div class="card" id="password" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:10px;background:var(--color-warning-bg);color:var(--color-warning);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="key-round" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Password Management</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Update your executive account password securely.</div>
            </div>
        </div>
        <div class="card-body" style="padding:24px;">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="password">

                <!-- Current Password -->
                <div class="form-group">
                    <label class="form-label" for="mgrCurrentPass">
                        Current Password
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon has-right-icon">
                        <i data-lucide="key" class="input-icon-left"></i>
                        <input type="password" id="mgrCurrentPass" name="current_password" class="form-control" required placeholder="Enter current password">
                        <button type="button" class="password-toggle-btn" data-password-target="#mgrCurrentPass" aria-label="Toggle password visibility">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="form-group">
                    <label class="form-label" for="mgrNewPass">
                        New Password (minimum 8 characters)
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon has-right-icon">
                        <i data-lucide="lock" class="input-icon-left"></i>
                        <input type="password" id="mgrNewPass" name="new_password" class="form-control" 
                               minlength="8" required placeholder="Enter new strong password">
                        <button type="button" class="password-toggle-btn" data-password-target="#mgrNewPass" aria-label="Toggle password visibility">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group" style="margin-bottom:24px;">
                    <label class="form-label" for="mgrConfirmPass">
                        Confirm New Password
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon has-right-icon">
                        <i data-lucide="lock" class="input-icon-left"></i>
                        <input type="password" id="mgrConfirmPass" name="confirm_password" class="form-control" 
                               minlength="8" required placeholder="Re-enter new password">
                        <button type="button" class="password-toggle-btn" data-password-target="#mgrConfirmPass" aria-label="Toggle password visibility">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning" style="padding:9px 24px;">
                    <i data-lucide="shield-check" style="width:14px;height:14px;"></i>
                    Update Password
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.max-width container -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
