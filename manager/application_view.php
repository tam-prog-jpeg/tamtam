<?php
/**
 * manager/application_view.php
 * -----------------------------------------------------------------
 * Executive Dossier & Sign-Off Desk for the Manager:
 * Organized into clearly defined sections (Technical Dossier,
 * Submitted Documents with Previews, Evaluator Assessment,
 * Governance Audit Trail) and a dedicated Executive Decision Desk.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Application Dossier & Sign-Off';
$currentPage = 'dashboard';

$applicationId = (int) ($_GET['id'] ?? 0);
if ($applicationId <= 0) {
    set_flash('error', 'Invalid application ID.');
    redirect('manager/dashboard.php');
}

// ---- Handle Executive Sign-Off Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action  = sanitize_input($_POST['action'] ?? '');
    $remarks = sanitize_input($_POST['remarks'] ?? '');

    $ownerStmt = mysqli_prepare($conn, "
        SELECT a.status, u.user_id AS applicant_user_id 
        FROM applications a 
        JOIN applicants ap ON ap.applicant_id = a.applicant_id 
        JOIN users u ON u.user_id = ap.user_id 
        WHERE a.application_id = ?
    ");
    mysqli_stmt_bind_param($ownerStmt, 'i', $applicationId);
    mysqli_stmt_execute($ownerStmt);
    $ownerRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ownerStmt));
    mysqli_stmt_close($ownerStmt);

    if (!$ownerRow) {
        set_flash('error', 'Application record not found.');
        redirect('manager/dashboard.php');
    }

    if ($ownerRow['status'] !== 'evaluated') {
        set_flash('error', 'This application is currently not awaiting executive sign-off.');
        redirect('manager/application_view.php?id=' . $applicationId);
    }

    if (in_array($action, ['approve', 'return', 'reject'], true)) {
        if ($action === 'approve') {
            $newStatus = 'approved';
            $defaultRemarks = 'Final executive approval granted and registration certificate issued by the Manager.';
            $successMsg = 'Application officially approved and certified.';
        } elseif ($action === 'return') {
            $newStatus = 'returned';
            $defaultRemarks = 'Returned to applicant for corrective compliance and revision by the Manager.';
            $successMsg = 'Application returned to applicant for revision.';
        } else {
            $newStatus = 'rejected';
            $defaultRemarks = 'Rejected at final executive review by the Manager.';
            $successMsg = 'Application rejected at executive review.';
        }

        $stmt = mysqli_prepare($conn, "UPDATE applications SET status = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $applicationId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $historyRemarks = $remarks !== '' ? $remarks : $defaultRemarks;
        $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($hist, 'issi', $applicationId, $newStatus, $historyRemarks, $_SESSION['user_id']);
        mysqli_stmt_execute($hist);
        mysqli_stmt_close($hist);

        create_notification(
            $conn,
            $ownerRow['applicant_user_id'],
            'Application ' . status_label($newStatus),
            $historyRemarks,
            'applicant/my_applications.php'
        );

        log_audit($conn, $_SESSION['user_id'], 'manager_' . $action, "Application #$applicationId executive decision: $newStatus");
        set_flash('success', $successMsg);
    }
    redirect('manager/application_view.php?id=' . $applicationId);
}

// Fetch comprehensive application details
$stmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name, u.first_name, u.last_name, u.email, u.contact_number, ap.applicant_type,
           c.college_name, d.department_name,
           eu.first_name AS eval_first, eu.last_name AS eval_last,
           su.first_name AS staff_first, su.last_name AS staff_last
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN users eu ON eu.user_id = a.assigned_evaluator_id
    LEFT JOIN users su ON su.user_id = a.assigned_staff_id
    WHERE a.application_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $applicationId);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('manager/dashboard.php');
}

// Fetch evaluations
$evalStmt = mysqli_prepare($conn, "SELECT * FROM evaluations WHERE application_id = ? ORDER BY evaluated_at DESC");
mysqli_stmt_bind_param($evalStmt, 'i', $applicationId);
mysqli_stmt_execute($evalStmt);
$evaluations = mysqli_stmt_get_result($evalStmt);
$evalCount = mysqli_num_rows($evaluations);

// Fetch documents
$docsStmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docsStmt, 'i', $applicationId);
mysqli_stmt_execute($docsStmt);
$documents = mysqli_stmt_get_result($docsStmt);
$docCount = mysqli_num_rows($documents);

// Fetch status history
$histStmt = mysqli_prepare($conn, "
    SELECT h.*, u.first_name, u.last_name 
    FROM application_status_history h 
    LEFT JOIN users u ON u.user_id = h.changed_by 
    WHERE h.application_id = ? 
    ORDER BY h.changed_at DESC
");
mysqli_stmt_bind_param($histStmt, 'i', $applicationId);
mysqli_stmt_execute($histStmt);
$history = mysqli_stmt_get_result($histStmt);
$histCount = mysqli_num_rows($history);

$canDecide = $application['status'] === 'evaluated';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Header Bar -->
<div class="sub-header-bar">
    <div style="display:flex;align-items:center;gap:14px;">
        <a href="<?= BASE_URL ?>manager/dashboard.php" class="btn btn-outline btn-icon" title="Return to Executive Dashboard">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
        </a>
        <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">
                <span class="tracking" style="font-size:12px;font-weight:700;"><?= clean($application['tracking_code']) ?></span>
                <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span>
                <span class="badge badge-secondary" style="font-size:11px;"><?= clean($application['type_name']) ?></span>
            </div>
            <h2 style="font-size:18px;margin:0;font-weight:700;line-height:1.3;color:var(--color-text);"><?= clean($application['title']) ?></h2>
        </div>
    </div>
    <div class="action-controls-group">
        <span style="font-size:12px;color:var(--color-text-muted);">
            Filed: <strong style="color:var(--color-text);"><?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?></strong>
        </span>
        <?php if ($application['status'] === 'approved'): ?>
            <a href="<?= BASE_URL ?>manager/approved_applications.php" class="btn btn-outline btn-sm" style="gap:4px;">
                <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--color-success);"></i> Approved Registry
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 2. Workflow Context Status Alert -->
<?php if ($canDecide): ?>
    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;border-radius:50%;background:#F59E0B;color:#FFFFFF;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i data-lucide="clock" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <strong style="font-size:13.5px;color:var(--color-text);display:block;">Awaiting Manager Final Sign-Off</strong>
                <span style="font-size:12px;color:var(--color-text-muted);">
                    Evaluated and endorsed by <strong><?= clean($application['eval_first'] . ' ' . $application['eval_last']) ?></strong>. Review the technical assessment and issue your executive decision on the right.
                </span>
            </div>
        </div>
        <a href="#decisionDesk" class="btn btn-warning btn-sm" style="gap:6px;">
            <i data-lucide="signature" style="width:14px;height:14px;"></i> Proceed to Sign-Off
        </a>
    </div>
<?php elseif ($application['status'] === 'approved'): ?>
    <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#10B981;color:#FFFFFF;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="check" style="width:18px;height:18px;"></i>
        </div>
        <div>
            <strong style="font-size:13.5px;color:var(--color-text);display:block;">Executive Approval Granted &amp; Certified</strong>
            <span style="font-size:12px;color:var(--color-text-muted);">
                This intellectual property filing has officially completed all review gates and received final executive certification.
            </span>
        </div>
    </div>
<?php elseif ($application['status'] === 'returned'): ?>
    <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#EF4444;color:#FFFFFF;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i data-lucide="rotate-ccw" style="width:18px;height:18px;"></i>
        </div>
        <div>
            <strong style="font-size:13.5px;color:var(--color-text);display:block;">Returned to Applicant for Revisions</strong>
            <span style="font-size:12px;color:var(--color-text-muted);">
                Awaiting applicant resubmission of required documentation or technical corrections.
            </span>
        </div>
    </div>
<?php endif; ?>

<!-- 3. Main Two-Column Layout -->
<div style="display:grid;grid-template-columns:1.7fr 1fr;gap:24px;align-items:start;">
    <!-- LEFT COLUMN: Organized Dossier Sections with Tabs -->
    <div>
        <!-- Dossier Navigation Tabs -->
        <div class="staff-toolbar-tabs" style="margin-bottom:16px;" role="tablist">
            <button type="button" class="staff-tab-pill active" onclick="switchManagerTab('tab-dossier', this)">
                <i data-lucide="file-text" style="width:14px;height:14px;"></i>
                <span>Technical Dossier</span>
            </button>
            <button type="button" class="staff-tab-pill" onclick="switchManagerTab('tab-docs', this)">
                <i data-lucide="paperclip" style="width:14px;height:14px;"></i>
                <span>Submitted Documents</span>
                <span class="staff-tab-count"><?= $docCount ?></span>
            </button>
            <button type="button" class="staff-tab-pill" onclick="switchManagerTab('tab-eval', this)">
                <i data-lucide="clipboard-check" style="width:14px;height:14px;"></i>
                <span>Evaluator Assessment</span>
                <span class="staff-tab-count"><?= $evalCount ?></span>
            </button>
            <button type="button" class="staff-tab-pill" onclick="switchManagerTab('tab-history', this)">
                <i data-lucide="history" style="width:14px;height:14px;"></i>
                <span>Audit Trail</span>
                <span class="staff-tab-count"><?= $histCount ?></span>
            </button>
        </div>

        <!-- TAB 1: TECHNICAL DOSSIER -->
        <div id="tab-dossier" class="manager-dossier-panel">
            <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:20px;">
                <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                    <i data-lucide="align-left" style="width:17px;height:17px;color:var(--color-primary-600);"></i>
                    <h3 style="font-size:15px;margin:0;font-weight:700;">Executive Summary &amp; Abstract</h3>
                </div>
                <div class="card-body" style="padding:20px;">
                    <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:16px;margin-bottom:18px;">
                        <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;font-weight:700;">
                            Statutory Abstract / Description
                        </div>
                        <p style="color:var(--color-text);font-size:14px;line-height:1.75;margin:0;">
                            <?= nl2br(clean($application['description'] ?: 'No statutory abstract or technical description provided by applicant.')) ?>
                        </p>
                    </div>

                    <h4 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted);margin:0 0 12px;">
                        Applicant &amp; Institutional Affiliation
                    </h4>
                    <table class="data-table" style="border:none;margin-bottom:0;">
                        <tbody>
                            <tr>
                                <td style="color:var(--color-text-muted);width:170px;font-weight:500;">Applicant / Inventor</td>
                                <td style="font-weight:600;color:var(--color-text);">
                                    <?= clean($application['first_name'] . ' ' . $application['last_name']) ?>
                                    <span class="badge badge-info" style="font-size:11px;margin-left:6px;"><?= clean($application['applicant_type']) ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:var(--color-text-muted);font-weight:500;">Contact Email</td>
                                <td class="tabular">
                                    <a href="mailto:<?= clean($application['email']) ?>" style="color:var(--color-primary-600);text-decoration:none;">
                                        <?= clean($application['email']) ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:var(--color-text-muted);font-weight:500;">Mobile / Telephone</td>
                                <td class="tabular"><?= clean($application['contact_number'] ?: '—') ?></td>
                            </tr>
                            <tr>
                                <td style="color:var(--color-text-muted);font-weight:500;">College / Academic Unit</td>
                                <td>
                                    <?= clean($application['college_name'] ?? 'General Campus Repository') ?>
                                    <?= $application['department_name'] ? ' · ' . clean($application['department_name']) : '' ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:var(--color-text-muted);font-weight:500;">IP Classification</td>
                                <td>
                                    <span class="badge badge-secondary" style="font-weight:600;">
                                        <?= clean($application['type_name']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td style="color:var(--color-text-muted);font-weight:500;">Filing Date</td>
                                <td class="tabular"><?= format_date($application['submitted_at'], 'F d, Y · h:i A') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: SUBMITTED DOCUMENTS -->
        <div id="tab-docs" class="manager-dossier-panel" style="display:none;">
            <div class="staff-table-card" style="margin-bottom:20px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <i data-lucide="files" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
                        <h3 style="font-size:15px;margin:0;font-weight:700;">Submitted Technical Documents</h3>
                        <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= $docCount ?> files</span>
                    </div>
                    <div style="font-size:12px;color:var(--color-text-muted);">
                        Examine claims, specifications, and drawings prior to granting sign-off.
                    </div>
                </div>
                <div class="table-wrap">
                    <?php if ($docCount === 0): ?>
                        <div class="empty-state" style="padding:40px 20px;text-align:center;">
                            <i data-lucide="folder-open" style="width:36px;height:36px;color:var(--color-text-muted);margin:0 auto 10px;"></i>
                            <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No files attached</div>
                            <div style="font-size:12.5px;color:var(--color-text-muted);">No supporting documents uploaded for this application.</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th style="width:90px;">Format</th>
                                    <th style="width:80px;">Version</th>
                                    <th style="width:130px;">Uploaded Date</th>
                                    <th style="width:170px;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($documents, 0); ?>
                                <?php while ($doc = mysqli_fetch_assoc($documents)): ?>
                                <tr>
                                    <td style="font-weight:500;color:var(--color-text);">
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <i data-lucide="file" style="width:15px;height:15px;color:var(--color-primary-600);flex-shrink:0;"></i>
                                            <span style="line-height:1.35;"><?= clean($doc['file_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary" style="font-weight:700;font-size:10.5px;">
                                            <?= strtoupper(clean($doc['file_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="tabular">v<?= (int) $doc['version'] ?></td>
                                    <td class="tabular" style="font-size:12px;color:var(--color-text-muted);">
                                        <?= format_date($doc['uploaded_at'], 'M d, Y') ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:inline-flex;gap:6px;">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($doc['file_path']) ?>','<?= clean($doc['file_type']) ?>','<?= clean(addslashes($doc['file_name'])) ?>')" style="gap:4px;padding:4px 10px;font-size:12px;" title="Inspect Document Preview">
                                                <i data-lucide="eye" style="width:13px;height:13px;"></i> Preview
                                            </button>
                                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($doc['file_path']) ?>" target="_blank" download style="gap:4px;padding:4px 10px;font-size:12px;" title="Download Document">
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

        <!-- TAB 3: EVALUATOR ASSESSMENT -->
        <div id="tab-eval" class="manager-dossier-panel" style="display:none;">
            <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:20px;">
                <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i data-lucide="check-circle-2" style="width:17px;height:17px;color:var(--color-success);"></i>
                        <h3 style="font-size:15px;margin:0;font-weight:700;">Technical Evaluator Findings &amp; Recommendation</h3>
                    </div>
                    <?php if ($application['eval_first']): ?>
                        <span class="badge badge-info" style="font-size:11px;">
                            Examiner: <?= clean($application['eval_first'] . ' ' . $application['eval_last']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding:20px;">
                    <?php if ($evalCount === 0): ?>
                        <div class="empty-state" style="padding:36px 20px;text-align:center;">
                            <i data-lucide="clipboard-x" style="width:36px;height:36px;color:var(--color-text-muted);margin:0 auto 10px;"></i>
                            <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No evaluation findings recorded</div>
                            <div style="font-size:12.5px;color:var(--color-text-muted);">This application has not yet completed evaluator assessment.</div>
                        </div>
                    <?php else: ?>
                        <?php mysqli_data_seek($evaluations, 0); ?>
                        <?php while ($e = mysqli_fetch_assoc($evaluations)): ?>
                            <?php 
                            $decClass = $e['decision'] === 'approved' ? 'badge-success' : ($e['decision'] === 'rejected' ? 'badge-danger' : 'badge-warning');
                            ?>
                            <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:18px;margin-bottom:14px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span class="badge <?= $decClass ?>" style="font-size:11.5px;font-weight:700;text-transform:uppercase;">
                                            Recommendation: <?= clean($e['decision']) ?>
                                        </span>
                                    </div>
                                    <span class="tabular" style="font-size:12px;color:var(--color-text-muted);">
                                        <?= format_date($e['evaluated_at'], 'M d, Y · h:i A') ?>
                                    </span>
                                </div>
                                <div style="font-size:13.5px;color:var(--color-text);line-height:1.7;background:var(--color-bg);padding:14px;border-radius:var(--radius-sm);border:1px solid var(--color-border);">
                                    <?= nl2br(clean($e['comments'])) ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 4: GOVERNANCE AUDIT TRAIL -->
        <div id="tab-history" class="manager-dossier-panel" style="display:none;">
            <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);margin-bottom:20px;">
                <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                    <i data-lucide="history" style="width:17px;height:17px;color:var(--color-primary-600);"></i>
                    <h3 style="font-size:15px;margin:0;font-weight:700;">Full Lifecycle Audit Trail</h3>
                </div>
                <div class="card-body" style="padding:20px;">
                    <?php if ($histCount === 0): ?>
                        <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No audit records logged yet.</p>
                    <?php else: ?>
                        <div class="timeline" style="position:relative;padding-left:22px;border-left:2px solid var(--color-border);margin-left:10px;">
                            <?php mysqli_data_seek($history, 0); ?>
                            <?php while ($h = mysqli_fetch_assoc($history)): ?>
                                <div style="position:relative;margin-bottom:22px;">
                                    <div style="position:absolute;left:-28px;top:2px;width:12px;height:12px;border-radius:50%;background:var(--color-primary-600);border:2px solid var(--color-surface);"></div>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                        <span class="badge <?= status_badge_class($h['status']) ?>"><?= status_label($h['status']) ?></span>
                                        <span class="tabular" style="font-size:12px;color:var(--color-text-muted);">
                                            <?= format_date($h['changed_at'], 'M d, Y · h:i A') ?>
                                        </span>
                                    </div>
                                    <?php if ($h['first_name']): ?>
                                        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:4px;">
                                            Recorded by <strong><?= clean($h['first_name'] . ' ' . $h['last_name']) ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($h['remarks']): ?>
                                        <div style="font-size:13px;color:var(--color-text);background:var(--color-bg);padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--color-border);line-height:1.55;margin-top:6px;">
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

    <!-- RIGHT COLUMN: Executive Decision Desk & Snapshot -->
    <div style="display:flex;flex-direction:column;gap:20px;" id="decisionDesk">
        <!-- 1. Executive Decision Desk Card -->
        <div class="card" style="box-shadow:var(--shadow-md);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:8px;">
                <i data-lucide="signature" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Executive Decision Desk</h3>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if ($canDecide): ?>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <div class="form-group" style="margin-bottom:16px;">
                            <label style="font-size:13px;font-weight:700;display:block;margin-bottom:6px;">
                                Executive Directives &amp; Remarks (Optional)
                            </label>
                            <textarea 
                                name="remarks" 
                                class="form-control" 
                                rows="4" 
                                placeholder="Enter executive record notes, special grant stipulations, or instructions for the applicant..." 
                                style="font-size:13px;line-height:1.55;"
                            ></textarea>
                            <span style="font-size:11.5px;color:var(--color-text-muted);display:block;margin-top:4px;">
                                Remarks will be stamped into the official audit trail and notified to the applicant.
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <!-- Final Approve Button -->
                            <button 
                                type="submit" 
                                name="action" 
                                value="approve" 
                                class="btn btn-success" 
                                style="width:100%;justify-content:center;height:42px;gap:6px;font-weight:700;font-size:13.5px;"
                                data-confirm="Grant final executive approval to this intellectual property disclosure? This will issue the official Certificate of Approval and conclude the review pipeline."
                            >
                                <i data-lucide="check-circle-2" style="width:16px;height:16px;"></i> Final Approve &amp; Certify
                            </button>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <!-- Return for Revision Button -->
                                <button 
                                    type="submit" 
                                    name="action" 
                                    value="return" 
                                    class="btn btn-warning" 
                                    style="justify-content:center;height:38px;gap:6px;font-size:12.5px;"
                                    data-confirm="Return this application to the applicant for revisions or additional documentary requirements?"
                                >
                                    <i data-lucide="rotate-ccw" style="width:14px;height:14px;"></i> Return for Revision
                                </button>

                                <!-- Final Reject Button -->
                                <button 
                                    type="submit" 
                                    name="action" 
                                    value="reject" 
                                    class="btn btn-danger" 
                                    style="justify-content:center;height:38px;gap:6px;font-size:12.5px;"
                                    data-confirm="Are you sure you want to reject this application at final executive review? This decision will be permanently recorded."
                                >
                                    <i data-lucide="x-circle" style="width:14px;height:14px;"></i> Final Reject
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:rgba(37,99,235,0.1);display:flex;align-items:center;justify-content:center;color:var(--color-primary-600);flex-shrink:0;">
                            <i data-lucide="info" style="width:18px;height:18px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:13.5px;color:var(--color-text);margin-bottom:2px;">
                                Executive Action Not Pending
                            </div>
                            <div style="font-size:12.5px;color:var(--color-text-muted);line-height:1.5;">
                                This filing is currently <span class="badge <?= status_badge_class($application['status']) ?>"><?= status_label($application['status']) ?></span>. Executive sign-off controls are enabled exclusively when an application is in the <strong>Evaluated</strong> stage.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Application Governance Snapshot Card -->
        <div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                <i data-lucide="layers" style="width:17px;height:17px;color:var(--color-primary-600);"></i>
                <h3 style="font-size:15px;margin:0;font-weight:700;">Governance Snapshot</h3>
            </div>
            <div class="card-body" style="padding:18px 20px;font-size:12.5px;">
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <span style="color:var(--color-text-muted);display:block;margin-bottom:2px;">Assigned Verification Staff</span>
                        <strong style="color:var(--color-text);font-size:13px;">
                            <?= $application['staff_first'] ? clean($application['staff_first'] . ' ' . $application['staff_last']) : 'Unassigned' ?>
                        </strong>
                    </div>
                    <div>
                        <span style="color:var(--color-text-muted);display:block;margin-bottom:2px;">Technical Examiner</span>
                        <strong style="color:var(--color-text);font-size:13px;">
                            <?= $application['eval_first'] ? clean($application['eval_first'] . ' ' . $application['eval_last']) : 'Pending Examiner Assignment' ?>
                        </strong>
                    </div>
                    <div>
                        <span style="color:var(--color-text-muted);display:block;margin-bottom:2px;">Applicant Classification</span>
                        <strong style="color:var(--color-text);font-size:13px;">
                            <?= clean($application['applicant_type']) ?>
                        </strong>
                    </div>
                    <div>
                        <span style="color:var(--color-text-muted);display:block;margin-bottom:2px;">College / Academic Unit</span>
                        <strong style="color:var(--color-text);font-size:13px;">
                            <?= clean($application['college_name'] ?? 'General Campus Repository') ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchManagerTab(panelId, btn) {
    // Hide all dossier panels
    var panels = document.querySelectorAll('.manager-dossier-panel');
    panels.forEach(function(p) { p.style.display = 'none'; });

    // Show target panel
    var target = document.getElementById(panelId);
    if (target) target.style.display = 'block';

    // Update active tab buttons
    var buttons = btn.parentElement.querySelectorAll('.staff-tab-pill');
    buttons.forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');

    if (window.lucide) lucide.createIcons();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
