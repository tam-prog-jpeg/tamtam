<?php
/**
 * staff/application_view.php
 * -----------------------------------------------------------------
 * Staff review screen for one application:
 *  - view details + uploaded documents
 *  - Forward to Evaluator (assigns staff+evaluator, status -> under_review)
 *  - Return for Revision (status -> returned, remarks required)
 *  - Reject (status -> rejected, remarks required)
 * Every action notifies the applicant and logs history + audit.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Review Application';
$currentPage = 'verify';

$applicationId = (int) ($_GET['id'] ?? 0);
if ($applicationId <= 0) {
    set_flash('error', 'Invalid application.');
    redirect('staff/verify.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    $ownerStmt = mysqli_prepare($conn, "SELECT a.status, u.user_id FROM applications a JOIN applicants ap ON ap.applicant_id = a.applicant_id JOIN users u ON u.user_id = ap.user_id WHERE a.application_id = ?");
    mysqli_stmt_bind_param($ownerStmt, 'i', $applicationId);
    mysqli_stmt_execute($ownerStmt);
    $ownerRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ownerStmt));
    mysqli_stmt_close($ownerStmt);
    $applicantUserId = $ownerRow['user_id'] ?? null;

    if (!$ownerRow || $ownerRow['status'] !== 'pending') {
        set_flash('error', 'This application has already been processed and no longer needs staff action.');
        redirect('staff/application_view.php?id=' . $applicationId);
    }

    if ($action === 'forward') {
        $evaluatorId = (int) ($_POST['evaluator_id'] ?? 0);
        if ($evaluatorId <= 0) {
            set_flash('error', 'Please select an evaluator to forward this application to.');
            redirect('staff/application_view.php?id=' . $applicationId);
        }
        $stmt = mysqli_prepare($conn, "UPDATE applications SET status = 'under_review', assigned_staff_id = ?, assigned_evaluator_id = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $_SESSION['user_id'], $evaluatorId, $applicationId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, 'under_review', 'Verified by staff and forwarded to an evaluator.', ?)");
        mysqli_stmt_bind_param($hist, 'ii', $applicationId, $_SESSION['user_id']);
        mysqli_stmt_execute($hist);
        mysqli_stmt_close($hist);

        create_notification($conn, $evaluatorId, 'Application Assigned for Evaluation', "An application has been forwarded to you for technical evaluation.", 'evaluator/evaluations.php');
        if ($applicantUserId) create_notification($conn, $applicantUserId, 'Application Verified', 'Your application passed initial staff screening and is now under technical evaluation.', 'applicant/my_applications.php');

        log_audit($conn, $_SESSION['user_id'], 'forward_application', "Forwarded application #$applicationId to evaluator #$evaluatorId");
        set_flash('success', 'Application verified and forwarded to evaluator.');
    }

    if ($action === 'return' || $action === 'reject') {
        $remarks = sanitize_input($_POST['remarks'] ?? '');
        if ($remarks === '') {
            set_flash('error', 'Please provide a remark explaining the reason.');
            redirect('staff/application_view.php?id=' . $applicationId);
        }
        $newStatus = $action === 'return' ? 'returned' : 'rejected';

        $stmt = mysqli_prepare($conn, "UPDATE applications SET status = ?, assigned_staff_id = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $_SESSION['user_id'], $applicationId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($hist, 'issi', $applicationId, $newStatus, $remarks, $_SESSION['user_id']);
        mysqli_stmt_execute($hist);
        mysqli_stmt_close($hist);

        if ($applicantUserId) create_notification($conn, $applicantUserId, 'Application ' . status_label($newStatus), $remarks, 'applicant/my_applications.php');

        log_audit($conn, $_SESSION['user_id'], 'verify_' . $action, "Application #$applicationId marked $newStatus");
        set_flash('success', 'Application marked as ' . status_label($newStatus) . '.');
    }
    redirect('staff/application_view.php?id=' . $applicationId);
}

$stmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name, u.first_name, u.last_name, u.email, u.contact_number,
           ap.applicant_type, c.college_name, d.department_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    WHERE a.application_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $applicationId);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('staff/verify.php');
}

$docsStmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docsStmt, 'i', $applicationId);
mysqli_stmt_execute($docsStmt);
$documents = mysqli_stmt_get_result($docsStmt);

$histStmt = mysqli_prepare($conn, "SELECT h.*, u.first_name, u.last_name FROM application_status_history h LEFT JOIN users u ON u.user_id = h.changed_by WHERE h.application_id = ? ORDER BY h.changed_at DESC");
mysqli_stmt_bind_param($histStmt, 'i', $applicationId);
mysqli_stmt_execute($histStmt);
$history = mysqli_stmt_get_result($histStmt);

$evaluatorList = mysqli_query($conn, "SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'Evaluator' AND u.status = 'active' ORDER BY u.first_name");

$canAct = $application['status'] === 'pending';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div style="display:flex;align-items:center;gap:14px;">
        <a href="<?= BASE_URL ?>staff/verify.php" class="btn btn-outline btn-icon" title="Return to Queue">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
        </a>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                <span class="tracking" style="font-size:12px;font-weight:700;"><?= clean($application['tracking_code']) ?></span>
                <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span>
                <span class="badge badge-secondary" style="font-size:11px;"><?= clean($application['type_name']) ?></span>
            </div>
            <h2 style="font-size:17px;margin:0;font-weight:700;line-height:1.3;color:var(--color-text);"><?= clean($application['title']) ?></h2>
        </div>
    </div>
    <div class="action-controls-group">
        <span style="font-size:12px;color:var(--color-text-muted);">
            Submitted: <?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?>
        </span>
    </div>
</div>

<!-- 2. Two-Column Dossier Grid -->
<div class="grid-2" style="margin-top:16px;">
    <!-- Left Column: Details & Documents -->
    <div>
        <!-- IP Technical Details Card -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="file-text" style="width:16px;height:16px;color:var(--color-primary);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Technical Disclosure</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;">
                    <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;font-weight:700;">Abstract / Summary</div>
                    <p style="color:var(--color-text);font-size:13.5px;line-height:1.7;margin:0;"><?= nl2br(clean($application['description'] ?: 'No technical abstract provided.')) ?></p>
                </div>

                <table class="data-table" style="border:none;">
                    <tbody>
                        <tr>
                            <td style="color:var(--color-text-muted);width:160px;font-weight:500;">Applicant Name</td>
                            <td style="font-weight:600;color:var(--color-text);"><?= clean($application['first_name'] . ' ' . $application['last_name']) ?> <span class="badge badge-info" style="font-size:11px;margin-left:4px;"><?= clean($application['applicant_type']) ?></span></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">Contact Details</td>
                            <td><?= clean($application['email']) ?><?= $application['contact_number'] ? ' · <span class="tabular">' . clean($application['contact_number']) . '</span>' : '' ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">College / Unit</td>
                            <td><?= clean($application['college_name'] ?? '—') ?><?= $application['department_name'] ? ' / ' . clean($application['department_name']) : '' ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">Filing Date</td>
                            <td class="tabular"><?= format_date($application['submitted_at'], 'F d, Y · h:i A') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submitted Documents Card -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="paperclip" style="width:16px;height:16px;color:var(--color-primary);"></i>
                    <h3 style="font-size:15px;margin:0;font-weight:600;">Submitted Files &amp; Attachments</h3>
                </div>
                <span class="badge badge-secondary" style="font-size:11px;"><?= mysqli_num_rows($documents) ?> files</span>
            </div>
            <div class="table-wrap">
                <?php if (mysqli_num_rows($documents) === 0): ?>
                    <div class="empty-state" style="padding:36px 20px;text-align:center;">
                        <i data-lucide="folder-open" style="width:36px;height:36px;color:var(--color-warning);margin:0 auto 10px;"></i>
                        <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No documents attached</div>
                        <div style="font-size:12.5px;color:var(--color-text-muted);">This applicant has not uploaded required documentation. Consider returning for revision.</div>
                    </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Format</th>
                            <th>Ver</th>
                            <th>Uploaded</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($doc = mysqli_fetch_assoc($documents)): ?>
                        <tr>
                            <td style="font-weight:500;color:var(--color-text);">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="file" style="width:14px;height:14px;color:var(--color-primary);"></i>
                                    <span><?= clean($doc['file_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge badge-secondary"><?= strtoupper(clean($doc['file_type'])) ?></span></td>
                            <td class="tabular">v<?= (int) $doc['version'] ?></td>
                            <td class="tabular" style="font-size:12px;color:var(--color-text-muted);"><?= format_date($doc['uploaded_at'], 'M d, Y') ?></td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($doc['file_path']) ?>','<?= clean($doc['file_type']) ?>','<?= clean(addslashes($doc['file_name'])) ?>')" style="gap:4px;">
                                        <i data-lucide="eye" style="width:13px;height:13px;"></i> Preview
                                    </button>
                                    <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($doc['file_path']) ?>" target="_blank" style="gap:4px;">
                                        <i data-lucide="download" style="width:13px;height:13px;"></i> Download
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Verification Actions & History -->
    <div>
        <?php if ($canAct): ?>
        <!-- Card 1: Forward to Technical Evaluator -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="user-check" style="width:16px;height:16px;color:var(--color-success);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Assign &amp; Forward</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="forward">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Technical Evaluator</label>
                        <select name="evaluator_id" class="form-control" required style="font-size:13.5px;">
                            <option value="">— Select assigned examiner —</option>
                            <?php while ($e = mysqli_fetch_assoc($evaluatorList)): ?>
                                <option value="<?= $e['user_id'] ?>"><?= clean($e['first_name'] . ' ' . $e['last_name']) ?> (Certified Examiner)</option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint" style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">The selected evaluator will receive an automated assignment notification.</div>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm" style="width:100%;justify-content:center;gap:6px;height:38px;" data-confirm="Screening passed: forward this application to the selected evaluator?">
                        <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
                        Verify &amp; Forward to Evaluator
                    </button>
                </form>
            </div>
        </div>

        <!-- Card 2: Return for Revision / Disqualify -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="alert-triangle" style="width:16px;height:16px;color:var(--color-warning);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Return or Reject</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Screening Remarks / Findings <span style="color:var(--color-danger);font-weight:normal;">(required)</span></label>
                        <textarea name="remarks" class="form-control" rows="4" placeholder="Detail any missing attachments, incorrect formatting, or compliance discrepancies for the applicant…" required style="font-size:13px;line-height:1.5;"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <button type="submit" name="action" value="return" class="btn btn-warning btn-sm" style="justify-content:center;gap:6px;height:36px;">
                            <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i> Return for Revision
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" style="justify-content:center;gap:6px;height:36px;" data-confirm="Are you sure you want to reject this submission? This will disqualify the application.">
                            <i data-lucide="x" style="width:14px;height:14px;"></i> Reject Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;background:var(--color-surface);">
            <div class="card-body" style="padding:18px;display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:50%;background:rgba(37,99,235,0.08);display:flex;align-items:center;justify-content:center;color:var(--color-primary);flex-shrink:0;">
                    <i data-lucide="info" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:13.5px;color:var(--color-text);">Screening Closed</div>
                    <div style="font-size:12.5px;color:var(--color-text-muted);">
                        This application is currently <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span> and does not require staff intervention.
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status History Timeline Card -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="history" style="width:16px;height:16px;color:var(--color-primary);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Status Audit Trail</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if (mysqli_num_rows($history) === 0): ?>
                    <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No audit records logged yet.</p>
                <?php else: ?>
                    <div class="timeline" style="position:relative;padding-left:20px;border-left:2px solid var(--color-border);margin-left:8px;">
                        <?php while ($h = mysqli_fetch_assoc($history)): ?>
                        <div style="position:relative;margin-bottom:20px;">
                            <div style="position:absolute;left:-26px;top:2px;width:10px;height:10px;border-radius:50%;background:var(--color-primary);border:2px solid var(--color-surface);"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                                <span class="badge <?= status_badge_class($h['status']) ?>"><?= status_label($h['status']) ?></span>
                                <span class="tabular" style="font-size:11.5px;color:var(--color-text-muted);"><?= format_date($h['changed_at'], 'M d, Y · h:i A') ?></span>
                            </div>
                            <?php if ($h['first_name']): ?>
                                <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:3px;">Updated by <strong><?= clean($h['first_name'] . ' ' . $h['last_name']) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($h['remarks']): ?>
                                <div style="font-size:12.5px;color:var(--color-text);background:var(--color-bg);padding:8px 12px;border-radius:var(--radius-sm);margin-top:6px;line-height:1.5;">
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
