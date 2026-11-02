<?php
/**
 * applicant/application_view.php
 * -----------------------------------------------------------------
 * Applicant-facing detail/tracking view for ONE of their own
 * applications (ownership is verified — an applicant can never view
 * another applicant's record). Shows status history, documents, and
 * lets them upload additional supporting documents. If approved,
 * shows a link to the printable approval certificate.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$pageTitle   = 'Application Details';
$currentPage = 'my_applications';

$applicationId = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT applicant_id FROM applicants WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$applicantId = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['applicant_id'] ?? 0;
mysqli_stmt_close($stmt);

// ---- Handle additional document upload ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    // Re-verify ownership before allowing any write.
    $own = mysqli_prepare($conn, "SELECT application_id FROM applications WHERE application_id = ? AND applicant_id = ?");
    mysqli_stmt_bind_param($own, 'ii', $applicationId, $applicantId);
    mysqli_stmt_execute($own);
    $owns = mysqli_num_rows(mysqli_stmt_get_result($own)) > 0;
    mysqli_stmt_close($own);

    if (!$owns) {
        set_flash('error', 'You do not have access to that application.');
        redirect('applicant/my_applications.php');
    }

    $postAction = $_POST['action'] ?? 'upload';

    // ---- Edit title/description while a revision is requested ----
    if ($postAction === 'edit_details') {
        $current = mysqli_prepare($conn, "SELECT status FROM applications WHERE application_id = ?");
        mysqli_stmt_bind_param($current, 'i', $applicationId);
        mysqli_stmt_execute($current);
        $currentStatus = mysqli_fetch_assoc(mysqli_stmt_get_result($current))['status'] ?? null;
        mysqli_stmt_close($current);

        if ($currentStatus !== 'returned') {
            set_flash('error', 'This application can only be edited while it is "Returned for Revision".');
            redirect('applicant/application_view.php?id=' . $applicationId);
        }

        $newTitle = sanitize_input($_POST['title'] ?? '');
        $newDescription = sanitize_input($_POST['description'] ?? '');

        if ($newTitle === '') {
            set_flash('error', 'Title cannot be empty.');
            redirect('applicant/application_view.php?id=' . $applicationId);
        }

        $upd = mysqli_prepare($conn, "UPDATE applications SET title = ?, description = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($upd, 'ssi', $newTitle, $newDescription, $applicationId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        log_audit($conn, $_SESSION['user_id'], 'edit_application', "Applicant edited application #$applicationId while revising");
        set_flash('success', 'Application details updated. You can now upload/replace documents and resubmit when ready.');
        redirect('applicant/application_view.php?id=' . $applicationId);
    }

    // ---- Delete a document while a revision is requested ----
    if ($postAction === 'delete_document') {
        $current = mysqli_prepare($conn, "SELECT status FROM applications WHERE application_id = ?");
        mysqli_stmt_bind_param($current, 'i', $applicationId);
        mysqli_stmt_execute($current);
        $currentStatus = mysqli_fetch_assoc(mysqli_stmt_get_result($current))['status'] ?? null;
        mysqli_stmt_close($current);

        if ($currentStatus !== 'returned') {
            set_flash('error', 'Documents can only be removed while the application is "Returned for Revision".');
            redirect('applicant/application_view.php?id=' . $applicationId);
        }

        $docId = (int) ($_POST['document_id'] ?? 0);
        $docStmt = mysqli_prepare($conn, "SELECT file_path FROM documents WHERE document_id = ? AND application_id = ?");
        mysqli_stmt_bind_param($docStmt, 'ii', $docId, $applicationId);
        mysqli_stmt_execute($docStmt);
        $doc = mysqli_fetch_assoc(mysqli_stmt_get_result($docStmt));
        mysqli_stmt_close($docStmt);

        if ($doc) {
            $fullPath = __DIR__ . '/../' . $doc['file_path'];
            if (is_file($fullPath)) { @unlink($fullPath); }

            $del = mysqli_prepare($conn, "DELETE FROM documents WHERE document_id = ? AND application_id = ?");
            mysqli_stmt_bind_param($del, 'ii', $docId, $applicationId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);

            log_audit($conn, $_SESSION['user_id'], 'delete_document', "Applicant removed a document from application #$applicationId while revising");
            set_flash('success', 'Document removed.');
        }
        redirect('applicant/application_view.php?id=' . $applicationId);
    }

    // ---- Resubmit: send a "returned" application back into the pipeline ----
    if ($postAction === 'resubmit') {
        $current = mysqli_prepare($conn, "SELECT status, assigned_staff_id FROM applications WHERE application_id = ?");
        mysqli_stmt_bind_param($current, 'i', $applicationId);
        mysqli_stmt_execute($current);
        $currentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($current));
        mysqli_stmt_close($current);

        if (!$currentRow || $currentRow['status'] !== 'returned') {
            set_flash('error', 'Only applications marked "Returned for Revision" can be resubmitted.');
            redirect('applicant/application_view.php?id=' . $applicationId);
        }

        $upd = mysqli_prepare($conn, "UPDATE applications SET status = 'pending' WHERE application_id = ?");
        mysqli_stmt_bind_param($upd, 'i', $applicationId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, 'pending', 'Resubmitted by applicant after revision.', ?)");
        mysqli_stmt_bind_param($hist, 'ii', $applicationId, $_SESSION['user_id']);
        mysqli_stmt_execute($hist);
        mysqli_stmt_close($hist);

        // Notify the staff member it was previously assigned to, or every
        // active Staff member if it was never assigned to anyone specific.
        if (!empty($currentRow['assigned_staff_id'])) {
            create_notification($conn, $currentRow['assigned_staff_id'], 'Application Resubmitted', 'An applicant resubmitted a previously returned application for re-verification.', 'staff/application_view.php?id=' . $applicationId);
        } else {
            $staffUsers = mysqli_query($conn, "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'Staff' AND u.status = 'active'");
            while ($s = mysqli_fetch_assoc($staffUsers)) {
                create_notification($conn, $s['user_id'], 'Application Resubmitted', 'An applicant resubmitted a previously returned application for verification.', 'staff/verify.php');
            }
        }

        log_audit($conn, $_SESSION['user_id'], 'resubmit_application', "Application #$applicationId resubmitted after revision");
        set_flash('success', 'Your application has been resubmitted for review.');
        redirect('applicant/application_view.php?id=' . $applicationId);
    }

    // ---- Upload additional document(s) ----
    if ($postAction === 'upload' && !empty($_FILES['documents']['name'][0])) {
        $uploadConfig = get_upload_config($conn);
        $anyUploaded = false;
        foreach ($_FILES['documents']['name'] as $i => $name) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $file = [
                'name' => $name, 'type' => $_FILES['documents']['type'][$i],
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'error' => $_FILES['documents']['error'][$i], 'size' => $_FILES['documents']['size'][$i],
            ];
            $check = validate_upload($file, $uploadConfig['allowed_types'], $uploadConfig['max_mb']);
            if ($check['ok']) {
                $stored = store_upload($file, $check['ext'], 'documents');
                if ($stored) {
                    $version = next_document_version($conn, $applicationId, $name);
                    $docIns = mysqli_prepare($conn, "INSERT INTO documents (application_id, uploaded_by, file_name, stored_name, file_path, file_type, file_size, version) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($docIns, 'iisssssi', $applicationId, $_SESSION['user_id'], $file['name'], $stored['stored_name'], $stored['file_path'], $check['ext'], $file['size'], $version);
                    mysqli_stmt_execute($docIns);
                    mysqli_stmt_close($docIns);
                    $anyUploaded = true;
                }
            } else {
                set_flash('error', $file['name'] . ': ' . $check['error']);
            }
        }
        if ($anyUploaded) {
            log_audit($conn, $_SESSION['user_id'], 'upload_document', "Applicant uploaded additional document(s) to application #$applicationId");
            set_flash('success', 'Document(s) uploaded successfully.');
        }
    }
    redirect('applicant/application_view.php?id=' . $applicationId);
}

// ---- Fetch application, verifying ownership ----
$stmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name
    FROM applications a JOIN application_types t ON t.type_id = a.type_id
    WHERE a.application_id = ? AND a.applicant_id = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $applicationId, $applicantId);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    set_flash('error', 'Application not found or you do not have access to it.');
    redirect('applicant/my_applications.php');
}

$docsStmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docsStmt, 'i', $applicationId);
mysqli_stmt_execute($docsStmt);
$documents = mysqli_stmt_get_result($docsStmt);

$histStmt = mysqli_prepare($conn, "
    SELECT h.*, u.first_name, u.last_name
    FROM application_status_history h LEFT JOIN users u ON u.user_id = h.changed_by
    WHERE h.application_id = ? ORDER BY h.changed_at DESC
");
mysqli_stmt_bind_param($histStmt, 'i', $applicationId);
mysqli_stmt_execute($histStmt);
$history = mysqli_stmt_get_result($histStmt);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Sub-Header Bar -->
<div class="sub-header-bar">
    <div style="display:flex;align-items:center;gap:12px;">
        <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline btn-sm btn-icon" title="Back to My Applications">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
        </a>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                <h2 style="font-size:18px;margin:0;font-weight:700;"><?= clean($application['title']) ?></h2>
                <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span>
            </div>
            <div style="font-size:12.5px;color:var(--color-text-muted);display:flex;align-items:center;gap:8px;">
                <span style="font-family:var(--font-mono);font-weight:600;color:var(--color-primary-600);background:var(--color-primary-50);padding:2px 8px;border-radius:6px;border:1px solid rgba(37,99,235,0.15);">
                    <?= clean($application['tracking_code']) ?>
                </span>
                <span>·</span>
                <span>Category: <strong><?= clean($application['type_name']) ?></strong></span>
                <span>·</span>
                <span>Filed <?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?></span>
            </div>
        </div>
    </div>
    <div class="action-controls-group">
        <?php if ($application['status'] === 'approved'): ?>
            <a href="<?= BASE_URL ?>applicant/certificate.php?id=<?= $applicationId ?>" class="btn btn-success btn-sm" target="_blank" title="Download official registration certificate">
                <i data-lucide="award" style="width:15px;height:15px;"></i>
                Download Certificate
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline btn-sm">
            <i data-lucide="list" style="width:14px;height:14px;"></i>
            My Filings
        </a>
    </div>
</div>

<!-- 2. Two-Column Layout -->
<div style="display:grid;grid-template-columns:1.55fr 1fr;gap:24px;align-items:start;">
    <!-- Left Column: Dossier, Editing (if returned), & Documents -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <!-- Card 1: Application Dossier -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="file-text" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
                    <h3 style="margin:0;font-size:15px;">Technical Disclosure &amp; Overview</h3>
                </div>
                <span class="badge badge-secondary" style="font-family:var(--font-mono);font-size:11px;">
                    <?= clean($application['tracking_code']) ?>
                </span>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if ($application['status'] === 'returned'): ?>
                    <div style="background:var(--color-warning-bg);border:1px solid var(--color-warning);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:18px;">
                        <div style="font-weight:600;color:var(--color-warning);font-size:13.5px;display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <i data-lucide="alert-triangle" style="width:16px;height:16px;"></i>
                            Revision Requested by IPO Office
                        </div>
                        <div style="font-size:12.5px;color:var(--color-text);line-height:1.5;">
                            Please review the examiner's remarks in the timeline, adjust the title and description below, update supporting files if required, and click <strong>Resubmit for Review</strong>.
                        </div>
                    </div>

                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="edit_details">
                        <div class="form-group">
                            <label style="font-weight:600;font-size:13px;">Invention Title</label>
                            <input type="text" name="title" class="form-control" maxlength="255" value="<?= clean($application['title']) ?>" required style="height:40px;font-size:13.5px;">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;font-size:13px;">Technical Description &amp; Claims</label>
                            <textarea name="description" class="form-control" rows="5" style="font-size:13.5px;line-height:1.6;"><?= clean($application['description']) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i data-lucide="check" style="width:14px;height:14px;"></i>
                            Save Details
                        </button>
                    </form>
                <?php else: ?>
                    <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:18px;">
                        <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;font-weight:600;">Technical Description</div>
                        <p style="color:var(--color-text);font-size:13.5px;line-height:1.7;margin:0;"><?= nl2br(clean($application['description'] ?: 'No technical description provided.')) ?></p>
                    </div>

                    <table class="data-table">
                        <tr>
                            <td style="color:var(--color-text-muted);width:160px;font-size:13px;">Tracking Code</td>
                            <td>
                                <strong style="font-family:var(--font-mono);color:var(--color-primary-600);font-size:13px;"><?= clean($application['tracking_code']) ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:13px;">IP Category</td>
                            <td><span class="badge badge-secondary"><?= clean($application['type_name']) ?></span></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-size:13px;">Date Submitted</td>
                            <td class="tabular" style="font-size:13px;"><?= format_date($application['submitted_at'], 'F d, Y · h:i A') ?></td>
                        </tr>
                    </table>
                <?php endif; ?>

                <?php if ($application['status'] === 'returned'): ?>
                <div style="margin-top:20px;border-top:1px solid var(--color-border);padding-top:16px;">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="resubmit">
                        <button type="submit" class="btn btn-warning btn-sm" data-confirm="Resubmit this revised application to IPO Staff for re-screening?">
                            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i>
                            Resubmit Application for Review
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 2: Attached Documents Registry -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="paperclip" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
                    <h3 style="margin:0;font-size:15px;">Supporting Documents</h3>
                </div>
                <span style="font-size:12px;color:var(--color-text-muted);"><?= mysqli_num_rows($documents) ?> file<?= mysqli_num_rows($documents) === 1 ? '' : 's' ?> attached</span>
            </div>
            <div class="table-wrap">
                <?php if (mysqli_num_rows($documents) === 0): ?>
                    <div class="empty-state" style="padding:32px 20px;text-align:center;">
                        <i data-lucide="file-question" style="width:36px;height:36px;color:var(--color-text-subtle);margin-bottom:8px;"></i>
                        <p style="margin:0;font-size:13px;color:var(--color-text-muted);">No documents uploaded yet.</p>
                    </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document Title</th>
                            <th>Format</th>
                            <th>Version</th>
                            <th>Uploaded</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = mysqli_fetch_assoc($documents)): ?>
                        <tr>
                            <td>
                                <strong style="font-size:13px;color:var(--color-text);"><?= clean($doc['file_name']) ?></strong>
                            </td>
                            <td><span class="badge badge-secondary" style="font-size:11px;"><?= strtoupper(clean($doc['file_type'])) ?></span></td>
                            <td><span style="font-family:var(--font-mono);font-size:11.5px;color:var(--color-text-muted);">v<?= (int) $doc['version'] ?></span></td>
                            <td class="tabular" style="font-size:12px;color:var(--color-text-muted);"><?= format_date($doc['uploaded_at'], 'M d, Y') ?></td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;justify-content:flex-end;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($doc['file_path']) ?>','<?= clean($doc['file_type']) ?>','<?= clean(addslashes($doc['file_name'])) ?>')" title="Quick Preview">
                                        <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                        Preview
                                    </button>
                                    <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($doc['file_path']) ?>" target="_blank" download title="Download File">
                                        <i data-lucide="download" style="width:13px;height:13px;"></i>
                                        Download
                                    </a>
                                    <?php if ($application['status'] === 'returned'): ?>
                                    <form method="POST" onsubmit="return confirm('Permanently remove this document attachment?');" style="margin:0;display:inline-flex;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_document">
                                        <input type="hidden" name="document_id" value="<?= $doc['document_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove Document">
                                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Upload Additional Documents (if still in progress) -->
            <?php if (!in_array($application['status'], ['approved', 'rejected'], true)): 
                $appUploadConfig = get_upload_config($conn);
                $appAcceptAttr   = '.' . implode(',.', $appUploadConfig['allowed_types']);
                $appAcceptLabel  = strtoupper(implode(', ', $appUploadConfig['allowed_types']));
            ?>
            <div class="card-body" style="border-top:1px solid var(--color-border);background:var(--color-surface-subtle);padding:18px 20px;">
                <form method="POST" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="font-weight:600;font-size:12.5px;">Upload Additional Document</label>
                        <input type="file" name="documents[]" class="form-control" multiple accept="<?= clean($appAcceptAttr) ?>" style="font-size:12.5px;padding:6px 12px;height:38px;">
                        <div class="form-hint" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">
                            Accepted: <?= clean($appAcceptLabel) ?> · Max <?= (int)$appUploadConfig['max_mb'] ?>MB each.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="upload" style="width:14px;height:14px;"></i>
                        Upload Files
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Status Audit Trail & Official Actions -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <!-- Card 3: Certificate Ready (If Approved) -->
        <?php if ($application['status'] === 'approved'): ?>
        <div class="card" style="border-radius:var(--radius-lg);border:1px solid var(--color-success);background:rgba(16,185,129,0.04);">
            <div class="card-body" style="padding:20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:rgba(16,185,129,0.15);color:var(--color-success);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i data-lucide="award" style="width:24px;height:24px;"></i>
                </div>
                <h3 style="margin:0 0 4px;font-size:16px;color:var(--color-text);">Official Registration Granted</h3>
                <p style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;margin:0 0 16px;">
                    This application has received executive approval from the IPO Director. Your official university certificate is available for download.
                </p>
                <a href="<?= BASE_URL ?>applicant/certificate.php?id=<?= $applicationId ?>" class="btn btn-success" target="_blank" style="width:100%;justify-content:center;padding:10px;">
                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                    Download Official Certificate
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card 4: Evaluation Progress Timeline -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                <i data-lucide="git-commit" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
                <h3 style="margin:0;font-size:15px;">Status History &amp; Remarks</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if (mysqli_num_rows($history) === 0): ?>
                    <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No history recorded yet.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:18px;">
                        <?php while ($h = mysqli_fetch_assoc($history)): ?>
                        <div style="border-left:2px solid var(--color-primary-600);padding-left:14px;position:relative;">
                            <span class="badge <?= status_badge_class($h['status']) ?>" style="font-size:11px;">
                                <?= status_label($h['status']) ?>
                            </span>
                            <div class="tabular" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">
                                <?= format_date($h['changed_at'], 'M d, Y · h:i A') ?>
                            </div>
                            <?php if ($h['remarks']): ?>
                                <div style="font-size:12.5px;color:var(--color-text);margin-top:6px;line-height:1.5;background:var(--color-bg);padding:8px 10px;border-radius:6px;border:1px solid var(--color-border-subtle);">
                                    <?= clean($h['remarks']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

