<?php
/**
 * admin/export_csv.php
 * -----------------------------------------------------------------
 * Streams the same filtered application set from reports.php as a
 * downloadable CSV file (opens natively in Microsoft Excel).
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

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ipo_report_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Tracking Code', 'Title', 'Type', 'Status', 'Applicant', 'Office/Department', 'Submitted']);
while ($row = mysqli_fetch_assoc($result)) {
    $officeDept = ($row['department_name'] ?: ($row['college_name'] ?? ''));
    if ($row['department_name'] && $row['college_name']) {
        $officeDept .= ' · ' . $row['college_name'];
    }
    fputcsv($out, [
        $row['tracking_code'],
        $row['title'],
        $row['type_name'],
        status_label($row['status']),
        $row['first_name'] . ' ' . $row['last_name'],
        $officeDept,
        format_date($row['submitted_at'], 'Y-m-d H:i'),
    ]);
}
fclose($out);
log_audit($conn, $_SESSION['user_id'], 'export_report', 'Exported applications report as CSV');
exit;
