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

// ---- Active tab selection ----
$activeTab = sanitize_input($_GET['tab'] ?? 'account');
if (!in_array($activeTab, ['account', 'security'], true)) {
    $activeTab = 'account';
}

// ---- Handle profile update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $activeTab = 'account';

    $contact_number = sanitize_input($_POST['contact_number'] ?? '');

    // Sensitive institutional data (first_name, last_name, applicant_type, college_id)
    // are strictly locked and read-only for applicants to comply with Republic Act No. 8293
    // and university intellectual property legal attributions. Only contact details can be modified.
    $u = mysqli_prepare($conn, "UPDATE users SET contact_number = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($u, 'si', $contact_number, $_SESSION['user_id']);
    mysqli_stmt_execute($u);
    mysqli_stmt_close($u);

    log_audit($conn, $_SESSION['user_id'], 'update_contact', 'Applicant updated their contact telephone/mobile number');
    set_flash('success', 'Contact details updated successfully.');
    redirect('applicant/profile.php?tab=account');
}

// ---- Handle password change ----
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
        $u = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($u, 'si', $newHash, $_SESSION['user_id']);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);

        log_audit($conn, $_SESSION['user_id'], 'change_password', 'Applicant changed their account password');
        set_flash('success', 'Security password updated successfully.');
        redirect('applicant/profile.php?tab=security');
    }
}

// ---- Load current profile ----
$stmt = mysqli_prepare($conn, "
    SELECT u.*, ap.applicant_id, ap.applicant_type, ap.college_id, c.college_name
    FROM users u 
    JOIN applicants ap ON ap.user_id = u.user_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    WHERE u.user_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$collegeOffices = [
    'College of Engineering and Technology (CET)',
    'College of Teacher Education (CTE) / College of Technical Vocational Teacher Education',
    'School of Business Administration',
    'College of Arts, Humanities, and Social Sciences (CAHSS)',
    'College of Information and Computing Sciences (CICS)',
    'College of Physical Education and Sports (CPES)',
    'Office of the University President',
    'Office of the University Registrar',
    'Office of Student Affairs and Services (OSAS)',
    'Admission Office',
    'Public Information Office (PIO)',
    'Learning Commons Center / ZPPSU Library',
    'Alumni Affairs Office',
    'Professional Continuing Education (PCE)',
    'Innovation Hub / Research and Extension Offices',
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Centered Profile Layout Container -->
<div style="max-width:920px;margin:0 auto;width:100%;">

    <!-- 1. Deli-Prop Sub-Header Bar -->
    <div class="sub-header-bar" style="margin-bottom:20px;">
        <div>
            <div class="form-label-eyebrow" style="color:var(--color-primary-600);margin-bottom:2px;">Applicant Portal</div>
            <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Account Settings &amp; Security</h2>
            <div style="font-size:13px;color:var(--color-text-muted);">Manage your institutional account details, contact reachability, and portal security credentials.</div>
        </div>
        <div class="action-controls-group">
            <span class="badge badge-info" style="font-size:12px;padding:6px 12px;">
                <i data-lucide="badge-check" style="width:14px;height:14px;margin-right:4px;"></i>
                <?= clean($me['applicant_type']) ?> Account
            </span>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom:20px;">
            <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- 2. Segmented Navigation Tabs (Separates Account from Password to Avoid Confusion) -->
    <div class="category-pill-tabs" style="margin-bottom:24px;width:fit-content;">
        <button 
            type="button" 
            class="category-pill <?= $activeTab === 'account' ? 'active' : '' ?>" 
            id="tabBtnAccount" 
            onclick="switchProfileTab('account')"
        >
            <i data-lucide="user" style="width:14px;height:14px;margin-right:6px;vertical-align:-2px;"></i>
            Account Information
        </button>
        <button 
            type="button" 
            class="category-pill <?= $activeTab === 'security' ? 'active' : '' ?>" 
            id="tabBtnSecurity" 
            onclick="switchProfileTab('security')"
        >
            <i data-lucide="shield-check" style="width:14px;height:14px;margin-right:6px;vertical-align:-2px;"></i>
            Security &amp; Password
        </button>
    </div>

    <!-- =====================================================================
         TAB 1: ACCOUNT INFORMATION (WITH READ-ONLY PROTECTED SENSITIVE DATA)
         ===================================================================== -->
    <div id="section-account" style="<?= $activeTab === 'account' ? '' : 'display:none;' ?>">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:8px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="user-check" style="width:17px;height:17px;"></i>
                    </div>
                    <div>
                        <h3 style="font-size:15px;margin:0;font-weight:600;">Official Institutional Profile</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Verified identity and contact information for intellectual property filings</div>
                    </div>
                </div>
                <span class="badge badge-secondary" style="font-size:11.5px;">
                    <i data-lucide="shield" style="width:12px;height:12px;margin-right:3px;"></i>
                    Protected Record
                </span>
            </div>

            <div class="card-body" style="padding:22px 24px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form" value="profile">

                    <!-- First Name & Last Name (Protected Legal Identity) -->
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="first_name" class="form-label">First Name</label>
                                <span class="optional-badge">
                                    <i data-lucide="lock" style="width:10px;height:10px;margin-right:3px;vertical-align:-1px;"></i>
                                    Legal Identity
                                </span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="user" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    class="form-control" 
                                    value="<?= clean($me['first_name']) ?>" 
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="last_name" class="form-label">Last Name</label>
                                <span class="optional-badge">
                                    <i data-lucide="lock" style="width:10px;height:10px;margin-right:3px;vertical-align:-1px;"></i>
                                    Legal Identity
                                </span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="user" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    class="form-control" 
                                    value="<?= clean($me['last_name']) ?>" 
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Username & Institutional Email (Protected Credentials) -->
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="username" class="form-label">Portal Username</label>
                                <span class="optional-badge">System ID</span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="at-sign" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    id="username" 
                                    class="form-control" 
                                    value="<?= clean($me['username']) ?>" 
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="email" class="form-label">Institutional Email</label>
                                <span class="optional-badge">Protected Record</span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="mail" class="input-icon-left"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    class="form-control" 
                                    value="<?= clean($me['email']) ?>" 
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Institutional Classification & College / Office (Protected Institutional Affiliation) -->
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="applicant_type" class="form-label">Applicant Classification</label>
                                <span class="optional-badge">HR Verified</span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="badge-check" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    id="applicant_type" 
                                    class="form-control" 
                                    value="<?= clean($me['applicant_type']) ?>" 
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-label-row">
                                <label for="college_office" class="form-label">College / Office Affiliation</label>
                                <span class="optional-badge">Unit Verified</span>
                            </div>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="building-2" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    id="college_office" 
                                    class="form-control" 
                                    value="<?= clean($me['college_name'] ?: 'Not Specified') ?>" 
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Contact Number (Editable by Applicant) -->
                    <div class="form-group">
                        <div class="form-label-row">
                            <label for="contact_number" class="form-label">Contact Telephone / Mobile Number</label>
                            <span class="badge badge-success" style="font-size:11px;padding:2px 8px;">Editable</span>
                        </div>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="phone" class="input-icon-left"></i>
                            <input 
                                type="text" 
                                id="contact_number" 
                                name="contact_number" 
                                class="form-control" 
                                value="<?= clean($me['contact_number']) ?>" 
                                placeholder="e.g. 0917-123-4567"
                            >
                        </div>
                        <div class="form-hint">
                            <i data-lucide="info"></i>
                            <span>Used by examiners and office staff for urgent technical notifications or filing queries.</span>
                        </div>
                    </div>

                    <!-- Action Controls -->
                    <div style="padding-top:16px;border-top:1px solid var(--color-border);margin-top:22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                        <button type="submit" class="btn btn-primary" style="height:40px;padding:0 22px;">
                            <i data-lucide="check" style="width:15px;height:15px;"></i>
                            Save Contact Details
                        </button>
                        <span style="font-size:12px;color:var(--color-text-muted);">
                            Last profile verification: <?= format_date($me['updated_at'] ?? $me['created_at'], 'M d, Y') ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Legal Integrity & Compliance Callout -->
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-left:4px solid var(--color-primary-600);border-radius:var(--radius-lg);padding:18px 22px;margin-top:20px;box-shadow:var(--shadow-xs);">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="color:var(--color-primary-600);flex-shrink:0;margin-top:2px;">
                    <i data-lucide="shield-alert" style="width:20px;height:20px;"></i>
                </div>
                <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.65;">
                    <strong style="color:var(--color-text);display:block;font-size:13.5px;margin-bottom:4px;">
                        Legal Compliance &amp; Institutional Record Integrity Notice
                    </strong>
                    In strict compliance with <strong>Republic Act No. 8293 (Intellectual Property Code of the Philippines)</strong> and university IP policies, personal identity records (legal name, username, official classification, and college/office affiliation) are permanently locked on active applicant accounts. This safeguard protects against fraudulent authorship claims, guarantees the legal validity of issued certificates, and preserves the university's official patent and copyright registers.
                    <span style="display:block;margin-top:6px;color:var(--color-text);">
                        To request a legal name correction or update an institutional appointment, please submit a formal endorsement or affidavit to the <strong>ZPPSU Intellectual Property Office</strong>.
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         TAB 2: SECURITY & PASSWORD (STANDALONE CARD TO PREVENT CONFUSION)
         ===================================================================== -->
    <div id="section-security" style="<?= $activeTab === 'security' ? '' : 'display:none;' ?>">
        <div class="card" id="password">
            <div class="card-header" style="display:flex;align-items:center;gap:10px;padding:16px 20px;">
                <div style="width:34px;height:34px;border-radius:8px;background:var(--color-warning-bg);color:var(--color-warning);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="lock" style="width:17px;height:17px;"></i>
                </div>
                <div>
                    <h3 style="font-size:15px;margin:0;font-weight:600;">Change Security Password</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Ensure your account credentials remain private and secure</div>
                </div>
            </div>
            <div class="card-body" style="padding:22px 24px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="form" value="password">

                    <div class="form-group">
                        <label for="current_password" class="form-label">
                            Current Password <span class="required-mark">*</span>
                        </label>
                        <div class="input-icon-wrap has-left-icon has-right-icon">
                            <i data-lucide="lock" class="input-icon-left"></i>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="form-control" 
                                required 
                                placeholder="Enter your current password"
                            >
                            <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePass('current_password', this)" title="Show/hide password">
                                <i data-lucide="eye" style="width:15px;height:15px;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="form-label">
                                New Password <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon has-right-icon">
                                <i data-lucide="key-round" class="input-icon-left"></i>
                                <input 
                                    type="password" 
                                    id="new_password" 
                                    name="new_password" 
                                    class="form-control" 
                                    minlength="8" 
                                    required 
                                    placeholder="Minimum 8 characters"
                                >
                                <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePass('new_password', this)" title="Show/hide password">
                                    <i data-lucide="eye" style="width:15px;height:15px;"></i>
                                </button>
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Minimum 8 characters.</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                Confirm New Password <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon has-right-icon">
                                <i data-lucide="shield-check" class="input-icon-left"></i>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    minlength="8" 
                                    required 
                                    placeholder="Re-type your new password"
                                >
                                <button type="button" class="password-toggle-btn" tabindex="-1" onclick="togglePass('confirm_password', this)" title="Show/hide password">
                                    <i data-lucide="eye" style="width:15px;height:15px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="padding-top:16px;border-top:1px solid var(--color-border);margin-top:22px;">
                        <button type="submit" class="btn btn-warning" style="height:40px;padding:0 22px;">
                            <i data-lucide="key" style="width:15px;height:15px;"></i>
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Best Practices Callout -->
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:18px 22px;margin-top:20px;box-shadow:var(--shadow-xs);">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;color:var(--color-text);">
                <i data-lucide="shield" style="width:16px;height:16px;color:var(--color-warning);"></i>
                <strong style="font-size:13px;">Security Best Practices</strong>
            </div>
            <ul style="margin:0;padding-left:20px;font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">
                <li>Do not share your institutional credentials with co-authors or student researchers.</li>
                <li>Your account provides access to confidential invention disclosures and drafts before public release.</li>
                <li>Ensure you sign out after using shared university laboratory computers or public workstations.</li>
            </ul>
        </div>
    </div>
</div>

<script>
    function switchProfileTab(tab) {
        var secAccount = document.getElementById('section-account');
        var secSecurity = document.getElementById('section-security');
        var btnAccount = document.getElementById('tabBtnAccount');
        var btnSecurity = document.getElementById('tabBtnSecurity');

        if (tab === 'security') {
            if (secAccount) secAccount.style.display = 'none';
            if (secSecurity) secSecurity.style.display = 'block';
            if (btnAccount) btnAccount.classList.remove('active');
            if (btnSecurity) btnSecurity.classList.add('active');
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '?tab=security');
            }
        } else {
            if (secSecurity) secSecurity.style.display = 'none';
            if (secAccount) secAccount.style.display = 'block';
            if (btnSecurity) btnSecurity.classList.remove('active');
            if (btnAccount) btnAccount.classList.add('active');
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '?tab=account');
            }
        }
        if (window.lucide) lucide.createIcons();
    }

    function togglePass(inputId, btn) {
        var input = document.getElementById(inputId);
        if (!input) return;
        var icon = btn.querySelector('[data-lucide]') || btn.querySelector('svg');
        var isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        if (icon) {
            icon.setAttribute('data-lucide', isText ? 'eye' : 'eye-off');
            if (window.lucide) lucide.createIcons();
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
