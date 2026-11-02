<?php
/**
 * applicant/profile.php
 * -----------------------------------------------------------------
 * Lets the applicant view/edit their own profile (name, contact,
 * address, applicant type, college/department) and change their
 * password (requires current password verification).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$pageTitle   = 'My Profile';
$currentPage = 'profile';

$errors = [];
$success = null;

// ---- Handle profile update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $first_name     = sanitize_input($_POST['first_name'] ?? '');
    $last_name      = sanitize_input($_POST['last_name'] ?? '');
    $contact_number = sanitize_input($_POST['contact_number'] ?? '');
    $applicant_type = sanitize_input($_POST['applicant_type'] ?? 'External');
    $college_id     = !empty($_POST['college_id']) ? (int) $_POST['college_id'] : null;
    $department_id  = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $address        = sanitize_input($_POST['address'] ?? '');

    if ($first_name === '' || $last_name === '') {
        $errors[] = 'First and last name are required.';
    }

    if (empty($errors)) {
        $u = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, contact_number = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($u, 'sssi', $first_name, $last_name, $contact_number, $_SESSION['user_id']);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        $a = mysqli_prepare($conn, "UPDATE applicants SET applicant_type = ?, college_id = ?, department_id = ?, address = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($a, 'siisi', $applicant_type, $college_id, $department_id, $address, $_SESSION['user_id']);
        mysqli_stmt_execute($a);
        mysqli_stmt_close($a);

        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['full_name']  = $first_name . ' ' . $last_name;

        log_audit($conn, $_SESSION['user_id'], 'update_profile', 'Applicant updated their profile');
        set_flash('success', 'Profile updated successfully.');
        redirect('applicant/profile.php');
    }
}

// ---- Handle password change ----
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
        $u = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($u, 'si', $newHash, $_SESSION['user_id']);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        log_audit($conn, $_SESSION['user_id'], 'change_password', 'Applicant changed their password');
        set_flash('success', 'Password changed successfully.');
        redirect('applicant/profile.php');
    }
}

// ---- Load current profile ----
$stmt = mysqli_prepare($conn, "
    SELECT u.*, ap.applicant_id, ap.applicant_type, ap.college_id, ap.department_id, ap.address
    FROM users u JOIN applicants ap ON ap.user_id = u.user_id
    WHERE u.user_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$colleges = mysqli_query($conn, "SELECT college_id, college_name FROM colleges ORDER BY college_name");
$departments = mysqli_query($conn, "SELECT department_id, college_id, department_name FROM departments ORDER BY department_name");
$deptList = [];
while ($d = mysqli_fetch_assoc($departments)) { $deptList[] = $d; }

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Applicant Profile</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Account &amp; Academic Affiliation</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Manage your personal information, university department affiliation, and security password.</div>
    </div>
    <div class="action-controls-group">
        <span class="badge badge-info" style="font-size:12px;padding:6px 12px;">
            <i data-lucide="user-check" style="width:14px;height:14px;margin-right:4px;"></i>
            <?= clean($me['applicant_type']) ?> Account
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

<!-- 2. Two-Column Form Layout -->
<div class="grid-2">
    <!-- Profile Info Card -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="user" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Personal Information &amp; Affiliation</h3>
        </div>
        <div class="card-body" style="padding:20px;">
            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="form" value="profile">
                
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= clean($me['first_name']) ?>" required style="font-size:13.5px;height:38px;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= clean($me['last_name']) ?>" required style="font-size:13.5px;height:38px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Institutional Login Email</label>
                    <input type="email" class="form-control" value="<?= clean($me['email']) ?>" disabled style="font-size:13.5px;height:38px;opacity:0.75;cursor:not-allowed;">
                    <div class="form-hint" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">Primary login address (contact office to request email changes).</div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?= clean($me['contact_number']) ?>" placeholder="e.g. 0917-123-4567" style="font-size:13.5px;height:38px;">
                </div>

                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Applicant Classification</label>
                        <select name="applicant_type" class="form-control" style="font-size:13.5px;height:38px;">
                            <?php foreach (['Student','Faculty','Staff','External'] as $t): ?>
                                <option value="<?= $t ?>" <?= $me['applicant_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">College / School</label>
                        <select name="college_id" id="college_id" class="form-control" style="font-size:13.5px;height:38px;">
                            <option value="">— Not applicable —</option>
                            <?php mysqli_data_seek($colleges, 0); while ($c = mysqli_fetch_assoc($colleges)): ?>
                                <option value="<?= $c['college_id'] ?>" <?= (int)$me['college_id'] === (int)$c['college_id'] ? 'selected' : '' ?>><?= clean($c['college_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Academic Department</label>
                    <select name="department_id" id="department_id" class="form-control" style="font-size:13.5px;height:38px;"></select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Official Address / Campus Office</label>
                    <input type="text" name="address" class="form-control" value="<?= clean($me['address']) ?>" placeholder="e.g. Room 204, College of Engineering, ZPPSU Main Campus" style="font-size:13.5px;height:38px;">
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="gap:6px;height:38px;">
                    <i data-lucide="check" style="width:14px;height:14px;"></i> Save Profile Details
                </button>
            </form>
        </div>
    </div>

    <!-- Password Card -->
    <div class="card" id="password" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
            <i data-lucide="lock" style="width:16px;height:16px;color:var(--color-warning);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Change Security Password</h3>
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

<script>
    const ALL_DEPARTMENTS = <?= json_encode($deptList) ?>;
    const currentDept = <?= json_encode((int) $me['department_id']) ?>;
    const collegeSelect = document.getElementById('college_id');
    const deptSelect = document.getElementById('department_id');
    function renderDepartments() {
        const cid = parseInt(collegeSelect.value || 0, 10);
        const matches = ALL_DEPARTMENTS.filter(d => parseInt(d.college_id, 10) === cid);
        if (!cid || matches.length === 0) {
            deptSelect.innerHTML = '<option value="">— Not applicable —</option>';
            return;
        }
        deptSelect.innerHTML = '<option value="">— Select department —</option>' +
            matches.map(d => `<option value="${d.department_id}" ${parseInt(d.department_id,10)===currentDept ? 'selected':''}>${d.department_name}</option>`).join('');
    }
    collegeSelect.addEventListener('change', renderDepartments);
    renderDepartments();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
