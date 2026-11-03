<?php
/**
 * admin/applications.php
 * -----------------------------------------------------------------
 * Full list of every IP application in the system. Supports:
 *  - search (tracking code, title, applicant name)
 *  - filters: status, application type, college, department, date range
 *  - category status tabs
 *  - KPI summary metric cards with sparklines
 *  - pagination
 * Links to application_view.php for full detail + status management.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Applications';
$currentPage = 'applications';

// ---- Handle delete / clear-all ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['application_id'] ?? 0);
        delete_application_files($conn, $id);
        $stmt = mysqli_prepare($conn, "DELETE FROM applications WHERE application_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        log_audit($conn, $_SESSION['user_id'], 'delete_application', "Deleted application #$id");
        set_flash('success', 'Application deleted.');
    }

    if ($action === 'clear_all') {
        $ids = mysqli_query($conn, "SELECT application_id FROM applications");
        while ($row = mysqli_fetch_assoc($ids)) {
            delete_application_files($conn, $row['application_id']);
        }
        mysqli_query($conn, "DELETE FROM applications");
        log_audit($conn, $_SESSION['user_id'], 'clear_all_applications', 'Cleared every application in the system');
        set_flash('success', 'All applications have been cleared.');
    }
    redirect('admin/applications.php');
}

// ---- Read filters from the query string ----
$search    = sanitize_input($_GET['q'] ?? '');
$status    = sanitize_input($_GET['status'] ?? '');
$typeId    = (int) ($_GET['type_id'] ?? 0);
$collegeId = (int) ($_GET['college_id'] ?? 0);
$deptId    = (int) ($_GET['department_id'] ?? 0);
$dateFrom  = sanitize_input($_GET['date_from'] ?? '');
$dateTo    = sanitize_input($_GET['date_to'] ?? '');
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;

// ---- Overall Stats for KPI cards ----
$statsQuery = "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(status = 'pending'), 0) AS total_pending,
        COALESCE(SUM(status IN ('under_review', 'evaluated')), 0) AS total_review,
        COALESCE(SUM(status = 'approved'), 0) AS total_approved,
        COALESCE(SUM(status = 'rejected'), 0) AS total_rejected
    FROM applications
";
$statsRes = mysqli_query($conn, $statsQuery);
$kpiStats = mysqli_fetch_assoc($statsRes) ?: [
    'total_all' => 0,
    'total_pending' => 0,
    'total_review' => 0,
    'total_approved' => 0,
    'total_rejected' => 0
];

// ---- Build the WHERE clause dynamically & safely ----
$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%";
    $types .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status !== '') {
    $where[] = "a.status = ?";
    $types .= 's'; $params[] = $status;
}
if ($typeId > 0) {
    $where[] = "a.type_id = ?";
    $types .= 'i'; $params[] = $typeId;
}
if ($collegeId > 0) {
    $where[] = "a.college_id = ?";
    $types .= 'i'; $params[] = $collegeId;
}
if ($deptId > 0) {
    $where[] = "a.department_id = ?";
    $types .= 'i'; $params[] = $deptId;
}
if ($dateFrom !== '') {
    $where[] = "DATE(a.submitted_at) >= ?";
    $types .= 's'; $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "DATE(a.submitted_at) <= ?";
    $types .= 's'; $params[] = $dateTo;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Count total for pagination ----
$countSql = "
    SELECT COUNT(*) AS total
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
";
$countStmt = mysqli_prepare($conn, $countSql);
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);

$p = paginate($totalRows, $perPage, $page);

// ---- Fetch the page of results ----
$listSql = "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at,
           t.type_name, u.first_name, u.last_name, u.email,
           c.college_name, d.department_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    $whereSql
    ORDER BY a.submitted_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
";
$listStmt = mysqli_prepare($conn, $listSql);
$applications = bind_execute($listStmt, $types, $params);

// ---- Data for filter dropdowns ----
$types_lookup = mysqli_query($conn, "SELECT type_id, type_name FROM application_types ORDER BY type_name");
$colleges_lookup = mysqli_query($conn, "SELECT college_id, college_name FROM colleges ORDER BY college_name");
$departments_lookup = mysqli_query($conn, "SELECT department_id, department_name, college_id FROM departments ORDER BY department_name");
$deptList = [];
while ($d = mysqli_fetch_assoc($departments_lookup)) { $deptList[] = $d; }

function qs($overrides = [])
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
        <h2>Applications Directory</h2>
        <p>Monitor, review, and filter institutional IP filings across all categories and university colleges.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/export_csv.php<?= $whereSql ? ('?' . http_build_query($_GET)) : '' ?>" class="btn btn-outline btn-sm">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#clearAllModal">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Clear All
        </button>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="margin-bottom:20px;">
    <!-- Total Filings -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Submissions</span>
            <span class="kpi-info-tip" title="All-time registered intellectual property applications">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-blue-app" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6 L 80 24 L 0 24 Z" fill="url(#grad-blue-app)"/>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6" fill="none" stroke="#2563EB" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Master archive count</span>
        </div>
    </div>

    <!-- Pending Review -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Awaiting Review</span>
            <span class="kpi-info-tip" title="Applications awaiting assignment or staff evaluation">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-warning);"><?= number_format((int)$kpiStats['total_pending']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-yellow-app" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 20 Q 25 12, 50 16 T 80 4 L 80 24 L 0 24 Z" fill="url(#grad-yellow-app)"/>
                    <path d="M0 20 Q 25 12, 50 16 T 80 4" fill="none" stroke="#F59E0B" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-warning">Requires staff action</span>
        </div>
    </div>

    <!-- Under Review & Evaluated -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">In Assessment</span>
            <span class="kpi-info-tip" title="Applications assigned and under evaluation">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-primary-600);"><?= number_format((int)$kpiStats['total_review']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-purple-app" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#8B5CF6" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8 L 80 24 L 0 24 Z" fill="url(#grad-purple-app)"/>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8" fill="none" stroke="#8B5CF6" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Assigned & active</span>
        </div>
    </div>

    <!-- Approved / Registered -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Granted / Approved</span>
            <span class="kpi-info-tip" title="Officially approved or registered applications">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-success);"><?= number_format((int)$kpiStats['total_approved']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-green-app" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2 L 80 24 L 0 24 Z" fill="url(#grad-green-app)"/>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2" fill="none" stroke="#10B981" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-up">Successful registrations</span>
        </div>
    </div>
</div>

<!-- 3. Status Category Filter Tabs -->
<div class="category-tabs" style="margin-bottom:16px;">
    <a href="<?= qs(['status' => '', 'page' => 1]) ?>" class="tab-pill <?= empty($status) ? 'active' : '' ?>">
        All Statuses
        <span class="tab-count"><?= (int)$kpiStats['total_all'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'pending', 'page' => 1]) ?>" class="tab-pill <?= $status === 'pending' ? 'active' : '' ?>">
        Pending
        <span class="tab-count"><?= (int)$kpiStats['total_pending'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'under_review', 'page' => 1]) ?>" class="tab-pill <?= $status === 'under_review' ? 'active' : '' ?>">
        Under Review
    </a>
    <a href="<?= qs(['status' => 'evaluated', 'page' => 1]) ?>" class="tab-pill <?= $status === 'evaluated' ? 'active' : '' ?>">
        Evaluated
    </a>
    <a href="<?= qs(['status' => 'approved', 'page' => 1]) ?>" class="tab-pill <?= $status === 'approved' ? 'active' : '' ?>">
        Approved
        <span class="tab-count"><?= (int)$kpiStats['total_approved'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'rejected', 'page' => 1]) ?>" class="tab-pill <?= $status === 'rejected' ? 'active' : '' ?>">
        Rejected
    </a>
</div>

<!-- 4. Advanced Filter Bar Card -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1.2fr auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Search Portfolio</label>
                <div class="input-icon-wrap has-left-icon">
                    <i data-lucide="search" class="input-icon-left"></i>
                    <input type="text" name="q" class="form-control" placeholder="Tracking code, title, applicant…" value="<?= clean($search) ?>">
                </div>
            </div>

            <!-- Keep current status tab if selected -->
            <?php if (!empty($status)): ?>
                <input type="hidden" name="status" value="<?= clean($status) ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">IP Type</label>
                <select name="type_id" class="form-control form-select">
                    <option value="0">All IP Types</option>
                    <?php mysqli_data_seek($types_lookup, 0); while ($t = mysqli_fetch_assoc($types_lookup)): ?>
                        <option value="<?= $t['type_id'] ?>" <?= $typeId === (int)$t['type_id'] ? 'selected' : '' ?>><?= clean($t['type_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">College</label>
                <select name="college_id" id="filterCollege" class="form-control form-select">
                    <option value="0">All Colleges</option>
                    <?php mysqli_data_seek($colleges_lookup, 0); while ($c = mysqli_fetch_assoc($colleges_lookup)): ?>
                        <option value="<?= $c['college_id'] ?>" <?= $collegeId === (int)$c['college_id'] ? 'selected' : '' ?>><?= clean($c['college_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Department</label>
                <select name="department_id" id="filterDept" class="form-control form-select">
                    <option value="0">All Departments</option>
                    <?php foreach ($deptList as $d): ?>
                        <option value="<?= $d['department_id'] ?>" data-college="<?= $d['college_id'] ?>" <?= $deptId === (int)$d['department_id'] ? 'selected' : '' ?>><?= clean($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Filing Date Range</label>
                <div style="display:flex;gap:6px;align-items:center;">
                    <input type="date" name="date_from" class="form-control" style="font-size:12px;padding:6px 8px;height:42px;" value="<?= clean($dateFrom) ?>" title="From">
                    <span style="color:var(--color-text-subtle);">&ndash;</span>
                    <input type="date" name="date_to" class="form-control" style="font-size:12px;padding:6px 8px;height:42px;" value="<?= clean($dateTo) ?>" title="To">
                </div>
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:42px;padding:0 16px;">
                    <i data-lucide="filter" style="width:14px;height:14px;"></i>
                    Filter
                </button>
                <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm" style="height:42px;display:inline-flex;align-items:center;padding:0 14px;" title="Reset filters">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>
            </div>
        </form>
    </div>
</div>

<!-- 5. Applications Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Application Records</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Results</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h4>No applications found</h4>
                <p>No applications match your specified search keywords or active filters.</p>
                <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Clear All Filters</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Invention Title</th>
                    <th>IP Type</th>
                    <th>Applicant</th>
                    <th>College / Department</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" class="tracking" style="color:var(--color-primary-600);font-weight:600;text-decoration:none;">
                            <?= clean($a['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= clean($a['title']) ?>">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11px;">
                            <?= clean($a['type_name']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:500;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                    </td>
                    <td>
                        <div style="font-size:12px;color:var(--color-text);"><?= clean($a['college_name'] ?? '—') ?></div>
                        <?php if ($a['department_name']): ?>
                            <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($a['department_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" title="View Dossier">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this application and all its documents permanently?');" style="margin:0;display:inline-flex;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Application">
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
        <a href="<?= qs(['page' => $p['currentPage'] - 1]) ?>" class="page-link" title="Previous Page">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qs(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qs(['page' => $p['currentPage'] + 1]) ?>" class="page-link" title="Next Page">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 7. Clear All confirmation modal -->
<div class="modal-overlay" id="clearAllModal">
    <div class="modal-box">
        <form method="POST">
            <div class="modal-head">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:#FEF2F2;color:#EF4444;display:flex;align-items:center;justify-content:center;">
                        <svg class="lucide-icon" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <h3>Clear All Applications</h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:13.5px;color:var(--color-text);line-height:1.5;">This will permanently purge <strong>every application</strong> in the system—including all associated documents, evaluations, and audit status histories. This action cannot be undone.</p>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear_all">
                <div class="form-group" style="margin-top:14px;">
                    <label style="font-size:12px;font-weight:600;">Type <strong style="color:var(--color-danger);">DELETE</strong> to confirm destruction:</label>
                    <input type="text" id="clearAllAppsConfirmInput" class="form-control" autocomplete="off" placeholder="DELETE">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" id="clearAllAppsSubmitBtn" disabled>Clear All Applications</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('clearAllAppsConfirmInput')?.addEventListener('input', function () {
        document.getElementById('clearAllAppsSubmitBtn').disabled = this.value.trim() !== 'DELETE';
    });

    // Cascading college -> department filter (client-side)
    const collegeFilter = document.getElementById('filterCollege');
    const deptFilter = document.getElementById('filterDept');
    const deptOptions = Array.from(deptFilter.options).map(o => ({ el: o, college: o.dataset.college || null }));
    function filterDepts() {
        const cid = collegeFilter.value;
        deptOptions.forEach(o => {
            if (!o.college) return; // "All" option always visible
            o.el.hidden = cid !== '0' && o.college !== cid;
        });
    }
    collegeFilter?.addEventListener('change', filterDepts);
    if (collegeFilter) filterDepts();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
