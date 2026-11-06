<?php
/**
 * admin/applications.php
 * -----------------------------------------------------------------
 * Full institutional list of every IP application in the system.
 *  - Segregated status tabs with live aggregate counts
 *  - Real-time live debounce search (300ms) - manual Filter button removed
 *  - Unified Office/Department filter and table display
 *  - Fixed filing date range validation & portfolio query binding
 *  - Export CSV & Purge All actions
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
$deptId    = (int) ($_GET['department_id'] ?? 0);
$dateFrom  = sanitize_input($_GET['date_from'] ?? '');
$dateTo    = sanitize_input($_GET['date_to'] ?? '');
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = 10;

// Auto-swap filing dates if inverted
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $tempDate = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo   = $tempDate;
}

// ---- Status segregation counts for tabs and KPI cards ----
$statusCountsQuery = "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(status = 'pending'), 0) AS total_pending,
        COALESCE(SUM(status = 'under_review'), 0) AS total_under_review,
        COALESCE(SUM(status = 'evaluated'), 0) AS total_evaluated,
        COALESCE(SUM(status = 'returned'), 0) AS total_returned,
        COALESCE(SUM(status = 'approved'), 0) AS total_approved,
        COALESCE(SUM(status = 'rejected'), 0) AS total_rejected
    FROM applications
";
$statusCountsRes = mysqli_query($conn, $statusCountsQuery);
$statusCounts = mysqli_fetch_assoc($statusCountsRes) ?: [
    'total_all'          => 0,
    'total_pending'      => 0,
    'total_under_review' => 0,
    'total_evaluated'    => 0,
    'total_returned'     => 0,
    'total_approved'     => 0,
    'total_rejected'     => 0
];

// ---- Build the WHERE clause dynamically & safely ----
$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR t.type_name LIKE ? OR a.custom_category LIKE ?)";
    $like = "%$search%";
    $types .= 'ssssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$validStatuses = ['pending', 'under_review', 'evaluated', 'returned', 'approved', 'rejected'];
if (in_array($status, $validStatuses, true)) {
    $where[] = "a.status = ?";
    $types .= 's';
    $params[] = $status;
}

if ($typeId > 0) {
    $where[] = "a.type_id = ?";
    $types .= 'i';
    $params[] = $typeId;
}

if ($deptId > 0) {
    $where[] = "a.department_id = ?";
    $types .= 'i';
    $params[] = $deptId;
}

if ($dateFrom !== '') {
    $where[] = "DATE(a.submitted_at) >= ?";
    $types .= 's';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(a.submitted_at) <= ?";
    $types .= 's';
    $params[] = $dateTo;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Count total for pagination ----
$countSql = "
    SELECT COUNT(*) AS total
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    $whereSql
";
$countStmt = mysqli_prepare($conn, $countSql);
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);

$p = paginate($totalRows, $perPage, $page);

// ---- Fetch the page of results ----
$listSql = "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, a.custom_category,
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

// Hierarchical departments grouped by college for unified "Office/Department"
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

function qs($overrides = [])
{
    $current = $_GET;
    foreach ($overrides as $k => $v) { $current[$k] = $v; }
    return '?' . http_build_query($current);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Sub-Header Bar -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <h2>Applications Directory</h2>
        <p>Monitor, review, and organize institutional IP filings across all categories and university departments.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/export_csv.php<?= $whereSql ? ('?' . http_build_query($_GET)) : '' ?>" class="btn btn-outline btn-sm" title="Export filtered applications to CSV">
            <i data-lucide="download" style="width:15px;height:15px;"></i>
            Export CSV
        </a>
        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#clearAllModal" title="Purge all applications">
            <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
            Clear All
        </button>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="margin-bottom:20px;">
    <!-- Total Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Submissions</span>
            <span class="info-icon" title="All-time registered intellectual property applications">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format((int)$statusCounts['total_all']) ?></div>
            <?= render_kpi_sparkline((int)$statusCounts['total_all'], 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="archive" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Master archive count</span>
        </div>
    </div>

    <!-- Pending Intake -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Review</span>
            <span class="info-icon" title="Applications awaiting formality check or evaluator assignment">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-warning);"><?= number_format((int)$statusCounts['total_pending']) ?></div>
            <?= render_kpi_sparkline((int)$statusCounts['total_pending'], 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-circle" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Requires office action</span>
        </div>
    </div>

    <!-- In Assessment (Under Review + Evaluated) -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>In Assessment</span>
            <span class="info-icon" title="Applications under technical review or substantive evaluation">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary-600);"><?= number_format((int)($statusCounts['total_under_review'] + $statusCounts['total_evaluated'])) ?></div>
            <?= render_kpi_sparkline((int)($statusCounts['total_under_review'] + $statusCounts['total_evaluated']), 'purple') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Actively in technical evaluation</span>
        </div>
    </div>

    <!-- Approved / Registered -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved / Registered</span>
            <span class="info-icon" title="Officially approved and certified intellectual properties">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-success);"><?= number_format((int)$statusCounts['total_approved']) ?></div>
            <?= render_kpi_sparkline((int)$statusCounts['total_approved'], 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Certified registered assets</span>
        </div>
    </div>
</div>

<!-- 3. Status Segregation Tabs with Live Count Badges -->
<div class="category-pill-tabs" style="margin-bottom:18px;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="<?= qs(['status' => '', 'page' => 1]) ?>" class="category-pill <?= empty($status) ? 'active' : '' ?>">
        <i data-lucide="layers" style="width:14px;height:14px;"></i>
        <span>All Filings</span>
        <span class="badge badge-secondary" style="font-size:11px;padding:2px 7px;"><?= (int)$statusCounts['total_all'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'pending', 'page' => 1]) ?>" class="category-pill <?= $status === 'pending' ? 'active' : '' ?>">
        <i data-lucide="clock" style="width:14px;height:14px;color:var(--color-warning);"></i>
        <span>Pending Intake</span>
        <span class="badge badge-warning" style="font-size:11px;padding:2px 7px;"><?= (int)$statusCounts['total_pending'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'under_review', 'page' => 1]) ?>" class="category-pill <?= $status === 'under_review' ? 'active' : '' ?>">
        <i data-lucide="search" style="width:14px;height:14px;color:var(--color-primary-600);"></i>
        <span>Under Review</span>
        <span class="badge badge-primary" style="font-size:11px;padding:2px 7px;"><?= (int)$statusCounts['total_under_review'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'evaluated', 'page' => 1]) ?>" class="category-pill <?= $status === 'evaluated' ? 'active' : '' ?>">
        <i data-lucide="file-check-2" style="width:14px;height:14px;color:#8B5CF6;"></i>
        <span>Evaluated</span>
        <span class="badge" style="font-size:11px;padding:2px 7px;background:rgba(139,92,246,0.15);color:#8B5CF6;border:1px solid rgba(139,92,246,0.3);"><?= (int)$statusCounts['total_evaluated'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'returned', 'page' => 1]) ?>" class="category-pill <?= $status === 'returned' ? 'active' : '' ?>">
        <i data-lucide="rotate-ccw" style="width:14px;height:14px;color:#F97316;"></i>
        <span>Needs Revision</span>
        <span class="badge" style="font-size:11px;padding:2px 7px;background:rgba(249,115,22,0.15);color:#F97316;border:1px solid rgba(249,115,22,0.3);"><?= (int)$statusCounts['total_returned'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'approved', 'page' => 1]) ?>" class="category-pill <?= $status === 'approved' ? 'active' : '' ?>">
        <i data-lucide="check-circle" style="width:14px;height:14px;color:var(--color-success);"></i>
        <span>Approved &amp; Registered</span>
        <span class="badge badge-success" style="font-size:11px;padding:2px 7px;"><?= (int)$statusCounts['total_approved'] ?></span>
    </a>
    <a href="<?= qs(['status' => 'rejected', 'page' => 1]) ?>" class="category-pill <?= $status === 'rejected' ? 'active' : '' ?>">
        <i data-lucide="x-circle" style="width:14px;height:14px;color:var(--color-danger);"></i>
        <span>Rejected</span>
        <span class="badge badge-danger" style="font-size:11px;padding:2px 7px;"><?= (int)$statusCounts['total_rejected'] ?></span>
    </a>
</div>

<!-- 4. Real-time Search & Filter Card (Filter Button Removed) -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:18px 20px;">
        <form method="GET" action="<?= BASE_URL ?>admin/applications.php" id="appsFilterForm">
            <!-- Retain current status when filtering -->
            <?php if (!empty($status)): ?>
                <input type="hidden" name="status" value="<?= clean($status) ?>">
            <?php endif; ?>

            <div class="filter-controls-grid" style="display:grid;grid-template-columns:repeat(12, 1fr);gap:14px;align-items:end;">
                <!-- Live Search Portfolio (Real-time debounce) -->
                <div class="form-group" style="margin-bottom:0;grid-column:span 4;">
                    <label class="form-label-eyebrow">Real-time Portfolio Search</label>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="search" class="input-icon-left"></i>
                        <input type="text" name="q" id="portfolioSearchInput" class="form-control" 
                               placeholder="Type tracking code, title, applicant name, or email…" 
                               value="<?= clean($search) ?>" autocomplete="off">
                    </div>
                </div>

                <!-- IP Type -->
                <div class="form-group" style="margin-bottom:0;grid-column:span 2;">
                    <label class="form-label-eyebrow">IP Category</label>
                    <select name="type_id" id="filterTypeId" class="form-control form-select">
                        <option value="0">All Categories</option>
                        <?php mysqli_data_seek($types_lookup, 0); while ($t = mysqli_fetch_assoc($types_lookup)): ?>
                            <option value="<?= $t['type_id'] ?>" <?= $typeId === (int)$t['type_id'] ? 'selected' : '' ?>><?= clean($t['type_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Unified Office / Department -->
                <div class="form-group" style="margin-bottom:0;grid-column:span 3;">
                    <label class="form-label-eyebrow">Office / Department</label>
                    <select name="department_id" id="filterDeptId" class="form-control form-select">
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

                <!-- Filing Date Range -->
                <div class="form-group" style="margin-bottom:0;grid-column:span 3;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <label class="form-label-eyebrow" style="margin-bottom:0;">Filing Date Range</label>
                        <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                            <a href="<?= qs(['date_from' => '', 'date_to' => '', 'page' => 1]) ?>" style="font-size:11px;color:var(--color-primary-600);text-decoration:none;" title="Clear date range">Clear Dates</a>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="date" name="date_from" id="filterDateFrom" class="form-control" value="<?= clean($dateFrom) ?>" title="Start Date" style="font-size:12px;padding:6px 10px;">
                        <span style="color:var(--color-text-subtle);font-weight:600;">&ndash;</span>
                        <input type="date" name="date_to" id="filterDateTo" class="form-control" value="<?= clean($dateTo) ?>" title="End Date" style="font-size:12px;padding:6px 10px;">
                    </div>
                </div>
            </div>

            <!-- Active Filter Badges Bar -->
            <?php 
            $hasActiveFilters = ($search !== '' || $typeId > 0 || $deptId > 0 || $dateFrom !== '' || $dateTo !== '');
            if ($hasActiveFilters): 
            ?>
                <div class="active-filter-bar" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--color-border);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span class="active-filter-label" style="font-size:12px;color:var(--color-text-muted);display:flex;align-items:center;gap:5px;font-weight:600;">
                        <i data-lucide="sliders-horizontal" style="width:13px;height:13px;"></i>
                        Active Filters:
                    </span>
                    <?php if ($search !== ''): ?>
                        <span class="filter-chip">
                            Keyword: "<?= clean($search) ?>"
                            <a href="<?= qs(['q' => '', 'page' => 1]) ?>" class="filter-chip-remove" title="Remove keyword filter">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($typeId > 0): 
                        mysqli_data_seek($types_lookup, 0);
                        $typeName = 'Category';
                        while ($t = mysqli_fetch_assoc($types_lookup)) {
                            if ((int)$t['type_id'] === $typeId) { $typeName = $t['type_name']; break; }
                        }
                    ?>
                        <span class="filter-chip">
                            Category: <?= clean($typeName) ?>
                            <a href="<?= qs(['type_id' => 0, 'page' => 1]) ?>" class="filter-chip-remove" title="Remove category filter">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($deptId > 0): 
                        $selectedDeptName = 'Office/Dept';
                        foreach ($groupedDepartments as $grp => $list) {
                            foreach ($list as $item) {
                                if ((int)$item['department_id'] === $deptId) {
                                    $selectedDeptName = $item['department_name'];
                                    break 2;
                                }
                            }
                        }
                    ?>
                        <span class="filter-chip">
                            Office/Dept: <?= clean($selectedDeptName) ?>
                            <a href="<?= qs(['department_id' => 0, 'page' => 1]) ?>" class="filter-chip-remove" title="Remove department filter">&times;</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                        <span class="filter-chip">
                            Filing Dates: <?= clean($dateFrom ?: 'Any') ?> &ndash; <?= clean($dateTo ?: 'Any') ?>
                            <a href="<?= qs(['date_from' => '', 'date_to' => '', 'page' => 1]) ?>" class="filter-chip-remove" title="Remove date range filter">&times;</a>
                        </span>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>admin/applications.php<?= !empty($status) ? '?status=' . urlencode($status) : '' ?>" class="filter-clear-all" style="font-size:12px;color:var(--color-primary-600);margin-left:auto;font-weight:600;">
                        Reset all filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 5. Applications Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Application Records</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Filings Found</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i data-lucide="file-question" style="width:36px;height:36px;color:var(--color-text-subtle);"></i>
                </div>
                <h4>No applications found</h4>
                <p>No applications match your specified search keywords or active filters.</p>
                <a href="<?= BASE_URL ?>admin/applications.php<?= !empty($status) ? '?status=' . urlencode($status) : '' ?>" class="btn btn-outline btn-sm" style="margin-top:10px;">Clear Filters</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Invention Title</th>
                    <th>Category</th>
                    <th>Applicant</th>
                    <th>Office / Department</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" class="tracking-pill" title="View details for <?= clean($a['tracking_code']) ?>">
                            <i data-lucide="hash" style="width:12px;height:12px;opacity:0.7;"></i>
                            <span><?= clean($a['tracking_code']) ?></span>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= clean($a['title']) ?>">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11px;">
                            <?= clean($a['type_name'] === 'Others' && !empty($a['custom_category']) ? $a['custom_category'] : $a['type_name']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:500;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                    </td>
                    <td>
                        <div style="font-size:12.5px;font-weight:600;color:var(--color-text);">
                            <?= clean($a['department_name'] ?: ($a['college_name'] ?? '—')) ?>
                        </div>
                        <?php if ($a['department_name'] && $a['college_name']): ?>
                            <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($a['college_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <?= status_label($a['status']) ?>
                        </span>
                        <?php if ($a['status'] === 'approved'): ?>
                            <span title="Record is locked in read-only mode" style="margin-left:4px;color:var(--color-success);display:inline-block;vertical-align:middle;">
                                <i data-lucide="lock" style="width:12px;height:12px;"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" title="View Dossier">
                                <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                View
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this application and all its documents permanently?');" style="margin:0;display:inline-flex;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="application_id" value="<?= $a['application_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Application">
                                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
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
    <div class="modal-box" style="border-radius:var(--radius-lg);max-width:500px;">
        <form method="POST">
            <div class="modal-head" style="padding:18px 22px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-danger-bg);color:var(--color-danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="alert-triangle" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">Purge All Application Records</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Irreversible institutional archive purge</div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <p style="font-size:13px;color:var(--color-text);line-height:1.55;margin:0 0 16px;">
                    This will permanently purge <strong>every application</strong> in the system—including all associated technical disclosures, evaluations, and audit status histories. This action <strong>cannot</strong> be undone.
                </p>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear_all">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="clearAllAppsConfirmInput">
                        Type <strong style="color:var(--color-danger);">DELETE</strong> to authorize purge:
                    </label>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="shield-alert" class="input-icon-left" style="color:var(--color-danger);"></i>
                        <input type="text" id="clearAllAppsConfirmInput" class="form-control" autocomplete="off" 
                               placeholder="Type DELETE in uppercase" style="font-family:var(--font-mono);font-weight:700;letter-spacing:0.05em;">
                    </div>
                </div>
            </div>
            <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" id="clearAllAppsSubmitBtn" disabled style="padding:8px 18px;">
                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    Confirm Purge
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Purge confirmation gate
    document.getElementById('clearAllAppsConfirmInput')?.addEventListener('input', function () {
        document.getElementById('clearAllAppsSubmitBtn').disabled = this.value.trim() !== 'DELETE';
    });

    // Real-time Live Debounce Search (300ms) without needing a Filter button
    const searchInput = document.getElementById('portfolioSearchInput');
    const filterForm = document.getElementById('appsFilterForm');
    let searchDebounceTimer = null;

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function () {
                filterForm.submit();
            }, 300);
        });
    }

    // Auto-filter immediately upon dropdown change
    ['filterTypeId', 'filterDeptId', 'filterDateFrom', 'filterDateTo'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el && filterForm) {
            el.addEventListener('change', function () {
                filterForm.submit();
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
