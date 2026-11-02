<?php
/**
 * applicant/certificate.php
 * -----------------------------------------------------------------
 * Printable "Certificate of Approval" for an application the
 * logged-in applicant owns and that has status = approved. Uses the
 * browser's native Print / Save-as-PDF (Ctrl+P) — no PDF library
 * required, keeping the stack plain PHP as specified.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Applicant']);

$applicationId = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT applicant_id FROM applicants WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$applicantId = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['applicant_id'] ?? 0;
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "
    SELECT a.*, t.type_name, u.first_name, u.last_name,
           c.college_name, d.department_name
    FROM applications a
    JOIN application_types t ON t.type_id = a.type_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    WHERE a.application_id = ? AND a.applicant_id = ? AND a.status = 'approved'
");
mysqli_stmt_bind_param($stmt, 'ii', $applicationId, $applicantId);
mysqli_stmt_execute($stmt);
$application = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$application) {
    die('<div style="font-family:sans-serif;padding:50px;text-align:center;color:#64748B;"><h2>Access Restricted</h2><p>This application is not approved yet, or you do not have permission to view its certificate.</p><p><a href="' . BASE_URL . 'applicant/my_applications.php" style="color:#2563EB;">&larr; Back to My Applications</a></p></div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Approval — <?= clean($application['tracking_code']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        :root {
            --cert-border: #123A6B;
            --cert-gold: #C89B3C;
            --cert-bg: #F8FAFC;
        }
        * { box-sizing: border-box; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--cert-bg); 
            margin: 0; 
            padding: 30px 20px; 
            color: #1E293B;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .cert-toolbar {
            width: 100%;
            max-width: 860px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-outline {
            background: #FFFFFF;
            border-color: #CBD5E1;
            color: #334155;
        }
        .btn-outline:hover {
            background: #F1F5F9;
            border-color: #94A3B8;
        }
        .btn-primary {
            background: #2563EB;
            color: #FFFFFF;
        }
        .btn-primary:hover {
            background: #1D4ED8;
        }

        .cert-wrapper { 
            width: 100%;
            max-width: 860px; 
            background: #FFFFFF; 
            border: 12px solid var(--cert-border); 
            padding: 60px 50px; 
            text-align: center; 
            position: relative; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
        }
        .cert-wrapper::before { 
            content: ""; 
            position: absolute; 
            inset: 12px; 
            border: 2px solid var(--cert-gold); 
            pointer-events: none; 
        }
        .cert-seal { 
            width: 84px;
            height: 84px;
            border-radius: 50%;
            border: 3px double var(--cert-gold);
            background: rgba(200, 155, 60, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: var(--cert-gold);
            font-family: 'Cinzel', serif;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: 1px;
        }
        .cert-seal span {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
        }
        .cert-institution { 
            color: #475569; 
            font-size: 13px; 
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 6px; 
        }
        .cert-title { 
            color: var(--cert-border); 
            font-family: 'Cinzel', serif;
            font-size: 28px; 
            font-weight: 700;
            letter-spacing: 2px; 
            margin: 8px 0 4px; 
        }
        .cert-office { 
            color: var(--cert-gold); 
            font-size: 13.5px; 
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 28px; 
        }
        .cert-body-text { 
            font-family: 'Playfair Display', serif; 
            color: #334155; 
            font-size: 15px; 
            line-height: 1.8; 
            margin: 20px 0; 
        }
        .cert-ip-title { 
            font-family: 'Playfair Display', serif;
            font-size: 24px; 
            font-weight: 700;
            color: var(--cert-border); 
            margin: 18px 0; 
            padding: 8px 24px;
            display: inline-block;
            border-bottom: 2px solid var(--cert-gold);
            max-width: 90%;
            line-height: 1.4;
        }
        .cert-meta { 
            font-size: 12.5px; 
            color: #64748B; 
            margin-top: 36px; 
            padding-top: 20px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-around;
        }
        .cert-meta-item {
            text-align: center;
        }
        .cert-meta-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94A3B8;
            margin-bottom: 4px;
        }
        .cert-meta-value {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: #1E293B;
            font-size: 13px;
        }

        @media print { 
            .cert-toolbar { display: none; } 
            body { background: #FFFFFF; padding: 0; } 
            .cert-wrapper { border: 10px solid #123A6B; box-shadow: none; max-width: 100%; } 
        }
    </style>
</head>
<body>
    <div class="cert-toolbar">
        <a href="<?= BASE_URL ?>applicant/application_view.php?id=<?= $applicationId ?>" class="btn btn-outline">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Return to Dossier
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i data-lucide="printer" style="width:16px;height:16px;"></i> Print / Save as PDF
        </button>
    </div>

    <div class="cert-wrapper">
        <div class="cert-seal">
            IPO
            <span>ZPPSU</span>
        </div>
        <div class="cert-institution">Republic of the Philippines &middot; Region IX</div>
        <div class="cert-institution" style="color:var(--cert-border);font-weight:800;">Zamboanga Peninsula Polytechnic State University</div>
        <h1 class="cert-title">Certificate of Approval</h1>
        <div class="cert-office">Intellectual Property Office</div>

        <p class="cert-body-text">
            This certifies that the intellectual property disclosure and formal filing titled
        </p>
        
        <div class="cert-ip-title">
            "<?= clean($application['title']) ?>"
        </div>

        <p class="cert-body-text">
            submitted by <strong><?= clean($application['first_name'] . ' ' . $application['last_name']) ?></strong>
            <?php if (!empty($application['college_name'])): ?>
                of the <strong><?= clean($application['college_name']) ?></strong>
            <?php endif; ?>
            under the institutional classification of <strong><?= clean($application['type_name']) ?></strong>,
            has been rigorously screened, technically evaluated, and officially granted
            <strong style="color:var(--cert-border);">FULL APPROVAL</strong> in accordance with Republic Act No. 8293
            (Intellectual Property Code of the Philippines) and university IP regulations.
        </p>

        <div class="cert-meta">
            <div class="cert-meta-item">
                <div class="cert-meta-label">Tracking Identification</div>
                <div class="cert-meta-value"><?= clean($application['tracking_code']) ?></div>
            </div>
            <div class="cert-meta-item">
                <div class="cert-meta-label">Approval Date</div>
                <div class="cert-meta-value"><?= format_date($application['updated_at'], 'F d, Y') ?></div>
            </div>
            <div class="cert-meta-item">
                <div class="cert-meta-label">Office Verification</div>
                <div class="cert-meta-value" style="color:var(--cert-gold);">VALIDATED &middot; IPO-ZPPSU</div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
