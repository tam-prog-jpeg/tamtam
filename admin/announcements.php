<?php
/**
 * admin/announcements.php
 * -----------------------------------------------------------------
 * Create, edit, activate/deactivate, and delete announcements shown
 * to applicants on their dashboard.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Announcements';
$currentPage = 'announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = sanitize_input($_POST['title'] ?? '');
        $content = sanitize_input($_POST['content'] ?? '');
        if ($title !== '' && $content !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssi', $title, $content, $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_audit($conn, $_SESSION['user_id'], 'create_announcement', $title);
            set_flash('success', 'Announcement published successfully.');
        } else {
            set_flash('error', 'Title and content are required.');
        }
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $title = sanitize_input($_POST['title'] ?? '');
        $content = sanitize_input($_POST['content'] ?? '');
        $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, content = ? WHERE announcement_id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $title, $content, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        log_audit($conn, $_SESSION['user_id'], 'update_announcement', "Updated announcement #$id");
        set_flash('success', 'Announcement updated successfully.');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $newVal = (int) ($_POST['new_active'] ?? 1);
        $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = ? WHERE announcement_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $newVal, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Announcement visibility updated.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE announcement_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        log_audit($conn, $_SESSION['user_id'], 'delete_announcement', "Deleted announcement #$id");
        set_flash('success', 'Announcement deleted.');
    }
    redirect('admin/announcements.php');
}

$query = mysqli_query($conn, "
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a JOIN users u ON u.user_id = a.posted_by
    ORDER BY a.posted_at DESC
");

$items = [];
$activeCount = 0;
$hiddenCount = 0;
while ($row = mysqli_fetch_assoc($query)) {
    $items[] = $row;
    if ($row['is_active']) { $activeCount++; } else { $hiddenCount++; }
}
$totalCount = count($items);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">System Announcements &amp; Broadcasts</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Publish campus notices, policy updates, and application deadlines to applicant and faculty portals.</div>
    </div>
    <div class="action-controls-group">
        <button type="button" class="btn btn-primary" data-modal-target="#addAnnouncementModal" title="Create a new system announcement">
            <i data-lucide="plus" style="width:16px;height:16px;"></i>
            New Announcement
        </button>
    </div>
</div>

<!-- =====================================================================
     3 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
    <!-- Card 1: Total Announcements -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Announcements</span>
            <span class="info-icon" title="Total notices created across the system lifetime">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($totalCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="annGradBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,30 Q25,22 50,14 T100,6 L100,40 L0,40 Z" fill="url(#annGradBlue)"/>
                <path d="M0,30 Q25,22 50,14 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="bell" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Institutional broadcasts</span>
        </div>
    </div>

    <!-- Card 2: Active Broadcasts -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Active Broadcasts</span>
            <span class="info-icon" title="Notices currently visible on the Applicant and Faculty dashboards">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($activeCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="annGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,34 Q25,24 50,14 T100,4 L100,40 L0,40 Z" fill="url(#annGradGreen)"/>
                <path d="M0,34 Q25,24 50,14 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="eye" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Live on applicant portals</span>
        </div>
    </div>

    <!-- Card 3: Hidden / Archived -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Hidden / Archived</span>
            <span class="info-icon" title="Announcements saved as drafts or deactivated from view">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($hiddenCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="annGradMuted" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#9CA3AF" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#9CA3AF" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,20 Q30,30 60,20 T100,24 L100,40 L0,40 Z" fill="url(#annGradMuted)"/>
                <path d="M0,20 Q30,30 60,20 T100,24" stroke="#9CA3AF" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="eye-off" style="width:13px;height:13px;color:var(--color-text-subtle);"></i>
            <span>Archived / non-published</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     ANNOUNCEMENTS TABLE CARD
     ===================================================================== -->
<div class="card" style="border-radius:var(--radius-lg);">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
        <div>
            <h3 style="margin:0 0 2px;">Published Announcements</h3>
            <span style="font-size:12.5px;color:var(--color-text-muted);">Manage news updates and announcements pushed to applicant dashboards.</span>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-modal-target="#addAnnouncementModal">
            <i data-lucide="plus" style="width:13px;height:13px;"></i>
            New Announcement
        </button>
    </div>
    <div class="table-wrap">
        <?php if ($totalCount === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <i data-lucide="megaphone" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:12px;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;color:var(--color-text);">No announcements yet</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">Create your first announcement to broadcast updates to all users.</p>
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="#addAnnouncementModal" style="margin-top:14px;">
                    <i data-lucide="plus" style="width:13px;height:13px;"></i>
                    Create Announcement
                </button>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title &amp; Announcement Content</th>
                    <th style="width:180px;">Author</th>
                    <th style="width:130px;">Date Posted</th>
                    <th style="width:110px;">Visibility</th>
                    <th style="text-align:right;width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $a): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);font-size:14px;margin-bottom:4px;"><?= clean($a['title']) ?></div>
                        <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;max-width:520px;">
                            <?= clean(mb_strimwidth($a['content'], 0, 140, '…')) ?>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <i data-lucide="user" style="width:13px;height:13px;color:var(--color-text-subtle);"></i>
                            <span style="font-size:13px;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></span>
                        </div>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($a['posted_at'], 'M d, Y') ?>
                        <div style="font-size:11px;color:var(--color-text-subtle);"><?= format_date($a['posted_at'], 'h:i A') ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $a['is_active'] ? 'badge-success' : 'badge-secondary' ?>" style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:3px 10px;">
                            <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                            <?= $a['is_active'] ? 'Active' : 'Hidden' ?>
                        </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <div style="display:inline-flex;gap:6px;align-items:center;">
                            <button type="button" class="btn btn-outline btn-sm" data-modal-target="#editAnnouncement<?= (int)$a['announcement_id'] ?>" title="Edit Announcement">
                                <i data-lucide="edit-3" style="width:13px;height:13px;"></i>
                                Edit
                            </button>
                            <form method="POST" style="display:inline;margin:0;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">
                                <input type="hidden" name="new_active" value="<?= $a['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="<?= $a['is_active'] ? 'Hide from applicants' : 'Publish to applicants' ?>">
                                    <i data-lucide="<?= $a['is_active'] ? 'eye-off' : 'eye' ?>" style="width:13px;height:13px;"></i>
                                    <?= $a['is_active'] ? 'Hide' : 'Show' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Permanently delete this announcement?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Announcement">
                                    <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- =====================================================================
     MODALS (Rendered outside table for valid DOM structure)
     ===================================================================== -->

<!-- Add Announcement Modal -->
<div class="modal-overlay" id="addAnnouncementModal">
    <div class="modal-box" style="max-width:540px;border-radius:var(--radius-lg);">
        <form method="POST">
            <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--color-border);">
                <h3 style="margin:0;font-size:16px;display:flex;align-items:center;gap:8px;">
                    <i data-lucide="megaphone" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
                    New System Announcement
                </h3>
                <button type="button" class="modal-close" data-modal-close style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--color-text-muted);">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Announcement Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g., Intellectual Property Filing Workshop" required style="border-radius:var(--radius-md);">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Announcement Content</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="Enter the complete text or instructions for the university community…" required style="border-radius:var(--radius-md);"></textarea>
                </div>
            </div>
            <div class="modal-foot" style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="send" style="width:13px;height:13px;"></i>
                    Post Announcement
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Announcement Modals -->
<?php foreach ($items as $a): ?>
<div class="modal-overlay" id="editAnnouncement<?= (int)$a['announcement_id'] ?>">
    <div class="modal-box" style="max-width:540px;border-radius:var(--radius-lg);">
        <form method="POST">
            <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--color-border);">
                <h3 style="margin:0;font-size:16px;display:flex;align-items:center;gap:8px;">
                    <i data-lucide="edit-3" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
                    Edit Announcement
                </h3>
                <button type="button" class="modal-close" data-modal-close style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--color-text-muted);">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">
                <div class="form-group" style="margin-bottom:14px;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Announcement Title</label>
                    <input type="text" name="title" class="form-control" value="<?= clean($a['title']) ?>" required style="border-radius:var(--radius-md);">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">Announcement Content</label>
                    <textarea name="content" class="form-control" rows="5" required style="border-radius:var(--radius-md);"><?= clean($a['content']) ?></textarea>
                </div>
            </div>
            <div class="modal-foot" style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i data-lucide="check" style="width:13px;height:13px;"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

