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

$pageTitle   = 'Verify Applications';
$currentPage = 'verify';

$search  = sanitize_input($_GET['q'] ?? '');
$status  = sanitize_input($_GET['status'] ?? 'pending');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// ---- Quick KPI counts for cards & status tabs ----
$kpiCounts = [
    'pending'      => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'pending'"))['c'] ?? 0),
    'under_review' => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'under_review'"))['c'] ?? 0),
    'returned'     => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'returned'"))['c'] ?? 0),
    'rejected'     => (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'rejected'"))['c'] ?? 0),
];
$totalAll = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications"))['c'] ?? 0);

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
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name,
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
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Screening</span>
            <span class="info-icon" title="New applications pending document verification"><i data-lucide="clock" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['pending']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="vPendingGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,28 Q25,12 50,22 T100,8 L100,40 L0,40 Z" fill="url(#vPendingGrad)"/>
                <path d="M0,28 Q25,12 50,22 T100,8" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="alert-circle" style="width:13px;height:13px;"></i> Needs Action</span>
            <span>Priority Queue</span>
        </div>
    </div>

    <!-- Card 2: Forwarded / Under Review -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Under Technical Review</span>
            <span class="info-icon" title="Screened and assigned to evaluators"><i data-lucide="flask-conical" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['under_review']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="vReviewGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q35,8 70,20 T100,10 L100,40 L0,40 Z" fill="url(#vReviewGrad)"/>
                <path d="M0,32 Q35,8 70,20 T100,10" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="activity" style="width:13px;height:13px;"></i> In Evaluation</span>
            <span>With Evaluator</span>
        </div>
    </div>

    <!-- Card 3: Returned for Revision -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned for Revision</span>
            <span class="info-icon" title="Returned to applicant due to missing documents or requirements"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['returned']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="vReturnedGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EC4899" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#EC4899" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,25 Q30,35 60,18 T100,14 L100,40 L0,40 Z" fill="url(#vReturnedGrad)"/>
                <path d="M0,25 Q30,35 60,18 T100,14" stroke="#EC4899" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="user-check" style="width:13px;height:13px;"></i> Applicant Resubmission</span>
            <span>Awaiting Upload</span>
        </div>
    </div>

    <!-- Card 4: Rejected / Closed -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Disqualified / Rejected</span>
            <span class="info-icon" title="Applications rejected at initial screening"><i data-lucide="x-circle" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiCounts['rejected']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="vRejectGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,15 Q30,30 65,15 T100,30 L100,40 L0,40 Z" fill="url(#vRejectGrad)"/>
                <path d="M0,15 Q30,30 65,15 T100,30" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="archive" style="width:13px;height:13px;"></i> Ineligible</span>
            <span>Closed Files</span>
        </div>
    </div>
</div>

<!-- 3. Deli-Prop Filter Row: Status Pills + Search Input -->
<div class="filter-card">
    <div class="filter-row">
        <!-- Status Pills -->
        <div class="segmented-control">
            <a href="<?= qsV(['status' => '', 'page' => 1]) ?>" class="segment-btn <?= $status === '' ? 'active' : '' ?>">
                All <span class="count-pill"><?= number_format($totalAll) ?></span>
            </a>
            <a href="<?= qsV(['status' => 'pending', 'page' => 1]) ?>" class="segment-btn <?= $status === 'pending' ? 'active' : '' ?>">
                Pending <span class="count-pill"><?= number_format($kpiCounts['pending']) ?></span>
            </a>
            <a href="<?= qsV(['status' => 'under_review', 'page' => 1]) ?>" class="segment-btn <?= $status === 'under_review' ? 'active' : '' ?>">
                Under Review <span class="count-pill"><?= number_format($kpiCounts['under_review']) ?></span>
            </a>
            <a href="<?= qsV(['status' => 'returned', 'page' => 1]) ?>" class="segment-btn <?= $status === 'returned' ? 'active' : '' ?>">
                Returned <span class="count-pill"><?= number_format($kpiCounts['returned']) ?></span>
            </a>
            <a href="<?= qsV(['status' => 'rejected', 'page' => 1]) ?>" class="segment-btn <?= $status === 'rejected' ? 'active' : '' ?>">
                Rejected <span class="count-pill"><?= number_format($kpiCounts['rejected']) ?></span>
            </a>
        </div>

        <!-- Inline Search Form -->
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= clean($status) ?>">
            <?php endif; ?>
            <div style="position:relative;display:flex;align-items:center;">
                <i data-lucide="search" style="position:absolute;left:10px;width:14px;height:14px;color:var(--color-text-muted);pointer-events:none;"></i>
                <input type="text" name="q" class="form-control" placeholder="Search tracking, title, applicant…" value="<?= clean($search) ?>" style="padding-left:32px;height:36px;font-size:13px;width:260px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:36px;">Search</button>
            <?php if ($search !== '' || $status !== 'pending'): ?>
                <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm" style="height:36px;" title="Reset filters">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 4. Applications Data Table -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
    <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;background:var(--color-surface);">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="file-check-2" style="width:18px;height:18px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Application Queue</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;"><?= number_format($totalRows) ?> total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int) $totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?>
        </div>
    </div>
    
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="inbox" style="width:26px;height:26px;"></i>
                </div>
                <div style="font-weight:600;font-size:15px;color:var(--color-text);margin-bottom:4px;">No applications found</div>
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
                    <th style="width:140px;">Tracking Code</th>
                    <th>Intellectual Property Title</th>
                    <th>Category</th>
                    <th>Applicant</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:120px;">Submitted</th>
                    <th style="width:100px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td>
                        <span class="tracking" style="font-weight:600;font-size:12px;letter-spacing:0.02em;"><?= clean($a['tracking_code']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:13.5px;line-height:1.4;margin-bottom:2px;">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:11.5px;"><?= clean($a['type_name']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:500;font-size:13px;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div style="font-size:11.5px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $a['application_id'] ?>" style="gap:5px;">
                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
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
