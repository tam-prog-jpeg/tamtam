<?php
/**
 * evaluator/dashboard.php
 * -----------------------------------------------------------------
 * Evaluator landing page: how many applications are waiting for
 * their evaluation, plus how many they've approved/rejected/returned
 * historically, and a queue of what's pending right now.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Evaluator']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

$uid = (int) $_SESSION['user_id'];

$pending  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_evaluator_id = $uid AND status = 'under_review'"))['c'];
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM evaluations WHERE evaluator_id = $uid AND decision = 'approved'"))['c'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM evaluations WHERE evaluator_id = $uid AND decision = 'rejected'"))['c'];
$returned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM evaluations WHERE evaluator_id = $uid AND decision = 'returned'"))['c'];

// Category workload breakdown for Chart.js Bar Chart
$evalCategories = [];
$ecRes = mysqli_query($conn, "
    SELECT t.type_name, COUNT(a.application_id) AS total_count
    FROM application_types t
    LEFT JOIN applications a ON a.type_id = t.type_id AND a.assigned_evaluator_id = $uid
    GROUP BY t.type_id, t.type_name
    ORDER BY t.type_id ASC
");
if ($ecRes) {
    while ($row = mysqli_fetch_assoc($ecRes)) {
        $evalCategories[$row['type_name']] = (int) $row['total_count'];
    }
}

// Decision history breakdown for Chart.js Doughnut
$evalDecisions = [
    'Recommended Approval'  => (int) $approved,
    'Awaiting My Decision'  => (int) $pending,
    'Returned for Revision' => (int) $returned,
    'Recommended Rejection' => (int) $rejected,
];

$queueStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.submitted_at, t.type_name, u.first_name, u.last_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    WHERE a.assigned_evaluator_id = ? AND a.status = 'under_review'
    ORDER BY a.submitted_at ASC
    LIMIT 8
");
mysqli_stmt_bind_param($queueStmt, 'i', $uid);
mysqli_stmt_execute($queueStmt);
$queue = mysqli_stmt_get_result($queueStmt);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">IP Technical Evaluation Workspace</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Perform technical examination, evaluate claims, and recommend IP applications for final sign-off.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>evaluator/evaluations.php" class="btn btn-primary" title="View Assigned Applications">
            <i data-lucide="clipboard-check" style="width:16px;height:16px;"></i>
            My Evaluations (<?= (int)$pending ?>)
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Awaiting Evaluation -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Evaluation</span>
            <span class="info-icon" title="Applications currently assigned to you awaiting your evaluation decision">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($pending) ?></div>
            <?= render_kpi_sparkline($pending, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Action required</span>
        </div>
    </div>

    <!-- Card 2: Recommended Approval -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Recommended Approval</span>
            <span class="info-icon" title="Applications you evaluated and recommended for approval">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approved) ?></div>
            <?= render_kpi_sparkline($approved, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Forwarded to manager</span>
        </div>
    </div>

    <!-- Card 3: Rejected -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Rejected Applications</span>
            <span class="info-icon" title="Applications you evaluated that did not meet IP criteria">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($rejected) ?></div>
            <?= render_kpi_sparkline($rejected, 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="x-circle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Negative evaluations</span>
        </div>
    </div>

    <!-- Card 4: Returned for Revision -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned for Revision</span>
            <span class="info-icon" title="Applications returned for applicant technical revisions">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($returned) ?></div>
            <?= render_kpi_sparkline($returned, 'purple') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
            <span>Revisions requested</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     ANALYTICS SECTION: 2-COLUMN CHART.JS VISUALIZATIONS
     ===================================================================== -->
<div class="grid-2" style="margin-bottom:24px;">
    <!-- Chart 1: Evaluation Decision History Doughnut Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">My Examination Outcomes</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Historical breakdown of technical recommendations and pending assignments</span>
            </div>
            <span class="badge badge-success" style="font-size:11px;"><?= $approved + $returned + $rejected + $pending ?> Total Handled</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px 20px;">
            <div style="position:relative;height:200px;width:100%;max-width:260px;">
                <canvas id="evalDecisionDoughnut"></canvas>
            </div>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px 12px;justify-content:center;font-size:11.5px;color:var(--color-text-muted);">
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#10B981;"></span> Recommended (<?= $evalDecisions['Recommended Approval'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F59E0B;"></span> Awaiting Action (<?= $evalDecisions['Awaiting My Decision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F97316;"></span> Revisions (<?= $evalDecisions['Returned for Revision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#EF4444;"></span> Denied (<?= $evalDecisions['Recommended Rejection'] ?>)</span>
            </div>
        </div>
    </div>

    <!-- Chart 2: Category Workload Bar Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">Assigned Applications by Category</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Filing categories assigned to your examiner profile</span>
            </div>
            <span class="badge badge-info" style="font-size:11px;"><?= array_sum($evalCategories) ?> Filings</span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="evalCategoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Evaluation Queue</h3>
        <a href="<?= BASE_URL ?>evaluator/evaluations.php" class="btn btn-outline btn-sm">
            View All
            <i data-lucide="arrow-right" style="width:13px;height:13px;"></i>
        </a>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($queue) === 0): ?>
            <div class="empty-state">
                <i data-lucide="check-circle-2" style="width:36px;height:36px;color:var(--color-success);"></i>
                <p>Nothing waiting for your evaluation right now. You are all caught up!</p>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Applicant</th>
                    <th>Submitted</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($queue)): ?>
                <tr>
                    <td class="tracking"><?= clean($a['tracking_code']) ?></td>
                    <td><strong style="color:var(--color-text);"><?= clean($a['title']) ?></strong></td>
                    <td><span class="badge badge-info"><?= clean($a['type_name']) ?></span></td>
                    <td><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);"><?= format_date($a['submitted_at'], 'M d, Y') ?></td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>evaluator/evaluation_view.php?id=<?= $a['application_id'] ?>" title="Evaluate Application">
                            <i data-lucide="file-check" style="width:13px;height:13px;"></i>
                            Evaluate
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Decision Outcomes Doughnut Chart
    const decCanvas = document.getElementById('evalDecisionDoughnut');
    if (decCanvas) {
        new Chart(decCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($evalDecisions)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($evalDecisions)) ?>,
                    backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF',
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
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' applications';
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Category Workload Bar Chart
    const catCanvas = document.getElementById('evalCategoryChart');
    if (catCanvas) {
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($evalCategories)) ?>,
                datasets: [{
                    label: 'Assigned Filings',
                    data: <?= json_encode(array_values($evalCategories)) ?>,
                    backgroundColor: '#2563EB',
                    borderRadius: 6,
                    maxBarThickness: 32
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
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' filings';
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
