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

// Current user email lookup
$userEmail = '';
if (isset($_SESSION['user_id'])) {
    $emailStmt = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($emailStmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($emailStmt);
    $userEmail = mysqli_fetch_assoc(mysqli_stmt_get_result($emailStmt))['email'] ?? '';
    mysqli_stmt_close($emailStmt);
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
        <!-- Settings Quick Button -->
        <?php if ($_SESSION['role_name'] === 'Administrator'): ?>
        <a href="<?= BASE_URL ?>admin/settings.php" class="topbar-icon-btn" title="System Settings">
            <i data-lucide="settings" style="width:18px;height:18px;"></i>
        </a>
        <?php endif; ?>

        <!-- Notifications Bell -->
        <a href="<?= BASE_URL . $notifLink ?>" class="topbar-icon-btn" title="Notifications">
            <i data-lucide="bell" style="width:18px;height:18px;"></i>
            <?php if ($unread > 0): ?>
                <span class="badge-dot"></span>
            <?php endif; ?>
        </a>

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

            <!-- Dropdown Menu -->
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
                    <?php endif; ?>
                    
                    <a href="<?= BASE_URL . $notifLink ?>" class="account-menu-item">
                        <span class="left">
                            <i data-lucide="bell" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
                            Notifications
                        </span>
                        <span class="left">
                            <?php if ($unread > 0): ?><span class="nav-pill-badge" style="margin-right:4px;"><?= $unread ?></span><?php endif; ?>
                            <span class="chevron"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></span>
                        </span>
                    </a>

                    <?php if ($profileLink): ?>
                    <a href="<?= BASE_URL . $profileLink ?>#password" class="account-menu-item">
                        <span class="left">
                            <i data-lucide="key-round" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
                            Change Password
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
                            Log Out
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
