<?php
/**
 * manager/dashboard.php
 * -----------------------------------------------------------------
 * Executive Manager Landing & Portfolio Governance:
 * Office-wide KPIs, Chart.js analytics for category and review status,
 * and high-priority queue of evaluated applications awaiting sign-off.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

// Executive Core KPIs
$awaiting = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'evaluated'"))['c'] ?? 0);
$approved = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'approved'"))['c'] ?? 0);
$rejected = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'rejected'"))['c'] ?? 0);
$total    = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications"))['c'] ?? 0);

// Category breakdown for Chart.js Bar Chart
$categories = [];
$catRes = mysqli_query($conn, "
    SELECT t.type_name, COUNT(a.application_id) AS total_count
    FROM application_types t
    LEFT JOIN applications a ON a.type_id = t.type_id
    GROUP BY t.type_id, t.type_name
    ORDER BY t.type_id ASC
");
if ($catRes) {
    while ($row = mysqli_fetch_assoc($catRes)) {
        $categories[$row['type_name']] = (int) $row['total_count'];
    }
}

// Executive status breakdown for Chart.js Doughnut
$underReview = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'under_review'"))['c'] ?? 0);
$pendingIntake = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'pending'"))['c'] ?? 0);
$needsRevision = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'returned'"))['c'] ?? 0);

$statusBreakdown = [
    'Approved'          => $approved,
    'Awaiting Sign-Off' => $awaiting,
    'Under Review'      => $underReview,
    'Pending Intake'    => $pendingIntake,
    'Needs Revision'    => $needsRevision,
    'Rejected'          => $rejected,
];

// Evaluated queue waiting on final Manager sign-off
$queue = mysqli_query($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.submitted_at, t.type_name,
           u.first_name, u.last_name,
           ev.decision, ev.comments, ev.evaluated_at, eu.first_name AS eval_first, eu.last_name AS eval_last
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN users eu ON eu.user_id = a.assigned_evaluator_id
    LEFT JOIN evaluations ev ON ev.application_id = a.application_id
    WHERE a.status = 'evaluated'
    ORDER BY a.submitted_at ASC
    LIMIT 10
");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     1. SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary-600);margin-bottom:2px;">
            Executive Governance
        </div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Executive Sign-Off &amp; Portfolio Governance</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">
            Review technical evaluations, issue executive sign-offs, and monitor institutional IP productivity.
        </div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>manager/approved_applications.php" class="btn btn-outline btn-sm" style="gap:6px;" title="View all applications approved by the Manager">
            <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--color-success);"></i> Approved Registry
        </a>
        <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-primary btn-sm" style="gap:6px;" title="View IPO Statistical Reports">
            <i data-lucide="bar-chart-3" style="width:14px;height:14px;"></i> Analytics &amp; Reports
        </a>
    </div>
</div>

<!-- =====================================================================
     2. TOP KPI CARDS (Deli-Prop Exact Match with Interactive Links)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Awaiting Final Approval -->
    <a href="#signoffQueue" class="kpi-card" title="Click to view queue of applications awaiting sign-off">
        <div class="kpi-card-header">
            <span>Awaiting Final Approval</span>
            <span class="info-icon" title="Evaluated applications recommended by examiners awaiting your executive decision">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-warning);"><?= number_format($awaiting) ?></div>
            <?= render_kpi_sparkline($awaiting, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Executive sign-off pending</span>
        </div>
    </a>

    <!-- Card 2: Approved Filings -->
    <a href="<?= BASE_URL ?>manager/approved_applications.php" class="kpi-card" title="Click to view all Manager-approved applications">
        <div class="kpi-card-header">
            <span>Approved Filings</span>
            <span class="info-icon" title="Applications officially approved and registered by executive sign-off">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-success);"><?= number_format($approved) ?></div>
            <?= render_kpi_sparkline($approved, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Approved &amp; certified</span>
        </div>
    </a>

    <!-- Card 3: Rejected Applications -->
    <a href="<?= BASE_URL ?>manager/reports.php?status=rejected" class="kpi-card" title="Click to view rejected applications in reports">
        <div class="kpi-card-header">
            <span>Rejected Applications</span>
            <span class="info-icon" title="Applications denied registration or failing institutional criteria">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-danger);"><?= number_format($rejected) ?></div>
            <?= render_kpi_sparkline($rejected, 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="x-circle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Closed filings</span>
        </div>
    </a>

    <!-- Card 4: Total Filings -->
    <a href="<?= BASE_URL ?>manager/reports.php" class="kpi-card" title="Click to view full portfolio reports">
        <div class="kpi-card-header">
            <span>Total IP Filings</span>
            <span class="info-icon" title="Cumulative intellectual property applications recorded in ZPPSU IPO">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($total) ?></div>
            <?= render_kpi_sparkline($total, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="file-text" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>University portfolio size</span>
        </div>
    </a>
</div>

<!-- =====================================================================
     3. ANALYTICS SECTION: 2-COLUMN CHART.JS VISUALIZATIONS
     ===================================================================== -->
<div class="grid-2">
    <!-- Chart 1: Category Distribution Bar Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">Portfolio by IP Category</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Active registrations and pending filings across university classifications</span>
            </div>
            <span class="badge badge-secondary" style="font-size:11px;"><?= count($categories) ?> Categories</span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="managerCategoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Status Breakdown Doughnut Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">Executive Review Pipeline</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Stage breakdown of all institutional intellectual property filings</span>
            </div>
            <span class="badge badge-info" style="font-size:11px;"><?= number_format($total) ?> Total</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px 20px;">
            <div style="position:relative;height:200px;width:100%;max-width:260px;">
                <canvas id="managerStatusDoughnut"></canvas>
            </div>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px 12px;justify-content:center;font-size:11.5px;color:var(--color-text-muted);">
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#10B981;"></span> Approved (<?= $statusBreakdown['Approved'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F59E0B;"></span> Awaiting Sign-Off (<?= $statusBreakdown['Awaiting Sign-Off'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#2563EB;"></span> Under Review (<?= $statusBreakdown['Under Review'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#6B7280;"></span> Pending (<?= $statusBreakdown['Pending Intake'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F97316;"></span> Revision (<?= $statusBreakdown['Needs Revision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#EF4444;"></span> Rejected (<?= $statusBreakdown['Rejected'] ?>)</span>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     4. PRIORITY QUEUE: AWAITING EXECUTIVE SIGN-OFF
     ===================================================================== -->
<div class="staff-table-card" id="signoffQueue">
    <div class="card-header">
        <div>
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="signature" style="width:17px;height:17px;color:var(--color-warning);"></i>
                <h3 style="margin:0;font-size:15px;font-weight:700;">Awaiting Executive Sign-Off</h3>
                <span class="badge badge-warning tabular" style="font-size:11px;font-weight:700;"><?= $awaiting ?> pending</span>
            </div>
            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;">
                Applications verified and endorsed by technical evaluators awaiting your final executive sign-off or return for revision.
            </div>
        </div>
        <div class="action-controls-group">
            <a href="<?= BASE_URL ?>manager/approved_applications.php" class="btn btn-outline btn-sm" style="gap:4px;">
                <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--color-success);"></i> Approved Registry
            </a>
            <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline btn-sm" style="gap:4px;">
                <i data-lucide="bar-chart-3" style="width:14px;height:14px;"></i> View Reports
            </a>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($queue) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-success);">
                    <i data-lucide="check-circle-2" style="width:24px;height:24px;"></i>
                </div>
                <h4 style="margin:0 0 4px;font-size:15px;color:var(--color-text);">No Filings Awaiting Sign-Off</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">
                    All evaluated intellectual property applications have received executive decisions.
                </p>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:160px;">Tracking Code</th>
                    <th>Application Title</th>
                    <th style="width:130px;">IP Category</th>
                    <th>Applicant Name</th>
                    <th>Technical Examiner</th>
                    <th style="width:140px;">Date Filed</th>
                    <th style="width:140px;text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($queue)): ?>
                <tr>
                    <td class="tracking">
                        <a href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$a['application_id'] ?>" style="color:var(--color-primary-600);font-weight:700;text-decoration:none;">
                            <?= clean($a['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);line-height:1.35;max-width:320px;">
                            <?= clean($a['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-weight:600;">
                            <?= clean($a['type_name']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:13px;">
                            <?= clean($a['first_name'] . ' ' . $a['last_name']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:500;color:var(--color-text);font-size:13px;">
                            <?= $a['eval_first'] ? clean($a['eval_first'] . ' ' . $a['eval_last']) : '<span style="color:var(--color-text-muted);">—</span>' ?>
                        </div>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$a['application_id'] ?>" style="gap:4px;padding:4px 10px;font-size:12px;" title="Review Findings & Issue Executive Sign-Off">
                            <i data-lucide="signature" style="width:13px;height:13px;"></i> Sign-Off
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
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? '#1F2937' : '#EAECF0';
    var textColor = isDark ? '#94A3B8' : '#64748B';

    // 1. IP Category Bar Chart
    const catCanvas = document.getElementById('managerCategoryChart');
    if (catCanvas) {
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($categories)) ?>,
                datasets: [{
                    label: 'Filings',
                    data: <?= json_encode(array_values($categories)) ?>,
                    backgroundColor: ['#2563EB', '#8B5CF6', '#06B6D4', '#10B981', '#F59E0B', '#64748B'],
                    borderRadius: 6,
                    maxBarThickness: 34
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
                        ticks: { font: { family: 'Inter', size: 11 }, color: textColor }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { precision: 0, font: { family: 'Inter', size: 11 }, color: textColor }
                    }
                }
            }
        });
    }

    // 2. Executive Pipeline Doughnut Chart
    const pipeCanvas = document.getElementById('managerStatusDoughnut');
    if (pipeCanvas) {
        new Chart(pipeCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($statusBreakdown)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusBreakdown)) ?>,
                    backgroundColor: ['#10B981', '#F59E0B', '#2563EB', '#6B7280', '#F97316', '#EF4444'],
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
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 12 },
                        bodyFont: { family: 'Inter', size: 12 },
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' filings';
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
