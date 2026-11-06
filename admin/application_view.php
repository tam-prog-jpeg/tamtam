<?php
/**
 * admin/application_view.php
 * -----------------------------------------------------------------
 * Full institutional dossier view for Administrator:
 *  - Continuous sequential document layout (no fragmented card clutter)
 *  - Strict Read-Only mode for Approved applications (backend + UI)
 *  - Consolidated "Office/Department" display
 *  - Complete audit timeline & assignment controls
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Application Dossier';
$currentPage = 'applications';

$applicationId = (int) ($_GET['id'] ?? 0);
if ($applicationId <= 0) {
    set_flash('error', 'Invalid application ID.');
    redirect('admin/applications.php');
}

// ---- Fetch current application details to check status before handling POST ----
$initStmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name, u.first_name, u.last_name, u.email, u.contact_number,
           ap.applicant_type, c.college_name, d.department_name,
           su.first_name AS staff_first, su.last_name AS staff_last,
           eu.first_name AS eval_first, eu.last_name AS eval_last
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN users su ON su.user_id = a.assigned_staff_id
    LEFT JOIN users eu ON eu.user_id = a.assigned_evaluator_id
    WHERE a.application_id = ?
");
mysqli_stmt_bind_param($initStmt, 'i', $applicationId);
mysqli_stmt_execute($initStmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($initStmt));
mysqli_stmt_close($initStmt);

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('admin/applications.php');
}

$isApproved = ($application['status'] === 'approved');

// ---- Handle POST actions (assign / update status) with Read-Only protection ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // Requirement 2: Approved records are strictly READ-ONLY. Block modifications!
    if ($isApproved) {
        set_flash('error', 'This application has already been approved and registered. All fields and assignments are permanently locked in read-only mode.');
        redirect('admin/application_view.php?id=' . $applicationId);
    }

    // Fetch applicant's user_id for notifications
    $ownerStmt = mysqli_prepare($conn, "SELECT u.user_id FROM applications a JOIN applicants ap ON ap.applicant_id = a.applicant_id JOIN users u ON u.user_id = ap.user_id WHERE a.application_id = ?");
    mysqli_stmt_bind_param($ownerStmt, 'i', $applicationId);
    mysqli_stmt_execute($ownerStmt);
    $applicantUserId = mysqli_fetch_assoc(mysqli_stmt_get_result($ownerStmt))['user_id'] ?? null;
    mysqli_stmt_close($ownerStmt);

    if ($action === 'assign') {
        $staffId = !empty($_POST['staff_id']) ? (int) $_POST['staff_id'] : null;
        $evaluatorId = !empty($_POST['evaluator_id']) ? (int) $_POST['evaluator_id'] : null;

        $stmt = mysqli_prepare($conn, "UPDATE applications SET assigned_staff_id = ?, assigned_evaluator_id = ? WHERE application_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $staffId, $evaluatorId, $applicationId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_audit($conn, $_SESSION['user_id'], 'assign_application', "Application #$applicationId personnel assignment updated");
        if ($applicantUserId) {
            create_notification($conn, $applicantUserId, 'Application Update', 'Your application has been assigned for processing.', 'applicant/my_applications.php');
        }
        set_flash('success', 'Personnel assignment updated successfully.');
    }

    if ($action === 'update_status') {
        $newStatus = sanitize_input($_POST['status'] ?? '');
        $remarks   = sanitize_input($_POST['remarks'] ?? '');
        $validStatuses = ['pending','under_review','evaluated','returned','approved','rejected'];

        if (in_array($newStatus, $validStatuses, true)) {
            $stmt = mysqli_prepare($conn, "UPDATE applications SET status = ? WHERE application_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $newStatus, $applicationId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($hist, 'issi', $applicationId, $newStatus, $remarks, $_SESSION['user_id']);
            mysqli_stmt_execute($hist);
            mysqli_stmt_close($hist);

            log_audit($conn, $_SESSION['user_id'], 'update_status', "Application #$applicationId status updated to $newStatus");
            if ($applicantUserId) {
                create_notification($conn, $applicantUserId, 'Application Status Updated', 'Your application status changed to ' . status_label($newStatus) . '.', 'applicant/my_applications.php');
            }
            set_flash('success', 'Status progression updated to ' . status_label($newStatus) . '.');
        }
    }
    redirect('admin/application_view.php?id=' . $applicationId);
}

// ---- Documents ----
$docsStmt = mysqli_prepare($conn, "SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
mysqli_stmt_bind_param($docsStmt, 'i', $applicationId);
mysqli_stmt_execute($docsStmt);
$documents = mysqli_stmt_get_result($docsStmt);

// ---- Status history ----
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

// ---- Staff / evaluator lists for assignment dropdowns ----
$staffList = mysqli_query($conn, "SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'Staff' AND u.status = 'active' ORDER BY u.first_name");
$evaluatorList = mysqli_query($conn, "SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = 'Evaluator' AND u.status = 'active' ORDER BY u.first_name");

// Unified Office / Department string
$officeDeptDisplay = clean($application['department_name'] ?: ($application['college_name'] ?? '—'));
if ($application['department_name'] && $application['college_name']) {
    $officeDeptDisplay .= ' <span style="color:var(--color-text-subtle);">&middot;</span> <span style="font-size:12px;color:var(--color-text-muted);">' . clean($application['college_name']) . '</span>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Dossier Header & Navigation Ribbon -->
<div class="sub-header-bar" style="margin-bottom:16px;">
    <div class="sub-header-left">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm btn-icon" title="Return to Applications Directory">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
            </a>
            <span class="tracking-pill" style="font-size:13px;font-weight:700;">
                <i data-lucide="hash" style="width:13px;height:13px;opacity:0.7;"></i>
                <?= clean($application['tracking_code']) ?>
            </span>
            <span class="badge <?= status_badge_class($application['status']) ?>" style="font-size:12px;padding:3px 10px;">
                <?= status_label($application['status']) ?>
            </span>
            <?php if ($isApproved): ?>
                <span class="badge badge-success" style="font-size:11.5px;padding:3px 10px;display:inline-flex;align-items:center;gap:4px;">
                    <i data-lucide="lock" style="width:12px;height:12px;"></i>
                    Approved &amp; Certified (Read-Only)
                </span>
            <?php endif; ?>
        </div>
        <h2 style="margin:0 0 4px;font-size:20px;font-weight:700;"><?= clean($application['title']) ?></h2>
        <div style="font-size:12.5px;color:var(--color-text-muted);">
            Filing recorded on <strong style="color:var(--color-text);"><?= format_date($application['submitted_at'], 'F d, Y · h:i A') ?></strong>
        </div>
    </div>
    <div class="sub-header-actions">
        <button type="button" onclick="window.print()" class="btn btn-outline btn-sm" title="Print Institutional Dossier">
            <i data-lucide="printer" style="width:15px;height:15px;"></i>
            Print Dossier
        </button>
        <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm">
            <i data-lucide="list" style="width:15px;height:15px;"></i>
            All Applications
        </a>
    </div>
</div>

<!-- Requirement 2: Approved Lock Banner -->
<?php if ($isApproved): ?>
<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-lg);padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
    <div style="width:38px;height:38px;border-radius:10px;background:rgba(16,185,129,0.18);color:var(--color-success);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i data-lucide="shield-check" style="width:22px;height:22px;"></i>
    </div>
    <div style="flex:1;">
        <div style="font-size:14px;font-weight:700;color:var(--color-success);">Official Record Approved &amp; Registered &mdash; Read-Only Mode</div>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;">
            This intellectual property has received final executive approval and registration. All technical disclosures, documentary attachments, personnel assignments, and administrative decisions are permanently locked to preserve archive integrity.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 2. Continuous Sequential Document Layout -->
<div class="card" style="border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);margin-bottom:24px;">
    
    <!-- Document Sequential Container -->
    <div style="padding:28px 32px;">

        <!-- =================================================================
             SECTION 01: APPLICANT IDENTITY & INSTITUTIONAL AFFILIATION
             ================================================================= -->
        <section style="margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                    01
                </div>
                <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                    Applicant Profile &amp; Institutional Affiliation
                </h3>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
                <div style="padding:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                    <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Applicant Name</div>
                    <div style="font-size:14px;font-weight:700;color:var(--color-text);"><?= clean($application['first_name'] . ' ' . $application['last_name']) ?></div>
                    <span class="badge badge-info" style="font-size:10.5px;margin-top:6px;display:inline-block;"><?= clean($application['applicant_type']) ?></span>
                </div>

                <div style="padding:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                    <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Office / Department</div>
                    <div style="font-size:13.5px;font-weight:600;color:var(--color-text);"><?= $officeDeptDisplay ?></div>
                </div>

                <div style="padding:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                    <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Email Address</div>
                    <div style="font-size:13px;color:var(--color-text);font-weight:500;"><?= clean($application['email']) ?></div>
                </div>

                <div style="padding:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                    <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Contact Number</div>
                    <div style="font-size:13px;color:var(--color-text);font-weight:500;"><?= clean($application['contact_number'] ?: 'No telephone provided') ?></div>
                </div>
            </div>
        </section>


        <!-- =================================================================
             SECTION 02: INTELLECTUAL PROPERTY TECHNICAL DISCLOSURE
             ================================================================= -->
        <section style="margin-bottom:28px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                    02
                </div>
                <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                    Intellectual Property Technical Disclosure
                </h3>
            </div>

            <div style="display:grid;grid-template-columns:1fr;gap:16px;">
                <div style="display:flex;align-items:center;gap:14px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:14px 18px;">
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:3px;">IP Classification Track</div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span class="badge badge-secondary" style="font-size:12px;font-weight:700;padding:3px 10px;">
                                <?= clean($application['type_name']) ?>
                            </span>
                            <?php if ($application['type_name'] === 'Others' && !empty($application['custom_category'])): ?>
                                <span class="badge badge-primary" style="font-size:12px;padding:3px 10px;">
                                    Specified: <?= clean($application['custom_category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-left:auto;text-align:right;">
                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:3px;">Submission Timestamp</div>
                        <div style="font-family:var(--font-mono);font-size:12px;color:var(--color-text);">
                            <?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?>
                        </div>
                    </div>
                </div>

                <!-- Abstract & Technical Specifications -->
                <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:18px 20px;">
                    <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
                        Technical Abstract, Summary &amp; Description
                    </div>
                    <div style="color:var(--color-text);font-size:13.5px;line-height:1.75;white-space:pre-wrap;">
                        <?= clean($application['description'] ?: 'No technical specification or abstract provided for this application.') ?>
                    </div>
                </div>
            </div>
        </section>


        <!-- =================================================================
             SECTION 03: SUBMITTED TECHNICAL DOCUMENTS & ATTACHMENTS
             ================================================================= -->
        <section style="margin-bottom:28px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                        03
                    </div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                        Documentary Evidence &amp; Attached Specifications
                    </h3>
                </div>
                <span class="badge badge-secondary" style="font-size:11px;padding:2px 8px;">
                    <?= mysqli_num_rows($documents) ?> File<?= mysqli_num_rows($documents) === 1 ? '' : 's' ?> Attached
                </span>
            </div>

            <div class="table-wrap" style="border:1px solid var(--color-border);border-radius:var(--radius-md);">
                <?php if (mysqli_num_rows($documents) === 0): ?>
                    <div class="empty-state" style="padding:24px;">
                        <div class="empty-state-icon">
                            <i data-lucide="file-question" style="width:30px;height:30px;color:var(--color-text-subtle);"></i>
                        </div>
                        <h4 style="margin:8px 0 4px;font-size:14px;">No files uploaded</h4>
                        <p style="font-size:12px;color:var(--color-text-muted);margin:0;">No technical disclosures, claims, or schematics were attached to this filing.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document Filename</th>
                                <th>Format</th>
                                <th>Version</th>
                                <th>Uploaded Date</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc = mysqli_fetch_assoc($documents)): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <i data-lucide="file-text" style="width:16px;height:16px;color:var(--color-primary-600);flex-shrink:0;"></i>
                                        <span style="font-weight:600;color:var(--color-text);"><?= clean($doc['file_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary" style="font-size:11px;font-weight:700;">
                                        <?= strtoupper(clean($doc['file_type'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family:var(--font-mono);font-size:11.5px;padding:2px 6px;border-radius:4px;background:var(--color-bg);border:1px solid var(--color-border);">
                                        v<?= (int) $doc['version'] ?>
                                    </span>
                                </td>
                                <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);">
                                    <?= format_date($doc['uploaded_at'], 'M d, Y · h:i A') ?>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex;gap:6px;justify-content:flex-end;">
                                        <button type="button" class="btn btn-primary btn-sm" 
                                                onclick="openDocPreview('<?= BASE_URL . clean($doc['file_path']) ?>','<?= clean($doc['file_type']) ?>','<?= clean(addslashes($doc['file_name'])) ?>')" 
                                                title="Preview Document">
                                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                            Preview
                                        </button>
                                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($doc['file_path']) ?>" target="_blank" download title="Download File">
                                            <i data-lucide="download" style="width:13px;height:13px;"></i>
                                            Download
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>


        <!-- =================================================================
             SECTION 04: WORKFLOW PERSONNEL ASSIGNMENT
             ================================================================= -->
        <section style="margin-bottom:28px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                        04
                    </div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                        Workflow Personnel Assignment Desk
                    </h3>
                </div>
                <?php if ($isApproved): ?>
                    <span class="badge badge-success" style="font-size:11px;padding:2px 8px;display:inline-flex;align-items:center;gap:4px;">
                        <i data-lucide="lock" style="width:11px;height:11px;"></i>
                        Locked
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($isApproved): ?>
                <!-- Read-Only Personnel Assignment Display -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="padding:16px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:6px;">Assigned IPO Staff (Intake &amp; Compliance)</div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i data-lucide="user-check" style="width:16px;height:16px;color:var(--color-primary-600);"></i>
                            <span style="font-size:14px;font-weight:700;color:var(--color-text);">
                                <?= $application['staff_first'] ? clean($application['staff_first'] . ' ' . $application['staff_last']) : 'No staff assigned during intake' ?>
                            </span>
                        </div>
                    </div>
                    <div style="padding:16px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:6px;">Assigned Technical Evaluator (Substantive Review)</div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i data-lucide="award" style="width:16px;height:16px;color:#8B5CF6;"></i>
                            <span style="font-size:14px;font-weight:700;color:var(--color-text);">
                                <?= $application['eval_first'] ? clean($application['eval_first'] . ' ' . $application['eval_last']) : 'No evaluator assigned' ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Interactive Assignment Form -->
                <form method="POST" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:20px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="assign">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="staff_id">Assigned IPO Staff (Intake &amp; Formality)</label>
                            <select name="staff_id" id="staff_id" class="form-control form-select">
                                <option value="">&mdash; Unassigned &mdash;</option>
                                <?php mysqli_data_seek($staffList, 0); while ($s = mysqli_fetch_assoc($staffList)): ?>
                                    <option value="<?= $s['user_id'] ?>" <?= (int)$application['assigned_staff_id'] === (int)$s['user_id'] ? 'selected' : '' ?>>
                                        <?= clean($s['first_name'] . ' ' . $s['last_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-hint" style="margin-top:6px;font-size:11.5px;color:var(--color-text-muted);">
                                Conducts completeness verification and formality check.
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="evaluator_id">Assigned Technical Evaluator (Substantive)</label>
                            <select name="evaluator_id" id="evaluator_id" class="form-control form-select">
                                <option value="">&mdash; Unassigned &mdash;</option>
                                <?php mysqli_data_seek($evaluatorList, 0); while ($e = mysqli_fetch_assoc($evaluatorList)): ?>
                                    <option value="<?= $e['user_id'] ?>" <?= (int)$application['assigned_evaluator_id'] === (int)$e['user_id'] ? 'selected' : '' ?>>
                                        <?= clean($e['first_name'] . ' ' . $e['last_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-hint" style="margin-top:6px;font-size:11.5px;color:var(--color-text-muted);">
                                Conducts novelty audit and subject-matter evaluation.
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm" style="padding:8px 18px;">
                            <i data-lucide="save" style="width:14px;height:14px;"></i>
                            Update Personnel Assignment
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>


        <!-- =================================================================
             SECTION 05: ADMINISTRATIVE STATUS PROGRESSION & DECISION
             ================================================================= -->
        <section style="margin-bottom:28px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                        05
                    </div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                        Administrative Status Progression &amp; Official Decision
                    </h3>
                </div>
                <?php if ($isApproved): ?>
                    <span class="badge badge-success" style="font-size:11px;padding:2px 8px;display:inline-flex;align-items:center;gap:4px;">
                        <i data-lucide="lock" style="width:11px;height:11px;"></i>
                        Read-Only Finalized
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($isApproved): ?>
                <!-- Read-Only Status Progression View -->
                <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:20px;display:flex;align-items:center;justify-content:space-between;gap:20px;">
                    <div>
                        <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">Current Operational Status</div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="badge badge-success" style="font-size:13px;padding:4px 12px;font-weight:700;">
                                <?= status_label($application['status']) ?>
                            </span>
                            <span style="font-size:12.5px;color:var(--color-text-muted);">
                                Permanent institutional registration complete.
                            </span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--color-success);background:rgba(16,185,129,0.1);padding:6px 12px;border-radius:var(--radius-sm);border:1px solid rgba(16,185,129,0.3);">
                            <i data-lucide="shield-check" style="width:14px;height:14px;"></i>
                            Archived &amp; Certified
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <!-- Interactive Status Update Form -->
                <form method="POST" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:20px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">

                    <div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;margin-bottom:18px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="status_select">Status Progression Stage</label>
                            <select name="status" id="status_select" class="form-control form-select" required>
                                <?php foreach (['pending','under_review','evaluated','returned','approved','rejected'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $application['status'] === $s ? 'selected' : '' ?>>
                                        <?= status_label($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint" style="margin-top:6px;font-size:11.5px;color:var(--color-text-muted);">
                                Updating status immediately triggers an audit log and notifies the applicant.
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <div class="form-label-row">
                                <label class="form-label" for="statusRemarks">Official Audit Remarks / Institutional Notice</label>
                                <span class="field-counter" id="statusRemarksCount">0 / 500</span>
                            </div>
                            <textarea name="remarks" id="statusRemarks" class="form-control" rows="3" 
                                      placeholder="Provide official rationale, examination findings, or applicant requirements…" 
                                      maxlength="500" data-char-counter="#statusRemarksCount"></textarea>
                            
                            <!-- Preset Template Chips -->
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:8px;">
                                <span style="font-size:11px;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;">Quick Presets:</span>
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Formalities and submission requirements verified complete. Passed for technical evaluation.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Formalities Complete
                                </button>
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Technical evaluation complete. Application meets institutional requirements.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Passed Evaluation
                                </button>
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Formality deficiencies identified. Please provide detailed technical description and revised claims.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Revisions Needed
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-success btn-sm" style="padding:8px 20px;" data-confirm="Confirm changing application status? The applicant and assigned personnel will be notified immediately.">
                            <i data-lucide="check" style="width:14px;height:14px;"></i>
                            Commit Status Progression
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>


        <!-- =================================================================
             SECTION 06: AUDIT TRAIL & STATUS HISTORY TIMELINE
             ================================================================= -->
        <section>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--color-border);">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:6px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                        06
                    </div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-text);">
                        Complete Audit Trail &amp; Remarks Timeline
                    </h3>
                </div>
                <span class="badge badge-secondary" style="font-size:11px;padding:2px 8px;">
                    <?= mysqli_num_rows($history) ?> Recorded Event<?= mysqli_num_rows($history) === 1 ? '' : 's' ?>
                </span>
            </div>

            <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:22px;">
                <?php if (mysqli_num_rows($history) === 0): ?>
                    <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No status change history recorded yet.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:18px;">
                        <?php while ($h = mysqli_fetch_assoc($history)): ?>
                        <div style="position:relative;padding-left:22px;border-left:2px solid var(--color-border);">
                            <div style="position:absolute;left:-6px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--color-primary-600);border:2px solid var(--color-surface);"></div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="badge <?= status_badge_class($h['status']) ?>" style="font-size:11.5px;padding:2px 8px;">
                                        <?= status_label($h['status']) ?>
                                    </span>
                                    <?php if ($h['first_name']): ?>
                                        <span style="font-size:12px;color:var(--color-text-muted);">
                                            Changed by <strong style="color:var(--color-text);"><?= clean($h['first_name'] . ' ' . $h['last_name']) ?></strong>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span style="font-family:var(--font-mono);font-size:11.5px;color:var(--color-text-muted);">
                                    <?= format_date($h['changed_at'], 'M d, Y · h:i A') ?>
                                </span>
                            </div>
                            <?php if ($h['remarks']): ?>
                                <div style="font-size:13px;color:var(--color-text);margin-top:8px;background:var(--color-bg);padding:10px 14px;border-radius:6px;border:1px solid var(--color-border);line-height:1.55;">
                                    <?= nl2br(clean($h['remarks'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
