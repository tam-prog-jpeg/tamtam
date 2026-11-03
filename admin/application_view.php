<?php
/**
 * admin/application_view.php
 * -----------------------------------------------------------------
 * Full detail view of a single application for the Administrator:
 *  - applicant + application info
 *  - uploaded documents (download & modal preview)
 *  - status change history (timeline)
 *  - actions: assign staff, assign evaluator, change status
 * Every action writes to application_status_history, notifies the
 * applicant, and logs an audit entry.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Application Details';
$currentPage = 'applications';

$applicationId = (int) ($_GET['id'] ?? 0);
if ($applicationId <= 0) {
    set_flash('error', 'Invalid application.');
    redirect('admin/applications.php');
}

// ---- Handle POST actions (assign / update status) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

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

        log_audit($conn, $_SESSION['user_id'], 'assign_application', "Application #$applicationId staff/evaluator updated");
        if ($applicantUserId) {
            create_notification($conn, $applicantUserId, 'Application Update', 'Your application has been assigned for processing.', 'applicant/my_applications.php');
        }
        set_flash('success', 'Assignment updated successfully.');
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

            log_audit($conn, $_SESSION['user_id'], 'update_status', "Application #$applicationId status set to $newStatus");
            if ($applicantUserId) {
                create_notification($conn, $applicantUserId, 'Application Status Updated', 'Your application status changed to ' . status_label($newStatus) . '.', 'applicant/my_applications.php');
            }
            set_flash('success', 'Status updated to ' . status_label($newStatus) . '.');
        }
    }
    redirect('admin/application_view.php?id=' . $applicationId);
}

// ---- Fetch application details ----
$stmt = mysqli_prepare($conn, "
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
mysqli_stmt_bind_param($stmt, 'i', $applicationId);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    set_flash('error', 'Application not found.');
    redirect('admin/applications.php');
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

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar with Navigation -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm btn-icon" title="Back to directory">
                <svg class="lucide-icon" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            </a>
            <span class="tracking" style="font-size:13px;padding:3px 8px;background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-sm);color:var(--color-primary-600);font-weight:600;">
                <?= clean($application['tracking_code']) ?>
            </span>
            <span class="badge <?= status_badge_class($application['status']) ?>">
                <?= status_label($application['status']) ?>
            </span>
        </div>
        <h2><?= clean($application['title']) ?></h2>
        <p>Submitted by <strong><?= clean($application['first_name'] . ' ' . $application['last_name']) ?></strong> on <?= format_date($application['submitted_at'], 'M d, Y · h:i A') ?></p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
            All Applications
        </a>
    </div>
</div>

<!-- 2. Two-Column Layout -->
<div class="grid-2" style="grid-template-columns: 1.55fr 1fr; gap:20px; align-items:start;">
    
    <!-- LEFT MAIN: Application Dossier & Files -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        
        <!-- Invention Dossier Card -->
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg class="lucide-icon" style="color:var(--color-primary-600);" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <h3>Invention Dossier & Specifications</h3>
                </div>
                <span class="badge badge-secondary"><?= clean($application['type_name']) ?></span>
            </div>
            <div class="card-body">
                <!-- Description / Abstract -->
                <div style="background:var(--color-bg);border:1px solid var(--color-border);border-radius:var(--radius-md);padding:16px;margin-bottom:18px;">
                    <div style="font-size:11px;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;font-weight:700;">Invention Abstract & Description</div>
                    <div style="color:var(--color-text);font-size:13.5px;line-height:1.7;">
                        <?= nl2br(clean($application['description'] ?: 'No detailed technical description provided for this submission.')) ?>
                    </div>
                </div>

                <!-- Metadata Attribute Table -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div style="padding:12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">IP Classification</div>
                        <div style="font-weight:600;color:var(--color-text);"><?= clean($application['type_name']) ?></div>
                    </div>

                    <div style="padding:12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">Tracking ID</div>
                        <div class="tracking" style="color:var(--color-primary-600);font-weight:600;"><?= clean($application['tracking_code']) ?></div>
                    </div>

                    <div style="padding:12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">Applicant Name & Type</div>
                        <div style="font-weight:600;color:var(--color-text);"><?= clean($application['first_name'] . ' ' . $application['last_name']) ?></div>
                        <span class="badge badge-info" style="font-size:10.5px;margin-top:4px;"><?= clean($application['applicant_type']) ?></span>
                    </div>

                    <div style="padding:12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">Contact & Email</div>
                        <div style="font-size:13px;color:var(--color-text);font-weight:500;"><?= clean($application['email']) ?></div>
                        <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;"><?= clean($application['contact_number'] ?: 'No phone provided') ?></div>
                    </div>

                    <div style="padding:12px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-md);grid-column:span 2;">
                        <div style="font-size:11px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;margin-bottom:4px;">Academic Affiliation</div>
                        <div style="font-weight:600;color:var(--color-text);"><?= clean($application['college_name'] ?? '—') ?></div>
                        <?php if ($application['department_name']): ?>
                            <div style="font-size:12px;color:var(--color-text-muted);margin-top:2px;"><?= clean($application['department_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Uploaded Documents Card -->
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg class="lucide-icon" style="color:var(--color-primary-600);" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <h3>Attached Documents & Files</h3>
                </div>
                <span class="badge badge-secondary"><?= mysqli_num_rows($documents) ?> Files</span>
            </div>
            <div class="table-wrap">
                <?php if (mysqli_num_rows($documents) === 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg class="lucide-icon" style="width:28px;height:28px;" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/></svg>
                        </div>
                        <h4>No documents uploaded</h4>
                        <p>No specifications, claims, or drawings have been attached to this filing yet.</p>
                    </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
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
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <svg class="lucide-icon" style="color:var(--color-text-muted);width:16px;height:16px;" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    <span style="font-weight:600;color:var(--color-text);"><?= clean($doc['file_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-size:10.5px;font-weight:700;">
                                    <?= strtoupper(clean($doc['file_type'])) ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-family:var(--font-mono);font-size:12px;background:var(--color-bg);padding:2px 6px;border-radius:4px;border:1px solid var(--color-border);">
                                    v<?= (int) $doc['version'] ?>
                                </span>
                            </td>
                            <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);">
                                <?= format_date($doc['uploaded_at'], 'M d, Y') ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:6px;justify-content:flex-end;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($doc['file_path']) ?>','<?= clean($doc['file_type']) ?>','<?= clean(addslashes($doc['file_name'])) ?>')" title="Quick Preview">
                                        <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Preview
                                    </button>
                                    <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($doc['file_path']) ?>" target="_blank" download title="Download File">
                                        <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
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
        </div>

    </div>

    <!-- RIGHT SIDEBAR: Assignment, Status Control & Audit History -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        
        <!-- Assignment Card -->
        <div class="card" style="border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:10px;background:var(--color-primary-50);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="users" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Staff &amp; Evaluator Assignment</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Assign workflow personnel for intake and technical review.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="form-group">
                        <label class="form-label" for="staff_id">Assigned IPO Staff (Intake &amp; Compliance)</label>
                        <select name="staff_id" id="staff_id" class="form-control form-select">
                            <option value="">— Unassigned —</option>
                            <?php mysqli_data_seek($staffList, 0); while ($s = mysqli_fetch_assoc($staffList)): ?>
                                <option value="<?= $s['user_id'] ?>" <?= (int)$application['assigned_staff_id'] === (int)$s['user_id'] ? 'selected' : '' ?>>
                                    <?= clean($s['first_name'] . ' ' . $s['last_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">
                            <i data-lucide="info"></i>
                            <span>Staff member conducts completeness check and intake logging.</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:18px;">
                        <label class="form-label" for="evaluator_id">Assigned Technical Evaluator (Novelty Review)</label>
                        <select name="evaluator_id" id="evaluator_id" class="form-control form-select">
                            <option value="">— Unassigned —</option>
                            <?php mysqli_data_seek($evaluatorList, 0); while ($e = mysqli_fetch_assoc($evaluatorList)): ?>
                                <option value="<?= $e['user_id'] ?>" <?= (int)$application['assigned_evaluator_id'] === (int)$e['user_id'] ? 'selected' : '' ?>>
                                    <?= clean($e['first_name'] . ' ' . $e['last_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-hint">
                            <i data-lucide="info"></i>
                            <span>Evaluator conducts prior art search and patentability audit.</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;height:40px;">
                        <i data-lucide="save" style="width:14px;height:14px;"></i>
                        Save Personnel Assignment
                    </button>
                </form>
            </div>
        </div>

        <!-- Status Management Card -->
        <div class="card" style="border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:10px;background:var(--color-success-bg);color:var(--color-success);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="git-commit" style="width:18px;height:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:15px;font-weight:700;">Update Application Status</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">Advance the application through official examination gates.</div>
                </div>
            </div>
            <div class="card-body" style="padding:20px;">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="form-group">
                        <label class="form-label" for="status_select">Current Status Progression</label>
                        <select name="status" id="status_select" class="form-control form-select" required>
                            <?php foreach (['pending','under_review','evaluated','returned','approved','rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $application['status'] === $s ? 'selected' : '' ?>>
                                    <?= status_label($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" for="statusRemarks">Official Audit Remarks / Feedback</label>
                            <span class="field-counter" id="statusRemarksCount">0 / 500</span>
                        </div>
                        <textarea name="remarks" id="statusRemarks" class="form-control" rows="3" 
                                  placeholder="Provide official rationale, required revisions, or notification notice to applicant…" 
                                  maxlength="500" data-char-counter="#statusRemarksCount"></textarea>
                        
                        <!-- Quick Preset Template Chips -->
                        <div style="margin-top:8px;">
                            <span style="font-size:11px;color:var(--color-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Preset Remarks:</span>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;">
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Formalities and submission requirements verified complete. Passed for technical evaluation.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Formalities Complete
                                </button>
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Technical assessment passed. Application recommended for certificate issuance.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Passed Evaluation
                                </button>
                                <button type="button" class="btn btn-outline btn-xs" style="font-size:11px;padding:2px 8px;" onclick="document.getElementById('statusRemarks').value='Formality deficiencies identified. Please provide detailed description and updated claims.'; document.getElementById('statusRemarks').dispatchEvent(new Event('input'));">
                                    Revisions Needed
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-sm" style="width:100%;justify-content:center;height:40px;" data-confirm="Confirm changing the application status? The applicant will be notified immediately.">
                        <i data-lucide="check" style="width:14px;height:14px;"></i>
                        Commit Status Progression
                    </button>
                </form>
            </div>
        </div>

        <!-- Status History Timeline Card -->
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg class="lucide-icon" style="color:var(--color-text-muted);" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <h3>Status Audit History</h3>
                </div>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($history) === 0): ?>
                    <p style="color:var(--color-text-muted);font-size:13px;margin:0;">No status changes recorded yet.</p>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:16px;">
                        <?php while ($h = mysqli_fetch_assoc($history)): ?>
                        <div style="position:relative;padding-left:18px;border-left:2px solid var(--color-border);">
                            <div style="position:absolute;left:-5px;top:4px;width:8px;height:8px;border-radius:50%;background:var(--color-primary-600);"></div>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                <span class="badge <?= status_badge_class($h['status']) ?>" style="font-size:11px;">
                                    <?= status_label($h['status']) ?>
                                </span>
                                <span style="font-family:var(--font-mono);font-size:11px;color:var(--color-text-muted);">
                                    <?= format_date($h['changed_at'], 'M d, Y · h:i A') ?>
                                </span>
                            </div>
                            <?php if ($h['first_name']): ?>
                                <div style="font-size:11.5px;color:var(--color-text-muted);margin-top:4px;">
                                    By: <strong><?= clean($h['first_name'] . ' ' . $h['last_name']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ($h['remarks']): ?>
                                <div style="font-size:12.5px;color:var(--color-text);margin-top:6px;background:var(--color-bg);padding:8px 10px;border-radius:6px;border:1px solid var(--color-border-subtle);line-height:1.5;">
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
