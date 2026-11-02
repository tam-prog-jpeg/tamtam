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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    verify_csrf($_POST['csrf_token'] ?? '');

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
        redirect('manager/profile.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    verify_csrf($_POST['csrf_token'] ?? '');

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
        redirect('manager/profile.php');
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

require_once __DIR__ . '/../includes/header.php';
?>

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

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom:18px;">
        <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
        <ul style="margin:0;padding-left:18px;">
            <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- 2. Two-Column Settings Grid -->
<div class="grid-2">
    <!-- Profile Info Card -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="user" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Personal Information</h3>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="profile">
                
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= clean($me['first_name']) ?>" required style="font-size:13.5px;height:38px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= clean($me['last_name']) ?>" required style="font-size:13.5px;height:38px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Institutional Login Email</label>
                    <input type="email" class="form-control" value="<?= clean($me['email']) ?>" disabled style="font-size:13.5px;height:38px;opacity:0.75;cursor:not-allowed;">
                    <div class="form-hint" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">Primary executive login email.</div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Recovery Email</label>
                    <input type="email" name="recovery_email" class="form-control" value="<?= clean($me['recovery_email'] ?? '') ?>" placeholder="e.g. executive.backup@gmail.com" style="font-size:13.5px;height:38px;">
                    <div class="form-hint" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">Secondary contact for administrative password resets.</div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= clean($me['contact_number']) ?>" placeholder="e.g. 0917-123-4567" style="font-size:13.5px;height:38px;">
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="gap:6px;height:38px;">
                    <i data-lucide="check" style="width:14px;height:14px;"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Security & Password Card -->
    <div class="card" id="password" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="lock" style="width:16px;height:16px;color:var(--color-warning);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Update Security Password</h3>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="password">

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required style="font-size:13.5px;height:38px;">
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required placeholder="Minimum 8 characters" style="font-size:13.5px;height:38px;">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required style="font-size:13.5px;height:38px;">
                </div>

                <button type="submit" class="btn btn-warning btn-sm" style="gap:6px;height:38px;">
                    <i data-lucide="key" style="width:14px;height:14px;"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
