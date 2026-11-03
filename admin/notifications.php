<?php
/**
 * admin/notifications.php
 * -----------------------------------------------------------------
 * Notification inbox for the Administrator (e.g. "New application
 * submitted"). Same read/mark-all pattern as the applicant version.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Notifications';
$currentPage = 'notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $notifId = (int) ($_POST['notification_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $notifId, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Notification deleted.');
    }

    if ($action === 'clear_all') {
        $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'All notifications cleared.');
    }
    redirect('admin/notifications.php');
}

if (isset($_GET['mark_all'])) {
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    redirect('admin/notifications.php');
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

    redirect($link ?: 'admin/notifications.php');
}

// ---- Filter by Read status ----
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
    <div class="sub-header-left">
        <h2>Notifications & Alerts</h2>
        <p>Real-time notifications for incoming applications, status changes, and evaluation updates.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/notifications.php?mark_all=1" class="btn btn-outline btn-sm">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
            Mark all as read
        </a>
        <?php if ($kpiStats['total_all'] > 0): ?>
        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#clearAllNotifModal">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Clear All
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
    <!-- Total Notifications -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Inbox Total</span>
            <span class="kpi-info-tip" title="All active notifications in your inbox">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-blue-notif" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6 L 80 24 L 0 24 Z" fill="url(#grad-blue-notif)"/>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6" fill="none" stroke="#2563EB" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Administrator messages</span>
        </div>
    </div>

    <!-- Unread Alerts -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Unread Alerts</span>
            <span class="kpi-info-tip" title="Notifications requiring your attention">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-danger);"><?= number_format((int)$kpiStats['total_unread']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-red-notif" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#EF4444" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4 L 80 24 L 0 24 Z" fill="url(#grad-red-notif)"/>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4" fill="none" stroke="#EF4444" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-warning">Unread notifications</span>
        </div>
    </div>

    <!-- Read / Archived -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Read Notifications</span>
            <span class="kpi-info-tip" title="Archived read notifications">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-success);"><?= number_format((int)$kpiStats['total_read']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-green-notif" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2 L 80 24 L 0 24 Z" fill="url(#grad-green-notif)"/>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2" fill="none" stroke="#10B981" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Acknowledged notices</span>
        </div>
    </div>
</div>

<!-- 3. Category Filter Tabs -->
<div class="category-tabs" style="margin-bottom:16px;">
    <a href="<?= BASE_URL ?>admin/notifications.php?filter=all" class="tab-pill <?= $filter === 'all' ? 'active' : '' ?>">
        All Notifications
        <span class="tab-count"><?= (int)$kpiStats['total_all'] ?></span>
    </a>
    <a href="<?= BASE_URL ?>admin/notifications.php?filter=unread" class="tab-pill <?= $filter === 'unread' ? 'active' : '' ?>">
        Unread
        <?php if ($kpiStats['total_unread'] > 0): ?>
            <span class="tab-count" style="background:var(--color-danger);color:#fff;"><?= (int)$kpiStats['total_unread'] ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>admin/notifications.php?filter=read" class="tab-pill <?= $filter === 'read' ? 'active' : '' ?>">
        Read
        <span class="tab-count"><?= (int)$kpiStats['total_read'] ?></span>
    </a>
</div>

<!-- 4. Notifications Feed Container -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Notification Stream</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= mysqli_num_rows($notifications) ?> Shown</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (mysqli_num_rows($notifications) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                </div>
                <h4>No notifications in this view</h4>
                <p>You're all caught up! When actions occur in the system, updates will appear here.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;">
                <?php while ($n = mysqli_fetch_assoc($notifications)): 
                    $isUnread = !$n['is_read'];
                ?>
                <div style="display:flex;align-items:center;padding:16px 20px;border-bottom:1px solid var(--color-border);transition:background 0.15s ease;<?= $isUnread ? 'background:rgba(37, 99, 235, 0.03);' : '' ?>">
                    <!-- Indicator Dot / Icon -->
                    <div style="margin-right:16px;display:flex;align-items:center;justify-content:center;">
                        <div style="width:36px;height:36px;border-radius:50%;background:<?= $isUnread ? 'var(--color-primary-50)' : 'var(--color-bg)' ?>;color:<?= $isUnread ? 'var(--color-primary-600)' : 'var(--color-text-subtle)' ?>;display:flex;align-items:center;justify-content:center;border:1px solid var(--color-border);flex-shrink:0;">
                            <svg class="lucide-icon" style="width:18px;height:18px;" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        </div>
                    </div>

                    <!-- Content -->
                    <a href="<?= BASE_URL ?>admin/notifications.php?mark=<?= $n['notification_id'] ?>" style="flex:1;text-decoration:none;color:inherit;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span style="font-weight:600;font-size:14px;color:var(--color-text);"><?= clean($n['title']) ?></span>
                            <?php if ($isUnread): ?>
                                <span class="badge badge-primary" style="font-size:10px;padding:1px 6px;">New</span>
                            <?php endif; ?>
                            <span style="margin-left:auto;font-family:var(--font-mono);font-size:11.5px;color:var(--color-text-muted);white-space:nowrap;">
                                <?= format_date($n['created_at'], 'M d, Y · h:i A') ?>
                            </span>
                        </div>
                        <div style="font-size:13px;color:var(--color-text-muted);line-height:1.5;">
                            <?= clean($n['message']) ?>
                        </div>
                    </a>

                    <!-- Actions -->
                    <div style="margin-left:16px;">
                        <form method="POST" onsubmit="return confirm('Delete this notification?');" style="margin:0;">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm btn-icon" title="Delete Notification">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 5. Clear All confirmation modal -->
<div class="modal-overlay" id="clearAllNotifModal">
    <div class="modal-box" style="border-radius:var(--radius-lg);max-width:480px;">
        <form method="POST">
            <div class="modal-head" style="padding:18px 22px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-danger-bg);color:var(--color-danger);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="bell-off" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">Clear All Notifications</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Purge personal notification feed</div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear_all">
                <p style="font-size:13px;color:var(--color-text);line-height:1.55;margin:0;">
                    This will permanently clear <strong>all of your notifications</strong> (both read and unread). New notifications will continue to arrive as applications and reviews progress. This action cannot be undone.
                </p>
            </div>
            <div class="modal-foot" style="padding:16px 22px;background:var(--color-surface-subtle);border-top:1px solid var(--color-border);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" style="padding:8px 18px;">
                    <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    Clear All Notifications
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
