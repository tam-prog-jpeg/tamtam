<?php
/**
 * manager/dashboard.php
 * -----------------------------------------------------------------
 * Manager landing page: office-wide KPIs plus a queue of applications
 * that have been evaluated (recommended for approval) and are now
 * waiting on the Manager's final sign-off.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

$awaiting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'evaluated'"))['c'];
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'approved'"))['c'];
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'rejected'"))['c'];
$total    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications"))['c'];

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
    LIMIT 8
");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Executive Sign-Off &amp; Portfolio Governance</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Review technical evaluations, issue executive sign-offs, and monitor institutional IP productivity.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline" title="View IPO Statistical Reports">
            <i data-lucide="bar-chart-3" style="width:16px;height:16px;"></i>
            Analytics &amp; Reports
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Awaiting Final Approval -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Awaiting Final Approval</span>
            <span class="info-icon" title="Evaluated applications pending your executive approval or rejection decision">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($awaiting) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mgrGradWarning" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,24 Q25,32 50,16 T100,8 L100,40 L0,40 Z" fill="url(#mgrGradWarning)"/>
                <path d="M0,24 Q25,32 50,16 T100,8" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Executive sign-off pending</span>
        </div>
    </div>

    <!-- Card 2: Approved Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved Filings</span>
            <span class="info-icon" title="Applications officially approved and registered across all time">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approved) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mgrGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,35 Q25,25 50,15 T100,5 L100,40 L0,40 Z" fill="url(#mgrGradGreen)"/>
                <path d="M0,35 Q25,25 50,15 T100,5" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Approved &amp; certified</span>
        </div>
    </div>

    <!-- Card 3: Rejected Applications -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Rejected Applications</span>
            <span class="info-icon" title="Applications denied registration or failing institutional criteria">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($rejected) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mgrGradRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,28 60,18 T100,26 L100,40 L0,40 Z" fill="url(#mgrGradRed)"/>
                <path d="M0,18 Q30,28 60,18 T100,26" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="x-circle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Closed filings</span>
        </div>
    </div>

    <!-- Card 4: Total Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total IP Filings</span>
            <span class="info-icon" title="Cumulative intellectual property applications recorded in ZPPSU IPO">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($total) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="mgrGradBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#1E3A8A" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#1E3A8A" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,24 50,14 T100,6 L100,40 L0,40 Z" fill="url(#mgrGradBlue)"/>
                <path d="M0,32 Q25,24 50,14 T100,6" stroke="#1E3A8A" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="file-text" style="width:13px;height:13px;color:var(--color-primary);"></i>
            <span>University portfolio size</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h3 style="margin:0 0 2px;">Awaiting Executive Sign-Off</h3>
            <span style="font-size:12.5px;color:var(--color-text-muted);">Applications verified and recommended by technical evaluators awaiting final office decision.</span>
        </div>
        <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-outline btn-sm">
            <i data-lucide="bar-chart-3" style="width:13px;height:13px;"></i>
            View Reports
        </a>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($queue) === 0): ?>
            <div class="empty-state">
                <i data-lucide="check-circle-2" style="width:36px;height:36px;color:var(--color-success);"></i>
                <p>Nothing waiting for executive sign-off right now. All evaluated filings are reviewed!</p>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking Code</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Applicant</th>
                    <th>Evaluator</th>
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
                    <td><?= $a['eval_first'] ? clean($a['eval_first'] . ' ' . $a['eval_last']) : '<span style="color:var(--color-text-muted);">—</span>' ?></td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);"><?= format_date($a['submitted_at'], 'M d, Y') ?></td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>manager/application_view.php?id=<?= $a['application_id'] ?>" title="Review for Final Sign-Off">
                            <i data-lucide="file-check-2" style="width:13px;height:13px;"></i>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
