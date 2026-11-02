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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="gradTotal" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,24 50,18 T100,8 L100,40 L0,40 Z" fill="url(#gradTotal)"/>
                <path d="M0,32 Q25,24 50,18 T100,8" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="gradPending" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,30 60,20 T100,28 L100,40 L0,40 Z" fill="url(#gradPending)"/>
                <path d="M0,18 Q30,30 60,20 T100,28" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <span class="info-icon" title="Applications granted final approval with downloadable certificates">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approved) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="gradApproved" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,34 Q25,28 50,16 T100,4 L100,40 L0,40 Z" fill="url(#gradApproved)"/>
                <path d="M0,34 Q25,28 50,16 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-circle" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Certificate ready</span>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,20 Q30,32 60,22 T100,30 L100,40 L0,40 Z" fill="url(#gradPending)"/>
                <path d="M0,20 Q30,32 60,22 T100,30" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-triangle" style="width:13px;height:13px;"></i>
            <span>Check remarks if returned</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
