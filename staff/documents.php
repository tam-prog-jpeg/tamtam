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
        COALESCE(SUM(file_type IN ('jpg', 'jpeg', 'png')), 0) AS total_images
    FROM documents
");
$kpiStats = mysqli_fetch_assoc($kpiRes) ?: [
    'total_all' => 0,
    'total_pdf' => 0,
    'total_docx' => 0,
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
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Documents</span>
            <span class="info-icon" title="All uploaded application files"><i data-lucide="files" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_all']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="docTotalGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,30 Q30,10 60,20 T100,5 L100,40 L0,40 Z" fill="url(#docTotalGrad)"/>
                <path d="M0,30 Q30,10 60,20 T100,5" stroke="#2563EB" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="database" style="width:13px;height:13px;"></i> Active Archive</span>
            <span>All Formats</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>PDF Disclosures</span>
            <span class="info-icon" title="Standard PDF disclosures"><i data-lucide="file-text" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_pdf']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="docPdfGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,28 Q25,12 55,24 T100,8 L100,40 L0,40 Z" fill="url(#docPdfGrad)"/>
                <path d="M0,28 Q25,12 55,24 T100,8" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="check-circle" style="width:13px;height:13px;"></i> Compliant Format</span>
            <span>PDF Files</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Word Documents</span>
            <span class="info-icon" title="DOCX formatted claims"><i data-lucide="file-code" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_docx']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="docWordGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,20 Q35,35 70,15 T100,10 L100,40 L0,40 Z" fill="url(#docWordGrad)"/>
                <path d="M0,20 Q35,35 70,15 T100,10" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend neutral"><i data-lucide="file" style="width:13px;height:13px;"></i> Editable Disclosures</span>
            <span>DOCX Files</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Visual Diagrams</span>
            <span class="info-icon" title="JPG, JPEG, PNG technical drawings"><i data-lucide="image" style="width:14px;height:14px;"></i></span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($kpiStats['total_images']) ?></div>
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="docImgGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,25 Q30,10 65,22 T100,12 L100,40 L0,40 Z" fill="url(#docImgGrad)"/>
                <path d="M0,25 Q30,10 65,22 T100,12" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <span class="kpi-trend up"><i data-lucide="eye" style="width:13px;height:13px;"></i> Schematics</span>
            <span>Drawings / Photos</span>
        </div>
    </div>
</div>

<!-- 3. Deli-Prop Segmented Filter Tabs & Search -->
<div class="filter-card">
    <div class="filter-row">
        <div class="segmented-control">
            <a href="<?= qsSD(['file_type' => '', 'page' => 1]) ?>" class="segment-btn <?= $fileType === '' ? 'active' : '' ?>">
                All Formats <span class="count-pill"><?= number_format($kpiStats['total_all']) ?></span>
            </a>
            <a href="<?= qsSD(['file_type' => 'pdf', 'page' => 1]) ?>" class="segment-btn <?= $fileType === 'pdf' ? 'active' : '' ?>">
                PDF <span class="count-pill"><?= number_format($kpiStats['total_pdf']) ?></span>
            </a>
            <a href="<?= qsSD(['file_type' => 'docx', 'page' => 1]) ?>" class="segment-btn <?= $fileType === 'docx' ? 'active' : '' ?>">
                DOCX <span class="count-pill"><?= number_format($kpiStats['total_docx']) ?></span>
            </a>
            <a href="<?= qsSD(['file_type' => 'png', 'page' => 1]) ?>" class="segment-btn <?= $fileType === 'png' ? 'active' : '' ?>">
                PNG
            </a>
            <a href="<?= qsSD(['file_type' => 'jpg', 'page' => 1]) ?>" class="segment-btn <?= $fileType === 'jpg' ? 'active' : '' ?>">
                JPG
            </a>
        </div>

        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <?php if ($fileType !== ''): ?>
                <input type="hidden" name="file_type" value="<?= clean($fileType) ?>">
            <?php endif; ?>
            <div style="position:relative;">
                <i data-lucide="search" style="position:absolute;left:10px;top:10px;width:14px;height:14px;color:var(--color-text-muted);pointer-events:none;"></i>
                <input type="text" name="q" class="form-control" placeholder="Search file, code, applicant…" value="<?= clean($search) ?>" style="padding-left:32px;height:36px;font-size:13px;width:240px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:36px;">Filter</button>
            <?php if ($search !== '' || $fileType !== ''): ?>
                <a href="<?= BASE_URL ?>staff/documents.php" class="btn btn-outline btn-sm" style="height:36px;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 4. Documents Data Table -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
    <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="folder-git-2" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Uploaded Technical Documents</h3>
            <span class="badge badge-secondary tabular" style="font-size:11px;"><?= number_format($totalRows) ?> total</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int) $totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($documents) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="file-question" style="width:24px;height:24px;"></i>
                </div>
                <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No documents found</div>
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
                    <th>Linked Application</th>
                    <th>Applicant</th>
                    <th>Type</th>
                    <th>Uploaded Date</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = mysqli_fetch_assoc($documents)): ?>
                <tr>
                    <td style="font-weight:500;color:var(--color-text);">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <i data-lucide="<?= in_array($d['file_type'], ['png','jpg','jpeg']) ? 'image' : (in_array($d['file_type'], ['docx','doc']) ? 'file-code' : 'file-text') ?>" style="width:15px;height:15px;color:var(--color-primary);"></i>
                            <span><?= clean($d['file_name']) ?></span>
                        </div>
                    </td>
                    <td>
                        <a class="tracking" href="<?= BASE_URL ?>staff/application_view.php?id=<?= $d['application_id'] ?>" style="font-size:12px;">
                            <?= clean($d['tracking_code']) ?>
                        </a>
                    </td>
                    <td>
                        <div style="font-weight:500;font-size:13px;"><?= clean($d['first_name'] . ' ' . $d['last_name']) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-secondary"><?= strtoupper(clean($d['file_type'])) ?></span>
                    </td>
                    <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= format_date($d['uploaded_at'], 'M d, Y · h:i A') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($d['file_path']) ?>','<?= clean($d['file_type']) ?>','<?= clean(addslashes($d['file_name'])) ?>')" style="gap:4px;">
                                <i data-lucide="eye" style="width:13px;height:13px;"></i> Preview
                            </button>
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($d['file_path']) ?>" target="_blank" style="gap:4px;">
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
