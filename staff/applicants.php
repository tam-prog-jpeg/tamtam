<?php
/**
 * staff/applicants.php
 * -----------------------------------------------------------------
 * Read-only directory of applicant records for Staff — search and
 * filter, same data as the admin view but without account-management
 * actions (that stays exclusive to Administrator).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Staff']);

$pageTitle   = 'Applicant Records';
$currentPage = 'applicants';

$search  = sanitize_input($_GET['q'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(CONCAT(u.first_name,' ',u.last_name) LIKE ? OR u.email LIKE ? OR ap.applicant_type LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM applicants ap JOIN users u ON u.user_id = ap.user_id $whereSql");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);
$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT ap.applicant_id, ap.applicant_type, u.first_name, u.last_name, u.email, u.contact_number,
           c.college_name, d.department_name,
           (SELECT COUNT(*) FROM applications a WHERE a.applicant_id = ap.applicant_id) AS total_applications
    FROM applicants ap
    JOIN users u ON u.user_id = ap.user_id
    LEFT JOIN colleges c ON c.college_id = ap.college_id
    LEFT JOIN departments d ON d.department_id = ap.department_id
    $whereSql
    ORDER BY u.first_name
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$applicants = bind_execute($listStmt, $types, $params);

function qsSA($overrides = [])
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
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--color-primary);margin-bottom:2px;">Staff Directory</div>
        <h2 style="font-size:18px;margin:0 0 4px;font-weight:700;">Applicant Registry</h2>
        <div style="font-size:13px;color:var(--color-text-muted);">Lookup enrolled faculty, student, and external researchers registered in the ZPPSU IP repository.</div>
    </div>
    <div class="action-controls-group">
        <span class="badge badge-secondary tabular" style="font-size:12px;padding:6px 12px;">
            <i data-lucide="users" style="width:14px;height:14px;margin-right:4px;"></i>
            <?= number_format($totalRows) ?> Registered Applicants
        </span>
    </div>
</div>

<!-- 2. Search & Filter Bar -->
<div class="filter-card">
    <div class="filter-row">
        <form method="GET" style="display:flex;gap:10px;align-items:center;width:100%;max-width:540px;">
            <div style="position:relative;flex:1;">
                <i data-lucide="search" style="position:absolute;left:11px;top:10px;width:15px;height:15px;color:var(--color-text-muted);pointer-events:none;"></i>
                <input type="text" name="q" class="form-control" placeholder="Search applicant by name, email, or classification…" value="<?= clean($search) ?>" style="padding-left:34px;height:38px;font-size:13px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height:38px;gap:6px;">
                <i data-lucide="search" style="width:14px;height:14px;"></i> Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= BASE_URL ?>staff/applicants.php" class="btn btn-outline btn-sm" style="height:38px;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- 3. Applicants Data Table -->
<div class="card" style="box-shadow:var(--shadow-sm);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;">
    <div class="card-header" style="padding:14px 20px;border-bottom:1px solid var(--color-border);background:var(--color-surface);display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="folder-users" style="width:16px;height:16px;color:var(--color-primary);"></i>
            <h3 style="font-size:15px;margin:0;font-weight:600;">Applicant Records</h3>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Showing <?= (int) $totalRows === 0 ? 0 : (($p['currentPage'] - 1) * $p['perPage'] + 1) ?>–<?= min($p['currentPage'] * $p['perPage'], $totalRows) ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($applicants) === 0): ?>
            <div class="empty-state" style="padding:48px 20px;text-align:center;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--color-text-muted);">
                    <i data-lucide="user-x" style="width:24px;height:24px;"></i>
                </div>
                <div style="font-weight:600;font-size:14px;color:var(--color-text);margin-bottom:4px;">No applicants found</div>
                <div style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:12px;">Try adjusting your keyword query.</div>
                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>staff/applicants.php" class="btn btn-outline btn-sm">Clear Search</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Applicant Name &amp; Contact</th>
                    <th>Classification</th>
                    <th>College / Academic Unit</th>
                    <th>Phone / Mobile</th>
                    <th style="text-align:right;">Submissions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($applicants)): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13.5px;color:var(--color-text);"><?= clean($a['first_name'] . ' ' . $a['last_name']) ?></div>
                        <div style="font-size:11.5px;color:var(--color-text-muted);"><?= clean($a['email']) ?></div>
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size:11.5px;"><?= clean($a['applicant_type']) ?></span>
                    </td>
                    <td style="font-size:12.5px;color:var(--color-text-muted);">
                        <?= clean($a['college_name'] ?? '—') ?><?= $a['department_name'] ? ' / ' . clean($a['department_name']) : '' ?>
                    </td>
                    <td class="tabular" style="font-size:12.5px;">
                        <?= clean($a['contact_number'] ?: '—') ?>
                    </td>
                    <td style="text-align:right;">
                        <span class="badge badge-secondary tabular" style="font-weight:600;"><?= (int) $a['total_applications'] ?></span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- 4. Pagination -->
<?php if ($p['totalPages'] > 1): ?>
<div class="pagination" style="margin-top:20px;display:flex;justify-content:center;gap:6px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qsSA(['page' => $p['currentPage'] - 1]) ?>" class="pagination-link">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qsSA(['page' => $i]) ?>" class="pagination-link <?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qsSA(['page' => $p['currentPage'] + 1]) ?>" class="pagination-link">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
