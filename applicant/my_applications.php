<?php
/**
 * applicant/my_applications.php
 * -----------------------------------------------------------------
 * Lists only the applications belonging to the logged-in applicant,
 * with search + status filter + pagination.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$pageTitle   = 'My Applications';
$currentPage = 'my_applications';

$stmt = mysqli_prepare($conn, "SELECT applicant_id FROM applicants WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$applicantId = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['applicant_id'] ?? 0;
mysqli_stmt_close($stmt);

// ---- KPI counts for tabs & metrics ----
function count_applicant_apps($conn, $applicantId, $extra = '') {
    $q = mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE applicant_id = $applicantId $extra");
    return (int) (mysqli_fetch_assoc($q)['c'] ?? 0);
}
$kpiTotal    = count_applicant_apps($conn, $applicantId);
$kpiPending  = count_applicant_apps($conn, $applicantId, "AND status IN ('pending','under_review')");
$kpiApproved = count_applicant_apps($conn, $applicantId, "AND status = 'approved'");
$kpiReturned = count_applicant_apps($conn, $applicantId, "AND status = 'returned'");
$kpiRejected = count_applicant_apps($conn, $applicantId, "AND status = 'rejected'");

$search  = sanitize_input($_GET['q'] ?? '');
$status  = sanitize_input($_GET['status'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = ['a.applicant_id = ?']; $types = 'i'; $params = [$applicantId];
if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ?)";
    $like = "%$search%";
    $types .= 'ss'; $params[] = $like; $params[] = $like;
}
if ($status !== '') {
    $where[] = "a.status = ?";
    $types .= 's'; $params[] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM applications a $whereSql");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);

$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, a.custom_category, t.type_name
    FROM applications a JOIN application_types t ON t.type_id = a.type_id
    $whereSql
    ORDER BY a.submitted_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
    ");
$applications = bind_execute($listStmt, $types, $params);

function qs4($overrides = [])
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
        <h2>My Applications Directory</h2>
        <p>Monitor your filed IP disclosures, documentary revisions, and formal evaluations.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>applicant/submit_application.php" class="btn btn-primary" title="File a new IP Application">
            <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
            Submit Application
        </a>
    </div>
</div>

<!-- 2. KPI Summary Grid -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Filings</span>
            <span class="info-icon" title="All IP applications submitted under your account">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiTotal) ?></div>
            <?= render_kpi_sparkline($kpiTotal, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="folder-check" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>All recorded submissions</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>In Processing</span>
            <span class="info-icon" title="Applications currently undergoing verification or technical review">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiPending) ?></div>
            <?= render_kpi_sparkline($kpiPending, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Pending or under review</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved Applications</span>
            <span class="info-icon" title="Applications granted official approval and recorded in registry">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiApproved) ?></div>
            <?= render_kpi_sparkline($kpiApproved, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-circle" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Registration Approved</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned / Rejected</span>
            <span class="info-icon" title="Applications needing revisions or marked rejected">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiReturned + $kpiRejected) ?></div>
            <?= render_kpi_sparkline($kpiReturned + $kpiRejected, 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-triangle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Check remarks if returned</span>
        </div>
    </div>
</div>

<!-- 3. Modern Segmented Toolbar (Status Tabs + Live Search) -->
<div class="staff-toolbar">
    <div class="staff-toolbar-tabs" role="tablist">
        <a href="<?= qs4(['status' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === '' ? 'active' : '' ?>">
            <i data-lucide="layers" style="width:14px;height:14px;"></i>
            <span>All</span>
            <span class="staff-tab-count"><?= number_format($kpiTotal) ?></span>
        </a>
        <a href="<?= qs4(['status' => 'pending', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'pending' ? 'active' : '' ?>">
            <i data-lucide="clock" style="width:14px;height:14px;"></i>
            <span>Pending</span>
            <span class="staff-tab-count"><?= number_format(count_applicant_apps($conn, $applicantId, "AND status = 'pending'")) ?></span>
        </a>
        <a href="<?= qs4(['status' => 'under_review', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'under_review' ? 'active' : '' ?>">
            <i data-lucide="flask-conical" style="width:14px;height:14px;"></i>
            <span>Under Review</span>
            <span class="staff-tab-count"><?= number_format(count_applicant_apps($conn, $applicantId, "AND status = 'under_review'")) ?></span>
        </a>
        <a href="<?= qs4(['status' => 'returned', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'returned' ? 'active' : '' ?>">
            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
            <span>Returned</span>
            <span class="staff-tab-count"><?= number_format($kpiReturned) ?></span>
        </a>
        <a href="<?= qs4(['status' => 'approved', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'approved' ? 'active' : '' ?>">
            <i data-lucide="check-circle-2" style="width:14px;height:14px;"></i>
            <span>Approved</span>
            <span class="staff-tab-count"><?= number_format($kpiApproved) ?></span>
        </a>
        <a href="<?= qs4(['status' => 'rejected', 'page' => 1]) ?>" class="staff-tab-pill <?= $status === 'rejected' ? 'active' : '' ?>">
            <i data-lucide="x-circle" style="width:14px;height:14px;"></i>
            <span>Rejected</span>
            <span class="staff-tab-count"><?= number_format($kpiRejected) ?></span>
        </a>
    </div>

    <!-- Live Search Form -->
    <form method="GET" class="staff-search-form">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= clean($status) ?>">
        <?php endif; ?>
        <div class="staff-search-input-wrap">
            <i data-lucide="search" class="staff-search-icon"></i>
            <input type="text" name="q" class="staff-search-input" placeholder="Search tracking # or title..." value="<?= clean($search) ?>" autocomplete="off">
            <?php if ($search !== ''): ?>
                <a href="<?= qs4(['q' => '']) ?>" class="staff-search-clear" title="Clear search">&times;</a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm staff-search-btn">
            <i data-lucide="search" style="width:14px;height:14px;"></i> Search
        </button>
        <?php if ($search !== '' || $status !== ''): ?>
            <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline btn-sm staff-reset-btn" title="Reset all filters">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- 4. Applications Table Card -->
<div class="staff-table-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="folder-archive" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
            <h3 style="margin:0;font-size:15px;font-weight:700;">Submissions Registry</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= number_format($totalRows) ?> total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int)$totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <i data-lucide="file-text" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:12px;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;color:var(--color-text);">No applications found</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">
                    <?= $search !== '' || $status !== '' ? 'No submissions match your active filter criteria.' : 'You have not submitted any IP applications yet.' ?>
                </p>
                <?php if ($search === '' && $status === ''): ?>
                    <a href="<?= BASE_URL ?>applicant/submit_application.php" class="btn btn-primary btn-sm" style="margin-top:14px;">
                        <i data-lucide="plus" style="width:14px;height:14px;"></i>
                        Submit Your First Application
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Clear Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Invention / Title</th>
                    <th>IP Category</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td class="tracking">
                        <span style="font-family:var(--font-mono);font-size:12.5px;font-weight:600;color:var(--color-primary-600);background:var(--color-primary-50);padding:3px 8px;border-radius:6px;border:1px solid rgba(37,99,235,0.15);">
                            <?= clean($a['tracking_code']) ?>
                        </span>
                    </td>
                    <td>
                        <strong style="color:var(--color-text);font-size:13.5px;display:block;"><?= clean($a['title']) ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-weight:500;">
                            <?= get_application_category_label($a) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>applicant/application_view.php?id=<?= $a['application_id'] ?>" title="View Application Details">
                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                            View
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
<div class="pagination">
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qs4(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
