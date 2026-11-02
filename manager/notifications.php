<?php
/**
 * manager/notifications.php
 * -----------------------------------------------------------------
 * Notification inbox for Manager (e.g. "Application Evaluated").
 * Same read/mark-all pattern used across every role.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Notifications';
$currentPage = 'notifications';

if (isset($_GET['mark_all'])) {
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    redirect('manager/notifications.php');
}

if (isset($_GET['mark'])) {
    $notifId = (int) $_GET['mark'];
    $stmt = mysqli_prepare($conn, "SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $notifId, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $link = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['link'] ?? null;
    mysqli_stmt_close($stmt);

    $upd = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($upd, 'ii', $notifId, $_SESSION['user_id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    redirect($link ?: 'manager/notifications.php');
}

$filter = sanitize_input($_GET['filter'] ?? 'all');
$whereFilter = '';
if ($filter === 'unread') {
    $whereFilter = 'AND is_read = 0';
} elseif ($filter === 'read') {
    $whereFilter = 'AND is_read = 1';
}

// ---- Stats for KPI cards ----
$kpiStmt = mysqli_prepare($conn, "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(is_read = 0), 0) AS total_unread,
        COALESCE(SUM(is_read = 1), 0) AS total_read
    FROM notifications 
    WHERE user_id = ?
");
mysqli_stmt_bind_param($kpiStmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($kpiStmt);
$kpiStats = mysqli_fetch_assoc(mysqli_stmt_get_result($kpiStmt)) ?: [
    'total_all' => 0,
    'total_unread' => 0,
    'total_read' => 0
];
mysqli_stmt_close($kpiStmt);

$stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE user_id = ? $whereFilter ORDER BY created_at DESC LIMIT 50");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$notifications = mysqli_stmt_get_result($stmt);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Executive Inbox</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Executive Alerts &amp; Decision Requests</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Real-time governance notifications for evaluated applications awaiting your final signature and institutional decisions.</div>
    </div>
    <div class="action-controls-group">
        <?php if ($kpiStats['total_unread'] > 0): ?>
        <a href="<?= BASE_URL ?>manager/notifications.php?mark_all=1" class="btn btn-outline btn-sm" style="gap:6px;">
            <i data-lucide="check-check" style="width:14px;height:14px;"></i> Mark All Read
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. KPI Summary Grid -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:18px;">
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Alerts</span>
            <span class="info-icon" title="All notifications received"><i data-lucide="bell" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_all']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,25 Q30,15 60,25 T100,10" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="inbox" style="width:13px;height:13px;"></i> All Activity</span>
            <span>Recorded</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Unread Notifications</span>
            <span class="info-icon" title="Notifications awaiting your review"><i data-lucide="bell-ring" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary);"><?= number_format($kpiStats['total_unread']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,30 Q35,10 70,25 T100,5" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend <?= $kpiStats['total_unread'] > 0 ? 'up' : 'neutral' ?>">
                <i data-lucide="clock" style="width:13px;height:13px;"></i> <?= $kpiStats['total_unread'] > 0 ? 'Needs Attention' : 'All Clear' ?>
            </span>
            <span>Unread</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Archived / Read</span>
            <span class="info-icon" title="Previously opened notifications"><i data-lucide="check-circle" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_read']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,20 Q30,30 60,15 T100,20" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="archive" style="width:13px;height:13px;"></i> Archived</span>
            <span>Completed</span>
        </div>
    </div>
</div>

<!-- 3. Segmented Filter Tabs -->
<div class="filter-card">
    <div class="filter-row">
        <div class="segmented-control">
            <a href="<?= BASE_URL ?>manager/notifications.php?filter=all" class="segment-btn <?= $filter === 'all' ? 'active' : '' ?>">
                All <span class="count-pill"><?= number_format($kpiStats['total_all']) ?></span>
            </a>
            <a href="<?= BASE_URL ?>manager/notifications.php?filter=unread" class="segment-btn <?= $filter === 'unread' ? 'active' : '' ?>">
                Unread <span class="count-pill"><?= number_format($kpiStats['total_unread']) ?></span>
            </a>
            <a href="<?= BASE_URL ?>manager/notifications.php?filter=read" class="segment-btn <?= $filter === 'read' ? 'active' : '' ?>">
                Read <span class="count-pill"><?= number_format($kpiStats['total_read']) ?></span>
            </a>
        </div>
    </div>
</div>

<!-- 4. Notification List Card -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
    <div class="card-body" style="padding:0;">
        <?php if (mysqli_num_rows($notifications) === 0): ?>
            <div class="empty-state" style="padding:56px 20px;text-align:center;">
                <div style="width:52px;height:52px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="bell-off" style="width:26px;height:26px;"></i>
                </div>
                <div style="font-weight:600;font-size:15px;color:var(--color-text);margin-bottom:4px;">No notifications found</div>
                <div style="font-size:13px;color:var(--color-text-muted);">You're all caught up! New approval requests will appear here.</div>
            </div>
        <?php else: ?>
            <?php while ($n = mysqli_fetch_assoc($notifications)): ?>
            <a href="<?= BASE_URL ?>manager/notifications.php?mark=<?= $n['notification_id'] ?>"
               style="display:flex;gap:14px;padding:16px 20px;border-bottom:1px solid var(--color-border);<?= $n['is_read'] ? 'background:var(--color-surface);' : 'background:rgba(37,99,235,0.04);' ?>color:inherit;text-decoration:none;transition:background var(--transition-fast);">
                <div style="width:36px;height:36px;border-radius:50%;background:<?= $n['is_read'] ? 'var(--color-bg)' : 'rgba(37,99,235,0.12)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $n['is_read'] ? 'var(--color-text-muted)' : 'var(--color-primary)' ?>;flex-shrink:0;">
                    <i data-lucide="<?= $n['is_read'] ? 'bell' : 'bell-ring' ?>" style="width:17px;height:17px;"></i>
                </div>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <div style="font-weight:600;font-size:13.5px;color:var(--color-text);">
                            <?= clean($n['title']) ?>
                            <?php if (!$n['is_read']): ?>
                                <span class="badge badge-info" style="font-size:10.5px;margin-left:6px;padding:2px 6px;">New</span>
                            <?php endif; ?>
                        </div>
                        <span class="tabular" style="font-size:11.5px;color:var(--color-text-muted);white-space:nowrap;">
                            <?= format_date($n['created_at'], 'M d, Y · h:i A') ?>
                        </span>
                    </div>
                    <div style="font-size:13px;color:var(--color-text-muted);line-height:1.5;">
                        <?= clean($n['message']) ?>
                    </div>
                </div>
            </a>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
