<?php
/**
 * manager/approved_applications.php
 * -----------------------------------------------------------------
 * Executive Registry of all applications granted final approval and
 * certification by the Manager. Provides comprehensive auditing,
 * category filtering, keyword search, and access to full dossiers.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Manager']);

$pageTitle   = 'Manager-Approved Applications';
$currentPage = 'approved_applications';

// ---- Filters & Pagination ----
$search = sanitize_input($_GET['q'] ?? '');
$typeId = (int) ($_GET['type_id'] ?? 0);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

// Base condition: Only applications with approved status
$where = ["a.status = 'approved'"];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = "(a.tracking_code LIKE ? OR a.title LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $like = "%$search%";
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($typeId > 0) {
    $where[] = "a.type_id = ?";
    $types .= 'i';
    $params[] = $typeId;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count total matching records
$countSql = "
    SELECT COUNT(DISTINCT a.application_id) AS total
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    $whereSql
";
$countStmt = mysqli_prepare($conn, $countSql);
$countRes = bind_execute($countStmt, $types, $params);
$totalRows = (int) (mysqli_fetch_assoc($countRes)['total'] ?? 0);
mysqli_stmt_close($countStmt);

// Pagination calculation
$p = paginate($totalRows, $perPage, $page);
$offset = $p['offset'];

// Fetch approved applications with approval metadata from status history
$querySql = "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at, a.updated_at,
           t.type_name,
           u.first_name, u.last_name, u.email,
           c.college_name, d.department_name,
           MAX(h.changed_at) AS approved_at,
           MAX(h.remarks) AS approval_remarks
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    LEFT JOIN application_status_history h ON h.application_id = a.application_id AND h.status = 'approved'
    $whereSql
    GROUP BY a.application_id
    ORDER BY COALESCE(MAX(h.changed_at), a.updated_at) DESC
    LIMIT $offset, $perPage
";
$queryStmt = mysqli_prepare($conn, $querySql);
$applications = bind_execute($queryStmt, $types, $params);
mysqli_stmt_close($queryStmt);

// ---- Approved Portfolio Statistics ----
$totalApprovedAll = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'approved'"))['c'] ?? 0);
$currentYear = date('Y');
$approvedThisYear = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT a.application_id) c 
    FROM applications a
    LEFT JOIN application_status_history h ON h.application_id = a.application_id AND h.status = 'approved'
    WHERE a.status = 'approved' AND (YEAR(h.changed_at) = '$currentYear' OR (h.changed_at IS NULL AND YEAR(a.updated_at) = '$currentYear'))
"))['c'] ?? 0);

$certifiedInventions = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c 
    FROM applications a 
    JOIN application_types t ON t.type_id = a.type_id 
    WHERE a.status = 'approved' AND t.type_name IN ('Patent', 'Utility Model')
"))['c'] ?? 0);

$certifiedCreative = (int) (mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c 
    FROM applications a 
    JOIN application_types t ON t.type_id = a.type_id 
    WHERE a.status = 'approved' AND t.type_name IN ('Copyright', 'Trademark')
"))['c'] ?? 0);

// Fetch categories for filter tabs
$typesResult = mysqli_query($conn, "
    SELECT t.type_id, t.type_name, COUNT(a.application_id) AS approved_count
    FROM application_types t
    LEFT JOIN applications a ON a.type_id = t.type_id AND a.status = 'approved'
    GROUP BY t.type_id, t.type_name
    ORDER BY t.type_id ASC
");
$categoryOptions = [];
if ($typesResult) {
    while ($row = mysqli_fetch_assoc($typesResult)) {
        $categoryOptions[] = $row;
    }
}

// Helper to preserve query parameters
function qsApp($overrides = []) {
    $merged = array_merge($_GET, $overrides);
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) unset($merged[$k]);
    }
    return '?' . http_build_query($merged);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary-600);margin-bottom:2px;">
            Executive Governance Registry
        </div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Manager-Approved Applications</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">
            Official register of intellectual property filings that have received executive sign-off and registration certification.
        </div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>manager/dashboard.php" class="btn btn-outline btn-sm" title="Return to Dashboard">
            <i data-lucide="layout-dashboard" style="width:14px;height:14px;"></i> Executive Dashboard
        </a>
        <a href="<?= BASE_URL ?>manager/reports.php" class="btn btn-primary btn-sm" title="View Full Statistics & Reports">
            <i data-lucide="bar-chart-3" style="width:14px;height:14px;"></i> Analytics &amp; Reports
        </a>
    </div>
</div>

<!-- 2. KPI Summary Cards with Sparklines -->
<div class="kpi-grid">
    <!-- Card 1: Total Approved Assets -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Approved Filings</span>
            <span class="info-icon" title="All-time applications approved and registered by executive sign-off">
                <i data-lucide="award" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($totalApprovedAll) ?></div>
            <?= render_kpi_sparkline($totalApprovedAll, 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-up" style="color:var(--color-success);">
                <i data-lucide="shield-check" style="width:13px;height:13px;"></i> Certified Assets
            </span>
            <span>All-time</span>
        </div>
    </div>

    <!-- Card 2: Approved This Calendar Year -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved This Year (<?= $currentYear ?>)</span>
            <span class="info-icon" title="Filings certified within the current calendar year">
                <i data-lucide="calendar" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:var(--color-primary-600);"><?= number_format($approvedThisYear) ?></div>
            <?= render_kpi_sparkline($approvedThisYear, 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-neutral">
                <i data-lucide="trending-up" style="width:13px;height:13px;"></i> Active Fiscal Year
            </span>
            <span><?= $currentYear ?> Velocity</span>
        </div>
    </div>

    <!-- Card 3: Patents & Utility Models -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Inventions &amp; Utility Models</span>
            <span class="info-icon" title="Approved technical inventions, devices, and industrial utility models">
                <i data-lucide="lightbulb" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:#D97706;"><?= number_format($certifiedInventions) ?></div>
            <?= render_kpi_sparkline($certifiedInventions, 'amber') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-neutral">
                <i data-lucide="cpu" style="width:13px;height:13px;"></i> Technical Disclosures
            </span>
            <span>Patents &amp; Models</span>
        </div>
    </div>

    <!-- Card 4: Copyrights & Trademarks -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Copyrights &amp; Trademarks</span>
            <span class="info-icon" title="Approved software, literary manuscripts, brand marks, and designs">
                <i data-lucide="book-open" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular" style="color:#0891B2;"><?= number_format($certifiedCreative) ?></div>
            <?= render_kpi_sparkline($certifiedCreative, 'cyan') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend trend-neutral">
                <i data-lucide="sparkles" style="width:13px;height:13px;"></i> Creative Works
            </span>
            <span>Deposits &amp; Marks</span>
        </div>
    </div>
</div>

<!-- 3. Modern Segmented Toolbar (Category Tabs + Live Search) -->
<div class="staff-toolbar">
    <div class="staff-toolbar-tabs" role="tablist">
        <a href="<?= qsApp(['type_id' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $typeId === 0 ? 'active' : '' ?>">
            <i data-lucide="layers" style="width:14px;height:14px;"></i>
            <span>All Categories</span>
            <span class="staff-tab-count"><?= number_format($totalApprovedAll) ?></span>
        </a>
        <?php foreach ($categoryOptions as $cat): ?>
            <?php
            $catIcon = 'file-text';
            if ($cat['type_name'] === 'Patent') $catIcon = 'lightbulb';
            elseif ($cat['type_name'] === 'Copyright') $catIcon = 'book-open';
            elseif ($cat['type_name'] === 'Trademark') $catIcon = 'badge-check';
            elseif ($cat['type_name'] === 'Utility Model') $catIcon = 'wrench';
            elseif ($cat['type_name'] === 'Industrial Design') $catIcon = 'shapes';
            ?>
            <a href="<?= qsApp(['type_id' => $cat['type_id'], 'page' => 1]) ?>" class="staff-tab-pill <?= $typeId === (int)$cat['type_id'] ? 'active' : '' ?>">
                <i data-lucide="<?= $catIcon ?>" style="width:14px;height:14px;"></i>
                <span><?= clean($cat['type_name']) ?></span>
                <span class="staff-tab-count"><?= number_format((int)$cat['approved_count']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Live Search Form -->
    <form method="GET" class="staff-search-form">
        <?php if ($typeId > 0): ?>
            <input type="hidden" name="type_id" value="<?= $typeId ?>">
        <?php endif; ?>
        <div class="staff-search-input-wrap">
            <i data-lucide="search" class="staff-search-icon"></i>
            <input type="text" name="q" class="staff-search-input" placeholder="Search tracking #, title, applicant..." value="<?= clean($search) ?>" autocomplete="off">
            <?php if ($search !== ''): ?>
                <a href="<?= qsApp(['q' => '']) ?>" class="staff-search-clear" title="Clear search">&times;</a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm staff-search-btn">
            <i data-lucide="search" style="width:14px;height:14px;"></i> Search
        </button>
        <?php if ($search !== '' || $typeId > 0): ?>
            <a href="<?= BASE_URL ?>manager/approved_applications.php" class="btn btn-outline btn-sm staff-reset-btn" title="Reset all filters">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- 4. Approved Applications Data Table Card -->
<div class="staff-table-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="shield-check" style="width:18px;height:18px;color:var(--color-success);"></i>
            <h3 style="margin:0;font-size:15px;font-weight:700;">Executive Approved Repository</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= number_format($totalRows) ?> records</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int)$totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applications) === 0): ?>
            <div class="empty-state" style="padding:48px 24px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-success);">
                    <i data-lucide="shield-alert" style="width:24px;height:24px;"></i>
                </div>
                <h4 style="margin:0 0 6px;font-size:16px;color:var(--color-text);">No Manager-Approved Applications Found</h4>
                <p style="margin:0;font-size:13px;color:var(--color-text-muted);max-width:480px;margin:0 auto;">
                    <?= $search !== '' || $typeId > 0 ? 'No approved applications match your active search or category criteria.' : 'No intellectual property disclosures have received final executive approval yet.' ?>
                </p>
                <?php if ($search !== '' || $typeId > 0): ?>
                    <a href="<?= BASE_URL ?>manager/approved_applications.php" class="btn btn-outline btn-sm" style="margin-top:14px;gap:6px;">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Clear Active Filters
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>manager/dashboard.php" class="btn btn-primary btn-sm" style="margin-top:14px;gap:6px;">
                        <i data-lucide="check-square" style="width:14px;height:14px;"></i> Review Pending Submissions
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:160px;">Tracking Code</th>
                        <th>Application Title</th>
                        <th style="width:140px;">IP Category</th>
                        <th>Applicant Name &amp; Unit</th>
                        <th style="width:160px;">Approval Date</th>
                        <th style="width:150px;">Status</th>
                        <th style="width:110px;text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                        <?php 
                        $effectiveApprovalDate = $app['approved_at'] ?: $app['updated_at'];
                        ?>
                        <tr>
                            <td class="tracking">
                                <a href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$app['application_id'] ?>" style="color:var(--color-primary-600);font-weight:700;text-decoration:none;">
                                    <?= clean($app['tracking_code']) ?>
                                </a>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--color-text);line-height:1.35;max-width:360px;">
                                    <?= clean($app['title']) ?>
                                </div>
                                <?php if (!empty($app['approval_remarks'])): ?>
                                    <div style="font-size:11.5px;color:var(--color-text-muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:360px;">
                                        <em>"<?= clean($app['approval_remarks']) ?>"</em>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-weight:600;">
                                    <?= clean($app['type_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--color-text);font-size:13px;">
                                    <?= clean($app['first_name'] . ' ' . $app['last_name']) ?>
                                </div>
                                <div style="font-size:11.5px;color:var(--color-text-muted);">
                                    <?= clean($app['college_name'] ?? 'General Registry') ?>
                                </div>
                            </td>
                            <td class="tabular" style="font-size:12.5px;color:var(--color-text);">
                                <div style="font-weight:500;">
                                    <?= format_date($effectiveApprovalDate, 'M d, Y') ?>
                                </div>
                                <div style="font-size:11px;color:var(--color-text-muted);">
                                    <?= format_date($effectiveApprovalDate, 'h:i A') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-success" style="gap:4px;font-size:11px;font-weight:700;">
                                    <i data-lucide="check-circle-2" style="width:12px;height:12px;"></i>
                                    Approved &amp; Certified
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <a href="<?= BASE_URL ?>manager/application_view.php?id=<?= (int)$app['application_id'] ?>" class="btn btn-outline btn-sm" style="gap:4px;font-size:12px;padding:4px 10px;" title="View Complete Application Dossier">
                                    <i data-lucide="eye" style="width:13px;height:13px;"></i> Dossier
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination Bar -->
            <?php if ($p['totalPages'] > 1): ?>
                <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--color-border);background:var(--color-surface);">
                    <div style="font-size:12.5px;color:var(--color-text-muted);">
                        Page <strong><?= $p['currentPage'] ?></strong> of <strong><?= $p['totalPages'] ?></strong>
                    </div>
                    <div class="pagination-controls" style="display:flex;gap:4px;">
                        <?php if ($p['hasPrev']): ?>
                            <a href="<?= qsApp(['page' => $p['currentPage'] - 1]) ?>" class="btn btn-outline btn-sm" style="padding:4px 10px;">
                                &laquo; Prev
                            </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $p['currentPage'] - 2); $i <= min($p['totalPages'], $p['currentPage'] + 2); $i++): ?>
                            <a href="<?= qsApp(['page' => $i]) ?>" class="btn btn-sm <?= $i === $p['currentPage'] ? 'btn-primary' : 'btn-outline' ?>" style="padding:4px 10px;min-width:32px;">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($p['hasNext']): ?>
                            <a href="<?= qsApp(['page' => $p['currentPage'] + 1]) ?>" class="btn btn-outline btn-sm" style="padding:4px 10px;">
                                Next &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

