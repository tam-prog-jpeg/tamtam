<?php
/**
 * staff/dashboard.php
 * -----------------------------------------------------------------
 * Staff Work Management Dashboard:
 *  - Clear hierarchy: Actionable work queue at the forefront
 *  - 4 interactive KPI navigation cards (Pending, Assigned, Evaluator, Revision)
 *  - Prioritized "Applications Requiring Action" table with direct Review CTA
 *  - Compact side-by-side Chart.js visual analytics (Monthly Intake & Status Distribution)
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Staff Dashboard';
$currentPage = 'dashboard';

$staffUserId = (int) $_SESSION['user_id'];

// ---- 1. Accurate, Non-Overlapping KPI Metric Counts ----
// Card 1: Pending Verification (All unreviewed intake filings)
$pendingCount = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'pending'"))['c'];

// Card 2: My Assigned Applications (Filings assigned to the current staff member)
$myAssignedCount = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_staff_id = $staffUserId AND status IN ('pending', 'under_review', 'returned')"))['c'];

// Card 3: With Evaluators (Applications forwarded and currently undergoing evaluator assessment)
$withEvaluatorsCount = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'under_review'"))['c'];

// Card 4: Needs Applicant Updates (Applications returned to applicants for document revisions)
$needsRevisionCount = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'returned'"))['c'];

// Total items needing staff action (Pending + Returned awaiting rescreening)
$actionRequiredCount = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status IN ('pending', 'returned')"))['c'];

// ---- 2. Primary Work Queue: Applications Requiring Action ----
$actionQueueSql = "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, a.custom_category, t.type_name,
           u.first_name, u.last_name, u.email
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    WHERE a.status IN ('pending', 'returned')
    ORDER BY 
        CASE WHEN a.status = 'pending' THEN 1 ELSE 2 END,
        a.submitted_at ASC
    LIMIT 8
";
$actionQueue = mysqli_query($conn, $actionQueueSql);

// ---- 3. Monthly Intake Activity for Chart.js ----
$year = date('Y');
$monthlyIntake = array_fill(1, 12, 0);
$miRes = mysqli_query($conn, "SELECT MONTH(submitted_at) AS m, COUNT(*) AS c FROM applications WHERE YEAR(submitted_at) = $year GROUP BY MONTH(submitted_at)");
if ($miRes) {
    while ($r = mysqli_fetch_assoc($miRes)) {
        $monthlyIntake[(int)$r['m']] = (int)$r['c'];
    }
}
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// ---- 4. Complete Application Status Distribution for Doughnut ----
$statusCounts = [
    'Pending Verification'     => 0,
    'With Evaluators'          => 0,
    'Needs Revision'           => 0,
    'Recommended by Evaluator' => 0,
    'Approved & Registered'    => 0,
    'Denied / Rejected'        => 0,
];
$statusDistRes = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM applications GROUP BY status");
if ($statusDistRes) {
    while ($row = mysqli_fetch_assoc($statusDistRes)) {
        $st = $row['status'];
        $c = (int) $row['c'];
        if ($st === 'pending') $statusCounts['Pending Verification'] += $c;
        elseif ($st === 'under_review') $statusCounts['With Evaluators'] += $c;
        elseif ($st === 'returned') $statusCounts['Needs Revision'] += $c;
        elseif ($st === 'evaluated') $statusCounts['Recommended by Evaluator'] += $c;
        elseif ($st === 'approved') $statusCounts['Approved & Registered'] += $c;
        elseif ($st === 'rejected') $statusCounts['Denied / Rejected'] += $c;
    }
}
$totalApplicationsCount = array_sum($statusCounts);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     1. SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Staff Dashboard</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">
            Welcome back, <?= clean($_SESSION['first_name'] ?? 'Staff') ?>! Review incoming submissions, track active evaluations, and process applicant filings.
        </div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-primary btn-sm" title="Open Complete Application Review Queue">
            <i data-lucide="clipboard-check" style="width:15px;height:15px;"></i>
            Application Review Queue (<?= number_format($actionRequiredCount) ?>)
        </a>
    </div>
</div>

<!-- =====================================================================
     2. 4 INTERACTIVE KPI NAVIGATION CARDS
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Pending Verification -->
    <a href="<?= BASE_URL ?>staff/verify.php?status=pending" class="kpi-card" title="Click to view all applications awaiting initial staff review">
        <div class="kpi-card-header">
            <span>Pending Verification</span>
            <span class="info-icon" title="Applications awaiting initial staff review"><i data-lucide="clock" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-warning);"><?= number_format($pendingCount) ?></div>
            <?= render_kpi_sparkline($pendingCount, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-circle" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Awaiting initial staff review</span>
        </div>
    </a>

    <!-- Card 2: My Assigned Applications -->
    <a href="<?= BASE_URL ?>staff/verify.php?assigned=me" class="kpi-card" title="Click to view applications currently assigned to your staff profile">
        <div class="kpi-card-header">
            <span>My Assigned Applications</span>
            <span class="info-icon" title="Applications currently assigned to your staff profile"><i data-lucide="user-check" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary-600);"><?= number_format($myAssignedCount) ?></div>
            <?= render_kpi_sparkline($myAssignedCount, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="user" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Assigned to your desk</span>
        </div>
    </a>

    <!-- Card 3: With Evaluators -->
    <a href="<?= BASE_URL ?>staff/verify.php?status=under_review" class="kpi-card" title="Click to view applications forwarded for evaluator assessment">
        <div class="kpi-card-header">
            <span>With Evaluators</span>
            <span class="info-icon" title="Applications forwarded for evaluator assessment"><i data-lucide="flask-conical" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:#8B5CF6;"><?= number_format($withEvaluatorsCount) ?></div>
            <?= render_kpi_sparkline($withEvaluatorsCount, 'purple') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Forwarded for assessment</span>
        </div>
    </a>

    <!-- Card 4: Needs Applicant Updates -->
    <a href="<?= BASE_URL ?>staff/verify.php?status=returned" class="kpi-card" title="Click to view applications returned to applicants for revision">
        <div class="kpi-card-header">
            <span>Needs Applicant Updates</span>
            <span class="info-icon" title="Applications returned to applicants for revision"><i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-danger);"><?= number_format($needsRevisionCount) ?></div>
            <?= render_kpi_sparkline($needsRevisionCount, 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-triangle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Awaiting applicant revisions</span>
        </div>
    </a>
</div>

<!-- =====================================================================
     3. PRIMARY FOCUS: APPLICATIONS REQUIRING ACTION
     ===================================================================== -->
<div class="staff-table-card" style="margin-bottom:24px;">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="alert-circle" style="width:18px;height:18px;color:var(--color-warning);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:700;color:var(--color-text);">Applications Requiring Action</h3>
            <span class="badge badge-warning tabular" style="font-size:11px;font-weight:700;"><?= number_format($actionRequiredCount) ?> Active Tasks</span>
        </div>
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm" style="gap:5px;display:inline-flex;align-items:center;">
            <span>View All Applications</span>
            <i data-lucide="arrow-right" style="width:13px;height:13px;"></i>
        </a>
    </div>

    <div class="table-wrap">
        <?php if (mysqli_num_rows($actionQueue) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <i data-lucide="check-circle-2" style="width:40px;height:40px;color:var(--color-success);margin-bottom:8px;"></i>
                <h4 style="margin:0 0 4px;font-size:15px;font-weight:700;color:var(--color-text);">All Caught Up!</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">All submitted applications have been screened. No pending tasks require staff action.</p>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:160px;">Tracking Code</th>
                    <th>Application Title</th>
                    <th style="width:140px;">Category</th>
                    <th>Applicant</th>
                    <th style="width:130px;">Submitted</th>
                    <th style="width:170px;">Status</th>
                    <th style="width:160px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($actionQueue)): ?>
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
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>">
                            <span class="status-dot"></span>
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $a['application_id'] ?>" style="gap:5px;height:32px;display:inline-flex;align-items:center;" title="Review Application">
                            <i data-lucide="clipboard-check" style="width:13px;height:13px;"></i>
                            Review Application
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
     4. SECONDARY ANALYTICS: COMPACT 2-COLUMN CHART.JS VISUALIZATIONS
     ===================================================================== -->
<div class="dashboard-grid-2" style="margin-bottom:24px;">
    <!-- Chart 1: Monthly Application Activity -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">Monthly Application Activity (<?= $year ?>)</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Application submissions logged by month</span>
            </div>
            <span class="badge badge-primary tabular" style="font-size:11px;"><?= number_format(array_sum($monthlyIntake)) ?> Submissions</span>
        </div>
        <div class="card-body" style="padding:14px 18px;">
            <div style="position:relative;height:190px;width:100%;">
                <canvas id="staffIntakeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Application Status Distribution -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">Application Status Distribution</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Breakdown across all workflow stages</span>
            </div>
            <span class="badge badge-info tabular" style="font-size:11px;"><?= number_format($totalApplicationsCount) ?> Total</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px 18px;">
            <div style="position:relative;height:160px;width:100%;max-width:220px;">
                <canvas id="staffQueueDoughnut"></canvas>
            </div>
            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px 10px;justify-content:center;font-size:11px;color:var(--color-text-muted);">
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:7px;height:7px;border-radius:50%;background:#F59E0B;"></span> Pending (<?= $statusCounts['Pending Verification'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:7px;height:7px;border-radius:50%;background:#8B5CF6;"></span> Evaluator (<?= $statusCounts['With Evaluators'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:7px;height:7px;border-radius:50%;background:#EF4444;"></span> Revision (<?= $statusCounts['Needs Revision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:7px;height:7px;border-radius:50%;background:#10B981;"></span> Approved (<?= $statusCounts['Approved & Registered'] ?>)</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Monthly Application Activity Bar Chart
    const intakeCanvas = document.getElementById('staffIntakeChart');
    if (intakeCanvas) {
        new Chart(intakeCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Submissions',
                    data: <?= json_encode(array_values($monthlyIntake)) ?>,
                    backgroundColor: '#2563EB',
                    borderRadius: 5,
                    maxBarThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#EAECF0',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 12 },
                        bodyFont: { family: 'Inter', size: 12 },
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' applications';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#6B7280' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F2F4F7' },
                        ticks: { precision: 0, font: { family: 'Inter', size: 11 }, color: '#6B7280' }
                    }
                }
            }
        });
    }

    // 2. Application Status Distribution Doughnut Chart
    const queueCanvas = document.getElementById('staffQueueDoughnut');
    if (queueCanvas) {
        new Chart(queueCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($statusCounts)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusCounts)) ?>,
                    backgroundColor: ['#F59E0B', '#8B5CF6', '#EF4444', '#2563EB', '#10B981', '#64748B'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#EAECF0',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 12 },
                        bodyFont: { family: 'Inter', size: 12 },
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' files';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>