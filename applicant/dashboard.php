<?php
/**
 * applicant/dashboard.php
 * -----------------------------------------------------------------
 * Applicant landing page: personal KPI cards (their own applications
 * only), recent applications, latest announcements, and a quick link
 * to submit a new application.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

// ---- Resolve the applicant_id for the logged-in user ----
$stmt = mysqli_prepare($conn, "SELECT applicant_id FROM applicants WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$applicantRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$applicantId = $applicantRow['applicant_id'] ?? 0;

// ---- KPI counts (scoped to this applicant only) ----
function count_my($conn, $applicantId, $extraWhere = '')
{
    $sql = "SELECT COUNT(*) c FROM applications WHERE applicant_id = ? $extraWhere";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $applicantId);
    mysqli_stmt_execute($stmt);
    $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0;
    mysqli_stmt_close($stmt);
    return (int) $c;
}
$total    = count_my($conn, $applicantId);
$pending  = count_my($conn, $applicantId, "AND status IN ('pending','under_review')");
$approved = count_my($conn, $applicantId, "AND status = 'approved'");
$rejected = count_my($conn, $applicantId, "AND status = 'rejected'");
$returned = count_my($conn, $applicantId, "AND status = 'returned'");

// Status breakdown for Chart.js Doughnut
$myStatusBreakdown = [
    'Approved & Certified'    => (int) $approved,
    'Under Technical Review'  => (int) count_my($conn, $applicantId, "AND status = 'under_review'"),
    'Pending Intake Review'   => (int) count_my($conn, $applicantId, "AND status = 'pending'"),
    'Needs Revision'          => (int) $returned,
    'Rejected'                => (int) $rejected,
];

// Category breakdown for Chart.js Bar Chart
$myCategories = [];
$myCatRes = mysqli_query($conn, "
    SELECT t.type_name, COUNT(a.application_id) AS total_count
    FROM application_types t
    LEFT JOIN applications a ON a.type_id = t.type_id AND a.applicant_id = $applicantId
    GROUP BY t.type_id, t.type_name
    ORDER BY t.type_id ASC
");
if ($myCatRes) {
    while ($row = mysqli_fetch_assoc($myCatRes)) {
        $myCategories[$row['type_name']] = (int) $row['total_count'];
    }
}

// ---- Recent applications ----
$recentStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name
    FROM applications a JOIN application_types t ON t.type_id = a.type_id
    WHERE a.applicant_id = ?
    ORDER BY a.submitted_at DESC LIMIT 5
");
mysqli_stmt_bind_param($recentStmt, 'i', $applicantId);
mysqli_stmt_execute($recentStmt);
$recentApps = mysqli_stmt_get_result($recentStmt);

// ---- Latest announcements ----
$announcements = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 ORDER BY posted_at DESC LIMIT 3");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Welcome, <?= clean($_SESSION['first_name']) ?>!</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Manage and monitor your Intellectual Property filings in real-time.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline" title="View Full Application History">
            <i data-lucide="file-text" style="width:16px;height:16px;"></i>
            My Applications
        </a>
        <a href="<?= BASE_URL ?>applicant/submit_application.php" class="btn btn-primary" title="File a new IP Application">
            <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
            Submit Application
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Total Applications -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>My Applications</span>
            <span class="info-icon" title="All intellectual property applications filed under your account">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($total) ?></div>
            <?= render_kpi_sparkline($total, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="folder-check" style="width:13px;height:13px;"></i>
            <span>Total registered filings</span>
        </div>
    </div>

    <!-- Card 2: Pending Review -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Pending Review</span>
            <span class="info-icon" title="Applications currently undergoing staff screening or evaluation">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($pending) ?></div>
            <?= render_kpi_sparkline($pending, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Under office processing</span>
        </div>
    </div>

    <!-- Card 3: Approved -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved Applications</span>
            <span class="info-icon" title="Applications granted final executive approval and recorded in IP registry">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approved) ?></div>
            <?= render_kpi_sparkline($approved, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-circle" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Registration Approved</span>
        </div>
    </div>

    <!-- Card 4: Returned / Rejected -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned / Rejected</span>
            <span class="info-icon" title="Applications needing revisions or marked rejected">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($rejected) ?></div>
            <?= render_kpi_sparkline($rejected, 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-triangle" style="width:13px;height:13px;"></i>
            <span>Check remarks if returned</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     ANALYTICS SECTION: 2-COLUMN CHART.JS VISUALIZATIONS
     ===================================================================== -->
<div class="grid-2" style="margin-bottom:24px;">
    <!-- Chart 1: My Application Status Breakdown Doughnut Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">My Application Status Overview</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Current progress of all filings registered under your account</span>
            </div>
            <span class="badge badge-primary" style="font-size:11px;"><?= $total ?> Applications</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:18px 20px;">
            <div style="position:relative;height:200px;width:100%;max-width:260px;">
                <canvas id="applicantStatusDoughnut"></canvas>
            </div>
            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px 12px;justify-content:center;font-size:11.5px;color:var(--color-text-muted);">
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#10B981;"></span> Approved (<?= $myStatusBreakdown['Approved & Certified'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#2563EB;"></span> In Review (<?= $myStatusBreakdown['Under Technical Review'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F59E0B;"></span> Pending (<?= $myStatusBreakdown['Pending Intake Review'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#F97316;"></span> Revision (<?= $myStatusBreakdown['Needs Revision'] ?>)</span>
                <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#EF4444;"></span> Rejected (<?= $myStatusBreakdown['Rejected'] ?>)</span>
            </div>
        </div>
    </div>

    <!-- Chart 2: My IP Filings by Category Bar Chart -->
    <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0 0 2px;font-size:15px;font-weight:700;">My Portfolio by IP Category</h3>
                <span style="font-size:12px;color:var(--color-text-muted);">Classification distribution of your intellectual property submissions</span>
            </div>
            <span class="badge badge-info" style="font-size:11px;">Category Portfolio</span>
        </div>
        <div class="card-body" style="padding:18px 20px;">
            <div style="position:relative;height:240px;width:100%;">
                <canvas id="applicantCategoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3>My Recent Applications</h3>
            <a href="<?= BASE_URL ?>applicant/submit_application.php" class="btn btn-primary btn-sm">
                <i data-lucide="plus" style="width:13px;height:13px;"></i>
                New Application
            </a>
        </div>
        <div class="table-wrap">
            <?php if (mysqli_num_rows($recentApps) === 0): ?>
                <div class="empty-state">
                    <i data-lucide="file-question" style="width:36px;height:36px;"></i>
                    <p>You haven't submitted any applications yet.</p>
                    <a href="<?= BASE_URL ?>applicant/submit_application.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Submit Your First Application</a>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Tracking Code</th><th>Title</th><th>Type</th><th>Status</th><th>Submitted</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    <?php while ($a = mysqli_fetch_assoc($recentApps)): ?>
                    <tr>
                        <td class="tracking"><?= clean($a['tracking_code']) ?></td>
                        <td><strong style="color:var(--color-text);"><?= clean($a['title']) ?></strong></td>
                        <td><span class="badge badge-info"><?= clean($a['type_name']) ?></span></td>
                        <td><span class="badge <?= status_badge_class($a['status']) ?>"><?= status_label($a['status']) ?></span></td>
                        <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);"><?= format_date($a['submitted_at'], 'M d, Y') ?></td>
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

    <div class="card">
        <div class="card-header">
            <h3>Announcements</h3>
            <span style="font-size:12px;color:var(--color-text-muted);">From IPO Director</span>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($announcements) === 0): ?>
                <div class="empty-state" style="padding:24px;">
                    <i data-lucide="megaphone" style="width:28px;height:28px;"></i>
                    <p>No active announcements at this time.</p>
                </div>
            <?php else: ?>
                <?php while ($an = mysqli_fetch_assoc($announcements)): ?>
                <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--color-border);">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                        <i data-lucide="bell" style="width:14px;height:14px;color:var(--color-primary-600);"></i>
                        <strong style="font-size:13.5px;color:var(--color-text);"><?= clean($an['title']) ?></strong>
                    </div>
                    <p style="font-size:12.5px;color:var(--color-text-muted);margin:4px 0 6px;line-height:1.5;"><?= clean($an['content']) ?></p>
                    <span style="font-size:11px;color:var(--color-text-subtle);display:flex;align-items:center;gap:4px;">
                        <i data-lucide="calendar" style="width:11px;height:11px;"></i>
                        <?= format_date($an['posted_at'], 'M d, Y') ?>
                    </span>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. My Application Status Overview Doughnut Chart
    const statusCanvas = document.getElementById('applicantStatusDoughnut');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($myStatusBreakdown)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($myStatusBreakdown)) ?>,
                    backgroundColor: ['#10B981', '#2563EB', '#F59E0B', '#F97316', '#EF4444'],
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

    // 2. Category Portfolio Bar Chart
    const catCanvas = document.getElementById('applicantCategoryChart');
    if (catCanvas) {
        new Chart(catCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($myCategories)) ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?= json_encode(array_values($myCategories)) ?>,
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
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
