<?php
/**
 * includes/topbar.php
 * -----------------------------------------------------------------
 * Modern white topbar matching the Deli-Prop dashboard reference:
 *  - Page title
 *  - Global search
 *  - Quick settings & notification bell with indicator dot
 *  - User profile capsule with avatar, name, and role dropdown
 * -----------------------------------------------------------------
 */
$unread = isset($_SESSION['user_id']) ? count_unread_notifications($conn, $_SESSION['user_id']) : 0;
$initials = strtoupper(substr($_SESSION['first_name'] ?? 'A', 0, 1) . substr($_SESSION['last_name'] ?? 'D', 0, 1));

// Per-role notification targets
$notifLinkMap = [
    'Administrator' => 'admin/notifications.php',
    'Applicant'     => 'applicant/notifications.php',
    'Staff'         => 'staff/notifications.php',
    'Evaluator'     => 'evaluator/notifications.php',
    'Manager'       => 'manager/notifications.php',
];
$notifLink = $notifLinkMap[$_SESSION['role_name'] ?? ''] ?? '#';

// Per-role profile targets
$profileLinkMap = [
    'Administrator' => 'admin/profile.php',
    'Applicant'     => 'applicant/profile.php',
    'Staff'         => 'staff/profile.php',
    'Evaluator'     => 'evaluator/profile.php',
    'Manager'       => 'manager/profile.php',
];
$profileLink = $profileLinkMap[$_SESSION['role_name'] ?? ''] ?? null;

// Search target per role
$searchTargetMap = [
    'Administrator' => 'admin/applications.php',
    'Applicant'     => 'applicant/my_applications.php',
    'Staff'         => 'staff/verify.php',
    'Evaluator'     => 'evaluator/evaluations.php',
    'Manager'       => 'manager/reports.php',
];
$searchTarget = $searchTargetMap[$_SESSION['role_name'] ?? ''] ?? null;
$searchPlaceholderMap = [
    'Administrator' => 'Search applications, tracking #...',
    'Applicant'     => 'Search my applications...',
    'Staff'         => 'Search applications...',
    'Evaluator'     => 'Search evaluations...',
    'Manager'       => 'Search applications...',
];
$searchPlaceholder = $searchPlaceholderMap[$_SESSION['role_name'] ?? ''] ?? 'Search...';

// Current user email lookup & recent notifications
$userEmail = '';
$recentNotifs = [];
if (isset($_SESSION['user_id']) && isset($conn)) {
    $emailStmt = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ?");
    if ($emailStmt) {
        mysqli_stmt_bind_param($emailStmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($emailStmt);
        $userEmail = mysqli_fetch_assoc(mysqli_stmt_get_result($emailStmt))['email'] ?? '';
        mysqli_stmt_close($emailStmt);
    }

    $notifStmt = mysqli_prepare($conn, "SELECT notification_id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    if ($notifStmt) {
        mysqli_stmt_bind_param($notifStmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($notifStmt);
        $notifRes = mysqli_stmt_get_result($notifStmt);
        while ($nrow = mysqli_fetch_assoc($notifRes)) {
            $recentNotifs[] = $nrow;
        }
        mysqli_stmt_close($notifStmt);
    }
}
?>
<header class="topbar">
    <div style="display:flex;align-items:center;gap:16px;">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Navigation">
            <i data-lucide="menu" style="width:22px;height:22px;"></i>
        </button>
        <div class="page-title"><?= clean($pageTitle ?? 'Dashboard') ?></div>
    </div>

    <div class="topbar-actions">
        <!-- Interactive Notification Bell & Dropdown Popover -->
        <div class="topbar-notif-wrap" id="notifWrapper" style="position:relative;">
            <button type="button" class="topbar-icon-btn" id="notifBellBtn" title="Notifications" aria-label="Notifications" aria-expanded="false">
                <i data-lucide="bell" style="width:18px;height:18px;"></i>
                <?php if ($unread > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </button>

            <!-- Dropdown Popover -->
            <div id="notifDropdown" class="notif-dropdown">
                <div class="notif-dropdown-head">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-weight:700;font-size:14px;color:var(--color-text);">Notifications</span>
                        <?php if ($unread > 0): ?>
                            <span class="nav-pill-badge" style="background:var(--color-primary-600);color:#FFFFFF;"><?= $unread ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($unread > 0): ?>
                        <a href="<?= BASE_URL . $notifLink ?>?mark_all=1" class="notif-head-action">Mark all as read</a>
                    <?php endif; ?>
                </div>

                <div class="notif-dropdown-body">
                    <?php if (empty($recentNotifs)): ?>
                        <div class="notif-empty-state">
                            <i data-lucide="bell-off" style="width:26px;height:26px;color:var(--color-text-subtle);margin-bottom:6px;"></i>
                            <div style="font-size:13px;font-weight:600;color:var(--color-text);">No new notifications</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">You're all caught up with your updates.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifs as $n): ?>
                            <a href="<?= BASE_URL . $notifLink ?>?mark=<?= (int)$n['notification_id'] ?>" class="notif-drop-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                                <div class="notif-drop-icon">
                                    <i data-lucide="<?= $n['is_read'] ? 'bell' : 'bell-ring' ?>" style="width:15px;height:15px;"></i>
                                </div>
                                <div class="notif-drop-content">
                                    <div class="notif-drop-title"><?= clean($n['title']) ?></div>
                                    <div class="notif-drop-msg"><?= clean($n['message']) ?></div>
                                    <div class="notif-drop-time"><?= date('M j, Y • g:i A', strtotime($n['created_at'])) ?></div>
                                </div>
                                <?php if (!$n['is_read']): ?>
                                    <span class="notif-drop-dot"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="notif-dropdown-foot">
                    <a href="<?= BASE_URL . $notifLink ?>" class="notif-view-all">View all notifications &rarr;</a>
                </div>
            </div>
        </div>

        <div class="topbar-divider"></div>

        <!-- Profile Capsule -->
        <div class="topbar-user" id="userMenuToggle" style="position:relative;">
            <div class="avatar"><?= clean($initials) ?></div>
            <div class="who">
                <span class="name"><?= clean(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></span>
                <span class="role"><?= clean($userEmail ?: ($_SESSION['role_name'] ?? '')) ?></span>
            </div>
            <span class="chevron">
                <i data-lucide="chevron-down" style="width:16px;height:16px;"></i>
            </span>

            <!-- User Menu Dropdown -->
            <div id="userMenuDropdown" class="account-menu">
                <div class="account-menu-header">
                    <div class="avatar-lg"><?= clean($initials) ?></div>
                    <div class="account-menu-name-block">
                        <div class="account-menu-name"><?= clean(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></div>
                        <div class="account-menu-email"><?= clean($userEmail) ?></div>
                        <span class="badge badge-info" style="margin-top:6px;"><?= clean($_SESSION['role_name'] ?? '') ?></span>
                    </div>
                </div>

                <div class="account-menu-list">
                    <?php if ($profileLink): ?>
                    <a href="<?= BASE_URL . $profileLink ?>" class="account-menu-item">
                        <span class="left">
                            <i data-lucide="user" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
                            My Profile
                        </span>
                        <span class="chevron"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></span>
                    </a>

                    <a href="<?= BASE_URL . $profileLink ?>?tab=security" class="account-menu-item">
                        <span class="left">
                            <i data-lucide="key-round" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
                            Security &amp; Password
                        </span>
                        <span class="chevron"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></span>
                    </a>
                    <?php endif; ?>

                    <?php if ($_SESSION['role_name'] === 'Administrator'): ?>
                    <a href="<?= BASE_URL ?>admin/settings.php" class="account-menu-item">
                        <span class="left">
                            <i data-lucide="settings" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
                            System Settings
                        </span>
                        <span class="chevron"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></span>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="account-menu-divider"></div>

                <div class="account-menu-list">
                    <a href="<?= BASE_URL ?>logout.php" class="account-menu-item danger">
                        <span class="left">
                            <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                            Sign Out
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
