<?php
/**
 * admin/documents.php
 * -----------------------------------------------------------------
 * System-wide document registry: every file uploaded against any
 * application. Supports search (file name / tracking code / applicant),
 * filter by file type, download, and delete (removes the DB row and
 * the physical file, logs an audit entry).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Documents';
$currentPage = 'documents';

// ---- Handle delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $docId = (int) ($_POST['document_id'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT file_path FROM documents WHERE document_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $docId);
    mysqli_stmt_execute($stmt);
    $doc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($doc) {
        $fullPath = __DIR__ . '/../' . $doc['file_path'];
        if (is_file($fullPath)) { @unlink($fullPath); }

        $del = mysqli_prepare($conn, "DELETE FROM documents WHERE document_id = ?");
        mysqli_stmt_bind_param($del, 'i', $docId);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);

        log_audit($conn, $_SESSION['user_id'], 'delete_document', "Deleted document #$docId");
        set_flash('success', 'Document deleted successfully.');
    } else {
        set_flash('error', 'Document not found.');
    }
    redirect('admin/documents.php');
}

// ---- Filters ----
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
    $like = "%$search%";
    $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($fileType !== '') {
    $where[] = "doc.file_type = ?";
    $types .= 's'; $params[] = $fileType;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total
    FROM documents doc
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
    SELECT doc.*, a.tracking_code, a.title, u.first_name, u.last_name
    FROM documents doc
    JOIN applications a ON a.application_id = doc.application_id
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    $whereSql
    ORDER BY doc.uploaded_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$documents = bind_execute($listStmt, $types, $params);

function qs3($overrides = [])
{
    $current = $_GET;
    foreach ($overrides as $k => $v) { $current[$k] = $v; }
    return '?' . http_build_query($current);
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Deli-Prop Sub-Header Bar -->
<div class="sub-header-bar">
    <div class="sub-header-left">
        <h2>Document Repository & Files</h2>
        <p>Central archive of all specifications, claims, technical drawings, and supporting attachments.</p>
    </div>
    <div class="sub-header-actions">
        <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/></svg>
            View Applications
        </a>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="margin-bottom:20px;">
    <!-- Total Documents -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Documents</span>
            <span class="kpi-info-tip" title="All uploaded technical descriptions, drafts, and attachments">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-blue-doc" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6 L 80 24 L 0 24 Z" fill="url(#grad-blue-doc)"/>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6" fill="none" stroke="#2563EB" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Master file registry</span>
        </div>
    </div>

    <!-- PDF Documents -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">PDF Specifications</span>
            <span class="kpi-info-tip" title="Patent claims, descriptions, and formal documentation in PDF format">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-danger);"><?= number_format((int)$kpiStats['total_pdf']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-red-doc" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#EF4444" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4 L 80 24 L 0 24 Z" fill="url(#grad-red-doc)"/>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4" fill="none" stroke="#EF4444" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Formal documents</span>
        </div>
    </div>

    <!-- Images & Drawings -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Drawings & Images</span>
            <span class="kpi-info-tip" title="Technical diagrams, schematics, and prototype photos">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-success);"><?= number_format((int)$kpiStats['total_images']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-green-doc" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2 L 80 24 L 0 24 Z" fill="url(#grad-green-doc)"/>
                    <path d="M0 22 Q 30 18, 55 10 T 80 2" fill="none" stroke="#10B981" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Visual assets</span>
        </div>
    </div>

    <!-- Word Documents -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Word Drafts (DOCX)</span>
            <span class="kpi-info-tip" title="Editable drafts and descriptions">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-primary-600);"><?= number_format((int)$kpiStats['total_docx']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-cyan-doc" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#06B6D4" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#06B6D4" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8 L 80 24 L 0 24 Z" fill="url(#grad-cyan-doc)"/>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8" fill="none" stroke="#06B6D4" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Editable drafts</span>
        </div>
    </div>
</div>

<!-- 3. Category Filter Tabs -->
<div class="category-tabs" style="margin-bottom:16px;">
    <a href="<?= qs3(['file_type' => '', 'page' => 1]) ?>" class="tab-pill <?= empty($fileType) ? 'active' : '' ?>">
        All Formats
        <span class="tab-count"><?= (int)$kpiStats['total_all'] ?></span>
    </a>
    <a href="<?= qs3(['file_type' => 'pdf', 'page' => 1]) ?>" class="tab-pill <?= $fileType === 'pdf' ? 'active' : '' ?>">
        PDF
        <span class="tab-count"><?= (int)$kpiStats['total_pdf'] ?></span>
    </a>
    <a href="<?= qs3(['file_type' => 'docx', 'page' => 1]) ?>" class="tab-pill <?= $fileType === 'docx' ? 'active' : '' ?>">
        DOCX
    </a>
    <a href="<?= qs3(['file_type' => 'png', 'page' => 1]) ?>" class="tab-pill <?= $fileType === 'png' ? 'active' : '' ?>">
        PNG
    </a>
    <a href="<?= qs3(['file_type' => 'jpg', 'page' => 1]) ?>" class="tab-pill <?= $fileType === 'jpg' ? 'active' : '' ?>">
        JPG / JPEG
    </a>
</div>

<!-- 4. Search Filter Bar Card -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--color-text-muted);letter-spacing:0.04em;">Search Documents</label>
                <div style="position:relative;">
                    <svg class="lucide-icon" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--color-text-subtle);pointer-events:none;width:15px;height:15px;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="q" class="form-control" style="padding-left:32px;" placeholder="File name, tracking code, applicant…" value="<?= clean($search) ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--color-text-muted);letter-spacing:0.04em;">File Format</label>
                <select name="file_type" class="form-control">
                    <option value="">All Formats</option>
                    <?php foreach (['pdf','docx','jpg','jpeg','png'] as $ft): ?>
                        <option value="<?= $ft ?>" <?= $fileType === $ft ? 'selected' : '' ?>><?= strtoupper($ft) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">
                    <svg class="lucide-icon" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filter
                </button>
                <a href="<?= BASE_URL ?>admin/documents.php" class="btn btn-outline btn-sm" style="height:38px;padding:0 12px;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- 5. Documents Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Document Archive</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Files</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($documents) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h4>No documents found</h4>
                <p>No documents match your specified search keywords or active format filter.</p>
                <a href="<?= BASE_URL ?>admin/documents.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Reset Filter</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>File Details</th>
                    <th>Linked Application</th>
                    <th>Applicant</th>
                    <th>Format</th>
                    <th>Version</th>
                    <th>Uploaded</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = mysqli_fetch_assoc($documents)): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;border-radius:var(--radius-sm);background:var(--color-bg);border:1px solid var(--color-border);display:flex;align-items:center;justify-content:center;color:var(--color-primary-600);flex-shrink:0;">
                                <svg class="lucide-icon" style="width:16px;height:16px;" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <div style="font-weight:600;color:var(--color-text);max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= clean($d['file_name']) ?>">
                                    <?= clean($d['file_name']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--color-text-muted);font-family:var(--font-mono);">
                                    <?= clean($d['file_path']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a class="tracking" style="color:var(--color-primary-600);font-weight:600;text-decoration:none;" href="<?= BASE_URL ?>admin/application_view.php?id=<?= $d['application_id'] ?>" title="<?= clean($d['title']) ?>">
                            <?= clean($d['tracking_code']) ?>
                        </a>
                        <div style="font-size:11.5px;color:var(--color-text-muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= clean($d['title']) ?>
                        </div>
                    </td>
                    <td>
                        <span style="font-weight:500;color:var(--color-text);"><?= clean($d['first_name'] . ' ' . $d['last_name']) ?></span>
                    </td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:10.5px;font-weight:700;">
                            <?= strtoupper(clean($d['file_type'])) ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-family:var(--font-mono);font-size:11.5px;background:var(--color-bg);padding:2px 6px;border-radius:4px;border:1px solid var(--color-border);">
                            v<?= (int) $d['version'] ?>
                        </span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($d['uploaded_at'], 'M d, Y') ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:inline-flex;gap:6px;justify-content:flex-end;align-items:center;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="openDocPreview('<?= BASE_URL . clean($d['file_path']) ?>','<?= clean($d['file_type']) ?>','<?= clean(addslashes($d['file_name'])) ?>')" title="Quick Preview">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                Preview
                            </button>
                            <a class="btn btn-outline btn-sm" href="<?= BASE_URL . clean($d['file_path']) ?>" target="_blank" download title="Download File">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Download
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this document permanently?');" style="margin:0;display:inline-flex;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="document_id" value="<?= $d['document_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete File">
                                    <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 6. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qs3(['page' => $p['currentPage'] - 1]) ?>" class="page-link" title="Previous Page">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qs3(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qs3(['page' => $p['currentPage'] + 1]) ?>" class="page-link" title="Next Page">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
