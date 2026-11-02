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
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name
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
        <p>Monitor your filed IP disclosures, documentary revisions, and final evaluation certificates.</p>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="spkAppTotal" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,24 50,18 T100,8 L100,40 L0,40 Z" fill="url(#spkAppTotal)"/>
                <path d="M0,32 Q25,24 50,18 T100,8" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="spkAppPending" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,22 Q25,32 50,18 T100,10 L100,40 L0,40 Z" fill="url(#spkAppPending)"/>
                <path d="M0,22 Q25,32 50,18 T100,10" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Pending or under review</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved &amp; Certified</span>
            <span class="info-icon" title="Applications granted official approval with downloadable certificate">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiApproved) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="spkAppApproved" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,34 Q25,28 50,16 T100,4 L100,40 L0,40 Z" fill="url(#spkAppApproved)"/>
                <path d="M0,34 Q25,28 50,16 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-circle-2" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Approved by IPO Director</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Needs Revision</span>
            <span class="info-icon" title="Applications returned for applicant corrections or additional documents">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiReturned) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="spkAppReturned" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,30 60,20 T100,28 L100,40 L0,40 Z" fill="url(#spkAppReturned)"/>
                <path d="M0,18 Q30,30 60,20 T100,28" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-triangle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Action required on returned</span>
        </div>
    </div>
</div>

<!-- 3. Deli-Prop Filter Row -->
<div class="filter-controls-row">
    <!-- Category Status Pills -->
    <div class="category-pill-tabs">
        <a href="<?= qs4(['status' => '']) ?>" class="category-pill <?= $status === '' ? 'active' : '' ?>">
            All (<?= $kpiTotal ?>)
        </a>
        <a href="<?= qs4(['status' => 'pending']) ?>" class="category-pill <?= $status === 'pending' ? 'active' : '' ?>">
            Pending (<?= count_applicant_apps($conn, $applicantId, "AND status = 'pending'") ?>)
        </a>
        <a href="<?= qs4(['status' => 'under_review']) ?>" class="category-pill <?= $status === 'under_review' ? 'active' : '' ?>">
            Under Review (<?= count_applicant_apps($conn, $applicantId, "AND status = 'under_review'") ?>)
        </a>
        <a href="<?= qs4(['status' => 'returned']) ?>" class="category-pill <?= $status === 'returned' ? 'active' : '' ?>">
            Returned (<?= $kpiReturned ?>)
        </a>
        <a href="<?= qs4(['status' => 'approved']) ?>" class="category-pill <?= $status === 'approved' ? 'active' : '' ?>">
            Approved (<?= $kpiApproved ?>)
        </a>
        <a href="<?= qs4(['status' => 'rejected']) ?>" class="category-pill <?= $status === 'rejected' ? 'active' : '' ?>">
            Rejected (<?= $kpiRejected ?>)
        </a>
    </div>

    <!-- Search Box -->
    <form method="GET" class="search-input-pill" style="margin:0;">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= clean($status) ?>"><?php endif; ?>
        <i data-lucide="search" style="width:16px;height:16px;color:var(--color-text-subtle);"></i>
        <input type="text" name="q" placeholder="Search tracking # or title..." value="<?= clean($search) ?>">
        <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">Search</button>
        <?php if ($search !== '' || $status !== ''): ?>
            <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline btn-sm" style="height:38px;padding:0 12px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- 4. Applications Table Card -->
<div class="card" style="border-radius:var(--radius-lg);">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
        <div>
            <h3 style="margin:0 0 2px;">Submissions Registry</h3>
            <span style="font-size:12.5px;color:var(--color-text-muted);">
                Displaying <?= (int)$totalRows ?> application<?= $totalRows === 1 ? '' : 's' ?> under your account
            </span>
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
                            <?= clean($a['type_name']) ?>
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
