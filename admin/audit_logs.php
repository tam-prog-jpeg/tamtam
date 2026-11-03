<?php
/**
 * admin/audit_logs.php
 * -----------------------------------------------------------------
 * Read-only view of every recorded system action (logins, logouts,
 * status changes, user edits, deletions, etc). Filterable by user,
 * action keyword, and date range.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Audit Logs';
$currentPage = 'audit_logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM audit_logs WHERE log_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $logId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('success', 'Log entry deleted.');
    }

    if ($action === 'clear_all') {
        mysqli_query($conn, "DELETE FROM audit_logs");
        set_flash('success', 'All audit logs have been cleared.');
    }
    redirect('admin/audit_logs.php');
}

$search   = sanitize_input($_GET['q'] ?? '');
$dateFrom = sanitize_input($_GET['date_from'] ?? '');
$dateTo   = sanitize_input($_GET['date_to'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 20;

// ---- Overall Stats for KPI cards ----
$kpiRes = mysqli_query($conn, "
    SELECT 
        COUNT(*) AS total_all,
        COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS total_today,
        COALESCE(COUNT(DISTINCT user_id), 0) AS total_actors
    FROM audit_logs
");
$kpiStats = mysqli_fetch_assoc($kpiRes) ?: [
    'total_all' => 0,
    'total_today' => 0,
    'total_actors' => 0
];

$where = []; $types = ''; $params = [];
if ($search !== '') {
    $where[] = "(l.action LIKE ? OR l.details LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";
    $like = "%$search%"; $types .= 'sss'; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($dateFrom !== '') { $where[] = "DATE(l.created_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = "DATE(l.created_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id $whereSql");
$countResult = bind_execute($countStmt, $types, $params);
$totalRows = mysqli_fetch_assoc($countResult)['total'] ?? 0;
mysqli_stmt_close($countStmt);
$p = paginate($totalRows, $perPage, $page);

$listStmt = mysqli_prepare($conn, "
    SELECT l.*, u.first_name, u.last_name, u.username
    FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id
    $whereSql
    ORDER BY l.created_at DESC
    LIMIT {$p['perPage']} OFFSET {$p['offset']}
");
$logs = bind_execute($listStmt, $types, $params);

function qsA($overrides = [])
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
        <h2>System Audit Trail</h2>
        <p>Immutable security and activity logs tracking administrative events, status updates, and user authentication.</p>
    </div>
    <div class="sub-header-actions">
        <?php if ($totalRows > 0): ?>
        <button type="button" class="btn btn-danger btn-sm" data-modal-target="#clearAllModal">
            <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            Clear All Logs
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- 2. KPI Summary Grid with Sparklines -->
<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom:20px;">
    <!-- Total Logs -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Total Logged Events</span>
            <span class="kpi-info-tip" title="All-time recorded security and activity entries">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value"><?= number_format((int)$kpiStats['total_all']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-blue-log" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#2563EB" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#2563EB" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6 L 80 24 L 0 24 Z" fill="url(#grad-blue-log)"/>
                    <path d="M0 18 Q 20 8, 40 14 T 80 6" fill="none" stroke="#2563EB" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Audit master trail</span>
        </div>
    </div>

    <!-- Today's Events -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Today's Activity</span>
            <span class="kpi-info-tip" title="Events recorded since midnight today">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-primary-600);"><?= number_format((int)$kpiStats['total_today']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-cyan-log" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#06B6D4" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#06B6D4" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4 L 80 24 L 0 24 Z" fill="url(#grad-cyan-log)"/>
                    <path d="M0 20 Q 25 10, 50 14 T 80 4" fill="none" stroke="#06B6D4" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-up">Current session operations</span>
        </div>
    </div>

    <!-- Active Actors -->
    <div class="kpi-card">
        <div class="kpi-top">
            <span class="kpi-label">Active Operators</span>
            <span class="kpi-info-tip" title="Distinct user accounts recorded performing actions">i</span>
        </div>
        <div class="kpi-middle">
            <div class="kpi-value" style="color:var(--color-purple);"><?= number_format((int)$kpiStats['total_actors']) ?></div>
            <div class="kpi-spark">
                <svg class="sparkline-svg" viewBox="0 0 80 24" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="grad-purple-log" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#8B5CF6" stop-opacity="0.3"/>
                            <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8 L 80 24 L 0 24 Z" fill="url(#grad-purple-log)"/>
                    <path d="M0 16 Q 25 6, 50 12 T 80 8" fill="none" stroke="#8B5CF6" stroke-width="2"/>
                </svg>
            </div>
        </div>
        <div class="kpi-bottom">
            <span class="kpi-trend trend-neutral">Distinct system actors</span>
        </div>
    </div>
</div>

<!-- 3. Search & Date Filter Bar Card -->
<div class="card" style="margin-bottom:20px;border-radius:var(--radius-lg);">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">Search Security Trail</label>
                <div class="input-icon-wrap has-left-icon">
                    <i data-lucide="search" class="input-icon-left"></i>
                    <input type="text" name="q" class="form-control" value="<?= clean($search) ?>" placeholder="Action, details, user…">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= clean($dateFrom) ?>" style="height:42px;">
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label-eyebrow">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= clean($dateTo) ?>" style="height:42px;">
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm" style="height:42px;padding:0 16px;">
                    <i data-lucide="filter" style="width:14px;height:14px;"></i>
                    Filter
                </button>
                <a href="<?= BASE_URL ?>admin/audit_logs.php" class="btn btn-outline btn-sm" style="height:42px;display:inline-flex;align-items:center;padding:0 14px;">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- 4. Audit Logs Data Table -->
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
            <h3>Security & Event Records</h3>
            <span class="badge badge-secondary" style="font-size:11px;"><?= (int) $totalRows ?> Entries</span>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);">
            Page <?= (int)$p['currentPage'] ?> of <?= (int)$p['totalPages'] ?>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (mysqli_num_rows($logs) === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg class="lucide-icon" style="width:32px;height:32px;stroke-width:1.5;" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                </div>
                <h4>No matching audit log entries</h4>
                <p>No recorded system events match your search query or chosen date range.</p>
                <a href="<?= BASE_URL ?>admin/audit_logs.php" class="btn btn-outline btn-sm" style="margin-top:10px;">Reset Filter</a>
            </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Actor / Operator</th>
                    <th>Action</th>
                    <th>Event Details</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($l = mysqli_fetch_assoc($logs)): 
                    $actionLabel = ucwords(str_replace('_', ' ', $l['action']));
                    $badgeStyle = 'badge-secondary';
                    if (str_contains($l['action'], 'delete') || str_contains($l['action'], 'clear')) $badgeStyle = 'badge-danger';
                    elseif (str_contains($l['action'], 'login')) $badgeStyle = 'badge-success';
                    elseif (str_contains($l['action'], 'create') || str_contains($l['action'], 'assign')) $badgeStyle = 'badge-primary';
                    elseif (str_contains($l['action'], 'status')) $badgeStyle = 'badge-info';
                ?>
                <tr>
                    <td>
                        <?php if ($l['first_name']): ?>
                            <div style="font-weight:600;color:var(--color-text);"><?= clean($l['first_name'] . ' ' . $l['last_name']) ?></div>
                            <div style="font-family:var(--font-mono);font-size:11px;color:var(--color-text-muted);">@<?= clean($l['username']) ?></div>
                        <?php else: ?>
                            <span class="badge badge-secondary" style="font-size:11px;">System Event</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $badgeStyle ?>" style="font-size:11px;">
                            <?= clean($actionLabel) ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:12.5px;color:var(--color-text);max-width:320px;line-height:1.4;">
                            <?= clean($l['details']) ?>
                        </div>
                    </td>
                    <td>
                        <span style="font-family:var(--font-mono);font-size:11.5px;background:var(--color-bg);padding:3px 7px;border-radius:4px;border:1px solid var(--color-border);color:var(--color-text-muted);">
                            <?= clean($l['ip_address']) ?>
                        </span>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px;color:var(--color-text-muted);white-space:nowrap;">
                        <?= format_date($l['created_at'], 'M d, Y · h:i A') ?>
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" onsubmit="return confirm('Delete this individual log entry?');" style="margin:0;display:inline-flex;">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="log_id" value="<?= $l['log_id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Log Entry">
                                <svg class="lucide-icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                Delete
                            </button>
                        </form>
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
<div class="pagination" style="margin-top:20px;">
    <?php if ($p['currentPage'] > 1): ?>
        <a href="<?= qsA(['page' => $p['currentPage'] - 1]) ?>" class="page-link" title="Previous Page">&laquo;</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $p['totalPages']; $i++): ?>
        <a href="<?= qsA(['page' => $i]) ?>" class="<?= $i === $p['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($p['currentPage'] < $p['totalPages']): ?>
        <a href="<?= qsA(['page' => $p['currentPage'] + 1]) ?>" class="page-link" title="Next Page">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 6. Clear All confirmation modal -->
<div class="modal-overlay" id="clearAllModal">
    <div class="modal-box">
        <form method="POST">
            <div class="modal-head">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:#FEF2F2;color:#EF4444;display:flex;align-items:center;justify-content:center;">
                        <svg class="lucide-icon" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <h3>Clear All Audit Logs</h3>
                </div>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:13.5px;color:var(--color-text);line-height:1.5;">This permanently purges <strong>every audit log record</strong> in the system (<?= (int) $totalRows ?> total, ignoring current filters). This cannot be undone.</p>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="clear_all">
                <div class="form-group" style="margin-top:14px;">
                    <label style="font-size:12px;font-weight:600;">Type <strong style="color:var(--color-danger);">DELETE</strong> to confirm destruction:</label>
                    <input type="text" id="clearAllLogsConfirmInput" class="form-control" autocomplete="off" placeholder="DELETE">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" id="clearAllLogsSubmitBtn" disabled>Clear All Audit Logs</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('clearAllLogsConfirmInput')?.addEventListener('input', function () {
        document.getElementById('clearAllLogsSubmitBtn').disabled = this.value.trim() !== 'DELETE';
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
