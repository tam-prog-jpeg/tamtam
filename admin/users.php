<?php
/**
 * admin/users.php
 * -----------------------------------------------------------------
 * Full user management for the Administrator:
 *  - list every account with search + filter by role/status
 *  - KPI summary metric cards with sparklines
 *  - role category tabs
 *  - create new Staff / Evaluator / Manager / Administrator accounts
 *  - edit name/email/role
 *  - activate / deactivate / suspend
 *  - reset a user's password (generates a new temp password or sets custom)
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'User Management';
$currentPage = 'users';
$errors = [];

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name  = sanitize_input($_POST['last_name'] ?? '');
        $username   = sanitize_input($_POST['username'] ?? '');
        $email      = sanitize_input($_POST['email'] ?? '');
        $roleId     = (int) ($_POST['role_id'] ?? 0);
        $tempPass   = bin2hex(random_bytes(5));

        if ($first_name === '' || $last_name === '') $errors[] = 'First and last name are required.';
        if (strlen($username) < 4) $errors[] = 'Username must be at least 4 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
        if (!in_array($roleId, [1,2,3,4], true)) $errors[] = 'Please select a valid role (Applicant accounts self-register).';

        if (empty($errors)) {
            $dupe = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ?");
            mysqli_stmt_bind_param($dupe, 'ss', $username, $email);
            mysqli_stmt_execute($dupe);
            if (mysqli_num_rows(mysqli_stmt_get_result($dupe)) > 0) $errors[] = 'That username or email is already in use.';
            mysqli_stmt_close($dupe);
        }

        if (empty($errors)) {
            $hash = password_hash($tempPass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            mysqli_stmt_bind_param($stmt, 'isssss', $roleId, $username, $email, $hash, $first_name, $last_name);
            mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            log_audit($conn, $_SESSION['user_id'], 'create_user', "Created user #$newId ($username)");
            set_flash('success', "Account created for $username. Temporary password: $tempPass (share this securely — it will not be shown again).");
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/users.php');
    }

    if ($action === 'edit') {
        $userId     = (int) ($_POST['user_id'] ?? 0);
        $first_name = sanitize_input($_POST['first_name'] ?? '');
        $last_name  = sanitize_input($_POST['last_name'] ?? '');
        $email      = sanitize_input($_POST['email'] ?? '');
        $roleId     = (int) ($_POST['role_id'] ?? 0);

        if ($userId === (int) $_SESSION['user_id'] && $roleId !== 1) {
            set_flash('error', 'You cannot change your own role away from Administrator.');
            redirect('admin/users.php');
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET first_name = ?, last_name = ?, email = ?, role_id = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssii', $first_name, $last_name, $email, $roleId, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_audit($conn, $_SESSION['user_id'], 'edit_user', "Edited user #$userId");
        set_flash('success', 'User updated successfully.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = sanitize_input($_POST['new_status'] ?? 'active');

        if ($userId === (int) $_SESSION['user_id']) {
            set_flash('error', 'You cannot change your own account status.');
            redirect('admin/users.php');
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_audit($conn, $_SESSION['user_id'], 'toggle_status', "Set user #$userId status to $newStatus");
        set_flash('success', 'Account status updated.');
        redirect('admin/users.php');
    }

    if ($action === 'reset_password') {
        $userId      = (int) ($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        $confirm     = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            set_flash('error', 'New password must be at least 8 characters.');
        } elseif ($newPassword !== $confirm) {
            set_flash('error', 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            log_audit($conn, $_SESSION['user_id'], 'reset_password', "Reset password for user #$userId");
            set_flash('success', 'Password updated successfully. Share it securely with the user.');
        }
        redirect('admin/users.php');
    }
}

// ---- Overall Stats for KPI cards ----
$kpiRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(role_id IN (1, 2) AND status = 'active'), 0) AS total_admin_staff,
        COALESCE(SUM(role_id = 3 AND status = 'active'), 0) AS total_evaluators,
        COALESCE(SUM(status IN ('inactive', 'suspended')), 0) AS total_inactive
    FROM users
");
$kpiStats = mysqli_fetch_assoc($kpiRes) ?: [
    'total_all' => 0,
    'total_admin_staff' => 0,
    'total_evaluators' => 0,
    'total_inactive' => 0
];

// ---- List with search/filter ----
$search = sanitize_input($_GET['q'] ?? '');
$roleF  = (int) ($_GET['role_id'] ?? 0);
$statusF = sanitize_input($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($roleF > 0) { $where[] = "u.role_id = ?"; $types .= 'i'; $params[] = $roleF; }
if ($statusF !== '') { $where[] = "u.status = ?"; $types .= 's'; $params[] = $statusF; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM users u $whereSql");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);
$p = paginate($totalRows, $perPage, $page);

$listSql = "
    SELECT u.*, r.role_name
    FROM users u JOIN roles r ON r.role_id = u.role_id
    $whereSql
    ORDER BY u.created_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
";
$listStmt = mysqli_prepare($conn, $listSql);
$usersRes = bind_execute($listStmt, $types, $params);

// Buffer all users to allow rendering clean modals OUTSIDE the table!
$userList = [];
while ($u = mysqli_fetch_assoc($usersRes)) {
    $userList[] = $u;
}

$rolesRes = mysqli_query($conn, "SELECT * FROM roles ORDER BY role_id");
$allRoles = [];
while ($r = mysqli_fetch_assoc($rolesRes)) {
    $allRoles[] = $r;
}

function qsU($overrides = [])
{
    $current = $_GET;
    foreach ($overrides as $k => $v) { $current[$k] = $v; }
    return '?' . http_build_query($current);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <h2>User & Role Management</h2>
        <p>Manage administrative personnel, evaluators, staff members, and applicant access privileges.</p>
    </div>
    <div class="sub-header-actions">
        <button type="button" class="btn btn-primary btn-sm" data-modal-target="#addUserModal">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            + New Account
        </button>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="margin-bottom:20px;">
    <!-- Total Accounts -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">System Accounts</span>
            <span class="kpi-info-tip" title="All-time registered users across all roles">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <?= render_kpi_sparkline((int)$kpiStats['total_all'], 'blue', null, 'sparkline-svg') ?>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Total registered accounts</span>
        </div>
    </div>

    <!-- Active Admin & Staff -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Active Admin & Staff</span>
            <span class="kpi-info-tip" title="Active administrative and operations personnel">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-primary-600);"><?= number_format((int)$kpiStats['total_admin_staff']) ?></div>
            <div class="kpi-spark">
                <?= render_kpi_sparkline((int)$kpiStats['total_admin_staff'], 'cyan', null, 'sparkline-svg') ?>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-up">Operations team</span>
        </div>
    </div>

    <!-- Active Evaluators -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Technical Evaluators</span>
            <span class="kpi-info-tip" title="Active patent and copyright evaluators">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-purple);"><?= number_format((int)$kpiStats['total_evaluators']) ?></div>
            <div class="kpi-spark">
                <?= render_kpi_sparkline((int)$kpiStats['total_evaluators'], 'purple', null, 'sparkline-svg') ?>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Review committee</span>
        </div>
    </div>

    <!-- Inactive or Suspended -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Inactive / Suspended</span>
            <span class="kpi-info-tip" title="Deactivated or suspended user logins">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-danger);"><?= number_format((int)$kpiStats['total_inactive']) ?></div>
            <div class="kpi-spark">
                <?= render_kpi_sparkline((int)$kpiStats['total_inactive'], 'red', null, 'sparkline-svg') ?>
            </div>
        </div>
        <div class="kpi-bottom">
            <?php if ((int)$kpiStats['total_inactive'] === 0): ?>
                <span class="kpi-trend trend-up" style="color:var(--color-success);"><i data-lucide="shield-check" style="width:13px;height:13px;"></i> 0 locked • All active</span>
            <?php else: ?>
                <span class="kpi-trend trend-down" style="color:var(--color-danger);"><i data-lucide="alert-triangle" style="width:13px;height:13px;"></i> <?= (int)$kpiStats['total_inactive'] ?> accounts restricted</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 3. Role Category Tabs -->
<div class="category-tabs" style="margin-bottom:16px;">
    <a href="<?= qsU(['role_id' => 0, 'page' => 1]) ?>" class="tab-pill <?= $roleF === 0 ? 'active' : '' ?>">
        All Roles
        <span class="tab-count"><?= (int)$kpiStats['total_all'] ?></span>
    </a>
    <?php foreach ($allRoles as $r): ?>
        <a href="<?= qsU(['role_id' => $r['role_id'], 'page' => 1]) ?>" class="tab-pill <?= $roleF === (int)$r['role_id'] ? 'active' : '' ?>">
            <?= clean($r['role_name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- 4. Search & Filter Bar Card -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Search Personnel</label>
                <div class="input-icon-wrap has-left-icon">
                    <i data-lucide="search" class="input-icon-left"></i>
                    <input type="text" name="q" class="form-control" value="<?= clean($search) ?>" placeholder="Name, username, email…">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Assigned Role</label>
                <select name="role_id" class="form-control form-select">
                    <option value="0">All Roles</option>
                    <?php foreach ($allRoles as $r): ?>
                        <option value="<?= $r['role_id'] ?>" <?= $roleF === (int)$r['role_id'] ? 'selected' : '' ?>><?= clean($r['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Account Status</label>
                <select name="status" class="form-control form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['active','inactive','suspended'] as $s): ?>
                        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:42px;padding:0 16px;">
                    <i data-lucide="filter" style="width:14px;height:14px;"></i>
                    Filter
                </button>
                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline btn-sm" style="height:42px;display:inline-flex;align-items:center;padding:0 14px;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- 5. Users Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>User Accounts</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($userList)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h4>No user accounts found</h4>
                <p>No user accounts match your specified filter options or search term.</p>
                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Reset Filters</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>User & Identity</th>
                    <th>Email Address</th>
                    <th>Assigned Role</th>
                    <th>Account Status</th>
                    <th>Joined</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userList as $u): 
                    $initials = strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                    $isSelf = (int)$u['user_id'] === (int)$_SESSION['user_id'];
                    $roleBadgeClass = 'badge-secondary';
                    if ($u['role_name'] === 'Administrator') $roleBadgeClass = 'badge-primary';
                    elseif ($u['role_name'] === 'Staff') $roleBadgeClass = 'badge-info';
                    elseif ($u['role_name'] === 'Evaluator') $roleBadgeClass = 'badge-warning';
                    elseif ($u['role_name'] === 'Manager') $roleBadgeClass = 'badge-purple';
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--color-primary-50);color:var(--color-primary-600);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;border:1px solid var(--color-border);flex-shrink:0;">
                                <?= $initials ?>
                            </div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text);">
                                    <?= clean($u['first_name'] . ' ' . $u['last_name']) ?>
                                    <?php if ($isSelf): ?>
                                        <span class="badge badge-info" style="font-size:10px;margin-left:4px;">You</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-family:var(--font-mono);font-size:11px;color:var(--color-text-muted);">@<?= clean($u['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="font-size:13px;color:var(--color-text);"><?= clean($u['email']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $roleBadgeClass ?>" style="font-size:11px;">
                            <?= clean($u['role_name']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : ($u['status'] === 'suspended' ? 'badge-danger' : 'badge-secondary') ?>">
                            <?= ucfirst($u['status']) ?>
                        </span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($u['created_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">
                            <button type="button" class="btn btn-outline btn-sm" data-modal-target="#editModal<?= $u['user_id'] ?>" title="Edit User Details">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                Edit
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" data-modal-target="#resetPwModal<?= $u['user_id'] ?>" title="Reset User Password">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
                                Reset PW
                            </button>
                            <?php if (!$isSelf): ?>
                                <form method="POST" style="margin:0;display:inline-flex;" onsubmit="return confirm('Change account status for <?= clean(addslashes($u['username'])) ?>?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Deactivate User Account">
                                            <svg class="lucide-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Activate User Account">
                                            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 6. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qsU(['page' => $p['currentPage'] - 1]) ?>" class="page-link" title="Previous Page">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qsU(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qsU(['page' => $p['currentPage'] + 1]) ?>" class="page-link" title="Next Page">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ================= MODALS (CLEANLY LOCATED OUTSIDE THE TABLE) ================= -->

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box" style="border-radius:var(--radius-lg);max-width:520px;">
        <form method="POST">
            <div class="modal-head" style="padding:18px 22px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="user-plus" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">Create Institutional Account</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Provision internal access for IPO staff, evaluators, or managers.</div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                
                <div class="form-row" style="margin-bottom:16px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">
                            First Name
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="user" class="input-icon-left"></i>
                            <input type="text" name="first_name" class="form-control" placeholder="e.g. Juan" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">
                            Last Name
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="user" class="input-icon-left"></i>
                            <input type="text" name="last_name" class="form-control" placeholder="e.g. Dela Cruz" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Username
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text" style="font-family:var(--font-mono);font-weight:600;">@</span>
                        <input type="text" name="username" class="form-control" placeholder="jdelacruz" minlength="4" required style="font-family:var(--font-mono);">
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Must contain at least 4 alphanumeric characters without spaces.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Institutional Email Address
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="mail" class="input-icon-left"></i>
                        <input type="email" name="email" class="form-control" placeholder="juan.delacruz@zppsu.edu.ph" required>
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>University email address used for login and notifications.</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">
                        Assigned Institutional Role
                        <span class="required-mark" aria-hidden="true">*</span>
                    </label>
                    <select name="role_id" class="form-control form-select" required>
                        <option value="">— Select Institutional Role —</option>
                        <option value="1">Administrator (System Management &amp; Settings)</option>
                        <option value="2">Staff (Document Intake &amp; Initial Screening)</option>
                        <option value="3">Evaluator (Technical &amp; Novelty Assessment)</option>
                        <option value="4">Manager (Executive Approvals &amp; Certification)</option>
                    </select>
                    <div class="form-hint" style="margin-top:6px;">
                        <i data-lucide="shield"></i>
                        <span>Applicant accounts register independently via the public admissions portal.</span>
                    </div>
                </div>
            </div>
            <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 18px;">
                    <i data-lucide="plus" style="width:14px;height:14px;"></i>
                    Create User Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit & Password Modals for each rendered user -->
<?php foreach ($userList as $u): ?>
    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal<?= $u['user_id'] ?>">
        <div class="modal-box" style="border-radius:var(--radius-lg);max-width:520px;">
            <form method="POST">
                <div class="modal-head" style="padding:18px 22px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i data-lucide="edit-3" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:16px;font-weight:700;">Edit User: @<?= clean($u['username']) ?></h3>
                            <div style="font-size:12px;color:var(--color-text-muted);font-family:var(--font-mono);">ID #<?= $u['user_id'] ?> &bull; <?= clean($u['email']) ?></div>
                        </div>
                    </div>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
                </div>
                <div class="modal-body" style="padding:22px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">

                    <div class="form-row" style="margin-bottom:16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">First Name</label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="user" class="input-icon-left"></i>
                                <input type="text" name="first_name" class="form-control" value="<?= clean($u['first_name']) ?>" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Last Name</label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="user" class="input-icon-left"></i>
                                <input type="text" name="last_name" class="form-control" value="<?= clean($u['last_name']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-icon-wrap has-left-icon">
                            <i data-lucide="mail" class="input-icon-left"></i>
                            <input type="email" name="email" class="form-control" value="<?= clean($u['email']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Institutional Role</label>
                        <select name="role_id" class="form-control form-select" required <?= ((int)$u['user_id'] === (int)$_SESSION['user_id']) ? 'disabled title="Cannot change your own role"' : '' ?>>
                            <?php foreach ($allRoles as $r): ?>
                                <option value="<?= $r['role_id'] ?>" <?= (int)$u['role_id'] === (int)$r['role_id'] ? 'selected' : '' ?>><?= clean($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ((int)$u['user_id'] === (int)$_SESSION['user_id']): ?>
                            <input type="hidden" name="role_id" value="<?= $u['role_id'] ?>">
                            <div class="form-hint" style="color:var(--color-warning);">
                                <i data-lucide="alert-circle"></i>
                                <span>You cannot reassign your own role away from Administrator.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 18px;">
                        <i data-lucide="check" style="width:14px;height:14px;"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal-overlay" id="resetPwModal<?= $u['user_id'] ?>">
        <div class="modal-box" style="border-radius:var(--radius-lg);max-width:480px;">
            <form method="POST">
                <div class="modal-head" style="padding:18px 22px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:var(--color-warning-bg);color:var(--color-warning);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i data-lucide="key-round" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:16px;font-weight:700;">Reset Password</h3>
                            <div style="font-size:12px;color:var(--color-text-muted);">Account: <strong><?= clean($u['first_name'] . ' ' . $u['last_name']) ?></strong> (@<?= clean($u['username']) ?>)</div>
                        </div>
                    </div>
                    <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
                </div>
                <div class="modal-body" style="padding:22px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">

                    <!-- New Password Input with reveal eye and live strength meter -->
                    <div class="form-group">
                        <label class="form-label" for="newPass<?= $u['user_id'] ?>">New Password (minimum 8 characters)</label>
                        <div class="input-icon-wrap has-left-icon has-right-icon">
                            <i data-lucide="lock" class="input-icon-left"></i>
                            <input type="password" id="newPass<?= $u['user_id'] ?>" name="new_password" class="form-control" 
                                   minlength="8" required placeholder="Enter strong new password"
                                   data-strength-meter="#userStrength<?= $u['user_id'] ?>">
                            <button type="button" class="password-toggle-btn" data-password-target="#newPass<?= $u['user_id'] ?>" aria-label="Toggle password visibility">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                        
                        <!-- Live Password Strength Indicator -->
                        <div class="password-strength-wrap" id="userStrength<?= $u['user_id'] ?>">
                            <div class="password-strength-track">
                                <span class="password-strength-segment"></span>
                                <span class="password-strength-segment"></span>
                                <span class="password-strength-segment"></span>
                                <span class="password-strength-segment"></span>
                            </div>
                            <div class="password-strength-status">
                                <span>Strength: <strong class="strength-text">Enter password</strong></span>
                                <span>Min. 8 characters</span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password Input with live match validator -->
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" for="confirmPass<?= $u['user_id'] ?>">Confirm New Password</label>
                        <div class="input-icon-wrap has-left-icon has-right-icon">
                            <i data-lucide="lock" class="input-icon-left"></i>
                            <input type="password" id="confirmPass<?= $u['user_id'] ?>" name="confirm_password" class="form-control" 
                                   minlength="8" required placeholder="Re-enter password"
                                   data-match-target="#newPass<?= $u['user_id'] ?>">
                            <button type="button" class="password-toggle-btn" data-password-target="#confirmPass<?= $u['user_id'] ?>" aria-label="Toggle password visibility">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            <i data-lucide="alert-circle"></i>
                            <span>Passwords do not match.</span>
                        </div>
                        <div class="valid-feedback">
                            <i data-lucide="check-circle-2"></i>
                            <span>Passwords match!</span>
                        </div>
                    </div>
                </div>
                <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm" style="padding:8px 18px;">
                        <i data-lucide="key" style="width:14px;height:14px;"></i>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
