<?php
/**
 * manager/export_csv.php
 * -----------------------------------------------------------------
 * Streams the filtered application set from manager/reports.php as
 * a downloadable CSV (opens directly in Excel).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$status   = sanitize_input($_GET['status'] ?? '');
$typeId   = (int) ($_GET['type_id'] ?? 0);
$dateFrom = sanitize_input($_GET['date_from'] ?? '');
$dateTo   = sanitize_input($_GET['date_to'] ?? '');
$search   = sanitize_input($_GET['q'] ?? '');

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($status !== '')   { $where[] = "a.status = ?"; $types .= 's'; $params[] = $status; }
if ($typeId > 0)      { $where[] = "a.type_id = ?"; $types .= 'i'; $params[] = $typeId; }
if ($dateFrom !== '') { $where[] = "DATE(a.submitted_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = "DATE(a.submitted_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = mysqli_prepare($conn, "
    SELECT a.tracking_code, a.title, t.type_name, a.status, a.submitted_at, u.first_name, u.last_name
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    $whereSql ORDER BY a.submitted_at DESC
");
$result = bind_execute($stmt, $types, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ipo_manager_report_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Tracking Code', 'Title', 'Type', 'Status', 'Applicant', 'Submitted']);
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [$row['tracking_code'], $row['title'], $row['type_name'], status_label($row['status']), $row['first_name'] . ' ' . $row['last_name'], format_date($row['submitted_at'], 'Y-m-d H:i')]);
}
fclose($out);
log_audit($conn, $_SESSION['user_id'], 'export_report', 'Manager exported applications report as CSV');
exit;
