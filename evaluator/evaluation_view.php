<?php
/**
 * evaluator/evaluation_view.php
 * -----------------------------------------------------------------
 * Evaluator's review + decision screen for one assigned application:
 *  - Approve (recommend approval -> status becomes "evaluated",
 *    awaiting the Manager's final sign-off)
 *  - Reject (final -> status becomes "rejected")
 *  - Return for Revision (-> status becomes "returned", back to applicant)
 * Every decision is recorded in `evaluations`, added to the status
 * history, and notifies the applicant.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Evaluator']);

$pageTitle   = 'Evaluate Application';
$currentPage = 'evaluations';

$applicationId = (int) ($_GET['id'] ?? 0);
$uid = (int) $_SESSION['user_id'];

// ---- Handle decision submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    // Re-verify this application is actually assigned to this evaluator.
    $own = mysqli_prepare($conn, "SELECT a.status, u.user_id AS applicant_user_id FROM applications a JOIN applicants ap ON ap.applicant_id = a.applicant_id JOIN users u ON u.user_id = ap.user_id WHERE a.application_id = ? AND a.assigned_evaluator_id = ?");
    mysqli_stmt_bind_param($own, 'ii', $applicationId, $uid);
    mysqli_stmt_execute($own);
    $ownRow = mysqli_fetch_assoc(mysqli_stmt_get_result($own));
    mysqli_stmt_close($own);

    if (!$ownRow) {
        set_flash('error', 'This application is not assigned to you.');
        redirect('evaluator/evaluations.php');
    }
    if ($ownRow['status'] !== 'under_review') {
        set_flash('error', 'This application has already been evaluated.');
        redirect('evaluator/evaluation_view.php?id=' . $applicationId);
    }

    $decision = sanitize_input($_POST['decision'] ?? '');
    $comments = sanitize_input($_POST['comments'] ?? '');

    if (!in_array($decision, ['approved', 'rejected', 'returned'], true)) {
        set_flash('error', 'Please select a valid decision.');
        redirect('evaluator/evaluation_view.php?id=' . $applicationId);
    }
    if ($comments === '') {
        set_flash('error', 'Please provide evaluation comments.');
        redirect('evaluator/evaluation_view.php?id=' . $applicationId);
    }

    // Map the evaluator's decision to the application-wide status.
    $statusMap = ['approved' => 'evaluated', 'rejected' => 'rejected', 'returned' => 'returned'];
    $newStatus = $statusMap[$decision];

    mysqli_begin_transaction($conn);
    try {
        $ins = mysqli_prepare($conn, "INSERT INTO evaluations (application_id, evaluator_id, decision, comments) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iiss', $applicationId, $uid, $decision, $comments);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        $upd = mysqli_prepare($conn, "UPDATE applications SET status = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($upd, 'si', $newStatus, $applicationId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($hist, 'issi', $applicationId, $newStatus, $comments, $uid);
        mysqli_stmt_execute($hist);
        mysqli_stmt_close($hist);

        mysqli_commit($conn);

        $notifTitle = $decision === 'approved' ? 'Application Recommended for Approval' : ($decision === 'rejected' ? 'Application Rejected' : 'Application Returned for Revision');
        create_notification($conn, $ownRow['applicant_user_id'], $notifTitle, $comments, 'applicant/my_applications.php');

        if ($decision === 'approved') {
            $managers = mysqli_query($conn, "SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'Manager' AND u.status = 'active'");
            while ($m = mysqli_fetch_assoc($managers)) {
                create_notification($conn, $m['user_id'], 'Application Ready for Final Approval', 'An evaluator has recommended approval for an application.', 'manager/reports.php');
            }
        }

        log_audit($conn, $uid, 'evaluate_application', "Application #$applicationId evaluated: $decision");
        set_flash('success', 'Evaluation submitted successfully.');
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        error_log('Evaluation failed: ' . $e->getMessage());
        set_flash('error', 'Something went wrong submitting your evaluation. Please try again.');
    }
    redirect('evaluator/evaluation_view.php?id=' . $applicationId);
}

// ---- Fetch application (must be assigned to this evaluator) ----
$stmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name, u.first_name, u.last_name, u.email, u.contact_number, ap.applicant_type,
           c.college_name, d.department_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    WHERE a.application_id = ? AND a.assigned_evaluator_id = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $applicationId, $uid);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    set_flash('error', 'Application not found or not assigned to you.');
    redirect('evaluator/evaluations.php');
}

$docsStmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docsStmt, 'i', $applicationId);
mysqli_stmt_execute($docsStmt);
$documents = mysqli_stmt_get_result($docsStmt);

$evalStmt = mysqli_prepare($conn, "SELECT * FROM evaluations WHERE application_id = ? ORDER BY evaluated_at DESC");
mysqli_stmt_bind_param($evalStmt, 'i', $applicationId);
mysqli_stmt_execute($evalStmt);
$pastEvaluations = mysqli_stmt_get_result($evalStmt);

$canEvaluate = $application['status'] === 'under_review';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div style="display:flex;align-items:center;gap:14px;">
        <a href="<?= BASE_URL ?>evaluator/evaluations.php" class="btn btn-outline btn-icon" title="Return to Queue">
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
            Filed: <?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?>
        </span>
    </div>
</div>

<!-- 2. Two-Column Evaluation Grid -->
<div class="grid-2" style="margin-top:16px;">
    <!-- Left Column: Technical Disclosure & Documents -->
    <div>
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="file-text" style="width:16px;height:16px;color:var(--color-primary);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Technical Disclosure &amp; Scope</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;">
                    <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;font-weight:700;">Abstract / Invention Description</div>
                    <p style="color:var(--color-text);font-size:13.5px;line-height:1.7;margin:0;"><?= nl2br(clean($application['description'] ?: 'No technical abstract provided.')) ?></p>
                </div>

                <table class="data-table" style="border:none;">
                    <tbody>
                        <tr>
                            <td style="color:var(--color-text-muted);width:160px;font-weight:500;">Applicant / Inventor</td>
                            <td style="font-weight:600;color:var(--color-text);"><?= clean($application['first_name'] . ' ' . $application['last_name']) ?> <span class="badge badge-info" style="font-size:11px;margin-left:4px;"><?= clean($application['applicant_type']) ?></span></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">Academic Unit</td>
                            <td><?= clean($application['college_name'] ?? '—') ?><?= $application['department_name'] ? ' / ' . clean($application['department_name']) : '' ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">IP Classification</td>
                            <td><?= clean($application['type_name']) ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--color-text-muted);font-weight:500;">Submission Date</td>
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
                    <h3 style="font-size:15px;margin:0;font-weight:600;">Technical Drawings &amp; Attachments</h3>
                </div>
                <span class="badge badge-secondary" style="font-size:11px;"><?= mysqli_num_rows($documents) ?> files</span>
            </div>
            <div class="table-wrap">
                <?php if (mysqli_num_rows($documents) === 0): ?>
                    <div class="empty-state" style="padding:36px 20px;text-align:center;">
                        <i data-lucide="folder-open" style="width:36px;height:36px;color:var(--color-text-muted);margin:0 auto 10px;"></i>
                        <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No files uploaded</div>
                        <div style="font-size:12.5px;color:var(--color-text-muted);">No technical documentation attached to this application.</div>
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

    <!-- Right Column: Evaluation Action & History -->
    <div>
        <?php if ($canEvaluate): ?>
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="award" style="width:16px;height:16px;color:var(--color-primary);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Submit Substantive Evaluation</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Examination Finding / Recommendation</label>
                        <select name="decision" class="form-control" required style="font-size:13.5px;">
                            <option value="">— Select examination finding —</option>
                            <option value="approved">Recommend Approval (Novelty &amp; Inventive Step Satisfied)</option>
                            <option value="returned">Return for Revision (Clarification / Amendments Required)</option>
                            <option value="rejected">Disqualify / Reject (Prior Art Confirmed / Non-Patentable)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:18px;">
                        <label style="font-size:12.5px;font-weight:600;margin-bottom:6px;">Evaluation Notes &amp; Legal Citations <span style="color:var(--color-danger);font-weight:normal;">(required)</span></label>
                        <textarea name="comments" class="form-control" rows="5" placeholder="Detail your substantive findings, novelty examination results, prior-art search references, or required revisions…" required style="font-size:13px;line-height:1.5;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;gap:6px;height:38px;" data-confirm="Submit this formal evaluation? This will advance the application in the governance workflow.">
                        <i data-lucide="send" style="width:15px;height:15px;"></i> Submit Formal Evaluation
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:18px;background:var(--color-surface);">
            <div class="card-body" style="padding:18px;display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:50%;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;color:var(--color-success);flex-shrink:0;">
                    <i data-lucide="check-circle" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <div style="font-weight:600;font-size:13.5px;color:var(--color-text);">Evaluation Concluded</div>
                    <div style="font-size:12.5px;color:var(--color-text-muted);">
                        This application is now <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span> and has completed the examiner review stage.
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Evaluation History Timeline Card -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="history" style="width:16px;height:16px;color:var(--color-primary);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:600;">Recorded Technical Findings</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if (mysqli_num_rows($pastEvaluations) === 0): ?>
                    <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No evaluation logs recorded yet.</p>
                <?php else: ?>
                    <div class="timeline" style="position:relative;padding-left:20px;border-left:2px solid var(--color-border);margin-left:8px;">
                        <?php while ($e = mysqli_fetch_assoc($pastEvaluations)): ?>
                        <div style="position:relative;margin-bottom:20px;">
                            <div style="position:absolute;left:-26px;top:2px;width:10px;height:10px;border-radius:50%;background:var(--color-primary);border:2px solid var(--color-surface);"></div>
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
                                <span class="badge <?= status_badge_class($e['decision'] === 'approved' ? 'approved' : $e['decision']) ?>">
                                    <?= ucfirst($e['decision']) ?>
                                </span>
                                <span class="tabular" style="font-size:11.5px;color:var(--color-text-muted);"><?= format_date($e['evaluated_at'], 'M d, Y · h:i A') ?></span>
                            </div>
                            <div style="font-size:12.5px;color:var(--color-text);background:var(--color-bg);padding:10px 12px;border-radius:var(--radius-sm);margin-top:6px;line-height:1.5;">
                                <?= nl2br(clean($e['comments'])) ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
