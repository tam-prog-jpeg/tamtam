<?php
/**
 * admin/report_print.php
 * -----------------------------------------------------------------
 * Print-friendly, institutional document of the filtered report from
 * reports.php. Uses the browser's native Print / Save-as-PDF.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$status    = sanitize_input($_GET['status'] ?? '');
$typeId    = (int) ($_GET['type_id'] ?? 0);
$deptId    = (int) ($_GET['department_id'] ?? 0);
$dateFrom  = sanitize_input($_GET['date_from'] ?? '');
$dateTo    = sanitize_input($_GET['date_to'] ?? '');

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $tempD = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tempD;
}

$where = []; $types = ''; $params = [];
if ($status !== '')   { $where[] = "a.status = ?"; $types .= 's'; $params[] = $status; }
if ($typeId > 0)      { $where[] = "a.type_id = ?"; $types .= 'i'; $params[] = $typeId; }
if ($deptId > 0)      { $where[] = "a.department_id = ?"; $types .= 'i'; $params[] = $deptId; }
if ($dateFrom !== '') { $where[] = "DATE(a.submitted_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = "DATE(a.submitted_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = mysqli_prepare($conn, "
    SELECT a.tracking_code, a.title, t.type_name, a.status, a.submitted_at,
           u.first_name, u.last_name, c.college_name, d.department_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    $whereSql
    ORDER BY a.submitted_at DESC
");
$result = bind_execute($stmt, $types, $params);
$rows = [];
while ($r = mysqli_fetch_assoc($result)) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ZPPSU IPO — Applications Official Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            padding: 32px 40px;
            color: #111827;
            background: #fff;
            margin: 0;
            font-size: 13px;
        }
        .header-print {
            border-bottom: 2px solid #2563EB;
            padding-bottom: 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #111827;
            font-size: 20px;
            margin: 0 0 4px 0;
            font-weight: 700;
        }
        .sub {
            color: #6B7280;
            font-size: 12px;
            margin: 0;
        }
        .meta-pill {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 11px;
            color: #2563EB;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 10px;
        }
        th {
            background: #F8F9FB;
            color: #4B5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.04em;
            border-bottom: 1px solid #E5E7EB;
            padding: 8px 10px;
            text-align: left;
        }
        td {
            border-bottom: 1px solid #F3F4F6;
            padding: 9px 10px;
            text-align: left;
            color: #1F2937;
        }
        tr:nth-child(even) td {
            background: #FAFAFA;
        }
        .tracking {
            font-family: 'IBM Plex Mono', monospace;
            font-weight: 600;
            color: #2563EB;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            background: #EFF6FF;
            color: #2563EB;
        }
        .print-btn-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn-print {
            background: #2563EB;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        @media print {
            .print-btn-bar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button class="btn-print" onclick="window.print()">🖨 Print / Export PDF</button>
        <button onclick="window.close()" style="background:#fff;border:1px solid #ccc;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;">Close Window</button>
    </div>

    <div class="header-print">
        <div>
            <h1>ZPPSU Intellectual Property Office</h1>
            <p class="sub">Official IP Applications Master Registry Summary</p>
        </div>
        <div style="text-align:right;">
            <div class="meta-pill"><?= count($rows) ?> Total Record(s)</div>
            <div class="sub" style="font-size:11px;margin-top:2px;">Generated <?= date('M d, Y · h:i A') ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tracking Code</th>
                <th>Title of Invention</th>
                <th>Classification</th>
                <th>Status</th>
                <th>Applicant</th>
                <th>Office/Department</th>
                <th>Date Filed</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td class="tracking"><?= clean($row['tracking_code']) ?></td>
                <td style="font-weight:600;"><?= clean($row['title']) ?></td>
                <td><?= clean($row['type_name']) ?></td>
                <td><span class="status-badge"><?= status_label($row['status']) ?></span></td>
                <td><?= clean($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td style="color:#6B7280;"><?= clean($row['department_name'] ?: ($row['college_name'] ?? '—')) ?><?= ($row['department_name'] && $row['college_name']) ? ' · ' . clean($row['college_name']) : '' ?></td>
                <td style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;"><?= format_date($row['submitted_at'], 'M d, Y') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
