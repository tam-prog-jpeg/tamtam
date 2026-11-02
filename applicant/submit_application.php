<?php
/**
 * applicant/submit_application.php
 * -----------------------------------------------------------------
 * Lets an Applicant file a new IP application: title, description,
 * type, and one or more supporting documents (PDF/DOCX/images).
 * On success: creates the application (status = pending), logs the
 * first status-history entry, stores uploaded documents, and
 * notifies every active Administrator of the new submission.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$pageTitle   = 'Submit Application';
$currentPage = 'submit';

// ---- Resolve applicant profile ----
$stmt = mysqli_prepare($conn, "SELECT * FROM applicants WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$profile) {
    set_flash('error', 'Your applicant profile could not be found. Please contact the IPO.');
    redirect('applicant/dashboard.php');
}
$applicantId = $profile['applicant_id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $typeId      = (int) ($_POST['type_id'] ?? 0);
    $title       = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if ($typeId <= 0) $errors[] = 'Please select an application type.';
    if ($title === '') $errors[] = 'Please enter a title for your application.';
    if (strlen($title) > 255) $errors[] = 'Title is too long (max 255 characters).';

    // ---- Reshape $_FILES['documents'] into a simple list of file arrays ----
    $uploadedFiles = [];
    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['name'] as $i => $name) {
            if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $uploadedFiles[] = [
                'name'     => $_FILES['documents']['name'][$i],
                'type'     => $_FILES['documents']['type'][$i],
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'error'    => $_FILES['documents']['error'][$i],
                'size'     => $_FILES['documents']['size'][$i],
            ];
        }
    }

    $uploadConfig = get_upload_config($conn);
    // Validate each file up front so we fail before touching the database.
    $validatedFiles = [];
    foreach ($uploadedFiles as $file) {
        $check = validate_upload($file, $uploadConfig['allowed_types'], $uploadConfig['max_mb']);
        if (!$check['ok']) {
            $errors[] = $file['name'] . ': ' . $check['error'];
        } else {
            $validatedFiles[] = ['file' => $file, 'ext' => $check['ext']];
        }
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $trackingCode = generate_tracking_code($conn);

            $ins = mysqli_prepare($conn, "
                INSERT INTO applications (tracking_code, applicant_id, type_id, title, description, college_id, department_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            mysqli_stmt_bind_param($ins, 'siissii', $trackingCode, $applicantId, $typeId, $title, $description, $profile['college_id'], $profile['department_id']);
            mysqli_stmt_execute($ins);
            $newAppId = mysqli_insert_id($conn);
            mysqli_stmt_close($ins);

            $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, 'pending', 'Application submitted by applicant.', ?)");
            mysqli_stmt_bind_param($hist, 'ii', $newAppId, $_SESSION['user_id']);
            mysqli_stmt_execute($hist);
            mysqli_stmt_close($hist);

            foreach ($validatedFiles as $vf) {
                $stored = store_upload($vf['file'], $vf['ext'], 'documents');
                if ($stored) {
                    $version = next_document_version($conn, $newAppId, $vf['file']['name']);
                    $docIns = mysqli_prepare($conn, "
                        INSERT INTO documents (application_id, uploaded_by, file_name, stored_name, file_path, file_type, file_size, version)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    mysqli_stmt_bind_param($docIns, 'iisssssi', $newAppId, $_SESSION['user_id'], $vf['file']['name'], $stored['stored_name'], $stored['file_path'], $vf['ext'], $vf['file']['size'], $version);
                    mysqli_stmt_execute($docIns);
                    mysqli_stmt_close($docIns);
                }
            }

            // Notify all active Staff members of the incoming application
            $staffUsers = mysqli_query($conn, "
                SELECT u.user_id FROM users u
                JOIN roles r ON r.role_id = u.role_id
                WHERE r.role_name = 'Staff' AND u.status = 'active'
            ");
            while ($s = mysqli_fetch_assoc($staffUsers)) {
                create_notification(
                    $conn,
                    $s['user_id'],
                    'New Application Submitted',
                    "A new application ($trackingCode) was submitted by {$_SESSION['first_name']} {$_SESSION['last_name']} and is awaiting screening.",
                    'staff/verify.php'
                );
            }

            // Also notify Administrators
            $adminUsers = mysqli_query($conn, "
                SELECT u.user_id FROM users u
                JOIN roles r ON r.role_id = u.role_id
                WHERE r.role_name = 'Administrator' AND u.status = 'active'
            ");
            while ($adm = mysqli_fetch_assoc($adminUsers)) {
                create_notification(
                    $conn,
                    $adm['user_id'],
                    'New Application Filed',
                    "New application ($trackingCode) submitted: \"$title\".",
                    'admin/applications.php'
                );
            }

            log_audit($conn, $_SESSION['user_id'], 'submit_application', "Submitted application #$newAppId ($trackingCode)");
            mysqli_commit($conn);

            set_flash('success', "Application $trackingCode submitted successfully! You will receive updates as it progresses through verification.");
            redirect('applicant/application_view.php?id=' . $newAppId);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Something went wrong while submitting your application. Please try again.';
        }
    }
}

$applicationTypes = mysqli_query($conn, "SELECT * FROM application_types WHERE is_active = 1 ORDER BY type_name");
$uploadConfig = get_upload_config($conn);
$acceptAttr   = '.' . implode(',.', $uploadConfig['allowed_types']);
$acceptLabel  = strtoupper(implode(', ', $uploadConfig['allowed_types']));
$maxMb        = $uploadConfig['max_mb'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Sub-Header Bar -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <h2>File a New IP Application</h2>
        <p>Submit your technical disclosure, project specification, and supporting documents for evaluation.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline" title="Return to applications directory">
            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
            My Applications
        </a>
    </div>
</div>

<!-- 2. Main Two-Column Layout -->
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;align-items:start;">
    <!-- Left Column: Submission Form -->
    <div class="card" style="border-radius:var(--radius-lg);">
        <div class="card-header" style="padding:18px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:8px;background:rgba(37,99,235,0.1);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;">
                <i data-lucide="file-plus" style="width:18px;height:18px;"></i>
            </div>
            <div>
                <h3 style="margin:0;font-size:16px;">Application Details</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Fill in all required fields and upload complete attachments</div>
            </div>
        </div>

        <div class="card-body" style="padding:22px;">
            <?php if (!empty($errors)): ?>
                <div style="background:var(--color-danger-bg);border:1px solid rgba(239,68,68,0.3);color:var(--color-danger);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:20px;font-size:13px;">
                    <div style="font-weight:600;display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <i data-lucide="alert-circle" style="width:16px;height:16px;"></i>
                        Please resolve the following before submitting:
                    </div>
                    <ul style="margin:0;padding-left:22px;line-height:1.5;">
                        <?php foreach ($errors as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?php csrf_field(); ?>

                <!-- IP Type -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="type_id" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                        Intellectual Property Category <span style="color:var(--color-danger);">*</span>
                    </label>
                    <select name="type_id" id="type_id" class="form-control" style="height:42px;font-size:13.5px;" required>
                        <option value="">— Select IP Category —</option>
                        <?php while ($t = mysqli_fetch_assoc($applicationTypes)): ?>
                            <option value="<?= $t['type_id'] ?>" data-desc="<?= clean($t['requirements']) ?>" <?= (int)($_POST['type_id'] ?? 0) === (int)$t['type_id'] ? 'selected' : '' ?>>
                                <?= clean($t['type_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="typeRequirements" style="margin-top:6px;font-size:12px;color:var(--color-text-muted);line-height:1.4;"></div>
                </div>

                <!-- Title -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="title" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                        Invention / Work Title <span style="color:var(--color-danger);">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        id="title" 
                        class="form-control" 
                        maxlength="255" 
                        placeholder="e.g. Automated Soil Moisture & Nutrient Monitoring Apparatus" 
                        value="<?= clean($_POST['title'] ?? '') ?>" 
                        style="height:42px;font-size:13.5px;"
                        required
                    >
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:4px;">
                        Use an official, descriptive title without trademark buzzwords.
                    </div>
                </div>

                <!-- Description / Disclosure -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="description" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                        Executive Summary &amp; Technical Disclosure
                    </label>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="form-control" 
                        rows="5"
                        placeholder="Summarize the core technical features, novel claims, or creative utility of your intellectual property work..."
                        style="font-size:13.5px;line-height:1.6;"
                    ><?= clean($_POST['description'] ?? '') ?></textarea>
                </div>

                <!-- Supporting Documents Upload -->
                <div class="form-group" style="margin-bottom:24px;">
                    <label for="documents" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                        Supporting Technical Disclosures &amp; Files
                    </label>
                    <input 
                        type="file" 
                        name="documents[]" 
                        id="documents" 
                        class="form-control" 
                        multiple 
                        accept="<?= clean($acceptAttr) ?>"
                        style="height:42px;padding:8px 12px;font-size:13px;"
                    >
                    <div class="form-hint" style="font-size:12px;color:var(--color-text-muted);margin-top:6px;display:flex;align-items:center;gap:6px;">
                        <i data-lucide="info" style="width:13px;height:13px;color:var(--color-primary-600);flex-shrink:0;"></i>
                        <span>Accepted formats: <strong><?= clean($acceptLabel) ?></strong> · Max <?= (int)$maxMb ?>MB per file.</span>
                    </div>
                </div>

                <!-- Action Controls -->
                <div style="display:flex;align-items:center;gap:12px;border-top:1px solid var(--color-border);padding-top:18px;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:14px;">
                        <i data-lucide="send" style="width:15px;height:15px;"></i>
                        Submit Application for Screening
                    </button>
                    <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline" style="padding:10px 18px;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Filing Guidelines & Process Scope -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <!-- Card 1: Review Pipeline Guide -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                <i data-lucide="git-commit" style="width:17px;height:17px;color:var(--color-primary-600);"></i>
                <h3 style="margin:0;font-size:15px;">Submission Pipeline</h3>
            </div>
            <div class="card-body" style="padding:18px 20px;">
                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:26px;height:26px;border-radius:50%;background:rgba(37,99,235,0.1);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">1</div>
                        <div>
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Staff Verification</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">IPO Staff inspects documentary completeness and formats.</div>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:26px;height:26px;border-radius:50%;background:rgba(245,158,11,0.1);color:var(--color-warning);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">2</div>
                        <div>
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Technical Evaluation</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">Assigned Technical Evaluator examines claims and novelty.</div>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:26px;height:26px;border-radius:50%;background:rgba(16,185,129,0.1);color:var(--color-success);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">3</div>
                        <div>
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Director Executive Sign-Off</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">Final approval is granted and registration certificate generated.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Filing Checklist -->
        <div class="card" style="border-radius:var(--radius-lg);">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                <i data-lucide="check-square" style="width:17px;height:17px;color:var(--color-success);"></i>
                <h3 style="margin:0;font-size:15px;">Filing Checklist</h3>
            </div>
            <div class="card-body" style="padding:18px 20px;font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">
                <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                    <li>Ensure all co-inventors and student contributors are acknowledged.</li>
                    <li>Upload complete technical disclosures, drawings, or schematics.</li>
                    <li>Avoid premature public disclosures (conferences/social media) before filing.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    var typeSelect = document.getElementById('type_id');
    var typeReq = document.getElementById('typeRequirements');
    if (typeSelect && typeReq) {
        typeSelect.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            typeReq.textContent = opt.dataset.desc ? 'Standard requirements: ' + opt.dataset.desc : '';
        });
        if (typeSelect.selectedIndex > 0) {
            var opt = typeSelect.options[typeSelect.selectedIndex];
            typeReq.textContent = opt.dataset.desc ? 'Standard requirements: ' + opt.dataset.desc : '';
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
