<?php
/**
 * applicant/submit_application.php
 * -----------------------------------------------------------------
 * Lets an Applicant file a new IP application:
 * Dynamically adapts the statutory questionnaire, technical prompts,
 * and documentary checklist based on the selected IP category:
 *  - Patent (Invention Patent: Novelty, Inventive Step, Industrial Applicability - RA 8293 Part II Ch. II)
 *  - Utility Model (Petty Patent: Practical Utility, New Construction - RA 8293 Part II Ch. XII)
 *  - Industrial Design (Aesthetic/Ornamental Lines, Locarno Classification - RA 8293 Part II Ch. XIII)
 *  - Trademark (Distinctiveness, Mark Specimen, Nice Classification, DAU - RA 8293 Part III)
 *  - Copyright (Statutory Works Sec. 172, Authorship, Deposit Specimen - RA 8293 Part IV)
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

// Load active application types
$applicationTypesResult = mysqli_query($conn, "SELECT * FROM application_types WHERE is_active = 1 ORDER BY type_name");
$appTypesList = [];
while ($t = mysqli_fetch_assoc($applicationTypesResult)) {
    $appTypesList[] = $t;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');

    $typeId      = (int) ($_POST['type_id'] ?? 0);
    $title       = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if ($typeId <= 0) $errors[] = 'Please select an intellectual property category.';
    if ($title === '') $errors[] = 'Please enter a title for your application.';
    if (strlen($title) > 255) $errors[] = 'Title is too long (max 255 characters).';

    // Resolve category name
    $selectedTypeName = '';
    foreach ($appTypesList as $at) {
        if ((int)$at['type_id'] === $typeId) {
            $selectedTypeName = $at['type_name'];
            break;
        }
    }

    // Compile Category-Specific Statutory Questionnaire
    $compiledParts = [];

    if ($selectedTypeName === 'Patent') {
        $techField       = sanitize_input($_POST['patent_technical_field'] ?? '');
        $priorArt        = sanitize_input($_POST['patent_prior_art'] ?? '');
        $inventiveStep   = sanitize_input($_POST['patent_inventive_step'] ?? '');
        $claims          = sanitize_input($_POST['patent_claims'] ?? '');
        $industrialApp   = sanitize_input($_POST['patent_industrial_applicability'] ?? '');
        $priorDisclosure = sanitize_input($_POST['patent_prior_disclosure'] ?? '');
        $priorDetails    = sanitize_input($_POST['patent_prior_disclosure_details'] ?? '');

        if ($techField === '') $errors[] = 'Patent: Please specify the Technical Field / Discipline.';
        if ($priorArt === '') $errors[] = 'Patent: Please describe the Background of the Invention & Prior Art Deficiencies.';
        if ($inventiveStep === '') $errors[] = 'Patent: Please explain the Technical Solution & Inventive Step.';
        if ($claims === '') $errors[] = 'Patent: Please enumerate the Primary Claims & Novel Technical Features.';
        if ($industrialApp === '') $errors[] = 'Patent: Please state the Industrial & Commercial Applicability.';

        $compiledParts[] = "=== STATUTORY IP DISCLOSURE: INVENTION PATENT ===";
        $compiledParts[] = "Legal Basis: Republic Act No. 8293 (Intellectual Property Code of the Philippines), Part II, Ch. II\n";
        $compiledParts[] = "1. TECHNICAL FIELD & DISCIPLINE:\n" . $techField;
        $compiledParts[] = "\n2. BACKGROUND & DEFICIENCIES OF PRIOR ART:\n" . $priorArt;
        $compiledParts[] = "\n3. TECHNICAL SOLUTION & INVENTIVE STEP (NON-OBVIOUSNESS):\n" . $inventiveStep;
        $compiledParts[] = "\n4. PRIMARY CLAIMS & NOVEL TECHNICAL FEATURES:\n" . $claims;
        $compiledParts[] = "\n5. INDUSTRIAL & COMMERCIAL APPLICABILITY:\n" . $industrialApp;
        $disclosureFull  = $priorDisclosure . ($priorDetails !== '' ? " — $priorDetails" : "");
        $compiledParts[] = "\n6. PRIOR PUBLIC DISCLOSURE DECLARATION (RA 8293 SEC. 25):\n" . $disclosureFull;

    } elseif ($selectedTypeName === 'Utility Model') {
        $deviceField     = sanitize_input($_POST['um_device_field'] ?? '');
        $drawback        = sanitize_input($_POST['um_existing_drawback'] ?? '');
        $construction    = sanitize_input($_POST['um_technical_construction'] ?? '');
        $operationalGain = sanitize_input($_POST['um_operational_benefit'] ?? '');
        $prototypeStatus = sanitize_input($_POST['um_prototype_status'] ?? '');

        if ($deviceField === '') $errors[] = 'Utility Model: Please specify the Practical Apparatus / Device Field.';
        if ($drawback === '') $errors[] = 'Utility Model: Please describe the Deficiencies of Conventional Devices.';
        if ($construction === '') $errors[] = 'Utility Model: Please describe the New Physical Configuration & Structural Construction.';
        if ($operationalGain === '') $errors[] = 'Utility Model: Please state the Practical Advantages & Operational Utility.';

        $compiledParts[] = "=== STATUTORY IP DISCLOSURE: UTILITY MODEL ===";
        $compiledParts[] = "Legal Basis: Republic Act No. 8293, Part II, Chapter XII (Practical Petty Patent)\n";
        $compiledParts[] = "1. FIELD OF THE PRACTICAL APPARATUS / IMPLEMENT:\n" . $deviceField;
        $compiledParts[] = "\n2. DEFICIENCIES OF CONVENTIONAL DEVICES:\n" . $drawback;
        $compiledParts[] = "\n3. NEW PHYSICAL CONFIGURATION & STRUCTURAL CONSTRUCTION:\n" . $construction;
        $compiledParts[] = "\n4. PRACTICAL ADVANTAGES & OPERATIONAL UTILITY:\n" . $operationalGain;
        $compiledParts[] = "\n5. PROTOTYPE READINESS & PRACTICAL VERIFICATION:\n" . $prototypeStatus;

    } elseif ($selectedTypeName === 'Industrial Design') {
        $articleName     = sanitize_input($_POST['id_article_name'] ?? '');
        $locarnoClass    = sanitize_input($_POST['id_locarno_class'] ?? '');
        $visualFeatures  = sanitize_input($_POST['id_visual_features'] ?? '');
        $viewsConfirmed  = sanitize_input($_POST['id_orthographic_views'] ?? '');
        $noveltyStmt     = sanitize_input($_POST['id_novelty_statement'] ?? '');

        if ($articleName === '') $errors[] = 'Industrial Design: Please specify the Article of Manufacture.';
        if ($visualFeatures === '') $errors[] = 'Industrial Design: Please describe the Distinctive Visual & Aesthetic Features.';
        if ($noveltyStmt === '') $errors[] = 'Industrial Design: Please provide the Statement of Novelty.';

        $compiledParts[] = "=== STATUTORY IP DISCLOSURE: INDUSTRIAL DESIGN ===";
        $compiledParts[] = "Legal Basis: Republic Act No. 8293, Part II, Chapter XIII & Locarno Classification System\n";
        $compiledParts[] = "1. ARTICLE OF MANUFACTURE / PRODUCT EMBODIMENT:\n" . $articleName;
        $compiledParts[] = "\n2. LOCARNO INTERNATIONAL CLASSIFICATION:\n" . $locarnoClass;
        $compiledParts[] = "\n3. DISTINCTIVE VISUAL, AESTHETIC & ORNAMENTAL FEATURES:\n" . $visualFeatures;
        $compiledParts[] = "\n4. STANDARD ORTHOGRAPHIC PERSPECTIVES ATTACHED:\n" . $viewsConfirmed;
        $compiledParts[] = "\n5. STATEMENT OF NOVELTY:\n" . $noveltyStmt;

    } elseif ($selectedTypeName === 'Trademark') {
        $markType        = sanitize_input($_POST['tm_mark_type'] ?? '');
        $literalMeaning  = sanitize_input($_POST['tm_literal_meaning'] ?? '');
        $colorClaimed    = sanitize_input($_POST['tm_color_claimed'] ?? '');
        $niceClass       = sanitize_input($_POST['tm_nice_classification'] ?? '');
        $commercialStat  = sanitize_input($_POST['tm_commercial_status'] ?? '');

        if ($markType === '') $errors[] = 'Trademark: Please select the Mark Type.';
        if ($niceClass === '') $errors[] = 'Trademark: Please provide the Nice Classification and List of Goods/Services.';

        $compiledParts[] = "=== STATUTORY IP DISCLOSURE: TRADEMARK ===";
        $compiledParts[] = "Legal Basis: Republic Act No. 8293, Part III & Nice Classification System\n";
        $compiledParts[] = "1. TYPE OF TRADEMARK:\n" . $markType;
        $compiledParts[] = "\n2. LITERAL MEANING, ACRONYM, OR TRANSLATION:\n" . ($literalMeaning ?: 'None / Arbitrary Mark');
        $compiledParts[] = "\n3. COLOR CLAIM & SPECIFICATION:\n" . ($colorClaimed ?: 'None (All colors / Black & White)');
        $compiledParts[] = "\n4. NICE CLASSIFICATION & SPECIFICATION OF GOODS / SERVICES:\n" . $niceClass;
        $compiledParts[] = "\n5. COMMERCIAL USE STATUS (DECLARATION OF ACTUAL USE - DAU):\n" . $commercialStat;

    } elseif ($selectedTypeName === 'Copyright') {
        $workClass       = sanitize_input($_POST['cr_work_classification'] ?? '');
        $creationYear    = sanitize_input($_POST['cr_creation_year'] ?? '');
        $pubStatus       = sanitize_input($_POST['cr_publication_status'] ?? '');
        $pubDetails      = sanitize_input($_POST['cr_first_publication_details'] ?? '');
        $coAuthors       = sanitize_input($_POST['cr_co_authors'] ?? '');
        $fundingSource   = sanitize_input($_POST['cr_institutional_funding'] ?? '');
        $specimenDesc    = sanitize_input($_POST['cr_specimen_description'] ?? '');

        if ($workClass === '') $errors[] = 'Copyright: Please select the Classification of Work.';
        if ($creationYear === '') $errors[] = 'Copyright: Please enter the Year/Date of Creation.';
        if ($coAuthors === '') $errors[] = 'Copyright: Please enumerate the Co-Authors & Contribution Allocation.';

        $compiledParts[] = "=== STATUTORY IP DISCLOSURE: COPYRIGHT DEPOSIT ===";
        $compiledParts[] = "Legal Basis: Republic Act No. 8293, Part IV (Sec. 172 Statutory Works)\n";
        $compiledParts[] = "1. CLASSIFICATION OF WORK (SEC. 172 RA 8293):\n" . $workClass;
        $compiledParts[] = "\n2. YEAR & DATE OF CREATION:\n" . $creationYear;
        $pubFull         = $pubStatus . ($pubDetails !== '' ? " — $pubDetails" : "");
        $compiledParts[] = "\n3. PUBLICATION STATUS & FIRST PUBLICATION DETAILS:\n" . $pubFull;
        $compiledParts[] = "\n4. CO-AUTHORS & ACADEMIC CONTRIBUTION ALLOCATION:\n" . $coAuthors;
        $compiledParts[] = "\n5. INSTITUTIONAL SPONSORSHIP & FUNDING SOURCE:\n" . $fundingSource;
        $compiledParts[] = "\n6. NATURE OF DEPOSITED SPECIMEN:\n" . ($specimenDesc ?: 'Attached digital specimen');
    }

    if (!empty($description)) {
        $compiledParts[] = "\n---------------------------------------------------------------------";
        $compiledParts[] = "EXECUTIVE SUMMARY / SUPPLEMENTAL TECHNICAL NOTES:\n" . $description;
    }

    $finalDescription = !empty($compiledParts) ? implode("\n", $compiledParts) : $description;

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
            mysqli_stmt_bind_param($ins, 'siissii', $trackingCode, $applicantId, $typeId, $title, $finalDescription, $profile['college_id'], $profile['department_id']);
            mysqli_stmt_execute($ins);
            $newAppId = mysqli_insert_id($conn);
            mysqli_stmt_close($ins);

            $hist = mysqli_prepare($conn, "INSERT INTO application_status_history (application_id, status, remarks, changed_by) VALUES (?, 'pending', 'Application submitted with statutory IP disclosure.', ?)");
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
                    "A new $selectedTypeName application ($trackingCode) was submitted by {$_SESSION['first_name']} {$_SESSION['last_name']} and is awaiting screening.",
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
                    "New $selectedTypeName application ($trackingCode) submitted: \"$title\".",
                    'admin/applications.php'
                );
            }

            log_audit($conn, $_SESSION['user_id'], 'submit_application', "Submitted application #$newAppId ($trackingCode) under $selectedTypeName");
            mysqli_commit($conn);

            set_flash('success', "Application $trackingCode ($selectedTypeName) submitted successfully! You will receive updates as it progresses through formality verification.");
            redirect('applicant/application_view.php?id=' . $newAppId);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Something went wrong while submitting your application. Please try again.';
        }
    }
}

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
        <p>Submit your statutory disclosure, project specification, and supporting documents for IPO evaluation.</p>
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
                <h3 style="margin:0;font-size:16px;">Statutory Intellectual Property Disclosure</h3>
                <div style="font-size:12px;color:var(--color-text-muted);">Fill in all category-specific technical questions mandated by IPOPHL and RA 8293</div>
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

            <form method="POST" enctype="multipart/form-data" id="ipApplicationForm">
                <?php csrf_field(); ?>

                <!-- Step 1: Category Selection -->
                <div class="form-group" style="margin-bottom:22px;">
                    <div class="form-label-row">
                        <label for="type_id" class="form-label" style="font-size:14px;font-weight:700;">
                            1. Intellectual Property Category <span class="required-mark">*</span>
                        </label>
                        <span class="optional-badge">Statutory Track</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="layers" class="input-icon-left"></i>
                        <select name="type_id" id="type_id" class="form-control form-select" required onchange="onTypeChange()">
                            <option value="">— Select IP Category to Load Form —</option>
                            <?php foreach ($appTypesList as $t): ?>
                                <option 
                                    value="<?= $t['type_id'] ?>" 
                                    data-name="<?= clean($t['type_name']) ?>" 
                                    data-desc="<?= clean($t['requirements']) ?>" 
                                    <?= (int)($_POST['type_id'] ?? 0) === (int)$t['type_id'] ? 'selected' : '' ?>
                                >
                                    <?= clean($t['type_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="typeRequirements" class="form-hint" style="min-height:18px;"></div>
                </div>

                <!-- Step 2: Invention / Work Title -->
                <div class="form-group" style="margin-bottom:22px;">
                    <div class="form-label-row">
                        <label for="title" class="form-label" style="font-size:14px;font-weight:700;">
                            2. Title of the Intellectual Property Work / Creation <span class="required-mark">*</span>
                        </label>
                        <span class="field-counter" id="titleCounter">0 / 255</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="lightbulb" class="input-icon-left"></i>
                        <input 
                            type="text" 
                            name="title" 
                            id="title" 
                            class="form-control" 
                            maxlength="255" 
                            placeholder="e.g. Automated Cassava Washing &amp; Slicing Apparatus / AgriSmart Brand / Machine Learning Crop Predictor" 
                            value="<?= clean($_POST['title'] ?? '') ?>" 
                            oninput="updateTitleCount(this)"
                            required
                        >
                    </div>
                    <div class="form-hint">
                        <i data-lucide="info"></i>
                        <span>Provide a formal, descriptive technical title that clearly identifies the innovation or creative work.</span>
                    </div>
                </div>

                <!-- =============================================================
                     STEP 3: DYNAMIC CATEGORY-SPECIFIC STATUTORY QUESTIONNAIRE
                     ============================================================= -->
                <div id="categoryQuestionnaires" style="margin-top:10px;margin-bottom:24px;">

                    <!-- Empty State Placeholder -->
                    <div id="panel-empty" class="category-question-panel" style="text-align:center;padding:32px 20px;background:var(--color-surface-subtle);border:1.5px dashed var(--color-border);border-radius:var(--radius-lg);margin-bottom:20px;">
                        <div style="width:44px;height:44px;border-radius:50%;background:rgba(37,99,235,0.08);color:var(--color-primary-600);display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px;">
                            <i data-lucide="arrow-up" style="width:20px;height:20px;"></i>
                        </div>
                        <h4 style="margin:0 0 6px;font-size:15px;color:var(--color-text);">Select an IP Category Above</h4>
                        <p style="margin:0;font-size:12.5px;color:var(--color-text-muted);max-width:440px;margin:0 auto;line-height:1.5;">
                            Each intellectual property classification (Patent, Utility Model, Industrial Design, Trademark, or Copyright) enforces specific statutory questions mandated under Republic Act No. 8293 and IPOPHL regulations.
                        </p>
                    </div>

                    <!-- =========================================================
                         CATEGORY 1: INVENTION PATENT QUESTIONNAIRE
                         Criteria: Novelty, Inventive Step, Industrial Applicability
                         ========================================================= -->
                    <div id="panel-patent" class="category-question-panel" style="display:none;">
                        <div style="background:rgba(37,99,235,0.05);border:1px solid rgba(37,99,235,0.2);border-left:4px solid var(--color-primary-600);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="lightbulb" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
                                    <div>
                                        <strong style="font-size:14px;color:var(--color-text);display:block;">Invention Patent Statutory Disclosure</strong>
                                        <span style="font-size:11.5px;color:var(--color-text-muted);">Republic Act 8293, Part II, Chapter II · 20-Year Legal Protection Term</span>
                                    </div>
                                </div>
                                <span class="badge badge-primary" style="font-size:11px;">High Inventive Step Required</span>
                            </div>
                        </div>

                        <!-- 1. Technical Field -->
                        <div class="form-group">
                            <label class="form-label">
                                Technical Field / Engineering Discipline <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="cpu" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    name="patent_technical_field" 
                                    id="patent_technical_field" 
                                    class="form-control" 
                                    data-required="true"
                                    placeholder="e.g. Agricultural Mechanization, Embedded IoT Sensors, Biomedical Diagnostic Instrumentation" 
                                    value="<?= clean($_POST['patent_technical_field'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Identify the specific technological or industrial art to which this invention pertains.</span>
                            </div>
                        </div>

                        <!-- 2. Prior Art Background & Deficiencies -->
                        <div class="form-group">
                            <label class="form-label">
                                Background of the Invention &amp; Prior Art Deficiencies <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="patent_prior_art" 
                                id="patent_prior_art" 
                                class="form-control" 
                                rows="4" 
                                data-required="true"
                                placeholder="Describe existing conventional methods, published patents, or machines currently in use and their technical deficiencies, bottlenecks, high costs, or operational failures..."
                            ><?= clean($_POST['patent_prior_art'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>State the technical problems in prior technologies that this invention explicitly aims to overcome.</span>
                            </div>
                        </div>

                        <!-- 3. Technical Solution & Inventive Step -->
                        <div class="form-group">
                            <label class="form-label">
                                Technical Solution &amp; Inventive Step (Non-Obviousness) <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="patent_inventive_step" 
                                id="patent_inventive_step" 
                                class="form-control" 
                                rows="4" 
                                data-required="true"
                                placeholder="Explain how the invention technically solves the problem. Detail the non-obvious mechanism, sequence of operations, or chemical/electronic principle that would not be obvious to a person skilled in the art..."
                            ><?= clean($_POST['patent_inventive_step'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Crucial: Highlight what makes the technical mechanism surprising or unobvious compared to existing art.</span>
                            </div>
                        </div>

                        <!-- 4. Primary Claims -->
                        <div class="form-group">
                            <label class="form-label">
                                Primary Claims &amp; Novel Technical Features <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="patent_claims" 
                                id="patent_claims" 
                                class="form-control" 
                                rows="4" 
                                data-required="true"
                                placeholder="Claim 1 (Independent): An apparatus comprising a housing, a multi-sensor array, and a microcontroller configured to...&#10;Claim 2 (Dependent): The apparatus of Claim 1, further characterized by..."
                            ><?= clean($_POST['patent_claims'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Enumerate the specific structural, mechanical, or functional boundaries for which you seek exclusive monopoly.</span>
                            </div>
                        </div>

                        <!-- 5. Industrial Applicability -->
                        <div class="form-group">
                            <label class="form-label">
                                Industrial &amp; Commercial Applicability <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="factory" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    name="patent_industrial_applicability" 
                                    id="patent_industrial_applicability" 
                                    class="form-control" 
                                    data-required="true"
                                    placeholder="e.g. Can be mass-manufactured via standard PCB fabrication and adopted across rural farming cooperatives" 
                                    value="<?= clean($_POST['patent_industrial_applicability'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>State how the invention can be made or practically utilized in any kind of trade or industry.</span>
                            </div>
                        </div>

                        <!-- 6. Prior Public Disclosure Declaration -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Prior Public Disclosure Declaration <span class="required-mark">*</span>
                                </label>
                                <select name="patent_prior_disclosure" id="patent_prior_disclosure" class="form-control form-select" data-required="true" onchange="togglePatentDisclosureDetails()">
                                    <option value="No prior public disclosure (Strict Novelty Preserved)" <?= ($_POST['patent_prior_disclosure'] ?? '') === 'No prior public disclosure (Strict Novelty Preserved)' ? 'selected' : '' ?>>No prior public disclosure (Novelty Preserved)</option>
                                    <option value="Disclosed in Academic Thesis / Capstone Defense" <?= ($_POST['patent_prior_disclosure'] ?? '') === 'Disclosed in Academic Thesis / Capstone Defense' ? 'selected' : '' ?>>Disclosed in Academic Thesis / Defense</option>
                                    <option value="Disclosed at a Scientific Conference / Published Journal" <?= ($_POST['patent_prior_disclosure'] ?? '') === 'Disclosed at a Scientific Conference / Published Journal' ? 'selected' : '' ?>>Disclosed in Conference / Journal Paper</option>
                                    <option value="Exhibited in Innovation Competition / Public Expo" <?= ($_POST['patent_prior_disclosure'] ?? '') === 'Exhibited in Innovation Competition / Public Expo' ? 'selected' : '' ?>>Exhibited in Innovation Expo / Contest</option>
                                </select>
                            </div>

                            <div class="form-group" id="patent_disclosure_details_wrap">
                                <label class="form-label">Disclosure Date &amp; Venue Details</label>
                                <input 
                                    type="text" 
                                    name="patent_prior_disclosure_details" 
                                    id="patent_prior_disclosure_details" 
                                    class="form-control" 
                                    placeholder="e.g. Defended on Oct 12, 2026; ZPPSU CET Auditorium"
                                    value="<?= clean($_POST['patent_prior_disclosure_details'] ?? '') ?>"
                                >
                                <div class="form-hint" style="font-size:11px;">
                                    <span>RA 8293 Sec. 25 provides a 12-month grace period for disclosures made by the inventor.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         CATEGORY 2: UTILITY MODEL (UM) QUESTIONNAIRE
                         Criteria: Novelty, Practical Utility, Shape/Configuration
                         ========================================================= -->
                    <div id="panel-utility-model" class="category-question-panel" style="display:none;">
                        <div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.25);border-left:4px solid var(--color-warning);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="wrench" style="width:18px;height:18px;color:var(--color-warning);"></i>
                                    <div>
                                        <strong style="font-size:14px;color:var(--color-text);display:block;">Utility Model Practical Innovation Disclosure</strong>
                                        <span style="font-size:11.5px;color:var(--color-text-muted);">Republic Act 8293, Part II, Chapter XII · 7-Year Term (Fast-Track Protection)</span>
                                    </div>
                                </div>
                                <span class="badge badge-warning" style="font-size:11px;">Practical Utility Standard</span>
                            </div>
                        </div>

                        <!-- 1. Practical Apparatus Field -->
                        <div class="form-group">
                            <label class="form-label">
                                Field of the Practical Implement / Device <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="tool" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    name="um_device_field" 
                                    id="um_device_field" 
                                    class="form-control" 
                                    data-required="true"
                                    placeholder="e.g. Ergonomic Cassava Peeling Tool, Solar Dryer Rotary Drum Assembly, Handheld Seed Planter" 
                                    value="<?= clean($_POST['um_device_field'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Identify the tool, mechanical implement, or household device being improved.</span>
                            </div>
                        </div>

                        <!-- 2. Existing Deficiencies -->
                        <div class="form-group">
                            <label class="form-label">
                                Deficiencies of the Conventional Device or Apparatus <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="um_existing_drawback" 
                                id="um_existing_drawback" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="Describe the operational difficulties, heavy manual fatigue, high maintenance costs, or jamming that occurs in traditional models..."
                            ><?= clean($_POST['um_existing_drawback'] ?? '') ?></textarea>
                        </div>

                        <!-- 3. New Configuration & Construction -->
                        <div class="form-group">
                            <label class="form-label">
                                New Physical Configuration &amp; Structural Construction <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="um_technical_construction" 
                                id="um_technical_construction" 
                                class="form-control" 
                                rows="4" 
                                data-required="true"
                                placeholder="Describe the new spatial arrangement, mechanical connection of parts, modified shape of blades/brackets, or material assembly that produces practical utility..."
                            ><?= clean($_POST['um_technical_construction'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Focus on the structural shape, mechanical arrangement, and connection of physical components.</span>
                            </div>
                        </div>

                        <!-- 4. Operational Gains & Efficiency -->
                        <div class="form-group">
                            <label class="form-label">
                                Practical Advantages &amp; Operational Utility <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="um_operational_benefit" 
                                id="um_operational_benefit" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="e.g. Increases processing throughput by 40%, eliminates the need for electric power, and allows tool-free blade replacement..."
                            ><?= clean($_POST['um_operational_benefit'] ?? '') ?></textarea>
                        </div>

                        <!-- 5. Prototype Status -->
                        <div class="form-group">
                            <label class="form-label">
                                Prototype Readiness &amp; Practical Verification <span class="required-mark">*</span>
                            </label>
                            <select name="um_prototype_status" id="um_prototype_status" class="form-control form-select" data-required="true">
                                <option value="Functional Working Prototype Fabricated & Tested" <?= ($_POST['um_prototype_status'] ?? '') === 'Functional Working Prototype Fabricated & Tested' ? 'selected' : '' ?>>Functional Working Prototype Fabricated &amp; Tested</option>
                                <option value="Laboratory Bench-scale Prototype Built" <?= ($_POST['um_prototype_status'] ?? '') === 'Laboratory Bench-scale Prototype Built' ? 'selected' : '' ?>>Laboratory Bench-scale Prototype Built</option>
                                <option value="3D CAD Assembly & Complete Technical Drawings" <?= ($_POST['um_prototype_status'] ?? '') === '3D CAD Assembly & Complete Technical Drawings' ? 'selected' : '' ?>>3D CAD Assembly &amp; Complete Technical Drawings</option>
                            </select>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Please upload photos or drawings of the assembled unit in the attachment section below.</span>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         CATEGORY 3: INDUSTRIAL DESIGN QUESTIONNAIRE
                         Criteria: Aesthetic/Ornamental Lines, Shapes, Locarno Class
                         ========================================================= -->
                    <div id="panel-industrial-design" class="category-question-panel" style="display:none;">
                        <div style="background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.25);border-left:4px solid #8B5CF6;border-radius:var(--radius-md);padding:14px 18px;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="palette" style="width:18px;height:18px;color:#8B5CF6;"></i>
                                    <div>
                                        <strong style="font-size:14px;color:var(--color-text);display:block;">Industrial Design Aesthetic Specification</strong>
                                        <span style="font-size:11.5px;color:var(--color-text-muted);">Republic Act 8293, Part II, Chapter XIII &amp; Locarno Classification · 5 to 15 Years Term</span>
                                    </div>
                                </div>
                                <span class="badge" style="background:rgba(139,92,246,0.15);color:#7C3AED;font-size:11px;">Visual Aesthetic Standard</span>
                            </div>
                        </div>

                        <!-- 1. Article of Manufacture -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Article of Manufacture / Product Embodiment <span class="required-mark">*</span>
                                </label>
                                <div class="input-icon-wrap has-left-icon">
                                    <i data-lucide="shapes" class="input-icon-left"></i>
                                    <input 
                                        type="text" 
                                        name="id_article_name" 
                                        id="id_article_name" 
                                        class="form-control" 
                                        data-required="true"
                                        placeholder="e.g. Ergonomic Handheld Enclosure, Modular Bamboo Chair, Packaging Container" 
                                        value="<?= clean($_POST['id_article_name'] ?? '') ?>"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Locarno Classification (Suggested) <span class="required-mark">*</span>
                                </label>
                                <select name="id_locarno_class" id="id_locarno_class" class="form-control form-select" data-required="true">
                                    <option value="Class 09: Packages, Containers & Closures" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 09: Packages, Containers & Closures' ? 'selected' : '' ?>>Class 09: Packages, Containers &amp; Closures</option>
                                    <option value="Class 14: Telecommunications & Data Processing" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 14: Telecommunications & Data Processing' ? 'selected' : '' ?>>Class 14: Telecom &amp; Data Processing Equipment</option>
                                    <option value="Class 06: Furnishing & Interior Articles" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 06: Furnishing & Interior Articles' ? 'selected' : '' ?>>Class 06: Furnishing &amp; Furniture Articles</option>
                                    <option value="Class 02: Articles of Clothing & Apparel" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 02: Articles of Clothing & Apparel' ? 'selected' : '' ?>>Class 02: Articles of Clothing &amp; Apparel</option>
                                    <option value="Class 12: Means of Transport & Vehicles" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 12: Means of Transport & Vehicles' ? 'selected' : '' ?>>Class 12: Means of Transport &amp; Vehicles</option>
                                    <option value="Class 23: Sanitary, Heating & Ventilation" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 23: Sanitary, Heating & Ventilation' ? 'selected' : '' ?>>Class 23: Sanitary &amp; Heating Equipment</option>
                                    <option value="Class 99: Miscellaneous / Other Industrial Articles" <?= ($_POST['id_locarno_class'] ?? '') === 'Class 99: Miscellaneous / Other Industrial Articles' ? 'selected' : '' ?>>Class 99: Miscellaneous / Other Articles</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Distinctive Visual Features -->
                        <div class="form-group">
                            <label class="form-label">
                                Distinctive Visual, Aesthetic &amp; Ornamental Features <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="id_visual_features" 
                                id="id_visual_features" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="Describe the novel 3D contours, surface ribbing, flowing curves, geometric facets, bevels, or surface ornamentation (avoid technical or mechanical explanations)..."
                            ><?= clean($_POST['id_visual_features'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Protection applies strictly to the ornamental appearance, not internal mechanisms or functional dimensions.</span>
                            </div>
                        </div>

                        <!-- 3. Standard Views Included -->
                        <div class="form-group">
                            <label class="form-label">
                                Standard Orthographic Perspectives Uploaded <span class="required-mark">*</span>
                            </label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="image" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    name="id_orthographic_views" 
                                    id="id_orthographic_views" 
                                    class="form-control" 
                                    data-required="true"
                                    placeholder="e.g. Perspective, Front, Rear, Left Side, Right Side, Top Plan, and Bottom Plan views" 
                                    value="<?= clean($_POST['id_orthographic_views'] ?? 'Perspective, Front, Rear, Left Side, Right Side, Top, and Bottom views included in attachment') ?>"
                                >
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>IPOPHL eIDfile requires 7 clear views against a clean white or neutral background without dimensional lines.</span>
                            </div>
                        </div>

                        <!-- 4. Statement of Novelty -->
                        <div class="form-group">
                            <label class="form-label">
                                Statement of Novelty <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="id_novelty_statement" 
                                id="id_novelty_statement" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="The novelty of this design resides in the unique asymmetrical curvature of the outer casing combined with the chamfered geometric edge profile as illustrated..."
                            ><?= clean($_POST['id_novelty_statement'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- =========================================================
                         CATEGORY 4: TRADEMARK QUESTIONNAIRE
                         Criteria: Distinctiveness, Mark Specimen, Nice Class, DAU
                         ========================================================= -->
                    <div id="panel-trademark" class="category-question-panel" style="display:none;">
                        <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.25);border-left:4px solid var(--color-success);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="bookmark" style="width:18px;height:18px;color:var(--color-success);"></i>
                                    <div>
                                        <strong style="font-size:14px;color:var(--color-text);display:block;">Trademark Distinctiveness &amp; Brand Representation</strong>
                                        <span style="font-size:11.5px;color:var(--color-text-muted);">Republic Act 8293, Part III &amp; Nice Classification · 10-Year Renewable Protection Term</span>
                                    </div>
                                </div>
                                <span class="badge badge-success" style="font-size:11px;">Distinctiveness Standard</span>
                            </div>
                        </div>

                        <!-- 1. Mark Type & Literal Meaning -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Type of Mark <span class="required-mark">*</span>
                                </label>
                                <select name="tm_mark_type" id="tm_mark_type" class="form-control form-select" data-required="true">
                                    <option value="Combined Mark (Word with Stylized Graphic Logo)" <?= ($_POST['tm_mark_type'] ?? '') === 'Combined Mark (Word with Stylized Graphic Logo)' ? 'selected' : '' ?>>Combined Mark (Word with Stylized Graphic Logo)</option>
                                    <option value="Figurative Mark (Logo or Graphic Emblem Only)" <?= ($_POST['tm_mark_type'] ?? '') === 'Figurative Mark (Logo or Graphic Emblem Only)' ? 'selected' : '' ?>>Figurative Mark (Logo or Graphic Emblem Only)</option>
                                    <option value="Word Mark (Plain Text Name without stylized graphics)" <?= ($_POST['tm_mark_type'] ?? '') === 'Word Mark (Plain Text Name without stylized graphics)' ? 'selected' : '' ?>>Word Mark (Plain Text Name only)</option>
                                    <option value="3D Mark / Container Packaging Shape" <?= ($_POST['tm_mark_type'] ?? '') === '3D Mark / Container Packaging Shape' ? 'selected' : '' ?>>3D Mark / Product Container Shape</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Literal Meaning, Acronym, or Translation
                                </label>
                                <div class="input-icon-wrap has-left-icon">
                                    <i data-lucide="type" class="input-icon-left"></i>
                                    <input 
                                        type="text" 
                                        name="tm_literal_meaning" 
                                        id="tm_literal_meaning" 
                                        class="form-control" 
                                        placeholder="e.g. 'AgriSmart' is a portmanteau; no foreign dialect meaning" 
                                        value="<?= clean($_POST['tm_literal_meaning'] ?? '') ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- 2. Color Claim -->
                        <div class="form-group">
                            <label class="form-label">Color Claim &amp; Specification</label>
                            <div class="input-icon-wrap has-left-icon">
                                <i data-lucide="palette" class="input-icon-left"></i>
                                <input 
                                    type="text" 
                                    name="tm_color_claimed" 
                                    id="tm_color_claimed" 
                                    class="form-control" 
                                    placeholder="e.g. Navy Blue (#0F172A) and Gold (#D97706) / None (All Colors Claimed)" 
                                    value="<?= clean($_POST['tm_color_claimed'] ?? '') ?>"
                                >
                            </div>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>If specific colors are essential to brand recognition, list them explicitly. Otherwise, state 'None'.</span>
                            </div>
                        </div>

                        <!-- 3. Nice Classification & Specification -->
                        <div class="form-group">
                            <label class="form-label">
                                Nice International Classification &amp; Specification of Goods / Services <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="tm_nice_classification" 
                                id="tm_nice_classification" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="Class 09: Downloadable mobile application software for agricultural telemetry;&#10;Class 42: Software as a Service (SaaS) and scientific technical research services."
                            ><?= clean($_POST['tm_nice_classification'] ?? '') ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Specify the Nice Class Number(s) and list the exact commercial products or services identified by this mark.</span>
                            </div>
                        </div>

                        <!-- 4. Commercial Status (DAU) -->
                        <div class="form-group">
                            <label class="form-label">
                                Commercial Use Status (Declaration of Actual Use - DAU) <span class="required-mark">*</span>
                            </label>
                            <select name="tm_commercial_status" id="tm_commercial_status" class="form-control form-select" data-required="true">
                                <option value="Intent to Use (Mark will be commercialized post-filing)" <?= ($_POST['tm_commercial_status'] ?? '') === 'Intent to Use (Mark will be commercialized post-filing)' ? 'selected' : '' ?>>Intent to Use (Mark will be commercialized post-filing)</option>
                                <option value="Active Commercial Use (Currently in market in the Philippines)" <?= ($_POST['tm_commercial_status'] ?? '') === 'Active Commercial Use (Currently in market in the Philippines)' ? 'selected' : '' ?>>Active Commercial Use (Currently in market in the Philippines)</option>
                            </select>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Philippine trademark law requires filing a Declaration of Actual Use (DAU) within 3 years from application date.</span>
                            </div>
                        </div>
                    </div>

                    <!-- =========================================================
                         CATEGORY 5: COPYRIGHT QUESTIONNAIRE
                         Criteria: Originality, Authorship, Statutory Deposit Sec. 172
                         ========================================================= -->
                    <div id="panel-copyright" class="category-question-panel" style="display:none;">
                        <div style="background:rgba(6,182,212,0.06);border:1px solid rgba(6,182,212,0.25);border-left:4px solid #0891B2;border-radius:var(--radius-md);padding:14px 18px;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i data-lucide="book-open" style="width:18px;height:18px;color:#0891B2;"></i>
                                    <div>
                                        <strong style="font-size:14px;color:var(--color-text);display:block;">Copyright Authorship &amp; Statutory Deposit Record</strong>
                                        <span style="font-size:11.5px;color:var(--color-text-muted);">Republic Act 8293, Part IV (Sec. 172 Statutory Works) · Life + 50 Years Term</span>
                                    </div>
                                </div>
                                <span class="badge" style="background:rgba(6,182,212,0.15);color:#0891B2;font-size:11px;">Originality &amp; Authorship Standard</span>
                            </div>
                        </div>

                        <!-- 1. Work Classification & Creation Year -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Classification of Work (Sec. 172 RA 8293) <span class="required-mark">*</span>
                                </label>
                                <select name="cr_work_classification" id="cr_work_classification" class="form-control form-select" data-required="true">
                                    <option value="Class A: Books, Pamphlets, Research Theses, Articles & Manuscripts" <?= ($_POST['cr_work_classification'] ?? '') === 'Class A: Books, Pamphlets, Research Theses, Articles & Manuscripts' ? 'selected' : '' ?>>Class A: Books, Theses, Articles &amp; Manuscripts</option>
                                    <option value="Class G: Computer Programs, Software Applications & Source Code" <?= ($_POST['cr_work_classification'] ?? '') === 'Class G: Computer Programs, Software Applications & Source Code' ? 'selected' : '' ?>>Class G: Computer Programs &amp; Source Code</option>
                                    <option value="Class I: Teaching Modules, Lecture Notes, Syllabi & Instructional Guides" <?= ($_POST['cr_work_classification'] ?? '') === 'Class I: Teaching Modules, Lecture Notes, Syllabi & Instructional Guides' ? 'selected' : '' ?>>Class I: Teaching Modules &amp; Educational Materials</option>
                                    <option value="Class H: Architectural Drawings, Technical Blueprints & Plans" <?= ($_POST['cr_work_classification'] ?? '') === 'Class H: Architectural Drawings, Technical Blueprints & Plans' ? 'selected' : '' ?>>Class H: Architectural Plans &amp; Blueprints</option>
                                    <option value="Class D: Musical Compositions, Lyrics & Soundtracks" <?= ($_POST['cr_work_classification'] ?? '') === 'Class D: Musical Compositions, Lyrics & Soundtracks' ? 'selected' : '' ?>>Class D: Musical Compositions &amp; Soundtracks</option>
                                    <option value="Class O: Audiovisual Works, Films & Multimedia Presentations" <?= ($_POST['cr_work_classification'] ?? '') === 'Class O: Audiovisual Works, Films & Multimedia Presentations' ? 'selected' : '' ?>>Class O: Audiovisual Works &amp; Multimedia</option>
                                    <option value="Class E: Photographic Works & Original Digital Illustrations" <?= ($_POST['cr_work_classification'] ?? '') === 'Class E: Photographic Works & Original Digital Illustrations' ? 'selected' : '' ?>>Class E: Photographic &amp; Digital Illustrations</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Year &amp; Month of Creation / Completion <span class="required-mark">*</span>
                                </label>
                                <div class="input-icon-wrap has-left-icon">
                                    <i data-lucide="calendar" class="input-icon-left"></i>
                                    <input 
                                        type="text" 
                                        name="cr_creation_year" 
                                        id="cr_creation_year" 
                                        class="form-control" 
                                        data-required="true"
                                        placeholder="e.g. 2026 (Completed October 2026)" 
                                        value="<?= clean($_POST['cr_creation_year'] ?? date('Y')) ?>"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- 2. Publication Status & Details -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Publication Status <span class="required-mark">*</span>
                                </label>
                                <select name="cr_publication_status" id="cr_publication_status" class="form-control form-select" data-required="true">
                                    <option value="Unpublished Work (Private / Academic Archive Deposit)" <?= ($_POST['cr_publication_status'] ?? '') === 'Unpublished Work (Private / Academic Archive Deposit)' ? 'selected' : '' ?>>Unpublished Work (Private / Academic Deposit)</option>
                                    <option value="Published Work (Publicly circulated, sold, or printed)" <?= ($_POST['cr_publication_status'] ?? '') === 'Published Work (Publicly circulated, sold, or printed)' ? 'selected' : '' ?>>Published Work (Publicly circulated / printed)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">First Publication Date &amp; Place (If Published)</label>
                                <input 
                                    type="text" 
                                    name="cr_first_publication_details" 
                                    id="cr_first_publication_details" 
                                    class="form-control" 
                                    placeholder="e.g. Published on March 20, 2026 in Zamboanga City / N/A"
                                    value="<?= clean($_POST['cr_first_publication_details'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <!-- 3. Co-Authors & Contribution Allocation -->
                        <div class="form-group">
                            <label class="form-label">
                                Co-Authors &amp; Academic Contribution Allocation <span class="required-mark">*</span>
                            </label>
                            <textarea 
                                name="cr_co_authors" 
                                id="cr_co_authors" 
                                class="form-control" 
                                rows="3" 
                                data-required="true"
                                placeholder="e.g. Dr. Juan Dela Cruz (Lead Author - 50%), Engr. Maria Santos (Co-Author - 30%), Alex Reyes (Student Contributor - 20%)"
                            ><?= clean($_POST['cr_co_authors'] ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . ' (Sole Author - 100%)')) ?></textarea>
                            <div class="form-hint">
                                <i data-lucide="info"></i>
                                <span>Enumerate all contributing faculty, researchers, or student contributors with agreed percentage shares.</span>
                            </div>
                        </div>

                        <!-- 4. Institutional Funding & Nature of Specimen -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    Institutional Affiliation &amp; Research Funding Source <span class="required-mark">*</span>
                                </label>
                                <select name="cr_institutional_funding" id="cr_institutional_funding" class="form-control form-select" data-required="true">
                                    <option value="Regular University Employment Duties / Institutional Work" <?= ($_POST['cr_institutional_funding'] ?? '') === 'Regular University Employment Duties / Institutional Work' ? 'selected' : '' ?>>Regular University Duties / Institutional Work</option>
                                    <option value="Funded by University Institutional R&D Grant" <?= ($_POST['cr_institutional_funding'] ?? '') === 'Funded by University Institutional R&D Grant' ? 'selected' : '' ?>>Funded by University Institutional R&amp;D Grant</option>
                                    <option value="Funded by External Agency (DOST / CHED / DA)" <?= ($_POST['cr_institutional_funding'] ?? '') === 'Funded by External Agency (DOST / CHED / DA)' ? 'selected' : '' ?>>Funded by External Agency (DOST / CHED)</option>
                                    <option value="Independent Academic Study / Student Capstone" <?= ($_POST['cr_institutional_funding'] ?? '') === 'Independent Academic Study / Student Capstone' ? 'selected' : '' ?>>Independent Study / Student Capstone Project</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Nature of Deposited Specimen <span class="required-mark">*</span></label>
                                <input 
                                    type="text" 
                                    name="cr_specimen_description" 
                                    id="cr_specimen_description" 
                                    class="form-control" 
                                    data-required="true"
                                    placeholder="e.g. Complete 85-page Thesis Manuscript PDF / Source Code Repository (First & Last 25 pages)"
                                    value="<?= clean($_POST['cr_specimen_description'] ?? '') ?>"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Supplemental Executive Summary (Optional) -->
                <div class="form-group" style="margin-bottom:22px;">
                    <div class="form-label-row">
                        <label for="description" class="form-label">
                            Additional Executive Summary &amp; Supplemental Technical Notes
                        </label>
                        <span class="optional-badge">Optional</span>
                    </div>
                    <textarea 
                        name="description" 
                        id="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Add any extra project context, patent search citations, commercial partners, or special examiner remarks..."
                    ><?= clean($_POST['description'] ?? '') ?></textarea>
                </div>

                <!-- Step 5: Supporting Technical Disclosures & Files -->
                <div class="form-group" style="margin-bottom:24px;">
                    <div class="form-label-row">
                        <label for="documents" class="form-label" style="font-size:14px;font-weight:700;">
                            3. Supporting Technical Documents &amp; Formal Attachments
                        </label>
                        <span class="optional-badge">Max <?= (int)$maxMb ?>MB</span>
                    </div>
                    <div class="input-icon-wrap has-left-icon">
                        <i data-lucide="paperclip" class="input-icon-left"></i>
                        <input 
                            type="file" 
                            name="documents[]" 
                            id="documents" 
                            class="form-control" 
                            multiple 
                            accept="<?= clean($acceptAttr) ?>"
                            style="padding-top:8px;"
                        >
                    </div>
                    <div class="form-hint" id="fileCategoryHint">
                        <i data-lucide="info"></i>
                        <span>Accepted file formats: <strong><?= clean($acceptLabel) ?></strong>. You can upload multiple files at once.</span>
                    </div>
                </div>

                <!-- Action Controls -->
                <div style="display:flex;align-items:center;gap:12px;border-top:1px solid var(--color-border);padding-top:20px;margin-top:22px;">
                    <button type="submit" class="btn btn-primary" style="height:42px;padding:0 24px;">
                        <i data-lucide="send" style="width:15px;height:15px;"></i>
                        Submit Application for Screening
                    </button>
                    <a href="<?= BASE_URL ?>applicant/my_applications.php" class="btn btn-outline" style="height:42px;padding:0 20px;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Filing Guidelines & Dynamic Category Checklist -->
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
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Staff Formality Verification</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">IPO Staff inspects statutory field completeness and document formats.</div>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:26px;height:26px;border-radius:50%;background:rgba(245,158,11,0.1);color:var(--color-warning);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">2</div>
                        <div>
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Technical Substantive Evaluation</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">Assigned Technical Evaluator examines claims, novelty, and prior art.</div>
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="width:26px;height:26px;border-radius:50%;background:rgba(16,185,129,0.1);color:var(--color-success);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">3</div>
                        <div>
                            <strong style="font-size:13px;color:var(--color-text);display:block;">Director Executive Sign-Off</strong>
                            <div style="font-size:12px;color:var(--color-text-muted);line-height:1.4;">Final registration certificate is generated and issued to applicant.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Dynamic Category Checklist -->
        <div class="card" style="border-radius:var(--radius-lg);" id="categoryChecklistCard">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--color-border);display:flex;align-items:center;gap:8px;">
                <i data-lucide="check-square" style="width:17px;height:17px;color:var(--color-success);"></i>
                <h3 style="margin:0;font-size:15px;" id="checklistTitle">Category Documentary Checklist</h3>
            </div>
            <div class="card-body" style="padding:18px 20px;font-size:12.5px;color:var(--color-text-muted);line-height:1.6;">

                <!-- Default Checklist -->
                <div id="checklist-default" class="category-checklist">
                    <p style="margin:0 0 10px;">Select an intellectual property category from the dropdown to view the mandatory documentary attachments and legal requirements.</p>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li>Ensure all contributing faculty and student authors are listed.</li>
                        <li>Prepare clean PDF/image attachments meeting IPOPHL guidelines.</li>
                        <li>Avoid premature public disclosures before formal recording.</li>
                    </ul>
                </div>

                <!-- Patent Checklist -->
                <div id="checklist-patent" class="category-checklist" style="display:none;">
                    <div style="font-weight:700;color:var(--color-primary-600);margin-bottom:8px;font-size:13px;">
                        Mandatory Invention Patent Deliverables:
                    </div>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li><strong>Complete Specification Document (PDF):</strong> Technical Field, Background, Detailed Description, and Abstract.</li>
                        <li><strong>Formal Claims Sheet:</strong> Numbered independent and dependent claims defining legal boundaries.</li>
                        <li><strong>Technical Drawings &amp; Schematics (PDF/PNG):</strong> With numerical callouts referenced in the description.</li>
                        <li><strong>Deed of Assignment / University IP Conforme:</strong> Validating ownership under ZPPSU Institutional IP Policy.</li>
                        <li><strong>Grace Period Evidence:</strong> If disclosed in an academic defense, provide date/defense approval sheet (within 12 months).</li>
                    </ul>
                </div>

                <!-- Utility Model Checklist -->
                <div id="checklist-utility-model" class="category-checklist" style="display:none;">
                    <div style="font-weight:700;color:var(--color-warning);margin-bottom:8px;font-size:13px;">
                        Mandatory Utility Model Deliverables:
                    </div>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li><strong>Technical Description (PDF):</strong> Clear explanation of the physical assembly, components, and practical utility.</li>
                        <li><strong>Engineering Assembly Drawings:</strong> Exploded or sectional views illustrating part connections.</li>
                        <li><strong>Working Prototype Photos:</strong> High-resolution photos or 3D renders showing the constructed device.</li>
                        <li><strong>No Substantive Examination:</strong> Focuses on practical shape and utility for expedited 7-year protection.</li>
                    </ul>
                </div>

                <!-- Industrial Design Checklist -->
                <div id="checklist-industrial-design" class="category-checklist" style="display:none;">
                    <div style="font-weight:700;color:#7C3AED;margin-bottom:8px;font-size:13px;">
                        Mandatory Industrial Design Deliverables:
                    </div>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li><strong>7 Standard Perspectives (PDF/PNG/JPEG):</strong> Perspective, Front, Back, Top, Bottom, Left Side, and Right Side views.</li>
                        <li><strong>Neutral Studio Background:</strong> Solid white or clean contrast; no shadows, dimensions, or technical annotations.</li>
                        <li><strong>Locarno Classification:</strong> Identification of the industrial article product class.</li>
                        <li><strong>No Functional Claims:</strong> Drawings must depict only the ornamental shape and surface aesthetics.</li>
                    </ul>
                </div>

                <!-- Trademark Checklist -->
                <div id="checklist-trademark" class="category-checklist" style="display:none;">
                    <div style="font-weight:700;color:var(--color-success);margin-bottom:8px;font-size:13px;">
                        Mandatory Trademark Deliverables:
                    </div>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li><strong>High-Resolution Specimen (PNG/JPEG):</strong> Clear digital logo or wordmark on transparent/white canvas.</li>
                        <li><strong>Nice Classification List:</strong> Explicit listing of goods or commercial services per international class.</li>
                        <li><strong>Declaration of Actual Use (DAU):</strong> Mandatory statutory filing with invoices/packaging within 3 years.</li>
                        <li><strong>Avoid Descriptive Terms:</strong> Mark must be distinctive, non-generic, and not deceptive.</li>
                    </ul>
                </div>

                <!-- Copyright Checklist -->
                <div id="checklist-copyright" class="category-checklist" style="display:none;">
                    <div style="font-weight:700;color:#0891B2;margin-bottom:8px;font-size:13px;">
                        Mandatory Copyright Deposit Deliverables:
                    </div>
                    <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;">
                        <li><strong>Official Deposit Specimen (PDF):</strong> Complete manuscript, source code listing, curriculum module, or plan.</li>
                        <li><strong>Co-Author Conforme:</strong> Documented agreement confirming percentage contributions among all creators.</li>
                        <li><strong>Affiliation Declaration:</strong> Distinguishing between institutional work-for-hire and independent creation.</li>
                        <li><strong>Sec. 172 Certificate:</strong> Official ZPPSU IPO deposit certificate issued upon verification.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function onTypeChange() {
        var select = document.getElementById('type_id');
        var selectedOption = select.options[select.selectedIndex];
        var typeName = selectedOption ? (selectedOption.getAttribute('data-name') || '').trim() : '';

        // Hide all questionnaire panels
        var panels = document.querySelectorAll('.category-question-panel');
        panels.forEach(function(p) { p.style.display = 'none'; });

        // De-require all category inputs
        var allCatInputs = document.querySelectorAll('.category-question-panel [data-required="true"]');
        allCatInputs.forEach(function(inp) { inp.required = false; });

        var emptyPrompt = document.getElementById('panel-empty');
        var targetPanel = null;
        var targetChecklist = null;
        var hintHtml = 'Accepted file formats: <strong><?= clean($acceptLabel) ?></strong>. You can upload multiple files at once.';

        if (typeName === 'Patent') {
            targetPanel = document.getElementById('panel-patent');
            targetChecklist = document.getElementById('checklist-patent');
            hintHtml = '<i data-lucide="info"></i> <span>For <strong>Patent</strong>: Attach Complete Technical Specification (PDF), Formal Claims Sheet, and Figures/Drawings (PDF/PNG).</span>';
        } else if (typeName === 'Utility Model') {
            targetPanel = document.getElementById('panel-utility-model');
            targetChecklist = document.getElementById('checklist-utility-model');
            hintHtml = '<i data-lucide="info"></i> <span>For <strong>Utility Model</strong>: Attach Technical Description (PDF), Assembly Drawings, and Working Prototype Photos.</span>';
        } else if (typeName === 'Industrial Design') {
            targetPanel = document.getElementById('panel-industrial-design');
            targetChecklist = document.getElementById('checklist-industrial-design');
            hintHtml = '<i data-lucide="info"></i> <span>For <strong>Industrial Design</strong>: Attach the 7 Standard Perspectives (Perspective, Front, Back, Top, Bottom, Left, Right) in high-res PNG/PDF.</span>';
        } else if (typeName === 'Trademark') {
            targetPanel = document.getElementById('panel-trademark');
            targetChecklist = document.getElementById('checklist-trademark');
            hintHtml = '<i data-lucide="info"></i> <span>For <strong>Trademark</strong>: Attach Clear High-Resolution Mark Specimen (PNG/JPEG) and Goods Classification List.</span>';
        } else if (typeName === 'Copyright') {
            targetPanel = document.getElementById('panel-copyright');
            targetChecklist = document.getElementById('checklist-copyright');
            hintHtml = '<i data-lucide="info"></i> <span>For <strong>Copyright</strong>: Attach Full Deposit Specimen (Complete Manuscript PDF / Source Code listing / Module).</span>';
        }

        // Hide all checklists
        var checklists = document.querySelectorAll('.category-checklist');
        checklists.forEach(function(c) { c.style.display = 'none'; });

        if (targetPanel) {
            if (emptyPrompt) emptyPrompt.style.display = 'none';
            targetPanel.style.display = 'block';
            // Mark required inputs for this active panel
            var reqInputs = targetPanel.querySelectorAll('[data-required="true"]');
            reqInputs.forEach(function(inp) { inp.required = true; });
        } else {
            if (emptyPrompt) emptyPrompt.style.display = 'block';
        }

        if (targetChecklist) {
            targetChecklist.style.display = 'block';
        } else {
            var defaultChecklist = document.getElementById('checklist-default');
            if (defaultChecklist) defaultChecklist.style.display = 'block';
        }

        // Update requirements hint under type select
        var typeReq = document.getElementById('typeRequirements');
        if (typeReq && selectedOption && selectedOption.dataset.desc) {
            typeReq.innerHTML = '<i data-lucide="info"></i> <span>Statutory Scope: ' + selectedOption.dataset.desc + '</span>';
        } else if (typeReq) {
            typeReq.innerHTML = '';
        }

        // Update file category hint
        var fileHint = document.getElementById('fileCategoryHint');
        if (fileHint) {
            fileHint.innerHTML = hintHtml;
        }

        if (window.lucide) lucide.createIcons();
    }

    function togglePatentDisclosureDetails() {
        var select = document.getElementById('patent_prior_disclosure');
        var detailsWrap = document.getElementById('patent_disclosure_details_wrap');
        if (!select || !detailsWrap) return;
        var val = select.value || '';
        if (val.indexOf('No prior') !== -1) {
            detailsWrap.style.opacity = '0.5';
        } else {
            detailsWrap.style.opacity = '1';
        }
    }

    function updateTitleCount(input) {
        var counter = document.getElementById('titleCounter');
        if (!counter) return;
        var len = (input.value || '').length;
        counter.textContent = len + ' / 255';
        if (len >= 240) {
            counter.className = 'field-counter danger';
        } else if (len >= 200) {
            counter.className = 'field-counter warning';
        } else {
            counter.className = 'field-counter';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        onTypeChange();
        var titleEl = document.getElementById('title');
        if (titleEl) updateTitleCount(titleEl);
        togglePatentDisclosureDetails();
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
