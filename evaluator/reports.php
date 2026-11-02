<?php
/**
 * evaluator/reports.php
 * -----------------------------------------------------------------
 * Personal evaluation history, metrics, and audit log for the
 * logged-in evaluator, with multi-criteria filters and CSV export.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Evaluator']);

$pageTitle   = 'Reports';
$currentPage = 'reports';

$uid      = (int) $_SESSION['user_id'];
$search   = sanitize_input($_GET['q'] ?? '');
$decision = sanitize_input($_GET['decision'] ?? '');
$typeId   = (int) ($_GET['type_id'] ?? 0);
$dateFrom = sanitize_input($_GET['date_from'] ?? '');
$dateTo   = sanitize_input($_GET['date_to'] ?? '');

$where = ['e.evaluator_id = ?'];
$types = 'i';
$params = [$uid];

if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%$search%";
    $types .= 'sss';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($decision !== '' && in_array($decision, ['approved', 'rejected', 'returned'], true)) {
    $where[] = "e.decision = ?";
    $types .= 's';
    $params[] = $decision;
}
if ($typeId > 0) {
    $where[] = "a.type_id = ?";
    $types .= 'i';
    $params[] = $typeId;
}
if ($dateFrom !== '') {
    $where[] = "DATE(e.evaluated_at) >= ?";
    $types .= 's';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "DATE(e.evaluated_at) <= ?";
    $types .= 's';
    $params[] = $dateTo;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// ---- CSV Export Handler ----
if (isset($_GET['export'])) {
    $stmt = mysqli_prepare($conn, "
        SELECT e.evaluated_at, e.decision, e.comments, a.tracking_code, a.title, t.type_name,
               u.first_name, u.last_name
        FROM evaluations e
        JOIN applications a ON a.application_id = e.application_id
        JOIN application_types t ON t.type_id = a.type_id
        JOIN applicants ap ON ap.applicant_id = a.applicant_id
        JOIN users u ON u.user_id = ap.user_id
        $whereSql
        ORDER BY e.evaluated_at DESC
    ");
    $result = bind_execute($stmt, $types, $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="my_evaluations_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Tracking Code', 'Title', 'Type', 'Applicant', 'Decision', 'Comments']);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($out, [
            format_date($row['evaluated_at']),
            $row['tracking_code'],
            $row['title'],
            $row['type_name'],
            $row['first_name'] . ' ' . $row['last_name'],
            ucfirst($row['decision']),
            $row['comments']
        ]);
    }
    fclose($out);
    exit;
}

// ---- Summary Metrics ----
$sumStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) total,
           SUM(e.decision='approved') approved,
           SUM(e.decision='rejected') rejected,
           SUM(e.decision='returned') returned
    FROM evaluations e
    JOIN applications a ON a.application_id = e.application_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
");
$sumResult = bind_execute($sumStmt, $types, $params);
$summary = mysqli_fetch_assoc($sumResult) ?: ['total' => 0, 'approved' => 0, 'rejected' => 0, 'returned' => 0];
mysqli_stmt_close($sumStmt);

$totalCount    = (int) ($summary['total'] ?? 0);
$approvedCount = (int) ($summary['approved'] ?? 0);
$rejectedCount = (int) ($summary['rejected'] ?? 0);
$returnedCount = (int) ($summary['returned'] ?? 0);
$approvalRate  = $totalCount > 0 ? round(($approvedCount / $totalCount) * 100, 1) : 0;
$rejectionRate = $totalCount > 0 ? round(($rejectedCount / $totalCount) * 100, 1) : 0;

// ---- Application Types for Filter Dropdown ----
$typesList = mysqli_query($conn, "SELECT type_id, type_name FROM application_types WHERE is_active = 1 ORDER BY type_name");

// ---- Filtered Evaluation Records ----
$listStmt = mysqli_prepare($conn, "
    SELECT e.evaluation_id, e.evaluated_at, e.decision, e.comments,
           a.application_id, a.tracking_code, a.title,
           t.type_name, u.first_name, u.last_name
    FROM evaluations e
    JOIN applications a ON a.application_id = e.application_id
    JOIN application_types t ON t.type_id = a.type_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
    ORDER BY e.evaluated_at DESC
    LIMIT 100
");
$evaluations = bind_execute($listStmt, $types, $params);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Technical Evaluation Analytics &amp; Reports</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Historical performance, decision distribution, and comprehensive audit trail of your technical reviews.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-outline" title="Export current filtered report to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i>
            Export CSV
        </a>
        <a href="<?= BASE_URL ?>evaluator/evaluations.php" class="btn btn-primary" title="View active review queue">
            <i data-lucide="clipboard-check" style="width:16px;height:16px;"></i>
            My Review Queue
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Total Evaluations -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Evaluations</span>
            <span class="info-icon" title="Total evaluations completed by you within the active filter scope">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($totalCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repGradBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,30 Q25,22 50,14 T100,6 L100,40 L0,40 Z" fill="url(#repGradBlue)"/>
                <path d="M0,30 Q25,22 50,14 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="file-check-2" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Completed evaluations</span>
        </div>
    </div>

    <!-- Card 2: Recommended Approvals -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Recommended Approvals</span>
            <span class="info-icon" title="Applications where you recommended approval for final sign-off">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approvedCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,35 Q25,25 50,15 T100,4 L100,40 L0,40 Z" fill="url(#repGradGreen)"/>
                <path d="M0,35 Q25,25 50,15 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-circle" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span><?= $approvalRate ?>% approval rate</span>
        </div>
    </div>

    <!-- Card 3: Rejected -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Rejected Filings</span>
            <span class="info-icon" title="Applications evaluated that failed criteria or prior art examination">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($rejectedCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repGradRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,28 60,18 T100,26 L100,40 L0,40 Z" fill="url(#repGradRed)"/>
                <path d="M0,18 Q30,28 60,18 T100,26" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="x-circle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span><?= $rejectionRate ?>% rejection rate</span>
        </div>
    </div>

    <!-- Card 4: Returned for Revision -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned for Revision</span>
            <span class="info-icon" title="Applications returned for applicant technical adjustments and revisions">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($returnedCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repGradWarning" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,22 Q30,32 60,20 T100,28 L100,40 L0,40 Z" fill="url(#repGradWarning)"/>
                <path d="M0,22 Q30,32 60,20 T100,28" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Revisions requested</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     FILTER & CONTROL ROW
     ===================================================================== -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <!-- Decision pills -->
                <div class="category-tabs" style="margin-bottom:0;">
                    <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['decision' => ''])) ?>" class="tab-pill <?= empty($decision) ? 'active' : '' ?>">All Decisions</a>
                    <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['decision' => 'approved'])) ?>" class="tab-pill <?= $decision === 'approved' ? 'active' : '' ?>">Approved</a>
                    <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['decision' => 'rejected'])) ?>" class="tab-pill <?= $decision === 'rejected' ? 'active' : '' ?>">Rejected</a>
                    <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['decision' => 'returned'])) ?>" class="tab-pill <?= $decision === 'returned' ? 'active' : '' ?>">Returned</a>
                </div>

                <!-- Search Pill -->
                <div style="display:flex;align-items:center;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-full);padding:3px 12px;gap:8px;">
                    <i data-lucide="search" style="width:14px;height:14px;color:var(--color-text-subtle);"></i>
                    <input type="text" name="q" value="<?= clean($search) ?>" placeholder="Search code, title, applicant…" style="border:none;background:transparent;outline:none;font-size:13px;width:200px;color:var(--color-text);">
                </div>

                <!-- Type filter -->
                <div style="min-width:140px;">
                    <select name="type_id" class="form-control" style="font-size:13px;height:36px;border-radius:var(--radius-full);padding:0 12px;">
                        <option value="0">All IP Types</option>
                        <?php if ($typesList): while ($t = mysqli_fetch_assoc($typesList)): ?>
                            <option value="<?= $t['type_id'] ?>" <?= $typeId === (int)$t['type_id'] ? 'selected' : '' ?>><?= clean($t['type_name']) ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>

            <!-- Date range + Actions -->
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <div style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--color-text-muted);">
                    <span style="font-weight:500;">From:</span>
                    <input type="date" name="date_from" value="<?= clean($dateFrom) ?>" class="form-control" style="font-size:12.5px;height:36px;border-radius:var(--radius-md);width:130px;padding:4px 8px;">
                    <span style="font-weight:500;">To:</span>
                    <input type="date" name="date_to" value="<?= clean($dateTo) ?>" class="form-control" style="font-size:12.5px;height:36px;border-radius:var(--radius-md);width:130px;padding:4px 8px;">
                </div>

                <input type="hidden" name="decision" value="<?= clean($decision) ?>">

                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:var(--radius-full);padding:7px 16px;">
                    <i data-lucide="filter" style="width:13px;height:13px;"></i>
                    Filter
                </button>
                <a href="<?= BASE_URL ?>evaluator/reports.php" class="btn btn-outline btn-sm" style="border-radius:var(--radius-full);padding:7px 14px;" title="Reset all filters">
                    <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================================
     EVALUATION AUDIT TRAIL TABLE
     ===================================================================== -->
<div class="card" style="border-radius:var(--radius-lg);">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
        <div>
            <h3 style="margin:0 0 2px;">Evaluation Audit Trail</h3>
            <span style="font-size:12.5px;color:var(--color-text-muted);">Showing <?= mysqli_num_rows($evaluations) ?> evaluated record<?= mysqli_num_rows($evaluations) === 1 ? '' : 's' ?> matching active criteria.</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <a href="<?= BASE_URL ?>evaluator/reports.php?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-outline btn-sm" title="Download CSV report">
                <i data-lucide="download" style="width:13px;height:13px;"></i>
                Download CSV
            </a>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($evaluations) === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <i data-lucide="file-spreadsheet" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:12px;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;color:var(--color-text);">No evaluations found</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">No evaluated applications match your selected criteria or date range.</p>
                <?php if ($search !== '' || $dateFrom !== '' || $dateTo !== '' || $decision !== '' || $typeId > 0): ?>
                    <a href="<?= BASE_URL ?>evaluator/reports.php" class="btn btn-outline btn-sm" style="margin-top:14px;">Clear Active Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:110px;">Date</th>
                    <th style="width:140px;">Tracking Code</th>
                    <th>Application Title &amp; Type</th>
                    <th>Applicant</th>
                    <th style="width:130px;">Decision</th>
                    <th>Evaluation Remarks</th>
                    <th style="text-align:right;width:90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($e = mysqli_fetch_assoc($evaluations)): 
                    $badgeClass = match($e['decision']) {
                        'approved' => 'badge-success',
                        'rejected' => 'badge-danger',
                        'returned' => 'badge-warning',
                        default    => 'badge-secondary'
                    };
                ?>
                <tr>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($e['evaluated_at'], 'M d, Y') ?>
                        <div style="font-size:11px;color:var(--color-text-subtle);"><?= format_date($e['evaluated_at'], 'h:i A') ?></div>
                    </td>
                    <td class="tracking" style="white-space:nowrap;">
                        <?= clean($e['tracking_code']) ?>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);margin-bottom:3px;"><?= clean($e['title']) ?></div>
                        <span class="badge badge-info" style="font-size:11px;padding:2px 8px;"><?= clean($e['type_name']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:500;color:var(--color-text);"><?= clean($e['first_name'] . ' ' . $e['last_name']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>" style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:3px 10px;">
                            <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                            <?= ucfirst($e['decision']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;max-width:320px;" title="<?= clean($e['comments']) ?>">
                            <?= clean(mb_strimwidth($e['comments'] ?? '', 0, 95, '…')) ?>
                        </div>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>evaluator/evaluation_view.php?id=<?= (int)$e['application_id'] ?>" title="View evaluation details">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

