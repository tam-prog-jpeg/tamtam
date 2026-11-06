<?php
/**
 * manager/reports.php
 * -----------------------------------------------------------------
 * Executive Portfolio Intelligence & Reporting for the Manager:
 * Verified office-wide KPIs, 4 interactive Chart.js visualizations
 * (Intake Velocity, IP Category Distribution, Workflow Stage Breakdown,
 * and Executive Decision Throughput), advanced filter controls, and
 * exportable audit manifests.
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

$where = [];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%$search%";
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $where[] = "a.status = ?";
    $types .= 's';
    $params[] = $status;
}
if ($typeId > 0) {
    $where[] = "a.type_id = ?";
    $types .= 'i';
    $params[] = $typeId;
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

// 1. KPI Summary query with filter support
$sumStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) total,
           COALESCE(SUM(a.status IN ('pending', 'under_review')), 0) pending,
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

// 2. Chart.js Dataset A: Monthly Technical Disclosure Submissions (Current Year)
$year = date('Y');
$monthlyCounts = array_fill(1, 12, 0);
$monthlyResult = mysqli_query($conn, "
    SELECT MONTH(submitted_at) m, COUNT(*) c 
    FROM applications 
    WHERE YEAR(submitted_at) = '$year' 
    GROUP BY MONTH(submitted_at)
");
if ($monthlyResult) {
    while ($row = mysqli_fetch_assoc($monthlyResult)) { 
        $monthlyCounts[(int) $row['m']] = (int) $row['c']; 
    }
}
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// 3. Chart.js Dataset B: Filings by IP Category
$categoryCounts = [];
$catQuery = mysqli_query($conn, "
    SELECT t.type_name, COUNT(a.application_id) AS total_count
    FROM application_types t
    LEFT JOIN applications a ON a.type_id = t.type_id
    GROUP BY t.type_id, t.type_name
    ORDER BY t.type_id ASC
");
if ($catQuery) {
    while ($row = mysqli_fetch_assoc($catQuery)) {
        $categoryCounts[$row['type_name']] = (int) $row['total_count'];
    }
}

// 4. Chart.js Dataset C: Workflow Status Distribution
$rawStatusCounts = [];
$statQuery = mysqli_query($conn, "
    SELECT status, COUNT(*) AS c 
    FROM applications 
    GROUP BY status
");
if ($statQuery) {
    while ($row = mysqli_fetch_assoc($statQuery)) {
        $rawStatusCounts[$row['status']] = (int) $row['c'];
    }
}
$statusDistribution = [
    'Approved & Certified'    => $rawStatusCounts['approved'] ?? 0,
    'Awaiting Executive Sign' => $rawStatusCounts['evaluated'] ?? 0,
    'Under Technical Review'  => $rawStatusCounts['under_review'] ?? 0,
    'Pending Verification'    => $rawStatusCounts['pending'] ?? 0,
    'Needs Revision'          => $rawStatusCounts['returned'] ?? 0,
    'Rejected Filings'        => $rawStatusCounts['rejected'] ?? 0,
];

// 5. Chart.js Dataset D: Executive Decision Velocity
$decisionVelocity = [
    'Approved' => $rawStatusCounts['approved'] ?? 0,
    'Returned' => $rawStatusCounts['returned'] ?? 0,
    'Rejected' => $rawStatusCounts['rejected'] ?? 0,
    'Active Pipeline' => ($rawStatusCounts['pending'] ?? 0) + ($rawStatusCounts['under_review'] ?? 0) + ($rawStatusCounts['evaluated'] ?? 0),
];

// 6. Matching Applications Listing
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
mysqli_stmt_close($listStmt);

$types_lookup = mysqli_query($conn, "SELECT type_id, type_name FROM application_types ORDER BY type_name");
$queryString = http_build_query($_GET);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/report_period_picker.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary-600);margin-bottom:2px;">
            Executive Governance &amp; Auditing
        </div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Portfolio Intelligence &amp; Reporting</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">
            Analyze institutional research throughput, monitor pipeline velocity, and audit certified intellectual property records.
        </div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>manager/export_csv.php?<?= $queryString ?>" class="btn btn-outline btn-sm" style="gap:6px;" title="Export filtered records to spreadsheet">
            <i data-lucide="file-spreadsheet" style="width:14px;height:14px;color:var(--color-success);"></i> Export Excel (CSV)
        </a>
        <a href="<?= BASE_URL ?>manager/report_print.php?<?= $queryString ?>" class="btn btn-primary btn-sm" target="_blank" style="gap:6px;" title="Open clean printable audit manifest">
            <i data-lucide="printer" style="width:14px;height:14px;"></i> Print / PDF Manifest
        </a>
    </div>
</div>

<!-- 2. KPI Summary Cards with Sparklines -->
<div class="kpi-grid">
    <!-- Card 1: Total Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Filtered Filings</span>
            <span class="info-icon" title="All intellectual property disclosures matching active filters">
                <i data-lucide="layers" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format((int)$summary['total']) ?></div>
            <?= render_kpi_sparkline((int)$summary['total'], 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-neutral">
                <i data-lucide="database" style="width:13px;height:13px;"></i> Filtered Scope
            </span>
            <span>All Categories</span>
        </div>
    </div>

    <!-- Card 2: In Screening / Technical Review -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>In Screening / Review</span>
            <span class="info-icon" title="Applications currently in staff verification or evaluator examination">
                <i data-lucide="clock" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-warning);"><?= number_format((int)$summary['pending']) ?></div>
            <?= render_kpi_sparkline((int)$summary['pending'], 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-neutral">
                <i data-lucide="activity" style="width:13px;height:13px;"></i> Active Pipeline
            </span>
            <span>Under Review</span>
        </div>
    </div>

    <!-- Card 3: Awaiting Final Sign-Off -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Executive Sign-Off</span>
            <span class="info-icon" title="Applications recommended for approval by evaluators and waiting on the Manager">
                <i data-lucide="signature" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary-600);"><?= number_format((int)$summary['evaluated']) ?></div>
            <?= render_kpi_sparkline((int)$summary['evaluated'], 'purple') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend <?= (int)$summary['evaluated'] > 0 ? 'trend-up' : 'trend-neutral' ?>">
                <i data-lucide="alert-circle" style="width:13px;height:13px;"></i> <?= (int)$summary['evaluated'] > 0 ? 'Requires Sign-Off' : 'No Backlog' ?>
            </span>
            <span>Executive Desk</span>
        </div>
    </div>

    <!-- Card 4: Approved & Certified -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved / Registered</span>
            <span class="info-icon" title="Applications granted final executive approval and registration certification">
                <i data-lucide="award" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-success);"><?= number_format((int)$summary['approved']) ?></div>
            <?= render_kpi_sparkline((int)$summary['approved'], 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-up" style="color:var(--color-success);">
                <i data-lucide="shield-check" style="width:13px;height:13px;"></i> Certified Assets
            </span>
            <span>Registered</span>
        </div>
    </div>
</div>

<!-- 3. Metrics Clarification Notice -->
<div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:22px;display:flex;align-items:center;gap:12px;">
    <div style="width:34px;height:34px;border-radius:50%;background:rgba(37,99,235,0.08);display:flex;align-items:center;justify-content:center;color:var(--color-primary-600);flex-shrink:0;">
        <i data-lucide="info" style="width:17px;height:17px;"></i>
    </div>
    <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;">
        <strong style="color:var(--color-text);">Statistical Distinction:</strong>
        <strong>Technical Disclosures (Intake)</strong> counts the raw initial disclosure submissions filed by faculty, students, and campus inventors per month. 
        <strong>Intellectual Property Filings</strong> classifies those disclosures into official legal categories (Patent, Copyright, Trademark, Utility Model, etc.) based on verified statutory claims.
    </div>
</div>

<!-- 4. Advanced Filter Card -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:22px;">
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

<!-- =====================================================================
     5. CHART.JS VISUALIZATION SUITE (2x2 Balanced Grid)
     ===================================================================== -->
<!-- Row 1: Monthly Intake Trend + Category Distribution -->
<div class="grid-2">
    <!-- Chart 1: Monthly Technical Disclosure Submissions -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="trending-up" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Monthly Disclosure Submissions (<?= $year ?>)</h3>
            </div>
            <span class="badge badge-secondary" style="font-size:11px;">Intake Velocity</span>
        </div>
        <div class="card-body" style="padding:20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="monthlyIntakeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Filings by IP Category -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="bar-chart-2" style="width:16px;height:16px;color:#8B5CF6;"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Filings by IP Category</h3>
            </div>
            <span class="badge badge-secondary" style="font-size:11px;">Category Distribution</span>
        </div>
        <div class="card-body" style="padding:20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="categoryDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Workflow Status Distribution + Decision Velocity -->
<div class="grid-2">
    <!-- Chart 3: Workflow Status Distribution -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="pie-chart" style="width:16px;height:16px;color:var(--color-success);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Application Status Distribution</h3>
            </div>
            <span class="badge badge-secondary" style="font-size:11px;">Portfolio Health</span>
        </div>
        <div class="card-body" style="padding:20px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
            <div style="position:relative;height:200px;width:100%;max-width:260px;">
                <canvas id="statusDistributionChart"></canvas>
            </div>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px 12px;justify-content:center;font-size:11px;color:var(--color-text-muted);">
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#10B981;"></span> Approved (<?= $statusDistribution['Approved & Certified'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#3B82F6;"></span> Awaiting (<?= $statusDistribution['Awaiting Executive Sign'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#8B5CF6;"></span> Under Review (<?= $statusDistribution['Under Technical Review'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F59E0B;"></span> Pending (<?= $statusDistribution['Pending Verification'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F97316;"></span> Revision (<?= $statusDistribution['Needs Revision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#EF4444;"></span> Rejected (<?= $statusDistribution['Rejected Filings'] ?>)</span>
            </div>
        </div>
    </div>

    <!-- Chart 4: Executive Decision Velocity -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="check-check" style="width:16px;height:16px;color:#06B6D4;"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Executive Decision Throughput</h3>
            </div>
            <span class="badge badge-secondary" style="font-size:11px;">Resolution Metrics</span>
        </div>
        <div class="card-body" style="padding:20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="decisionVelocityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- 6. Matching Applications Data Table -->
<div class="staff-table-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="file-check" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:700;">Matching IP Filings &amp; Manifests</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= mysqli_num_rows($applications) ?> records</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Displaying live records conforming to active search and classification filters.
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
                <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline btn-sm">Clear Active Filters</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:160px;">Tracking Code</th>
                    <th>Application Title</th>
                    <th style="width:140px;">IP Category</th>
                    <th>Applicant Name</th>
                    <th style="width:150px;">Workflow Status</th>
                    <th style="width:140px;">Date Filed</th>
                    <th style="width:100px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td class="tracking">
                        <a href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$app['application_id'] ?>" style="color:var(--color-primary-600);font-weight:700;text-decoration:none;">
                            <?= clean($app['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);line-height:1.35;max-width:320px;">
                            <?= clean($app['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-weight:600;">
                            <?= clean($app['type_name']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:13px;">
                            <?= clean($app['first_name'] . ' ' . $app['last_name']) ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--color-text-muted);">
                            <?= clean($app['email']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($app['status']) ?>">
                            <?= status_label($app['status']) ?>
                        </span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text);">
                        <?= format_date($app['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <a href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$app['application_id'] ?>" class="btn btn-outline btn-sm" style="gap:4px;padding:4px 10px;font-size:12px;">
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

<!-- =====================================================================
     CHART.JS SCRIPTS & CONFIGURATIONS
     ===================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? '#1F2937' : '#EAECF0';
    var textColor = isDark ? '#94A3B8' : '#64748B';

    // 1. Monthly Intake Trend Chart (Line/Area)
    var intakeCanvas = document.getElementById('monthlyIntakeChart');
    if (intakeCanvas) {
        new Chart(intakeCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Disclosures Filed',
                    data: <?= json_encode(array_values($monthlyCounts)) ?>,
                    borderColor: '#2563EB',
                    backgroundColor: isDark ? 'rgba(37,99,235,0.18)' : 'rgba(37,99,235,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563EB',
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor, stepSize: 1, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Category Distribution Bar Chart
    var catCanvas = document.getElementById('categoryDistributionChart');
    if (catCanvas) {
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($categoryCounts)) ?>,
                datasets: [{
                    label: 'Submissions',
                    data: <?= json_encode(array_values($categoryCounts)) ?>,
                    backgroundColor: ['#2563EB', '#8B5CF6', '#06B6D4', '#10B981', '#F59E0B', '#64748B'],
                    borderRadius: 6,
                    maxBarThickness: 38
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor, stepSize: 1, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    }
                }
            }
        });
    }

    // 3. Status Distribution Doughnut Chart
    var statusCanvas = document.getElementById('statusDistributionChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($statusDistribution)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusDistribution)) ?>,
                    backgroundColor: ['#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#F97316', '#EF4444'],
                    borderWidth: 2,
                    borderColor: isDark ? '#111827' : '#FFFFFF',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700' }
                    }
                }
            }
        });
    }

    // 4. Executive Decision Velocity Bar Chart
    var decCanvas = document.getElementById('decisionVelocityChart');
    if (decCanvas) {
        new Chart(decCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($decisionVelocity)) ?>,
                datasets: [{
                    label: 'Count',
                    data: <?= json_encode(array_values($decisionVelocity)) ?>,
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#3B82F6'],
                    borderRadius: 6,
                    maxBarThickness: 44
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor, stepSize: 1, font: { family: 'Plus Jakarta Sans', size: 11 } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
