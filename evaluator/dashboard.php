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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="evalGradWarning" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,22 Q25,32 50,18 T100,10 L100,40 L0,40 Z" fill="url(#evalGradWarning)"/>
                <path d="M0,22 Q25,32 50,18 T100,10" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="evalGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,34 Q25,26 50,16 T100,4 L100,40 L0,40 Z" fill="url(#evalGradGreen)"/>
                <path d="M0,34 Q25,26 50,16 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="evalGradRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,30 60,20 T100,28 L100,40 L0,40 Z" fill="url(#evalGradRed)"/>
                <path d="M0,18 Q30,30 60,20 T100,28" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
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
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,20 Q30,32 60,22 T100,30 L100,40 L0,40 Z" fill="url(#evalGradWarning)"/>
                <path d="M0,20 Q30,32 60,22 T100,30" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
            <span>Revisions requested</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
