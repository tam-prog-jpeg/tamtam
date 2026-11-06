<?php
/**
 * admin/applicants.php
 * -----------------------------------------------------------------
 * Directory of every applicant profile (users with role Applicant,
 * joined to their extended applicants record). Search + filter by
 * applicant type / college / department, with pagination.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Applicants';
$currentPage = 'applicants';

// ---- Handle delete / clear-all ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $applicantId = (int) ($_POST['applicant_id'] ?? 0);
        $userStmt = mysqli_prepare($conn, "SELECT user_id FROM applicants WHERE applicant_id = ?");
        mysqli_stmt_bind_param($userStmt, 'i', $applicantId);
        mysqli_stmt_execute($userStmt);
        $userId = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt))['user_id'] ?? null;
        mysqli_stmt_close($userStmt);

        if ($userId) {
            delete_applicant_files($conn, $applicantId);
            $del = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($del, 'i', $userId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            log_audit($conn, $_SESSION['user_id'], 'delete_applicant', "Deleted applicant #$applicantId and all their data");
            set_flash('success', 'Applicant and all their applications/documents deleted.');
        } else {
            set_flash('error', 'Applicant not found.');
        }
    }

    if ($action === 'clear_all') {
        $ids = mysqli_query($conn, "SELECT applicant_id FROM applicants");
        while ($row = mysqli_fetch_assoc($ids)) {
            delete_applicant_files($conn, $row['applicant_id']);
        }
        mysqli_query($conn, "DELETE FROM users WHERE role_id = 5"); // role_id 5 = Applicant
        log_audit($conn, $_SESSION['user_id'], 'clear_all_applicants', 'Cleared every applicant account and their data');
        set_flash('success', 'All applicants and their data have been cleared.');
    }
    redirect('admin/applicants.php');
}

$search    = sanitize_input($_GET['q'] ?? '');
$type      = sanitize_input($_GET['applicant_type'] ?? '');
$deptId    = (int) ($_GET['department_id'] ?? 0);
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;

// ---- Overall Stats for KPI cards ----
$kpiRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(applicant_type = 'Student'), 0) AS total_students,
        COALESCE(SUM(applicant_type IN ('Faculty', 'Staff')), 0) AS total_faculty_staff,
        COALESCE(SUM(applicant_type = 'External'), 0) AS total_external
    FROM applicants
");
$kpiStats = mysqli_fetch_assoc($kpiRes) ?: [
    'total_all' => 0,
    'total_students' => 0,
    'total_faculty_staff' => 0,
    'total_external' => 0
];

$where = []; $types = ''; $params = [];

if ($search !== '') {
    $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $like = "%$search%";
    $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($type !== '') {
    $where[] = "ap.applicant_type = ?";
    $types .= 's'; $params[] = $type;
}
if ($deptId > 0) {
    $where[] = "ap.department_id = ?";
    $types .= 'i'; $params[] = $deptId;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total FROM applicants ap JOIN users u ON u.user_id = ap.user_id $whereSql
");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);

$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT ap.applicant_id, ap.applicant_type, u.user_id, u.first_name, u.last_name, u.email, u.contact_number, u.status,
           c.college_name, d.department_name,
           (SELECT COUNT(*) FROM applications a WHERE a.applicant_id = ap.applicant_id) AS total_applications
    FROM applicants ap
    JOIN users u ON u.user_id = ap.user_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    LEFT JOIN departments d ON d.department_id = ap.department_id
    $whereSql
    ORDER BY u.first_name
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$applicants = bind_execute($listStmt, $types, $params);

// Grouped departments for Office/Department
$deptQuery = mysqli_query($conn, "
    SELECT d.department_id, d.department_name, c.college_name
    FROM departments d
    LEFT JOIN colleges c ON c.college_id = d.college_id
    ORDER BY c.college_name, d.department_name
");
$groupedDepartments = [];
while ($row = mysqli_fetch_assoc($deptQuery)) {
    $colName = $row['college_name'] ?: 'General Administration & Offices';
    $groupedDepartments[$colName][] = $row;
}

function qs2($overrides = [])
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
        <h2>Applicant Registry</h2>
        <p>Directory of student, faculty, and external innovators registered within ZPPSU-IPO.</p>
    </div>
    <div class="sub-header-actions">
        <?php if ($kpiStats['total_all'] > 0): ?>
        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#clearAllApplicantsModal">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Clear All Applicants
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="margin-bottom:20px;">
    <!-- Total Applicants -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Applicants</span>
            <span class="kpi-info-tip" title="All-time registered inventors and creators">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-blue-ap" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6 L 80 24 L 0 24 Z" fill="url(#grad-blue-ap)"/>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6" fill="none" stroke="#2563EB" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Registered profiles</span>
        </div>
    </div>

    <!-- Students -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Student Creators</span>
            <span class="kpi-info-tip" title="Undergraduate and graduate student applicants">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-primary-600);"><?= number_format((int)$kpiStats['total_students']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-cyan-ap" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#06B6D4" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#06B6D4" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4 L 80 24 L 0 24 Z" fill="url(#grad-cyan-ap)"/>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4" fill="none" stroke="#06B6D4" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Academic researchers</span>
        </div>
    </div>

    <!-- Faculty & Staff -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Faculty & Staff</span>
            <span class="kpi-info-tip" title="Professors, instructors, and university staff">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-purple);"><?= number_format((int)$kpiStats['total_faculty_staff']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-purple-ap" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#8B5CF6" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8 L 80 24 L 0 24 Z" fill="url(#grad-purple-ap)"/>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8" fill="none" stroke="#8B5CF6" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Institutional researchers</span>
        </div>
    </div>

    <!-- External Innovators -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">External Innovators</span>
            <span class="kpi-info-tip" title="External partners and community inventors">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-success);"><?= number_format((int)$kpiStats['total_external']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-green-ap" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2 L 80 24 L 0 24 Z" fill="url(#grad-green-ap)"/>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2" fill="none" stroke="#10B981" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">External partnerships</span>
        </div>
    </div>
</div>

<!-- 3. Category Filter Tabs -->
<div class="category-tabs" style="margin-bottom:16px;">
    <a href="<?= qs2(['applicant_type' => '', 'page' => 1]) ?>" class="tab-pill <?= empty($type) ? 'active' : '' ?>">
        All Applicants
        <span class="tab-count"><?= (int)$kpiStats['total_all'] ?></span>
    </a>
    <a href="<?= qs2(['applicant_type' => 'Student', 'page' => 1]) ?>" class="tab-pill <?= $type === 'Student' ? 'active' : '' ?>">
        Students
        <span class="tab-count"><?= (int)$kpiStats['total_students'] ?></span>
    </a>
    <a href="<?= qs2(['applicant_type' => 'Faculty', 'page' => 1]) ?>" class="tab-pill <?= $type === 'Faculty' ? 'active' : '' ?>">
        Faculty
    </a>
    <a href="<?= qs2(['applicant_type' => 'Staff', 'page' => 1]) ?>" class="tab-pill <?= $type === 'Staff' ? 'active' : '' ?>">
        Staff
    </a>
    <a href="<?= qs2(['applicant_type' => 'External', 'page' => 1]) ?>" class="tab-pill <?= $type === 'External' ? 'active' : '' ?>">
        External
        <span class="tab-count"><?= (int)$kpiStats['total_external'] ?></span>
    </a>
</div>

<!-- 4. Search & Filter Bar (Live Search without Filter button) -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="<?= BASE_URL ?>admin/applicants.php" id="applicantsFilterForm" style="display:grid;grid-template-columns:2fr 1.5fr auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Real-time Search Applicants</label>
                <div class="input-icon-wrap has-left-icon">
                    <i data-lucide="search" class="input-icon-left"></i>
                    <input type="text" name="q" id="applicantSearchInput" class="form-control" placeholder="Type name, email, or username…" value="<?= clean($search) ?>" autocomplete="off">
                </div>
            </div>

            <?php if (!empty($type)): ?>
                <input type="hidden" name="applicant_type" value="<?= clean($type) ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Office / Department</label>
                <select name="department_id" id="filterDeptSelect" class="form-control form-select">
                    <option value="0">All Offices &amp; Departments</option>
                    <?php foreach ($groupedDepartments as $colName => $deptItems): ?>
                        <optgroup label="<?= clean($colName) ?>">
                            <?php foreach ($deptItems as $d): ?>
                                <option value="<?= $d['department_id'] ?>" <?= $deptId === (int)$d['department_id'] ? 'selected' : '' ?>>
                                    <?= clean($d['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($search !== '' || $deptId > 0 || !empty($type)): ?>
                <div>
                    <a href="<?= BASE_URL ?>admin/applicants.php" class="btn btn-outline btn-sm" style="height:42px;display:inline-flex;align-items:center;padding:0 14px;">Reset</a>
                </div>
            <?php else: ?>
                <div></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 5. Applicants Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Applicant Profiles</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Registered</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applicants) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h4>No applicants found</h4>
                <p>No applicant records match your search criteria or chosen filter options.</p>
                <a href="<?= BASE_URL ?>admin/applicants.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Clear Filter</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Applicant Details</th>
                    <th>Type</th>
                    <th>Office / Department</th>
                    <th>Contact</th>
                    <th>Filings</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applicants)): 
                    $initials = strtoupper(substr($a['first_name'] ?? 'U', 0, 1) . substr($a['last_name'] ?? '', 0, 1));
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--color-primary-50);color:var(--color-primary-600);font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;border:1px solid var(--color-border);flex-shrink:0;">
                                <?= $initials ?>
                            </div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <div style="font-size:11.5px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size:11px;">
                            <?= clean($a['applicant_type']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:12.5px;font-weight:600;color:var(--color-text);">
                            <?= clean($a['department_name'] ?: ($a['college_name'] ?? '—')) ?>
                        </div>
                        <?php if ($a['department_name'] && $a['college_name']): ?>
                            <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($a['college_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);">
                        <?= clean($a['contact_number'] ?: '—') ?>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11px;font-weight:600;">
                            <?= (int) $a['total_applications'] ?> <?= ((int)$a['total_applications'] === 1) ? 'app' : 'apps' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $a['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/applicant_view.php?id=<?= $a['applicant_id'] ?>" title="View Profile & Submissions">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this applicant and ALL their applications/documents permanently?');" style="margin:0;display:inline-flex;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="applicant_id" value="<?= $a['applicant_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Applicant">
                                    <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 6. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qs2(['page' => $p['currentPage'] - 1]) ?>" class="page-link" title="Previous Page">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qs2(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qs2(['page' => $p['currentPage'] + 1]) ?>" class="page-link" title="Next Page">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 7. Clear All confirmation modal -->
<div class="modal-overlay" id="clearAllApplicantsModal">
    <div class="modal-box" style="border-radius:var(--radius-lg);max-width:500px;">
        <form method="POST">
            <div class="modal-head" style="padding:18px 22px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-danger-bg);color:var(--color-danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="alert-triangle" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">Purge All Applicant Records</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Irreversible institutional data purge</div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <p style="font-size:13px;color:var(--color-text);line-height:1.55;margin:0 0 16px;">
                    This operation will permanently purge <strong>every applicant user account</strong>, including all filed applications, uploaded documents, evaluations, and audit status histories. This action <strong>cannot</strong> be undone.
                </p>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear_all">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="clearAllApplicantsConfirmInput">
                        Type <strong style="color:var(--color-danger);">DELETE</strong> to authorize purge:
                    </label>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="shield-alert" class="input-icon-left" style="color:var(--color-danger);"></i>
                        <input type="text" id="clearAllApplicantsConfirmInput" class="form-control" autocomplete="off" 
                               placeholder="Type DELETE in uppercase" style="font-family:var(--font-mono);font-weight:700;letter-spacing:0.05em;">
                    </div>
                </div>
            </div>
            <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" id="clearAllApplicantsSubmitBtn" disabled style="padding:8px 18px;">
                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    Confirm Purge
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('clearAllApplicantsConfirmInput')?.addEventListener('input', function () {
        document.getElementById('clearAllApplicantsSubmitBtn').disabled = this.value.trim() !== 'DELETE';
    });

    // Real-time live debounce search
    const appSearchInput = document.getElementById('applicantSearchInput');
    const appFilterForm = document.getElementById('applicantsFilterForm');
    let appSearchTimer = null;

    if (appSearchInput && appFilterForm) {
        appSearchInput.addEventListener('input', function () {
            clearTimeout(appSearchTimer);
            appSearchTimer = setTimeout(function () {
                appFilterForm.submit();
            }, 300);
        });
    }

    document.getElementById('filterDeptSelect')?.addEventListener('change', function () {
        appFilterForm?.submit();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
