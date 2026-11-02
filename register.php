<?php
/**
 * register.php
 * -----------------------------------------------------------------
 * Public self-registration form. Only creates "Applicant" accounts —
 * Staff/Evaluator/Manager/Administrator accounts are created by an
 * Administrator via admin/users.php, never through this public form.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(role_home($_SESSION['role_name']));
}

$flash = get_flash();

// Load colleges + departments to power the cascading dropdowns.
$colleges = mysqli_query($conn, "SELECT college_id, college_name FROM colleges ORDER BY college_name");
$departments = mysqli_query($conn, "SELECT department_id, college_id, department_name FROM departments ORDER BY department_name");
$departmentsList = [];
while ($d = mysqli_fetch_assoc($departments)) { $departmentsList[] = $d; }

// Re-populate the form after a failed submission (old input passed via session).
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register · ZPPSU Intellectual Property Office</title>

    <!-- Theme initialization -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- Google Fonts: Plus Jakarta Sans, Inter, IBM Plex Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css?v=<?= @filemtime(__DIR__ . '/assets/css/auth.css') ?: time() ?>">
    <style>.auth-shell{max-width:780px;} .auth-panel{padding:40px 44px;}</style>

    <!-- Lucide Outline Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-body">
    <!-- Top Navigation & Theme Utilities Bar -->
    <header class="auth-topbar" style="max-width:760px;">
        <a href="<?= BASE_URL ?>index.php" class="auth-nav-link" title="Return to public portal homepage">
            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
            <span>ZPPSU IPO Homepage</span>
        </a>
        <button type="button" class="auth-theme-toggle" id="themeToggleBtn" aria-label="Toggle visual theme">
            <i data-lucide="moon" id="themeIcon" style="width:14px;height:14px;"></i>
            <span id="themeText">Theme</span>
        </button>
    </header>

    <!-- Central Registration Container -->
    <main class="auth-container wide">
        <div class="auth-header">
            <div class="auth-seal">
                <i data-lucide="user-plus" style="width:26px;height:26px;stroke-width:1.8;"></i>
            </div>
            <div class="university-title">Zamboanga Peninsula Polytechnic State University</div>
            <h1>Create Applicant Account</h1>
            <p>Register to submit, track, and monitor institutional IP applications</p>
        </div>

        <div class="auth-card wide">
            <?php if ($flash): ?>
                <div class="auth-alert auth-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="alert">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : 'alert-circle' ?>"></i>
                    <div><?= clean($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <form action="register_process.php" method="POST" novalidate>
                <?php csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input class="form-control" type="text" id="first_name" name="first_name" value="<?= clean($old['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input class="form-control" type="text" id="last_name" name="last_name" value="<?= clean($old['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input class="form-control" type="text" id="username" name="username" value="<?= clean($old['username'] ?? '') ?>" required minlength="4">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= clean($old['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input class="form-control" type="password" id="password" name="password" required minlength="8">
                        <div class="form-hint">Minimum 8 characters.</div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input class="form-control" type="text" id="contact_number" name="contact_number" value="<?= clean($old['contact_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="applicant_type">Applicant Type</label>
                        <select class="form-control" id="applicant_type" name="applicant_type" required>
                            <?php foreach (['Student', 'Faculty', 'Staff', 'External'] as $type): ?>
                                <option value="<?= $type ?>" <?= ($old['applicant_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="college_id">College</label>
                        <select class="form-control" id="college_id" name="college_id">
                            <option value="">— Not applicable —</option>
                            <?php while ($c = mysqli_fetch_assoc($colleges)): ?>
                                <option value="<?= $c['college_id'] ?>" <?= (int)($old['college_id'] ?? 0) === (int)$c['college_id'] ? 'selected' : '' ?>><?= clean($c['college_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select class="form-control" id="department_id" name="department_id">
                            <option value="">— Select college first —</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input class="form-control" type="text" id="address" name="address" value="<?= clean($old['address'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;height:44px;font-size:14.5px;">
                    <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
                    Create Applicant Account
                </button>
            </form>
            <div class="auth-card-footer">Already have an account? <a href="<?= BASE_URL ?>login.php">Sign in here</a></div>
        </div>
    </main>

    <footer class="auth-footer" style="max-width:760px;">
        <div>Zamboanga Peninsula Polytechnic State University · Intellectual Property Office</div>
        <div style="margin-top:4px;">
            In compliance with Republic Act No. 8293 (Intellectual Property Code) &amp; RA 10173 (Data Privacy Act).
        </div>
    </footer>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Theme toggle controller
        var themeToggleBtn = document.getElementById('themeToggleBtn');
        var themeIcon = document.getElementById('themeIcon');
        function updateThemeIcon() {
            var currentTheme = document.documentElement.getAttribute('data-theme');
            if (themeIcon) {
                themeIcon.setAttribute('data-lucide', currentTheme === 'dark' ? 'sun' : 'moon');
                lucide.createIcons();
            }
        }
        updateThemeIcon();

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                var current = document.documentElement.getAttribute('data-theme');
                var next = current === 'dark' ? 'light' : 'dark';
                if (next === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                }
                updateThemeIcon();
            });
        }

        // Cascading college -> department dropdown (client-side filter).
        const ALL_DEPARTMENTS = <?= json_encode($departmentsList) ?>;
        const collegeSelect = document.getElementById('college_id');
        const deptSelect = document.getElementById('department_id');
        const oldDept = <?= json_encode((int)($old['department_id'] ?? 0)) ?>;

        function renderDepartments() {
            const collegeId = parseInt(collegeSelect.value || 0, 10);
            deptSelect.innerHTML = '';
            const matches = ALL_DEPARTMENTS.filter(d => parseInt(d.college_id, 10) === collegeId);
            if (!collegeId || matches.length === 0) {
                deptSelect.innerHTML = '<option value="">— Not applicable —</option>';
                return;
            }
            deptSelect.innerHTML = '<option value="">— Select department —</option>' +
                matches.map(d => `<option value="${d.department_id}" ${parseInt(d.department_id,10)===oldDept ? 'selected' : ''}>${d.department_name}</option>`).join('');
        }
        collegeSelect.addEventListener('change', renderDepartments);
        renderDepartments();
    </script>
</body>
</html>
