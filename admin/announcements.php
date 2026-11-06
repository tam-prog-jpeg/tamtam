<?php
/**
 * admin/announcements.php
 * -----------------------------------------------------------------
 * Create, edit, attach picture/media, activate/deactivate, and delete
 * announcements shown to applicants and on the public Landing Page.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Announcements';
$currentPage = 'announcements';

// Helper for announcement image upload
function handle_announcement_image_upload($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    $uploadDir = __DIR__ . '/../uploads/announcements';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $filename = 'ann_' . uniqid() . '_' . time() . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return 'uploads/announcements/' . $filename;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = sanitize_input($_POST['title'] ?? '');
        $content = sanitize_input($_POST['content'] ?? '');
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploaded = handle_announcement_image_upload($_FILES['image']);
            if ($uploaded === false) {
                set_flash('error', 'Invalid image file. Please upload JPG, PNG, or WEBP up to 5MB.');
                redirect('admin/announcements.php');
            }
            $imagePath = $uploaded;
        }

        if ($title !== '' && $content !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, image_path, posted_by) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssi', $title, $content, $imagePath, $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            log_audit($conn, $_SESSION['user_id'], 'create_announcement', $title);
            set_flash('success', 'Announcement published with image to portal and landing page.');
        } else {
            set_flash('error', 'Title and content are required.');
        }
    }

    if ($action === 'edit') {
        $id = (int) ($_POST['announcement_id'] ?? 0);
        $title = sanitize_input($_POST['title'] ?? '');
        $content = sanitize_input($_POST['content'] ?? '');
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

        $curStmt = mysqli_prepare($conn, "SELECT image_path FROM announcements WHERE announcement_id = ?");
        mysqli_stmt_bind_param($curStmt, 'i', $id);
        mysqli_stmt_execute($curStmt);
        $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($curStmt));
        mysqli_stmt_close($curStmt);

        $imagePath = $cur['image_path'] ?? null;

        if ($removeImage && $imagePath) {
            @unlink(__DIR__ . '/../' . $imagePath);
            $imagePath = null;
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploaded = handle_announcement_image_upload($_FILES['image']);
            if ($uploaded !== false) {
                if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                    @unlink(__DIR__ . '/../' . $imagePath);
                }
                $imagePath = $uploaded;
            }
        }

        $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, content = ?, image_path = ? WHERE announcement_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $content, $imagePath, $id);
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
        $curStmt = mysqli_prepare($conn, "SELECT image_path FROM announcements WHERE announcement_id = ?");
        mysqli_stmt_bind_param($curStmt, 'i', $id);
        mysqli_stmt_execute($curStmt);
        $cur = mysqli_fetch_assoc(mysqli_stmt_get_result($curStmt));
        mysqli_stmt_close($curStmt);
        if (!empty($cur['image_path']) && file_exists(__DIR__ . '/../' . $cur['image_path'])) {
            @unlink(__DIR__ . '/../' . $cur['image_path']);
        }

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

<!-- Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">System Announcements &amp; Broadcasts</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">
            Publish notices, photo broadcasts, and application deadlines to applicant dashboards and the system's Landing Page.
        </div>
    </div>
    <div class="action-controls-group">
        <button type="button" class="btn btn-primary" data-modal-target="#addAnnouncementModal" title="Create a new system announcement">
            <i data-lucide="plus" style="width:16px;height:16px;"></i>
            New Announcement
        </button>
    </div>
</div>

<!-- KPI Summary Grid -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);margin-bottom:20px;">
    <!-- Total Announcements -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Announcements</span>
            <span class="info-icon" title="Total notices created across system lifetime">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($totalCount) ?></div>
            <?= render_kpi_sparkline($totalCount, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="megaphone" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Institutional broadcasts</span>
        </div>
    </div>

    <!-- Active Broadcasts -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Published on Landing &amp; Portal</span>
            <span class="info-icon" title="Notices currently live on Landing Page and applicant dashboards">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-success);"><?= number_format($activeCount) ?></div>
            <?= render_kpi_sparkline($activeCount, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="globe" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Live on public Landing Page</span>
        </div>
    </div>

    <!-- Hidden Notices -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Hidden / Draft Notices</span>
            <span class="info-icon" title="Draft or expired notices hidden from public view">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-text-muted);"><?= number_format($hiddenCount) ?></div>
            <?= render_kpi_sparkline($hiddenCount, 'gray') ?>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="eye-off" style="width:13px;height:13px;color:var(--color-text-subtle);"></i>
            <span>Archived or hidden</span>
        </div>
    </div>
</div>

<!-- Announcements Table Card -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Institutional Announcements Registry</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= $totalCount ?> Notices</span>
        </div>
    </div>
    <div class="table-wrap">
        <?php if ($totalCount === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <i data-lucide="megaphone-off" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:12px;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;">No announcements published yet</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">Create your first university broadcast with optional image attachment.</p>
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="#addAnnouncementModal" style="margin-top:14px;">
                    <i data-lucide="plus" style="width:13px;height:13px;"></i>
                    Create Announcement
                </button>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:60px;">Image</th>
                    <th>Title &amp; Announcement Content</th>
                    <th style="width:170px;">Author</th>
                    <th style="width:130px;">Date Posted</th>
                    <th style="width:110px;">Visibility</th>
                    <th style="text-align:right;width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $a): ?>
                <tr>
                    <td>
                        <?php if (!empty($a['image_path'])): ?>
                            <img src="<?= BASE_URL . clean($a['image_path']) ?>" alt="" 
                                 style="width:52px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border);display:block;">
                        <?php else: ?>
                            <div style="width:52px;height:40px;border-radius:6px;background:var(--color-surface);border:1px dashed var(--color-border);display:flex;align-items:center;justify-content:center;color:var(--color-text-subtle);">
                                <i data-lucide="image" style="width:16px;height:16px;opacity:0.6;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--color-text);font-size:14px;margin-bottom:4px;"><?= clean($a['title']) ?></div>
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
                            <?= $a['is_active'] ? 'Active (Live)' : 'Hidden' ?>
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
                                <button type="submit" class="btn btn-outline btn-sm" title="<?= $a['is_active'] ? 'Hide from landing page & portal' : 'Publish to landing page & portal' ?>">
                                    <i data-lucide="<?= $a['is_active'] ? 'eye-off' : 'eye' ?>" style="width:13px;height:13px;"></i>
                                    <?= $a['is_active'] ? 'Hide' : 'Show' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Permanently delete this announcement and its image?');">
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
     MODALS WITH MULTIPART FILE UPLOAD
     ===================================================================== -->

<!-- Add Announcement Modal -->
<div class="modal-overlay" id="addAnnouncementModal">
    <div class="modal-box" style="max-width:580px;border-radius:var(--radius-lg);">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="megaphone" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">New System Broadcast</h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Publish announcements to the Landing Page and applicant dashboards.</div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label" for="addAnnTitle">
                            Announcement Headline / Title
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <span class="field-counter" id="addAnnTitleCount">0 / 120</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="heading" class="input-icon-left"></i>
                        <input type="text" id="addAnnTitle" name="title" class="form-control" 
                               placeholder="e.g. Intellectual Property Registration Workshop" 
                               maxlength="120" data-char-counter="#addAnnTitleCount" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="addAnnImage">
                        Attach Announcement Picture / Media (Optional)
                    </label>
                    <input type="file" id="addAnnImage" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div class="form-hint" style="margin-top:5px;font-size:11.5px;color:var(--color-text-muted);">
                        <i data-lucide="info"></i>
                        <span>Showcased on the system Landing Page and dashboard banners (JPG, PNG, WEBP, max 5MB).</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <div class="form-label-row">
                        <label class="form-label" for="addAnnContent">
                            Announcement Body Text
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>
                        <span class="field-counter" id="addAnnContentCount">0 / 1000</span>
                    </div>
                    <textarea id="addAnnContent" name="content" class="form-control" rows="5" 
                              placeholder="Enter complete details, schedule, venue, or application guidelines…" 
                              maxlength="1000" data-char-counter="#addAnnContentCount" required></textarea>
                </div>
            </div>
            <div class="modal-foot" style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 18px;">
                    <i data-lucide="send" style="width:14px;height:14px;"></i>
                    Publish Broadcast
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Announcement Modals -->
<?php foreach ($items as $a): ?>
<div class="modal-overlay" id="editAnnouncement<?= (int)$a['announcement_id'] ?>">
    <div class="modal-box" style="max-width:580px;border-radius:var(--radius-lg);">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-head" style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i data-lucide="edit-3" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:700;">Edit Announcement #<?= (int)$a['announcement_id'] ?></h3>
                        <div style="font-size:12px;color:var(--color-text-muted);">Posted on <?= format_date($a['posted_at'], 'M d, Y') ?> by <?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    </div>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="Close modal">&times;</button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="announcement_id" value="<?= (int)$a['announcement_id'] ?>">

                <div class="form-group">
                    <div class="form-label-row">
                        <label class="form-label" for="editAnnTitle<?= $a['announcement_id'] ?>">Announcement Headline</label>
                        <span class="field-counter" id="editAnnTitleCount<?= $a['announcement_id'] ?>">0 / 120</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="heading" class="input-icon-left"></i>
                        <input type="text" id="editAnnTitle<?= $a['announcement_id'] ?>" name="title" class="form-control" 
                               value="<?= clean($a['title']) ?>" maxlength="120" 
                               data-char-counter="#editAnnTitleCount<?= $a['announcement_id'] ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editAnnImage<?= $a['announcement_id'] ?>">Announcement Picture (Optional)</label>
                    <?php if (!empty($a['image_path'])): ?>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;padding:8px 12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:6px;">
                            <img src="<?= BASE_URL . clean($a['image_path']) ?>" alt="" style="width:64px;height:48px;object-fit:cover;border-radius:4px;border:1px solid var(--color-border);">
                            <div style="flex:1;font-size:12px;color:var(--color-text-muted);">Currently attached image</div>
                            <label style="font-size:12px;color:var(--color-danger);cursor:pointer;display:flex;align-items:center;gap:4px;margin:0;">
                                <input type="checkbox" name="remove_image" value="1"> Remove
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="editAnnImage<?= $a['announcement_id'] ?>" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <div class="form-hint" style="margin-top:5px;font-size:11.5px;color:var(--color-text-muted);">
                        <span>Choose a new file to replace the existing image (max 5MB).</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0;">
                    <div class="form-label-row">
                        <label class="form-label" for="editAnnContent<?= $a['announcement_id'] ?>">Announcement Content</label>
                        <span class="field-counter" id="editAnnContentCount<?= $a['announcement_id'] ?>">0 / 1000</span>
                    </div>
                    <textarea id="editAnnContent<?= $a['announcement_id'] ?>" name="content" class="form-control" rows="5" 
                              maxlength="1000" data-char-counter="#editAnnContentCount<?= $a['announcement_id'] ?>" required><?= clean($a['content']) ?></textarea>
                </div>
            </div>
            <div class="modal-foot" style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--color-border);background:var(--color-surface-subtle);border-bottom-left-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg);">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 18px;">
                    <i data-lucide="check" style="width:14px;height:14px;"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
