<?php
/**
 * admin/reports.php
 * -----------------------------------------------------------------
 * Filterable operational report: date range, status, type, college,
 * department. Shows summary KPI cards + the matching application
 * list, with buttons to export the same filtered set as CSV (opens
 * in Excel) or a printable view (Ctrl+P → Save as PDF).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Reports';
$currentPage = 'reports';

$status    = sanitize_input($_GET['status'] ?? '');
$typeId    = (int) ($_GET['type_id'] ?? 0);
$collegeId = (int) ($_GET['college_id'] ?? 0);
$deptId    = (int) ($_GET['department_id'] ?? 0);
$dateFrom  = sanitize_input($_GET['date_from'] ?? '');
$dateTo    = sanitize_input($_GET['date_to'] ?? '');

$where = []; $types = ''; $params = [];
if ($status !== '')   { $where[] = "a.status = ?"; $types .= 's'; $params[] = $status; }
if ($typeId > 0)      { $where[] = "a.type_id = ?"; $types .= 'i'; $params[] = $typeId; }
if ($collegeId > 0)   { $where[] = "a.college_id = ?"; $types .= 'i'; $params[] = $collegeId; }
if ($deptId > 0)      { $where[] = "a.department_id = ?"; $types .= 'i'; $params[] = $deptId; }
if ($dateFrom !== '') { $where[] = "DATE(a.submitted_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $where[] = "DATE(a.submitted_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Summary counts for the filtered set ----
$sumStmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(status IN ('pending','under_review')) AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'rejected') AS rejected
    FROM applications a $whereSql
");
$sumResult = bind_execute($sumStmt, $types, $params);
$summary = mysqli_fetch_assoc($sumResult) ?: ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
mysqli_stmt_close($sumStmt);

$totalCount    = (int) ($summary['total'] ?? 0);
$pendingCount  = (int) ($summary['pending'] ?? 0);
$approvedCount = (int) ($summary['approved'] ?? 0);
$rejectedCount = (int) ($summary['rejected'] ?? 0);
$approvalRate  = $totalCount > 0 ? round(($approvedCount / $totalCount) * 100, 1) : 0;

// ---- Matching applications ----
$listStmt = mysqli_prepare($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, t.type_name,
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
$applications = bind_execute($listStmt, $types, $params);

$types_lookup       = mysqli_query($conn, "SELECT type_id, type_name FROM application_types ORDER BY type_name");
$colleges_lookup    = mysqli_query($conn, "SELECT college_id, college_name FROM colleges ORDER BY college_name");
$departments_lookup = mysqli_query($conn, "SELECT department_id, department_name FROM departments ORDER BY department_name");

$queryString = http_build_query($_GET);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/report_period_picker.php';
?>

<!-- =====================================================================
     SUB-HEADER BAR
     ===================================================================== -->
<div class="sub-header-bar">
    <div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Institutional IP Operational Reports &amp; Analytics</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Cross-college filing distributions, examination throughput, and multi-format audit exports.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>admin/export_csv.php?<?= $queryString ?>" class="btn btn-outline" title="Export current filtered report to CSV (Excel)">
            <i data-lucide="download" style="width:16px;height:16px;"></i>
            Export Excel (CSV)
        </a>
        <a href="<?= BASE_URL ?>admin/report_print.php?<?= $queryString ?>" class="btn btn-primary" target="_blank" title="Open printable report (Save as PDF)">
            <i data-lucide="printer" style="width:16px;height:16px;"></i>
            Print / PDF Report
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Match)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Total in Report -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Filings in Scope</span>
            <span class="info-icon" title="Total applications matching the active report criteria">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($totalCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repAdmBlue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,24 50,14 T100,6 L100,40 L0,40 Z" fill="url(#repAdmBlue)"/>
                <path d="M0,32 Q25,24 50,14 T100,6" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="file-text" style="width:13px;height:13px;color:var(--color-primary-600);"></i>
            <span>Institutional portfolio size</span>
        </div>
    </div>

    <!-- Card 2: Pending / Under Review -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Pending Review</span>
            <span class="info-icon" title="Applications currently waiting for verification or technical evaluation">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($pendingCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repAdmWarning" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,24 Q25,32 50,16 T100,8 L100,40 L0,40 Z" fill="url(#repAdmWarning)"/>
                <path d="M0,24 Q25,32 50,16 T100,8" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Action required by office</span>
        </div>
    </div>

    <!-- Card 3: Approved Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved Registrations</span>
            <span class="info-icon" title="Filings granted official intellectual property registration">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($approvedCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repAdmGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,35 Q25,25 50,15 T100,4 L100,40 L0,40 Z" fill="url(#repAdmGreen)"/>
                <path d="M0,35 Q25,25 50,15 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span><?= $approvalRate ?>% overall approval rate</span>
        </div>
    </div>

    <!-- Card 4: Rejected Filings -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Rejected Applications</span>
            <span class="info-icon" title="Applications not meeting university or IPO registration criteria">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($rejectedCount) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="repAdmRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q30,28 60,18 T100,26 L100,40 L0,40 Z" fill="url(#repAdmRed)"/>
                <path d="M0,18 Q30,28 60,18 T100,26" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="x-circle" style="width:13px;height:13px;color:var(--color-danger);"></i>
            <span>Closed filings</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     MULTI-CRITERIA FILTER BAR
     ===================================================================== -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:18px 20px;">
        <form method="GET" id="reportFilterForm" style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:12px;align-items:end;">
                <!-- Status Filter -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">Status</label>
                    <select name="status" class="form-control" style="font-size:13px;height:38px;border-radius:var(--radius-md);">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','under_review','evaluated','returned','approved','rejected'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type Filter -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">IP Type</label>
                    <select name="type_id" class="form-control" style="font-size:13px;height:38px;border-radius:var(--radius-md);">
                        <option value="0">All Types</option>
                        <?php while ($t = mysqli_fetch_assoc($types_lookup)): ?>
                            <option value="<?= $t['type_id'] ?>" <?= $typeId === (int)$t['type_id'] ? 'selected' : '' ?>><?= clean($t['type_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- College Filter -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">College</label>
                    <select name="college_id" class="form-control" style="font-size:13px;height:38px;border-radius:var(--radius-md);">
                        <option value="0">All Colleges</option>
                        <?php while ($c = mysqli_fetch_assoc($colleges_lookup)): ?>
                            <option value="<?= $c['college_id'] ?>" <?= $collegeId === (int)$c['college_id'] ? 'selected' : '' ?>><?= clean($c['college_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Department Filter -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">Department</label>
                    <select name="department_id" class="form-control" style="font-size:13px;height:38px;border-radius:var(--radius-md);">
                        <option value="0">All Departments</option>
                        <?php while ($d = mysqli_fetch_assoc($departments_lookup)): ?>
                            <option value="<?= $d['department_id'] ?>" <?= $deptId === (int)$d['department_id'] ? 'selected' : '' ?>><?= clean($d['department_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>" style="font-size:12.5px;height:38px;border-radius:var(--radius-md);">
                </div>

                <!-- Date To -->
                <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:12px;font-weight:600;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:0.04em;">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>" style="font-size:12.5px;height:38px;border-radius:var(--radius-md);">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--color-border-subtle);padding-top:12px;">
                <div style="font-size:12.5px;color:var(--color-text-muted);">
                    Use quick presets above or customize date filters to narrow down report metrics.
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline btn-sm">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                        Reset Filters
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="filter" style="width:13px;height:13px;"></i>
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================================
     MATCHING APPLICATIONS AUDIT TABLE
     ===================================================================== -->
<div class="card" style="border-radius:var(--radius-lg);">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;">
        <div>
            <h3 style="margin:0 0 2px;">Matching Applications in Scope</h3>
            <span style="font-size:12.5px;color:var(--color-text-muted);">Displaying <?= mysqli_num_rows($applications) ?> record<?= mysqli_num_rows($applications) === 1 ? '' : 's' ?> matching active report criteria.</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <a href="<?= BASE_URL ?>admin/export_csv.php?<?= $queryString ?>" class="btn btn-outline btn-sm" title="Export this table to CSV">
                <i data-lucide="download" style="width:13px;height:13px;"></i>
                Export CSV
            </a>
            <a href="<?= BASE_URL ?>admin/report_print.php?<?= $queryString ?>" class="btn btn-outline btn-sm" target="_blank" title="Print this view">
                <i data-lucide="printer" style="width:13px;height:13px;"></i>
                Print
            </a>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <i data-lucide="file-spreadsheet" style="width:40px;height:40px;color:var(--color-text-subtle);margin-bottom:12px;"></i>
                <h4 style="margin:0 0 6px;font-size:16px;color:var(--color-text);">No applications found</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);">No intellectual property applications match your selected filters.</p>
                <?php if ($status !== '' || $typeId > 0 || $collegeId > 0 || $deptId > 0 || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline btn-sm" style="margin-top:14px;">Clear Active Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:140px;">Tracking Code</th>
                    <th>Title &amp; Type</th>
                    <th>Applicant</th>
                    <th>College / Department</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:120px;">Submitted</th>
                    <th style="text-align:right;width:90px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applications)): ?>
                <tr>
                    <td class="tracking" style="white-space:nowrap;">
                        <?= clean($a['tracking_code']) ?>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--color-text);margin-bottom:3px;"><?= clean($a['title']) ?></div>
                        <span class="badge badge-info" style="font-size:11px;padding:2px 8px;"><?= clean($a['type_name']) ?></span>
                    </td>
                    <td>
                        <div style="font-weight:500;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                    </td>
                    <td style="font-size:12.5px;color:var(--color-text-muted);">
                        <div style="font-weight:500;color:var(--color-text);"><?= clean($a['college_name'] ?? '—') ?></div>
                        <?php if (!empty($a['department_name'])): ?>
                            <div style="font-size:11.5px;color:var(--color-text-subtle);"><?= clean($a['department_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= status_badge_class($a['status']) ?>" style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;padding:3px 10px;">
                            <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                            <?= status_label($a['status']) ?>
                        </span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($a['submitted_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/application_view.php?id=<?= (int)$a['application_id'] ?>" title="View application details">
                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                            View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

