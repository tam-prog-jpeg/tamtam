<?php
/**
 * staff/documents.php
 * -----------------------------------------------------------------
 * Document registry for Staff — search, filter by file type,
 * download. Deletion stays exclusive to Administrator.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Documents';
$currentPage = 'documents';

$search   = sanitize_input($_GET['q'] ?? '');
$fileType = sanitize_input($_GET['file_type'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 12;

// ---- Overall Stats for KPI cards ----
$kpiRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(file_type = 'pdf'), 0) AS total_pdf,
        COALESCE(SUM(file_type IN ('docx', 'doc')), 0) AS total_docx,
        COALESCE(SUM(file_type = 'png'), 0) AS total_png,
        COALESCE(SUM(file_type IN ('jpg', 'jpeg')), 0) AS total_jpg,
        COALESCE(SUM(file_type IN ('jpg', 'jpeg', 'png')), 0) AS total_images
    FROM documents
");
$kpiStats = mysqli_fetch_assoc($kpiRes) ?: [
    'total_all' => 0,
    'total_pdf' => 0,
    'total_docx' => 0,
    'total_png' => 0,
    'total_jpg' => 0,
    'total_images' => 0
];

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(doc.file_name LIKE ? OR a.tracking_code LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($fileType !== '') { 
    $where[] = "doc.file_type = ?"; 
    $types .= 's'; 
    $params[] = $fileType; 
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) total FROM documents doc
    JOIN applications a ON a.application_id = doc.application_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);
$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT doc.*, a.tracking_code, a.application_id, u.first_name, u.last_name
    FROM documents doc
    JOIN applications a ON a.application_id = doc.application_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
    ORDER BY doc.uploaded_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$documents = bind_execute($listStmt, $types, $params);

function qsSD($overrides = [])
{
    $current = $_GET;
    foreach ($overrides as $k => $v) { 
        if ($v === null || $v === '') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return '?' . http_build_query($current);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Staff Verification Desk</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Document Registry</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Inspect technical disclosures, claims, diagrams, and compliance attachments across all filings.</div>
    </div>
    <div class="action-controls-group">
        <a href="<?= BASE_URL ?>staff/documents.php" class="btn btn-outline btn-sm">
            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Refresh
        </a>
    </div>
</div>

<!-- 2. KPI Summary Cards with Sparklines -->
<div class="kpi-grid">
    <a href="<?= qsSD(['file_type' => '', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Total Documents</span>
            <span class="info-icon" title="All uploaded application files"><i data-lucide="files" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_all']) ?></div>
            <?= render_kpi_sparkline($kpiStats['total_all'], 'blue') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="database" style="width:13px;height:13px;"></i> Active Archive</span>
            <span>All Formats</span>
        </div>
    </a>

    <a href="<?= qsSD(['file_type' => 'pdf', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>PDF Disclosures</span>
            <span class="info-icon" title="Standard PDF disclosures"><i data-lucide="file-text" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_pdf']) ?></div>
            <?= render_kpi_sparkline($kpiStats['total_pdf'], 'red') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Compliant Format</span>
            <span>PDF Files</span>
        </div>
    </a>

    <a href="<?= qsSD(['file_type' => 'docx', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Word Documents</span>
            <span class="info-icon" title="DOCX formatted claims"><i data-lucide="file-code" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_docx']) ?></div>
            <?= render_kpi_sparkline($kpiStats['total_docx'], 'cyan') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="file" style="width:13px;height:13px;"></i> Editable Disclosures</span>
            <span>DOCX Files</span>
        </div>
    </a>

    <a href="<?= qsSD(['file_type' => 'png', 'page' => 1]) ?>" class="kpi-card" style="text-decoration:none;cursor:pointer;">
        <div class="kpi-card-header">
            <span>Visual Diagrams</span>
            <span class="info-icon" title="JPG, JPEG, PNG technical drawings"><i data-lucide="image" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_images']) ?></div>
            <?= render_kpi_sparkline($kpiStats['total_images'], 'green') ?>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="eye" style="width:13px;height:13px;"></i> Schematics</span>
            <span>Drawings / Photos</span>
        </div>
    </a>
</div>

<!-- 3. Modern Segmented Toolbar (File Format Tabs + Live Search) -->
<div class="staff-toolbar">
    <div class="staff-toolbar-tabs" role="tablist">
        <a href="<?= qsSD(['file_type' => '', 'page' => 1]) ?>" class="staff-tab-pill <?= $fileType === '' ? 'active' : '' ?>">
            <i data-lucide="files" style="width:14px;height:14px;"></i>
            <span>All Formats</span>
            <span class="staff-tab-count"><?= number_format($kpiStats['total_all']) ?></span>
        </a>
        <a href="<?= qsSD(['file_type' => 'pdf', 'page' => 1]) ?>" class="staff-tab-pill <?= $fileType === 'pdf' ? 'active' : '' ?>">
            <i data-lucide="file-text" style="width:14px;height:14px;"></i>
            <span>PDF</span>
            <span class="staff-tab-count"><?= number_format($kpiStats['total_pdf']) ?></span>
        </a>
        <a href="<?= qsSD(['file_type' => 'docx', 'page' => 1]) ?>" class="staff-tab-pill <?= $fileType === 'docx' ? 'active' : '' ?>">
            <i data-lucide="file-code" style="width:14px;height:14px;"></i>
            <span>DOCX</span>
            <span class="staff-tab-count"><?= number_format($kpiStats['total_docx']) ?></span>
        </a>
        <a href="<?= qsSD(['file_type' => 'png', 'page' => 1]) ?>" class="staff-tab-pill <?= $fileType === 'png' ? 'active' : '' ?>">
            <i data-lucide="image" style="width:14px;height:14px;"></i>
            <span>PNG</span>
            <span class="staff-tab-count"><?= number_format($kpiStats['total_png']) ?></span>
        </a>
        <a href="<?= qsSD(['file_type' => 'jpg', 'page' => 1]) ?>" class="staff-tab-pill <?= $fileType === 'jpg' ? 'active' : '' ?>">
            <i data-lucide="image" style="width:14px;height:14px;"></i>
            <span>JPG</span>
            <span class="staff-tab-count"><?= number_format($kpiStats['total_jpg']) ?></span>
        </a>
    </div>

    <form method="GET" class="staff-search-form">
        <?php if ($fileType !== ''): ?>
            <input type="hidden" name="file_type" value="<?= clean($fileType) ?>">
        <?php endif; ?>
        <div class="staff-search-input-wrap">
            <i data-lucide="search" class="staff-search-icon"></i>
            <input type="text" name="q" class="staff-search-input" placeholder="Search file, tracking code, applicant..." value="<?= clean($search) ?>" autocomplete="off">
            <?php if ($search !== ''): ?>
                <a href="<?= qsSD(['q' => '']) ?>" class="staff-search-clear" title="Clear search">&times;</a>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm staff-search-btn">
            <i data-lucide="search" style="width:14px;height:14px;"></i> Search
        </button>
        <?php if ($search !== '' || $fileType !== ''): ?>
            <a href="<?= BASE_URL ?>staff/documents.php" class="btn btn-outline btn-sm staff-reset-btn" title="Reset all filters">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- 4. Documents Data Table -->
<div class="staff-table-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px;">
            <i data-lucide="folder-git-2" style="width:18px;height:18px;color:var(--color-primary-600);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:700;">Uploaded Technical Documents</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;font-weight:700;"><?= number_format($totalRows) ?> total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int) $totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?> of <?= number_format($totalRows) ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($documents) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="file-question" style="width:24px;height:24px;"></i>
                </div>
                <div style="font-weight:700;font-size:15px;color:var(--color-text);margin-bottom:4px;">No documents found</div>
                <div style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:12px;">No uploaded documents match this filter criteria.</div>
                <?php if ($search !== '' || $fileType !== ''): ?>
                    <a href="<?= BASE_URL ?>staff/documents.php" class="btn btn-outline btn-sm">Clear Filter</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>File Name</th>
                    <th style="width:160px;">Linked Application</th>
                    <th>Applicant</th>
                    <th style="width:110px;">Format</th>
                    <th style="width:170px;">Uploaded Date</th>
                    <th style="width:190px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = mysqli_fetch_assoc($documents)): ?>
                <?php
                    $fExt = strtolower($d['file_type']);
                    $badgeClass = match ($fExt) {
                        'pdf' => 'badge-file-pdf',
                        'docx', 'doc' => 'badge-file-docx',
                        'png', 'jpg', 'jpeg' => 'badge-file-png',
                        default => 'badge-secondary',
                    };
                ?>
                <tr>
                    <td style="font-weight:500;color:var(--color-text);">
                        <div style="display:flex;align-items:center;gap:9px;">
                            <i data-lucide="<?= in_array($fExt, ['png','jpg','jpeg']) ? 'image' : (in_array($fExt, ['docx','doc']) ? 'file-code' : 'file-text') ?>" style="width:16px;height:16px;color:var(--color-primary-600);flex-shrink:0;"></i>
                            <span style="font-weight:600;word-break:break-all;"><?= clean($d['file_name']) ?></span>
                        </div>
                    </td>
                    <td>
                        <a class="tracking-pill" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $d['application_id'] ?>" title="View application dossier">
                            <i data-lucide="hash" style="width:11px;height:11px;opacity:0.7;"></i>
                            <?= clean($d['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div class="user-avatar-initials">
                                <?= strtoupper(substr($d['first_name'],0,1) . substr($d['last_name'],0,1)) ?>
                            </div>
                            <div style="font-weight:600;font-size:13px;color:var(--color-text);"><?= clean($d['first_name'] . ' ' . $d['last_name']) ?></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $badgeClass ?>" style="font-size:11px;font-weight:600;"><?= strtoupper(clean($d['file_type'])) ?></span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($d['uploaded_at'], 'M d, Y · h:i A') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;align-items:center;justify-content:flex-end;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($d['file_path']) ?>','<?= clean($d['file_type']) ?>','<?= clean(addslashes($d['file_name'])) ?>')" style="gap:4px;height:32px;" title="Preview Document">
                                <i data-lucide="eye" style="width:13px;height:13px;"></i> Preview
                            </button>
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($d['file_path']) ?>" target="_blank" download style="gap:4px;height:32px;" title="Download File">
                                <i data-lucide="download" style="width:13px;height:13px;"></i> Download
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

<!-- 5. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;display:flex;justify-content:center;gap:6px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qsSD(['page' => $p['currentPage'] - 1]) ?>" class="pagination-link">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qsSD(['page' => $i]) ?>" class="pagination-link <?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qsSD(['page' => $p['currentPage'] + 1]) ?>" class="pagination-link">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
