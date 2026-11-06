<?php
/**
 * staff/verify.php
 * -----------------------------------------------------------------
 * Full list of applications relevant to Staff verification work:
 * search + filter by status + pagination. Links to
 * application_view.php for the actual verify/forward/return actions.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Application Review';
$currentPage = 'verify';

$search   = sanitize_input($_GET['q'] ?? '');
$status   = sanitize_input($_GET['status'] ?? (isset($_GET['assigned']) ? '' : 'pending'));
$assigned = sanitize_input($_GET['assigned'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 10;

// ---- Quick KPI counts for cards & status tabs ----
$kpiCounts = [
    'pending'      => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'pending'"))['c'] ?? 0),
    'under_review' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'under_review'"))['c'] ?? 0),
    'returned'     => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'returned'"))['c'] ?? 0),
    'rejected'     => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'rejected'"))['c'] ?? 0),
];
$totalAll = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications"))['c'] ?? 0);
$myAssignedTotal = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_staff_id = {$_SESSION['user_id']}"))['c'] ?? 0);

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status !== '') { 
    $where[] = "a.status = ?"; 
    $types .= 's'; 
    $params[] = $status; 
}
if ($assigned === 'me') {
    $where[] = "a.assigned_staff_id = ?";
    $types .= 'i';
    $params[] = (int) $_SESSION['user_id'];
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) total FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);
$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, a.custom_category, t.type_name,
           u.first_name, u.last_name, u.email
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    $whereSql
    ORDER BY a.submitted_at ASC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$applications = bind_execute($listStmt, $types, $params);

function qsV($overrides = [])
{
    $current = $_GET;
    foreach ($overrides as $k => $v) { 
        if ($v === null || $v === '') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return '?' . http_build_query($current);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Staff Verification Desk</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Screening &amp; Compliance Queue</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Verify submission integrity, examine required technical disclosures, and assign certified evaluators.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm" title="Refresh Queue">
            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i>
            Refresh
        </a>
    </div>
</div>

<!-- 2. KPI Summary Cards with Sparklines -->
<div class="kpi-grid">
    <!-- Card 1: Awaiting Screening -->
    <a href="<?= qsV(['status' => 'pending', 'assigned' => '', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Awaiting Screening</span>
            <span class="info-icon" title="New applications pending document verification"><i data-lucide="clock" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['pending']) ?></div>
            <?= render_kpi_sparkline($kpiCounts['pending'], 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="alert-circle" style="width:13px;height:13px;"></i> Needs Action</span>
            <span>Priority Queue</span>
        </div>
    </a>

    <!-- Card 2: Forwarded / Under Review -->
    <a href="<?= qsV(['status' => 'under_review', 'assigned' => '', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Under Technical Review</span>
            <span class="info-icon" title="Screened and assigned to evaluators"><i data-lucide="flask-conical" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['under_review']) ?></div>
            <?= render_kpi_sparkline($kpiCounts['under_review'], 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="activity" style="width:13px;height:13px;"></i> In Evaluation</span>
            <span>With Evaluator</span>
        </div>
    </a>

    <!-- Card 3: Returned for Revision -->
    <a href="<?= qsV(['status' => 'returned', 'assigned' => '', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Returned for Revision</span>
            <span class="info-icon" title="Returned to applicant due to missing documents or requirements"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['returned']) ?></div>
            <?= render_kpi_sparkline($kpiCounts['returned'], 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="user-check" style="width:13px;height:13px;"></i> Applicant Resubmission</span>
            <span>Awaiting Upload</span>
        </div>
    </a>

    <!-- Card 4: Rejected / Closed -->
    <a href="<?= qsV(['status' => 'rejected', 'assigned' => '', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Disqualified / Rejected</span>
            <span class="info-icon" title="Applications rejected at initial screening"><i data-lucide="x-circle" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['rejected']) ?></div>
            <?= render_kpi_sparkline($kpiCounts['rejected'], 'gray') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="archive" style="width:13px;height:13px;"></i> Ineligible</span>
            <span>Closed Files</span>
        </div>
    </a>
</div>

<!-- 3. Modern Segmented Toolbar (Tabs + Live Search) -->
<div class="staff-toolbar">
    <div class="staff-toolbar-tabs" role="tablist">
        <a href="<?= qsV(['status' => '', 'assigned' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === '' && $assigned === '' ? 'active' : '' ?>">
            <i data-lucide="layers" style="width:14px;height:14px;"></i>
            <span>All</span>
            <span class="staff-tab-count"><?= number_format($totalAll) ?></span>
        </a>
        <a href="<?= qsV(['assigned' => 'me', 'status' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $assigned === 'me' ? 'active' : '' ?>">
            <i data-lucide="user-check" style="width:14px;height:14px;"></i>
            <span>My Assigned</span>
            <span class="staff-tab-count"><?= number_format($myAssignedTotal) ?></span>
        </a>
        <a href="<?= qsV(['status' => 'pending', 'assigned' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'pending' && $assigned === '' ? 'active' : '' ?>">
            <i data-lucide="clock" style="width:14px;height:14px;"></i>
            <span>Pending</span>
            <span class="staff-tab-count"><?= number_format($kpiCounts['pending']) ?></span>
        </a>
        <a href="<?= qsV(['status' => 'under_review', 'assigned' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'under_review' && $assigned === '' ? 'active' : '' ?>">
            <i data-lucide="flask-conical" style="width:14px;height:14px;"></i>
            <span>Under Review</span>
            <span class="staff-tab-count"><?= number_format($kpiCounts['under_review']) ?></span>
        </a>
        <a href="<?= qsV(['status' => 'returned', 'assigned' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'returned' && $assigned === '' ? 'active' : '' ?>">
            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
            <span>Returned</span>
            <span class="staff-tab-count"><?= number_format($kpiCounts['returned']) ?></span>
        </a>
        <a href="<?= qsV(['status' => 'rejected', 'assigned' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'rejected' && $assigned === '' ? 'active' : '' ?>">
            <i data-lucide="x-circle" style="width:14px;height:14px;"></i>
            <span>Rejected</span>
            <span class="staff-tab-count"><?= number_format($kpiCounts['rejected']) ?></span>
        </a>
    </div>

    <form method="GET" class="staff-search-form">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= clean($status) ?>">
        <?php endif; ?>
        <?php if ($assigned !== ''): ?>
            <input type="hidden" name="assigned" value="<?= clean($assigned) ?>">
        <?php endif; ?>
        <div class="staff-search-input-wrap">
            <i data-lucide="search" class="staff-search-icon"></i>
            <input type="text" name="q" class="staff-search-input" placeholder="Search tracking, title, applicant..." value="<?= clean($search) ?>" autocomplete="off">
            <?php if ($search !== ''): ?>
                <a href="<?= qsV(['q' => '']) ?>" class="staff-search-clear" title="Clear search">&times;</a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm staff-search-btn">
            <i data-lucide="search" style="width:14px;height:14px;"></i> Search
        </button>
        <?php if ($search !== '' || $status !== 'pending' || $assigned !== ''): ?>
            <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm staff-reset-btn" title="Reset all filters">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- 4. Applications Data Table -->
<div class="staff-table-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="file-check-2" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:700;">Application Queue</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= number_format($totalRows) ?> total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int) $totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
    </div>
    
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="inbox" style="width:26px;height:26px;"></i>
                </div>
                <div style="font-weight:700;font-size:15px;color:var(--color-text);margin-bottom:4px;">No applications found</div>
                <div style="color:var(--color-text-muted);font-size:13px;max-width:360px;margin:0 auto 14px;">
                    <?= $search !== '' ? 'No records match your search criteria. Try a different term or clear filters.' : 'There are currently no applications in this status category.' ?>
                </div>
                <?php if ($search !== '' || $status !== 'pending'): ?>
                    <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:160px;">Tracking Code</th>
                    <th>Intellectual Property Title</th>
                    <th style="width:140px;">Category</th>
                    <th>Applicant</th>
                    <th style="width:170px;">Status</th>
                    <th style="width:130px;">Submitted</th>
                    <th style="width:110px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>staff/application_view.php?id=<?= $a['application_id'] ?>" class="tracking-pill" title="Review dossier">
                            <i data-lucide="hash" style="width:11px;height:11px;opacity:0.7;"></i>
                            <?= clean($a['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:13.5px;line-height:1.4;">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11.5px;font-weight:600;">
                            <?= get_application_category_label($a) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div class="user-avatar-initials">
                                <?= strtoupper(substr($a['first_name'],0,1) . substr($a['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                                <div style="font-size:11.5px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <span class="status-dot"></span>
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $a['application_id'] ?>" style="gap:5px;height:32px;display:inline-flex;align-items:center;">
                            <i data-lucide="clipboard-check" style="width:13px;height:13px;"></i>
                            Review
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 5. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;display:flex;justify-content:center;gap:6px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qsV(['page' => $p['currentPage'] - 1]) ?>" class="pagination-link">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qsV(['page' => $i]) ?>" class="pagination-link <?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qsV(['page' => $p['currentPage'] + 1]) ?>" class="pagination-link">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
