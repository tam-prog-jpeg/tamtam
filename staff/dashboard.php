<?php
/**
 * staff/dashboard.php
 * -----------------------------------------------------------------
 * Staff landing page: how many applications are waiting for
 * verification (system-wide and personally assigned), plus a queue
 * of the newest submissions that still need action.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

$unverified = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'pending'"))['c'];
$myAssigned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_staff_id = {$_SESSION['user_id']} AND status = 'pending'"))['c'];
$forwarded  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_staff_id = {$_SESSION['user_id']} AND status = 'under_review'"))['c'];
$returned   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE assigned_staff_id = {$_SESSION['user_id']} AND status = 'returned'"))['c'];

$queue = mysqli_query($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name,
           u.first_name, u.last_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    WHERE a.status = 'pending'
    ORDER BY a.submitted_at ASC
    LIMIT 8
");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">IPO Staff Verification Portal</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Screen submitted applications, check documentation requirements, and assign examiners.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-primary" title="View Applications Awaiting Verification">
            <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
            Verify Applications (<?= (int)$unverified ?>)
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Awaiting Verification -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Verification</span>
            <span class="info-icon" title="All new applications pending initial staff document review">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($unverified) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="staffGradWarning" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,22 Q25,32 50,18 T100,12 L100,40 L0,40 Z" fill="url(#staffGradWarning)"/>
                <path d="M0,22 Q25,32 50,18 T100,12" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Requires screening</span>
        </div>
    </div>

    <!-- Card 2: Assigned to Me -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Assigned to Me</span>
            <span class="info-icon" title="Applications currently assigned to your staff profile">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($myAssigned) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="staffGradBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,30 Q25,24 50,16 T100,6 L100,40 L0,40 Z" fill="url(#staffGradBlue)"/>
                <path d="M0,30 Q25,24 50,16 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="user-check" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Active assignments</span>
        </div>
    </div>

    <!-- Card 3: Forwarded by Me -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Forwarded to Evaluator</span>
            <span class="info-icon" title="Applications successfully screened and forwarded to evaluators">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($forwarded) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="staffGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,34 Q25,28 50,18 T100,4 L100,40 L0,40 Z" fill="url(#staffGradGreen)"/>
                <path d="M0,34 Q25,28 50,18 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Advanced in pipeline</span>
        </div>
    </div>

    <!-- Card 4: Returned by Me -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Returned for Revision</span>
            <span class="info-icon" title="Applications returned to applicant due to missing documents or requirements">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($returned) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="staffGradRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,30 60,20 T100,28 L100,40 L0,40 Z" fill="url(#staffGradRed)"/>
                <path d="M0,18 Q30,30 60,20 T100,28" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
            <span>Awaiting applicant revisions</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Verification Queue</h3>
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-sm">
            View All
            <i data-lucide="arrow-right" style="width:13px;height:13px;"></i>
        </a>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($queue) === 0): ?>
            <div class="empty-state">
                <i data-lucide="check-circle-2" style="width:36px;height:36px;color:var(--color-success);"></i>
                <p>All submitted applications have been processed. Great job!</p>
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
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $a['application_id'] ?>" title="Review Application">
                            <i data-lucide="shield-check" style="width:13px;height:13px;"></i>
                            Verify
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
