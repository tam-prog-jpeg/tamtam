<?php
/**
 * includes/sidebar.php
 * -----------------------------------------------------------------
 * Role-aware sidebar navigation — Re-architected to match the
 * Deli-Prop SaaS Dashboard reference:
 *  - Crisp white surface with subtle right border
 *  - Professional Lucide outline icons
 *  - Group labels ("Menu", "Others")
 *  - Active pill state (soft gray background, bold dark text)
 *  - Red notification pill badge
 *  - Dark mode switch at the bottom
 * -----------------------------------------------------------------
 */
$role = $_SESSION['role_name'] ?? '';
$currentPage = $currentPage ?? '';
$unreadCount = (isset($conn) && isset($_SESSION['user_id'])) ? count_unread_notifications($conn, $_SESSION['user_id']) : 0;

if (!function_exists('nav_link')) {
    function nav_link($href, $icon, $label, $key, $currentPage, $badge = null)
    {
        $active = ($currentPage === $key) ? ' active' : '';
        echo '<li><a class="' . trim($active) . '" href="' . BASE_URL . $href . '">';
        echo '<span class="nav-icon"><i data-lucide="' . $icon . '"></i></span>';
        echo '<span class="nav-label-text">' . $label . '</span>';
        if ($badge !== null && $badge > 0) {
            echo '<span class="nav-pill-badge">' . ($badge > 99 ? '99+' : $badge) . '</span>';
        }
        echo '</a></li>';
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand-wrapper">
        <a href="<?= BASE_URL ?>index.php?view=home" class="sidebar-brand" title="ZPPSU IPO Home">
            <div class="sidebar-brand-logo">
                <i data-lucide="layers" style="width:20px;height:20px;"></i>
            </div>
            <div class="sidebar-brand-text">
                ZPPSU-IPO
                <small>Management Portal</small>
            </div>
        </a>
        <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Toggle Sidebar">
            <i data-lucide="panel-left" style="width:18px;height:18px;"></i>
        </button>
    </div>

    <ul class="sidebar-nav">
        <?php if ($role === 'Administrator'): ?>
            <li class="nav-label">Menu</li>
            <?php nav_link('admin/dashboard.php', 'layout-dashboard', 'Dashboard', 'dashboard', $currentPage); ?>
            <?php nav_link('admin/applications.php', 'file-text', 'Applications', 'applications', $currentPage); ?>
            <?php nav_link('admin/applicants.php', 'users', 'Applicants', 'applicants', $currentPage); ?>
            <?php nav_link('admin/documents.php', 'folder', 'Documents', 'documents', $currentPage); ?>
            <?php nav_link('admin/users.php', 'user-cog', 'User Management', 'users', $currentPage); ?>
            <?php nav_link('admin/reports.php', 'bar-chart-3', 'Analytics & Reports', 'reports', $currentPage); ?>
            <?php nav_link('admin/notifications.php', 'bell', 'Notifications', 'notifications', $currentPage, $unreadCount); ?>

            <li class="nav-label">Others</li>
            <?php nav_link('admin/announcements.php', 'megaphone', 'Announcements', 'announcements', $currentPage); ?>
            <?php nav_link('admin/audit_logs.php', 'shield-check', 'Audit Logs', 'audit_logs', $currentPage); ?>
            <?php nav_link('admin/settings.php', 'settings', 'Settings', 'settings', $currentPage); ?>
            <?php nav_link('admin/backup.php', 'database', 'Database Backup', 'backup', $currentPage); ?>
            <?php nav_link('admin/profile.php', 'user', 'My Profile', 'profile', $currentPage); ?>

        <?php elseif ($role === 'Staff'): ?>
            <li class="nav-label">Menu</li>
            <?php nav_link('staff/dashboard.php', 'layout-dashboard', 'Dashboard', 'dashboard', $currentPage); ?>
            <?php nav_link('staff/verify.php', 'check-circle', 'Verify Applications', 'verify', $currentPage); ?>
            <?php nav_link('staff/applicants.php', 'users', 'Applicant Records', 'applicants', $currentPage); ?>
            <?php nav_link('staff/documents.php', 'folder', 'Documents', 'documents', $currentPage); ?>

            <li class="nav-label">Others</li>
            <?php nav_link('staff/notifications.php', 'bell', 'Notifications', 'notifications', $currentPage, $unreadCount); ?>
            <?php nav_link('staff/profile.php', 'user', 'My Profile', 'profile', $currentPage); ?>

        <?php elseif ($role === 'Evaluator'): ?>
            <li class="nav-label">Menu</li>
            <?php nav_link('evaluator/dashboard.php', 'layout-dashboard', 'Dashboard', 'dashboard', $currentPage); ?>
            <?php nav_link('evaluator/evaluations.php', 'clipboard-check', 'Evaluations', 'evaluations', $currentPage); ?>
            <?php nav_link('evaluator/reports.php', 'bar-chart-3', 'Reports', 'reports', $currentPage); ?>

            <li class="nav-label">Others</li>
            <?php nav_link('evaluator/notifications.php', 'bell', 'Notifications', 'notifications', $currentPage, $unreadCount); ?>
            <?php nav_link('evaluator/profile.php', 'user', 'My Profile', 'profile', $currentPage); ?>

        <?php elseif ($role === 'Manager'): ?>
            <li class="nav-label">Menu</li>
            <?php nav_link('manager/dashboard.php', 'layout-dashboard', 'Dashboard', 'dashboard', $currentPage); ?>
            <?php nav_link('manager/reports.php', 'bar-chart-3', 'Reports & Statistics', 'reports', $currentPage); ?>

            <li class="nav-label">Others</li>
            <?php nav_link('manager/notifications.php', 'bell', 'Notifications', 'notifications', $currentPage, $unreadCount); ?>
            <?php nav_link('manager/profile.php', 'user', 'My Profile', 'profile', $currentPage); ?>

        <?php elseif ($role === 'Applicant'): ?>
            <li class="nav-label">Menu</li>
            <?php nav_link('applicant/dashboard.php', 'layout-dashboard', 'Dashboard', 'dashboard', $currentPage); ?>
            <?php nav_link('applicant/submit_application.php', 'plus-circle', 'Submit Application', 'submit', $currentPage); ?>
            <?php nav_link('applicant/my_applications.php', 'file-text', 'My Applications', 'my_applications', $currentPage); ?>

            <li class="nav-label">Others</li>
            <?php nav_link('applicant/notifications.php', 'bell', 'Notifications', 'notifications', $currentPage, $unreadCount); ?>
            <?php nav_link('applicant/profile.php', 'user', 'My Profile', 'profile', $currentPage); ?>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="sidebar-dark-mode-row">
            <span class="dark-mode-label">
                <i data-lucide="moon" style="width:16px;height:16px;"></i>
                Dark Mode
            </span>
            <label class="ui-switch" title="Toggle theme">
                <input type="checkbox" id="themeSwitch">
                <span class="ui-switch-slider"></span>
            </label>
        </div>
        <div class="sidebar-user-quick">
            <span><?= clean($_SESSION['role_name'] ?? 'User') ?></span>
            <a href="<?= BASE_URL ?>logout.php" style="color:var(--color-danger);font-weight:600;display:inline-flex;align-items:center;gap:4px;" title="Sign out">
                <i data-lucide="log-out" style="width:14px;height:14px;"></i> Log Out
            </a>
        </div>
    </div>
</aside>
