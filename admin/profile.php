<?php
/**
 * admin/profile.php
 * -----------------------------------------------------------------
 * Administrator's own account settings: name/contact/recovery email,
 * and change password (current password required). The recovery
 * email is a separate address used only for account recovery — it
 * is NOT the login email.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'My Profile';
$currentPage = 'profile';
$errors = [];

// ---- Update profile info + recovery email ----
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
    if ($recovery_email !== '' && $recovery_email === ($_SESSION['email'] ?? '')) {
        $errors[] = 'Recovery email should be different from your login email.';
    }

    if (empty($errors)) {
        update_user_profile($conn, $_SESSION['user_id'], $first_name, $last_name, $contact_number, $recovery_email);

        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['full_name']  = $first_name . ' ' . $last_name;

        log_audit($conn, $_SESSION['user_id'], 'update_profile', 'Administrator updated their profile');
        set_flash('success', 'Profile updated successfully.');
        redirect('admin/profile.php');
    }
}

// ---- Change password ----
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

        log_audit($conn, $_SESSION['user_id'], 'change_password', 'Administrator changed their password');
        set_flash('success', 'Password changed successfully.');
        redirect('admin/profile.php');
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$_SESSION['email'] = $me['email']; // keep session aware of login email for the duplicate-check above

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Account Center</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Administrator Profile &amp; Security</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Manage your system administrator profile information, recovery email address, and authentication keys.</div>
    </div>
    <div class="action-controls-group">
        <span class="badge badge-primary" style="font-size:12px;padding:6px 12px;">
            <i data-lucide="shield-check" style="width:14px;height:14px;margin-right:4px;"></i>
            Master Administrator
        </span>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom:18px;">
        <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
        <ul style="margin:0;padding-left:18px;"><?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="user" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Profile Information</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="profile">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= clean($me['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= clean($me['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Login Email (not editable)</label>
                    <input type="email" class="form-control" value="<?= clean($me['email']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Recovery Email</label>
                    <input type="email" name="recovery_email" class="form-control" value="<?= clean($me['recovery_email'] ?? '') ?>" placeholder="An alternate email for account recovery">
                    <div class="form-hint">Used only if you lose access to your login email. Must be different from it.</div>
                </div>
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= clean($me['contact_number']) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="check" style="width:14px;height:14px;"></i>
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <div class="card" id="password" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="lock" style="width:16px;height:16px;color:var(--color-warning);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-warning btn-sm">
                    <i data-lucide="key" style="width:14px;height:14px;"></i>
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
