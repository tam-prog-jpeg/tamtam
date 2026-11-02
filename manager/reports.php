<?php
/**
 * manager/reports.php
 * -----------------------------------------------------------------
 * Office-wide statistics and reporting for the Manager: filterable
 * summary KPIs, a monthly trend chart, and the matching application
 * list, with CSV and printable exports.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Reports & Statistics';
$currentPage = 'reports';

$search    = sanitize_input($_GET['q'] ?? '');
$status    = sanitize_input($_GET['status'] ?? '');
$typeId    = (int) ($_GET['type_id'] ?? 0);
$dateFrom  = sanitize_input($_GET['date_from'] ?? '');
$dateTo    = sanitize_input($_GET['date_to'] ?? '');

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status !== '')   { $where[] = "a.status = ?"; $types .= 's'; $params[] = $status; }
if ($typeId > 0)      { $where[] = "a.type_id = ?"; $types .= 'i'; $params[] = $typeId; }
if ($dateFrom !== '') { $where[] = "DATE(a.submitted_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = "DATE(a.submitted_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sumStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) total,
           COALESCE(SUM(a.status IN ('pending','under_review')), 0) pending,
           COALESCE(SUM(a.status = 'evaluated'), 0) evaluated,
           COALESCE(SUM(a.status = 'approved'), 0) approved,
           COALESCE(SUM(a.status = 'rejected'), 0) rejected
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
");
$sumResult = bind_execute($sumStmt, $types, $params);
$summary = mysqli_fetch_assoc($sumResult) ?: [
    'total' => 0,
    'pending' => 0,
    'evaluated' => 0,
    'approved' => 0,
    'rejected' => 0
];
mysqli_stmt_close($sumStmt);

// ---- Monthly trend for current year (ignores filters for a clean overview) ----
$year = date('Y');
$monthlyCounts = array_fill(1, 12, 0);
$monthlyResult = mysqli_query($conn, "SELECT MONTH(submitted_at) m, COUNT(*) c FROM applications WHERE YEAR(submitted_at) = $year GROUP BY MONTH(submitted_at)");
while ($row = mysqli_fetch_assoc($monthlyResult)) { 
    $monthlyCounts[(int) $row['m']] = (int) $row['c']; 
}
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$listStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name, 
           u.first_name, u.last_name, u.email
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    $whereSql 
    ORDER BY a.submitted_at DESC
");
$applications = bind_execute($listStmt, $types, $params);
$types_lookup = mysqli_query($conn, "SELECT type_id, type_name FROM application_types ORDER BY type_name");
$queryString = http_build_query($_GET);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/report_period_picker.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Executive Governance</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Portfolio Intelligence &amp; Reporting</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Monitor IP registration throughput, pipeline bottlenecks, and export official audit manifests.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>manager/export_csv.php?<?= $queryString ?>" class="btn btn-outline btn-sm" style="gap:6px;">
            <i data-lucide="file-spreadsheet" style="width:14px;height:14px;color:var(--color-success);"></i> Export Excel (CSV)
        </a>
        <a href="<?= BASE_URL ?>manager/report_print.php?<?= $queryString ?>" class="btn btn-primary btn-sm" target="_blank" style="gap:6px;">
            <i data-lucide="printer" style="width:14px;height:14px;"></i> Print / PDF Manifest
        </a>
    </div>
</div>

<!-- 2. KPI Summary Cards with Sparklines -->
<div class="kpi-grid">
    <!-- Total Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Filings</span>
            <span class="info-icon" title="All intellectual property disclosures"><i data-lucide="layers" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format((int)$summary['total']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mTotGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,28 Q30,12 60,20 T100,6 L100,40 L0,40 Z" fill="url(#mTotGrad)"/>
                <path d="M0,28 Q30,12 60,20 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="bar-chart" style="width:13px;height:13px;"></i> Institutional</span>
            <span>All Categories</span>
        </div>
    </div>

    <!-- In Progress -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>In Screening / Review</span>
            <span class="info-icon" title="Pending staff verification or under evaluation"><i data-lucide="clock" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-warning);"><?= number_format((int)$summary['pending']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mPendGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,25 Q25,35 55,18 T100,10 L100,40 L0,40 Z" fill="url(#mPendGrad)"/>
                <path d="M0,25 Q25,35 55,18 T100,10" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="activity" style="width:13px;height:13px;"></i> Active Pipeline</span>
            <span>In Processing</span>
        </div>
    </div>

    <!-- Ready for Final Sign-Off -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Executive Sign-Off</span>
            <span class="info-icon" title="Recommended for approval by evaluators"><i data-lucide="check-square" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary);"><?= number_format((int)$summary['evaluated']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mEvalGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q35,8 70,22 T100,8 L100,40 L0,40 Z" fill="url(#mEvalGrad)"/>
                <path d="M0,32 Q35,8 70,22 T100,8" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend <?= (int)$summary['evaluated'] > 0 ? 'up' : 'neutral' ?>">
                <i data-lucide="alert-circle" style="width:13px;height:13px;"></i> <?= (int)$summary['evaluated'] > 0 ? 'Requires Signature' : 'No Backlog' ?>
            </span>
            <span>Sign-Off Desk</span>
        </div>
    </div>

    <!-- Approved / Registered -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved / Registered</span>
            <span class="info-icon" title="Granted certificates of approval"><i data-lucide="award" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-success);"><?= number_format((int)$summary['approved']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mAppGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,30 Q30,10 65,18 T100,5 L100,40 L0,40 Z" fill="url(#mAppGrad)"/>
                <path d="M0,30 Q30,10 65,18 T100,5" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="check" style="width:13px;height:13px;"></i> Certified Assets</span>
            <span>Granted</span>
        </div>
    </div>
</div>

<!-- 3. Advanced Filter Card -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="reportFilterForm" style="display:grid;grid-template-columns:1.5fr repeat(4, 1fr) auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;">Search Keywords</label>
                <div style="position:relative;">
                    <i data-lucide="search" style="position:absolute;left:10px;top:10px;width:14px;height:14px;color:var(--color-text-muted);pointer-events:none;"></i>
                    <input type="text" name="q" class="form-control" placeholder="Tracking code, title, applicant…" value="<?= clean($search) ?>" style="padding-left:32px;height:36px;font-size:13px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;">Workflow Status</label>
                <select name="status" class="form-control" style="height:36px;font-size:13px;">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','under_review','evaluated','returned','approved','rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;">IP Classification</label>
                <select name="type_id" class="form-control" style="height:36px;font-size:13px;">
                    <option value="0">All Categories</option>
                    <?php while ($t = mysqli_fetch_assoc($types_lookup)): ?>
                        <option value="<?= $t['type_id'] ?>" <?= $typeId === (int)$t['type_id'] ? 'selected' : '' ?>><?= clean($t['type_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>" style="height:36px;font-size:13px;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:12px;font-weight:600;margin-bottom:4px;">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>" style="height:36px;font-size:13px;">
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:36px;gap:4px;">
                    <i data-lucide="filter" style="width:13px;height:13px;"></i> Apply
                </button>
                <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline btn-sm" style="height:36px;" title="Reset Filters">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- 4. Monthly Trend Chart Card -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
    <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="trending-up" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Monthly Intellectual Property Submissions — <?= $year ?></h3>
        </div>
        <span class="badge badge-secondary" style="font-size:11px;">Annual Velocity</span>
    </div>
    <div class="card-body" style="padding:20px;">
        <canvas id="monthlyChart" height="75"></canvas>
    </div>
</div>

<!-- 5. Matching Applications Data Table -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
    <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="file-check" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Matching Disclosures</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;"><?= mysqli_num_rows($applications) ?> records</span>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="file-x" style="width:24px;height:24px;"></i>
                </div>
                <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No records match your criteria</div>
                <div style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:12px;">Try expanding your date range or clearing specific status filters.</div>
                <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline btn-sm">Clear Filters</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:140px;">Tracking Code</th>
                    <th>Intellectual Property Title</th>
                    <th>Category</th>
                    <th>Applicant / Researcher</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:120px;">Submitted</th>
                    <th style="width:90px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td>
                        <span class="tracking" style="font-weight:600;font-size:12px;"><?= clean($a['tracking_code']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:13.5px;line-height:1.4;margin-bottom:2px;">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary"><?= clean($a['type_name']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:500;font-size:13px;"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
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
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>manager/application_view.php?id=<?= $a['application_id'] ?>" style="gap:4px;">
                            <i data-lucide="eye" style="width:13px;height:13px;"></i> View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('monthlyChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Applications Filed',
                data: <?= json_encode(array_values($monthlyCounts)) ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                hoverBackgroundColor: 'rgba(29, 78, 216, 1)',
                borderRadius: 6,
                maxBarThickness: 36
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.parsed.y + ' Application' + (context.parsed.y === 1 ? '' : 's');
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
