<?php
/**
 * admin/applicant_view.php
 * -----------------------------------------------------------------
 * Read-only profile view of a single applicant for the Administrator:
 *  - contact + affiliation details
 *  - full list of every application they have ever submitted
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Applicant Profile';
$currentPage = 'applicants';

$applicantId = (int) ($_GET['id'] ?? 0);
if ($applicantId <= 0) {
    set_flash('error', 'Invalid applicant.');
    redirect('admin/applicants.php');
}

$stmt = mysqli_prepare($conn, "
    SELECT ap.*, u.first_name, u.last_name, u.email, u.contact_number, u.status, u.username, u.created_at,
           c.college_name, d.department_name
    FROM applicants ap
    JOIN users u ON u.user_id = ap.user_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    LEFT JOIN departments d ON d.department_id = ap.department_id
    WHERE ap.applicant_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $applicantId);
mysqli_stmt_execute($stmt);
$applicant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$applicant) {
    set_flash('error', 'Applicant not found.');
    redirect('admin/applicants.php');
}

$appsStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name
    FROM applications a
    JOIN application_types t ON t.type_id = a.type_id
    WHERE a.applicant_id = ?
    ORDER BY a.submitted_at DESC
");
mysqli_stmt_bind_param($appsStmt, 'i', $applicantId);
mysqli_stmt_execute($appsStmt);
$applications = mysqli_stmt_get_result($appsStmt);

$initials = strtoupper(substr($applicant['first_name'] ?? 'U', 0, 1) . substr($applicant['last_name'] ?? '', 0, 1));

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar with Navigation -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <a href="<?= BASE_URL ?>admin/applicants.php" class="btn btn-outline btn-sm btn-icon" title="Back to directory">
                <svg class="lucide-icon" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            </a>
            <span class="badge badge-info" style="font-size:11px;">
                <?= clean($applicant['applicant_type']) ?>
            </span>
            <span class="badge <?= $applicant['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                <?= ucfirst($applicant['status']) ?>
            </span>
        </div>
        <h2><?= clean($applicant['first_name'] . ' ' . $applicant['last_name']) ?></h2>
        <p>Registered on <?= format_date($applicant['created_at'], 'M d, Y') ?> &middot; Username: <code style="font-family:var(--font-mono);font-size:12px;background:var(--color-bg);padding:2px 6px;border-radius:4px;border:1px solid var(--color-border);"><?= clean($applicant['username']) ?></code></p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/applicants.php" class="btn btn-outline btn-sm">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
            All Applicants
        </a>
    </div>
</div>

<!-- 2. Two-Column Layout -->
<div class="grid-2 reverse" style="grid-template-columns: 1fr 1.6fr; gap:20px; align-items:start;">
    
    <!-- LEFT: Applicant Profile Dossier -->
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:8px;">
            <svg class="lucide-icon" style="color:var(--color-primary-600);" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <h3>Profile & Affiliation</h3>
        </div>
        <div class="card-body">
            <!-- Profile Avatar Block -->
            <div style="display:flex;align-items:center;gap:14px;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid var(--color-border);">
                <div style="width:52px;height:52px;border-radius:50%;background:var(--color-primary-50);color:var(--color-primary-600);font-weight:700;font-size:18px;display:flex;align-items:center;justify-content:center;border:1px solid var(--color-border);flex-shrink:0;">
                    <?= $initials ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:16px;color:var(--color-text);"><?= clean($applicant['first_name'] . ' ' . $applicant['last_name']) ?></div>
                    <div style="font-size:12.5px;color:var(--color-text-muted);"><?= clean($applicant['email']) ?></div>
                </div>
            </div>

            <!-- Profile Attributes -->
            <div style="display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Account Username</span>
                    <span style="font-family:var(--font-mono);font-weight:600;color:var(--color-text);"><?= clean($applicant['username']) ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Primary Email</span>
                    <span style="font-weight:500;color:var(--color-text);"><?= clean($applicant['email']) ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Contact Number</span>
                    <span style="font-family:var(--font-mono);color:var(--color-text);"><?= clean($applicant['contact_number'] ?: '—') ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Applicant Category</span>
                    <span class="badge badge-info"><?= clean($applicant['applicant_type']) ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:start;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">College</span>
                    <span style="font-weight:500;color:var(--color-text);text-align:right;max-width:60%;"><?= clean($applicant['college_name'] ?? '—') ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:start;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Department</span>
                    <span style="font-weight:500;color:var(--color-text);text-align:right;max-width:60%;"><?= clean($applicant['department_name'] ?? '—') ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:start;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Mailing Address</span>
                    <span style="font-size:12.5px;color:var(--color-text);text-align:right;max-width:60%;"><?= clean($applicant['address'] ?: '—') ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;border-bottom:1px solid var(--color-border-subtle);padding-bottom:8px;">
                    <span style="color:var(--color-text-muted);">Account Status</span>
                    <span class="badge <?= $applicant['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst($applicant['status']) ?></span>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;">
                    <span style="color:var(--color-text-muted);">Registration Date</span>
                    <span style="font-family:var(--font-mono);color:var(--color-text-muted);font-size:12px;"><?= format_date($applicant['created_at'], 'M d, Y') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Submitted Applications History -->
    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:8px;">
                <svg class="lucide-icon" style="color:var(--color-primary-600);" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                <h3>Submitted IP Applications</h3>
            </div>
            <span class="badge badge-secondary"><?= mysqli_num_rows($applications) ?> Total</span>
        </div>
        <div class="table-wrap">
            <?php if (mysqli_num_rows($applications) === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/></svg>
                    </div>
                    <h4>No applications submitted yet</h4>
                    <p>This applicant has not filed any intellectual property submissions in the system.</p>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tracking Code</th>
                        <th>Invention Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" class="tracking" style="color:var(--color-primary-600);font-weight:600;text-decoration:none;">
                                <?= clean($a['tracking_code']) ?>
                            </a>
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--color-text);max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= clean($a['title']) ?>">
                                <?= clean($a['title']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary" style="font-size:11px;">
                                <?= clean($a['type_name']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= status_badge_class($a['status']) ?>">
                                <?= status_label($a['status']) ?>
                            </span>
                        </td>
                        <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                            <?= format_date($a['submitted_at'], 'M d, Y') ?>
                        </td>
                        <td style="text-align:right;">
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/application_view.php?id=<?= $a['application_id'] ?>" title="View Dossier">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
