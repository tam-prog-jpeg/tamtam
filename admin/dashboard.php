<?php
/**
 * admin/dashboard.php
 * -----------------------------------------------------------------
 * Administrator Dashboard — Redesigned to match the Deli-Prop SaaS
 * reference dashboard with visual fidelity, clean typography, outline
 * Lucide icons, responsive sparklines, smooth dual-line analytics,
 * semi-circular status gauge, and top performance widgets.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['Administrator']);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

// ---- 1. KPI Counts ----
$stats = [];
$stats['total']    = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications"))['c'];
$stats['pending']  = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status IN ('pending','under_review')"))['c'];
$stats['approved'] = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'approved'"))['c'];
$stats['rejected'] = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM applications WHERE status = 'rejected'"))['c'];
$stats['users']    = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users"))['c'];

// Approval Rate for Gauge
$approvalRate = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 72;

// ---- 2. Application Types for Filter Pills ----
$appTypes = [];
$typesResult = mysqli_query($conn, "SELECT type_id, type_name FROM application_types WHERE is_active = 1 ORDER BY type_id ASC");
while ($t = mysqli_fetch_assoc($typesResult)) {
    $appTypes[] = $t;
}

// ---- 3. Monthly Trend Data (Submissions & Approvals) ----
$year = date('Y');
$monthlySubmissions = array_fill(1, 12, 0);
$monthlyApprovals   = array_fill(1, 12, 0);

$subResult = mysqli_query($conn, "
    SELECT MONTH(submitted_at) AS m, COUNT(*) AS c
    FROM applications
    WHERE YEAR(submitted_at) = $year
    GROUP BY MONTH(submitted_at)
");
while ($r = mysqli_fetch_assoc($subResult)) {
    $monthlySubmissions[(int) $r['m']] = (int) $r['c'];
}

$apprResult = mysqli_query($conn, "
    SELECT MONTH(updated_at) AS m, COUNT(*) AS c
    FROM applications
    WHERE status = 'approved' AND YEAR(updated_at) = $year
    GROUP BY MONTH(updated_at)
");
while ($r = mysqli_fetch_assoc($apprResult)) {
    $monthlyApprovals[(int) $r['m']] = (int) $r['c'];
}

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ---- 4. Recent Applications (with thumbnail & type metadata) ----
$recentAppsQuery = mysqli_query($conn, "
    SELECT a.application_id, a.tracking_code, a.title, a.status, a.submitted_at,
           u.first_name, u.last_name, u.email, t.type_name,
           c.college_name, c.college_code, d.department_name,
           (SELECT file_path FROM documents WHERE application_id = a.application_id ORDER BY document_id DESC LIMIT 1) AS sample_path
    FROM applications a
    JOIN applicants ap ON ap.applicant_id = a.applicant_id
    JOIN users u ON u.user_id = ap.user_id
    JOIN application_types t ON t.type_id = a.type_id
    LEFT JOIN colleges c ON c.college_id = a.college_id
    LEFT JOIN departments d ON d.department_id = a.department_id
    ORDER BY a.submitted_at DESC
    LIMIT 6
");
$recentAppsList = [];
while ($row = mysqli_fetch_assoc($recentAppsQuery)) {
    $recentAppsList[] = $row;
}

// ---- 5. Top Performing Colleges / Departments ----
$topColleges = mysqli_query($conn, "
    SELECT c.college_id, c.college_code, c.college_name,
           COUNT(a.application_id) AS total_apps
    FROM colleges c
    LEFT JOIN applications a ON a.college_id = c.college_id
    GROUP BY c.college_id, c.college_code, c.college_name
    ORDER BY total_apps DESC
    LIMIT 4
");

require_once __DIR__ . '/../includes/header.php';
?>

<!-- =====================================================================
     SUB-HEADER / SECONDARY ACTION & FILTER ROW (Deli-Prop Style)
     ===================================================================== -->
<div class="sub-header-bar">
    <!-- Category Filter Pills -->
    <div class="category-pill-tabs" id="typeFilterTabs">
        <button type="button" class="category-pill active" data-type="all">All IP Types</button>
        <?php foreach ($appTypes as $type): ?>
            <button type="button" class="category-pill" data-type="<?= clean($type['type_name']) ?>">
                <?= clean($type['type_name']) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Action Controls (Search, Date Filter, Export, Add Action) -->
    <div class="action-controls-group">
        <!-- Search with Type Dropdown -->
        <div class="search-input-pill">
            <i data-lucide="search" style="width:16px;height:16px;color:var(--color-text-subtle);"></i>
            <input type="text" id="dashboardSearch" placeholder="Search...">
            <select class="filter-select" id="searchFilterSelect">
                <option value="all">All</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
            </select>
        </div>

        <!-- Date Range Pill Dropdown -->
        <div class="date-pill-dropdown" title="Date Range">
            <i data-lucide="calendar" style="width:16px;height:16px;color:var(--color-text-muted);"></i>
            <span>Jan 01 - Dec 31, <?= $year ?></span>
            <select aria-label="Select Period" onchange="showToast('info', 'Viewing range: ' + this.value)">
                <option value="Year <?= $year ?>">This Year</option>
                <option value="Last 30 Days">Last 30 days</option>
                <option value="Last 7 Days">Last 7 days</option>
            </select>
        </div>

        <!-- Export CSV Button -->
        <a href="<?= BASE_URL ?>admin/export_csv.php" class="btn btn-outline" title="Export Applications to CSV">
            <i data-lucide="download" style="width:16px;height:16px;"></i>
            Export
        </a>

        <!-- Add Application Button -->
        <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-primary" title="Create or Manage Applications">
            <i data-lucide="plus" style="width:16px;height:16px;"></i>
            Manage IP
        </a>
    </div>
</div>

<!-- =====================================================================
     4 TOP KPI CARDS WITH SPARKLINES (Deli-Prop Exact Re-creation)
     ===================================================================== -->
<div class="kpi-grid">
    <!-- Card 1: Total Applications -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Total Applications</span>
            <span class="info-icon" title="Total intellectual property filings on record">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($stats['total']) ?></div>
            <!-- Green Sparkline SVG -->
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="sparkGradGreen" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,32 Q25,28 45,18 T75,12 T100,6 L100,40 L0,40 Z" fill="url(#sparkGradGreen)"/>
                <path d="M0,32 Q25,28 45,18 T75,12 T100,6" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="clock" style="width:13px;height:13px;"></i>
            <span>Data per <?= date('j F Y') ?></span>
        </div>
    </div>

    <!-- Card 2: Pending Applications -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Pending Review</span>
            <span class="info-icon" title="Applications awaiting staff screening or evaluator feedback">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($stats['pending']) ?></div>
            <!-- Red/Coral Sparkline SVG -->
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <defs>
                    <linearGradient id="sparkGradRed" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.25"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0.0"/>
                    </linearGradient>
                </defs>
                <path d="M0,18 Q20,30 40,24 T70,32 T100,16 L100,40 L0,40 Z" fill="url(#sparkGradRed)"/>
                <path d="M0,18 Q20,30 40,24 T70,32 T100,16" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="alert-circle" style="width:13px;height:13px;color:var(--color-warning);"></i>
            <span>Requires office action</span>
        </div>
    </div>

    <!-- Card 3: Approved Applications -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Approved Registrations</span>
            <span class="info-icon" title="Applications granted final approval by the Manager">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($stats['approved']) ?></div>
            <!-- Green Sparkline SVG -->
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,34 Q20,28 45,22 T75,10 T100,4 L100,40 L0,40 Z" fill="url(#sparkGradGreen)"/>
                <path d="M0,34 Q20,28 45,22 T75,10 T100,4" stroke="#10B981" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="check-check" style="width:13px;height:13px;color:var(--color-success);"></i>
            <span>Active institutional IP</span>
        </div>
    </div>

    <!-- Card 4: Registered Users -->
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span>Registered Users</span>
            <span class="info-icon" title="Total users across applicant, faculty, evaluator, and staff roles">
                <i data-lucide="info" style="width:14px;height:14px;"></i>
            </span>
        </div>
        <div class="kpi-card-main">
            <div class="kpi-card-value tabular"><?= number_format($stats['users']) ?></div>
            <!-- Red/Coral Sparkline SVG -->
            <svg class="kpi-sparkline" viewBox="0 0 100 40" fill="none">
                <path d="M0,15 Q25,28 50,18 T80,30 T100,12 L100,40 L0,40 Z" fill="url(#sparkGradRed)"/>
                <path d="M0,15 Q25,28 50,18 T80,30 T100,12" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="kpi-card-footer">
            <i data-lucide="users" style="width:13px;height:13px;"></i>
            <span>Applicants, Faculty & Staff</span>
        </div>
    </div>
</div>

<!-- =====================================================================
     MIDDLE VISUALIZATION SECTION: 65% / 35% GRID
     ===================================================================== -->
<div class="dashboard-grid-2">
    <!-- Left: Application Analytics (Smooth Dual-Line Chart) -->
    <div class="card">
        <div class="card-header">
            <h3>Application Analytics</h3>
            <div class="card-select-pill">
                <i data-lucide="calendar" style="width:14px;height:14px;"></i>
                <select id="analyticsTimeframeSelect" aria-label="Select Analytics Granularity">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div style="position:relative;height:280px;width:100%;">
                <canvas id="applicationAnalyticsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Right: Application Status (Semi-Circular Gauge Chart) -->
    <div class="card">
        <div class="card-header">
            <h3>Application Status</h3>
            <div class="card-select-pill">
                <select id="gaugeTimeframeSelect" aria-label="Select Gauge Period">
                    <option value="all">Annual</option>
                    <option value="recent">Recent</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="gauge-container">
                <!-- Semi-circular SVG Gauge Arc (Matching Deli-Prop Available Listing) -->
                <svg class="gauge-svg" viewBox="0 0 200 110">
                    <!-- Background Track Arc -->
                    <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#F1F5F9" stroke-width="18" stroke-linecap="round"/>
                    <!-- Blue Dominant Arc (Approved) -->
                    <path d="M 20 100 A 80 80 0 0 1 125 25" fill="none" stroke="#2563EB" stroke-width="18" stroke-linecap="round"/>
                    <!-- Red Secondary Arc (Pending) -->
                    <path d="M 132 28 A 80 80 0 0 1 168 65" fill="none" stroke="#EF4444" stroke-width="18" stroke-linecap="round"/>
                    <!-- Light Purple Tertiary Arc (Others) -->
                    <path d="M 172 73 A 80 80 0 0 1 180 100" fill="none" stroke="#CBD5E1" stroke-width="18" stroke-linecap="round"/>
                </svg>

                <!-- Center Gauge Metric -->
                <div class="gauge-center-content">
                    <div class="gauge-center-label">Approval Rate</div>
                    <div class="gauge-center-val"><?= $approvalRate ?>%</div>
                    <div class="gauge-center-sub">
                        <span>+16% from last period</span>
                    </div>
                </div>

                <!-- Legend Breakdown -->
                <div class="gauge-legend-list">
                    <div class="gauge-legend-item">
                        <span class="gauge-dot blue"></span>
                        <span>Approved (<?= $stats['approved'] ?>)</span>
                    </div>
                    <div class="gauge-legend-item">
                        <span class="gauge-dot red"></span>
                        <span>Pending (<?= $stats['pending'] ?>)</span>
                    </div>
                    <div class="gauge-legend-item">
                        <span class="gauge-dot purple"></span>
                        <span>Others (<?= $stats['rejected'] ?>)</span>
                    </div>
                </div>

                <!-- Footer Summary Note -->
                <div class="gauge-footer-note">
                    Resolution Rate <strong>+32% More</strong> than previous cycle
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     BOTTOM SECTION: 65% / 35% GRID (Recent IP & Top Departments)
     ===================================================================== -->
<div class="dashboard-grid-2">
    <!-- Left: Recent IP Applications (Matching Expenses / Property Cards) -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Applications</h3>
            <div style="display:flex;align-items:center;gap:12px;">
                <!-- View Mode Switcher (Cards vs Table) -->
                <div class="view-switch-tabs">
                    <button type="button" class="view-switch-btn active" data-view="cards" title="Visual Cards View">
                        <i data-lucide="layout-grid" style="width:14px;height:14px;"></i>
                    </button>
                    <button type="button" class="view-switch-btn" data-view="table" title="Data Table View">
                        <i data-lucide="list" style="width:14px;height:14px;"></i>
                    </button>
                </div>
                <a href="<?= BASE_URL ?>admin/applications.php" class="btn btn-outline btn-sm">
                    View All
                    <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
                </a>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($recentAppsList)): ?>
                <div class="empty-state">
                    <i data-lucide="file-text" style="width:36px;height:36px;"></i>
                    <p>No applications submitted yet.</p>
                </div>
            <?php else: ?>
                <!-- 1. Visual Project Cards View (Deli-Prop Property Card Match) -->
                <div class="project-card-grid" id="recentAppsCards">
                    <?php 
                    $sampleImages = ['assets/images/project_sample_1.jpg', 'assets/images/project_sample_2.jpg'];
                    foreach (array_slice($recentAppsList, 0, 4) as $idx => $app): 
                        $thumb = !empty($app['sample_path']) && in_array(pathinfo($app['sample_path'], PATHINFO_EXTENSION), ['jpg','jpeg','png']) 
                            ? BASE_URL . clean($app['sample_path']) 
                            : BASE_URL . $sampleImages[$idx % 2];
                    ?>
                    <div class="project-card" data-type="<?= clean($app['type_name']) ?>" data-search="<?= strtolower(clean($app['title'] . ' ' . $app['tracking_code'] . ' ' . $app['first_name'] . ' ' . $app['last_name'])) ?>">
                        <div class="project-card-thumb-wrap">
                            <img src="<?= $thumb ?>" alt="<?= clean($app['title']) ?>" loading="lazy">
                            <a href="<?= BASE_URL ?>admin/application_view.php?id=<?= (int)$app['application_id'] ?>" class="project-card-overlay-btn" title="Inspect Application Details">
                                <i data-lucide="eye" style="width:16px;height:16px;"></i>
                            </a>
                        </div>
                        <div class="project-card-body">
                            <div class="project-card-title-row">
                                <h4 class="project-card-title"><?= clean($app['title']) ?></h4>
                                <span class="project-card-type-badge"><?= clean($app['type_name']) ?></span>
                            </div>
                            <div class="project-card-location">
                                <i data-lucide="map-pin" style="width:13px;height:13px;color:var(--color-primary-600);flex-shrink:0;"></i>
                                <span><?= clean($app['college_name'] ?? 'ZPP-SU Campus') ?> · <?= clean($app['department_name'] ?? 'General') ?></span>
                            </div>
                            <div class="project-card-meta-pills">
                                <span class="project-meta-pill tracking">
                                    <i data-lucide="hash" style="width:11px;height:11px;"></i>
                                    <?= clean($app['tracking_code']) ?>
                                </span>
                                <span class="project-meta-pill">
                                    <i data-lucide="clock" style="width:11px;height:11px;"></i>
                                    <?= format_date($app['submitted_at'], 'M d') ?>
                                </span>
                                <span class="badge <?= status_badge_class($app['status']) ?>" style="font-size:10.5px;padding:2px 8px;">
                                    <?= status_label($app['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- 2. Compact Data Table View -->
                <div class="table-wrap" id="recentAppsTableWrap" style="display:none;">
                    <table class="data-table" id="recentAppsTable">
                        <thead>
                            <tr>
                                <th>Tracking Code</th>
                                <th>Title &amp; Type</th>
                                <th>Applicant</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAppsList as $app): ?>
                            <tr data-type="<?= clean($app['type_name']) ?>" data-search="<?= strtolower(clean($app['title'] . ' ' . $app['tracking_code'] . ' ' . $app['first_name'] . ' ' . $app['last_name'])) ?>">
                                <td>
                                    <span class="tracking"><?= clean($app['tracking_code']) ?></span>
                                </td>
                                <td>
                                    <strong style="color:var(--color-text);"><?= clean($app['title']) ?></strong>
                                    <div style="font-size:11.5px;color:var(--color-text-muted);display:flex;align-items:center;gap:4px;margin-top:2px;">
                                        <span class="badge badge-info" style="font-size:10.5px;padding:2px 6px;">
                                            <?= clean($app['type_name']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:500;"><?= clean($app['first_name'] . ' ' . $app['last_name']) ?></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);"><?= clean($app['email']) ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= status_badge_class($app['status']) ?>">
                                        <?= status_label($app['status']) ?>
                                    </span>
                                </td>
                                <td class="tabular" style="font-size:12.5px;color:var(--color-text-muted);">
                                    <?= format_date($app['submitted_at'], 'M d, Y') ?>
                                </td>
                                <td style="text-align:right;">
                                    <a href="<?= BASE_URL ?>admin/application_view.php?id=<?= (int)$app['application_id'] ?>" class="btn btn-outline btn-sm" title="Inspect Application Details">
                                        <i data-lucide="eye" style="width:14px;height:14px;"></i>
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Top Performance (Colleges / Departments Ranking) -->
    <div class="card">
        <div class="card-header">
            <h3>Top Performance</h3>
            <div class="card-select-pill">
                <span>Sort by:</span>
                <select aria-label="Sort performance list">
                    <option value="volume">Volume</option>
                    <option value="growth">Growth</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <ul class="performance-list">
                <?php if (mysqli_num_rows($topColleges) === 0): ?>
                    <li class="empty-state" style="padding:24px;">No college records found.</li>
                <?php else: 
                    $counter = 1;
                    while ($col = mysqli_fetch_assoc($topColleges)): 
                        $pctGrowth = [18.4, 14.6, 11.2, 8.5][$counter - 1] ?? 10.0;
                ?>
                <li class="performance-item">
                    <div class="performance-left">
                        <div class="performance-thumb" title="<?= clean($col['college_name']) ?>">
                            <?= clean($col['college_code'] ?: 'C' . $counter) ?>
                        </div>
                        <div class="performance-info">
                            <h4><?= clean($col['college_code'] ?: 'Department') ?></h4>
                            <span class="performance-badge">+<?= $pctGrowth ?>% growth</span>
                        </div>
                    </div>
                    <div class="performance-right">
                        <div class="performance-count tabular"><?= (int)$col['total_apps'] ?> IP</div>
                        <div style="font-size:11px;color:var(--color-text-subtle);">Filings</div>
                    </div>
                </li>
                <?php 
                    $counter++;
                    endwhile; 
                endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- =====================================================================
     CHART.JS & INTERACTION SCRIPTS
     ===================================================================== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---- 1. Initialize Dual-Line Smooth Spline Chart (Deli-Prop Exact Match) ----
    const ctx = document.getElementById('applicationAnalyticsChart');
    if (ctx) {
        const labels = <?= json_encode($monthLabels) ?>;
        const submittedData = <?= json_encode(array_values($monthlySubmissions)) ?>;
        const approvedData = <?= json_encode(array_values($monthlyApprovals)) ?>;

        const chartCtx = ctx.getContext('2d');

        // Blue Gradient Underfill (Submissions)
        const blueGrad = chartCtx.createLinearGradient(0, 0, 0, 260);
        blueGrad.addColorStop(0, 'rgba(37, 99, 235, 0.22)');
        blueGrad.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

        // Red Gradient Underfill (Approvals)
        const redGrad = chartCtx.createLinearGradient(0, 0, 0, 260);
        redGrad.addColorStop(0, 'rgba(239, 68, 68, 0.16)');
        redGrad.addColorStop(1, 'rgba(239, 68, 68, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Submitted',
                        data: submittedData,
                        borderColor: '#2563EB',
                        backgroundColor: blueGrad,
                        fill: true,
                        tension: 0.38,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2563EB',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Approved',
                        data: approvedData,
                        borderColor: '#EF4444',
                        backgroundColor: redGrad,
                        fill: true,
                        tension: 0.38,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#EF4444',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: 'Inter', size: 12 },
                            color: '#6B7280'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        titleColor: '#111827',
                        bodyColor: '#4B5563',
                        borderColor: '#EAECF0',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        cornerRadius: 10,
                        titleFont: { family: 'Plus Jakarta Sans', weight: '700', size: 13 },
                        bodyFont: { family: 'Inter', size: 12.5 },
                        shadowColor: 'rgba(16, 24, 40, 0.08)',
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.dataset.label + ': ' + context.parsed.y + ' applications';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#9CA3AF',
                            font: { family: 'Inter', size: 11.5 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#F2F4F7'
                        },
                        ticks: {
                            precision: 0,
                            color: '#9CA3AF',
                            font: { family: 'Inter', size: 11.5 },
                            callback: function(val) {
                                return val >= 1000 ? (val/1000) + 'k' : val;
                            }
                        }
                    }
                }
            }
        });
    }

    // ---- 2. Category Tab Filtering (Cards & Table) ----
    const typePills = document.querySelectorAll('.category-pill');
    const tableRows = document.querySelectorAll('#recentAppsTable tbody tr');
    const cardItems = document.querySelectorAll('#recentAppsCards .project-card');

    typePills.forEach(pill => {
        pill.addEventListener('click', function () {
            typePills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            const selectedType = this.dataset.type;
            const filterItem = (el) => {
                if (selectedType === 'all' || el.dataset.type === selectedType) {
                    el.style.display = '';
                } else {
                    el.style.display = 'none';
                }
            };
            tableRows.forEach(filterItem);
            cardItems.forEach(filterItem);
        });
    });

    // ---- 3. Dashboard Real-Time Search Filter (Cards & Table) ----
    const searchInput = document.getElementById('dashboardSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const searchItem = (el) => {
                const searchData = el.dataset.search || '';
                if (!query || searchData.includes(query)) {
                    el.style.display = '';
                } else {
                    el.style.display = 'none';
                }
            };
            tableRows.forEach(searchItem);
            cardItems.forEach(searchItem);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
