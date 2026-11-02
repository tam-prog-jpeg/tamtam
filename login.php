<?php
/**
 * login.php
 * -----------------------------------------------------------------
 * Official Authentication Gateway — ZPPSU Intellectual Property Office
 * 
 * Supports authorized institutional logins for:
 *  - Applicants (Faculty, Students, Researchers, External Inventors)
 *  - Staff
 *  - Evaluators
 *  - Managers
 *  - Administrators
 * 
 * Submits securely to login_process.php with CSRF protection.
 * Redirects already-authenticated users directly to their dashboard.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(role_home($_SESSION['role_name']));
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · ZPPSU Intellectual Property Office</title>

    <!-- Theme initialization (prevents FOUC) -->
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css?v=<?= @filemtime(__DIR__ . '/assets/css/auth.css') ?: time() ?>">

    <!-- Lucide Outline Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-body">

    <!-- 1. Top Navigation & Theme Utilities Bar -->
    <header class="auth-topbar">
        <a href="<?= BASE_URL ?>index.php" class="auth-nav-link" title="Return to public portal homepage">
            <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
            <span>ZPPSU IPO Homepage</span>
        </a>
        <button type="button" class="auth-theme-toggle" id="themeToggleBtn" aria-label="Toggle visual theme">
            <i data-lucide="moon" id="themeIcon" style="width:14px;height:14px;"></i>
            <span id="themeText">Theme</span>
        </button>
    </header>

    <!-- 2. Central Authentication Gateway -->
    <main class="auth-container">
        <!-- University Header & Crest -->
        <div class="auth-header">
            <div class="auth-seal">
                <i data-lucide="shield-check" style="width:28px;height:28px;stroke-width:1.8;"></i>
            </div>
            <div class="university-title">Zamboanga Peninsula Polytechnic State University</div>
            <h1>Intellectual Property Office</h1>
            <p>Institutional Sign In &amp; Evaluation Gateway</p>
        </div>

        <!-- Authentication Card -->
        <div class="auth-card">
            <!-- Flash Status / Error Messages -->
            <?php if ($flash): ?>
                <div class="auth-alert auth-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="alert">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : 'alert-circle' ?>"></i>
                    <div><?= clean($flash['message']) ?></div>
                </div>
            <?php endif; ?>

            <!-- Credentials Form -->
            <form action="login_process.php" method="POST" class="auth-form" novalidate id="loginForm">
                <?php csrf_field(); ?>

                <!-- Username or Email Field -->
                <div class="form-group">
                    <label for="username">Username or University Email</label>
                    <div class="auth-input-wrap">
                        <input 
                            class="form-control" 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="e.g. username or name@zppsu.edu.ph" 
                            required 
                            autofocus 
                            autocomplete="username"
                        >
                    </div>
                </div>

                <!-- Password Field with Show/Hide Toggle -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <input 
                            class="form-control" 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your account password" 
                            required 
                            autocomplete="current-password"
                        >
                        <button 
                            type="button" 
                            class="toggle-btn" 
                            id="togglePasswordBtn" 
                            aria-label="Toggle password visibility" 
                            title="Show/hide password"
                        >
                            <i data-lucide="eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Assistance Row -->
                <div class="auth-options">
                    <label class="auth-remember" for="rememberMe">
                        <input type="checkbox" name="remember" id="rememberMe" value="1">
                        <span>Remember this device</span>
                    </label>
                    <a href="javascript:void(0)" class="auth-link" onclick="openHelpModal()">
                        Forgot password?
                    </a>
                </div>

                <!-- Action Submit Button -->
                <button type="submit" class="btn btn-primary auth-submit-btn" id="loginSubmitBtn">
                    Sign In
                </button>
            </form>

            <!-- Registration Link -->
            <div class="auth-card-footer">
                New applicant or inventor? 
                <a href="<?= BASE_URL ?>register.php">Create an applicant account</a>
            </div>
        </div>

        <!-- Audience Scope Statement -->
        <div class="auth-constituents-note">
            Authorized sign in for University Inventors, Evaluators, Campus Staff, and System Administrators.
        </div>
    </main>

    <!-- 3. Official University & Compliance Footer -->
    <footer class="auth-footer">
        <div>Zamboanga Peninsula Polytechnic State University · Intellectual Property Office</div>
        <div style="margin-top:4px;">
            In compliance with Republic Act No. 8293 (Intellectual Property Code) &amp; RA 10173 (Data Privacy Act).
        </div>
        <div style="margin-top:4px;">
            Technical Support: <a href="mailto:ipo@zppsu.edu.ph">ipo@zppsu.edu.ph</a> · R.T. Lim Boulevard, Zamboanga City
        </div>
    </footer>

    <!-- 4. Assistance Modal (Account & Recovery Guidance) -->
    <div id="helpModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(3px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
        <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);max-width:440px;width:100%;padding:28px;box-shadow:var(--shadow-xl);box-sizing:border-box;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(37,99,235,0.1);color:var(--color-primary-600);display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="help-circle" style="width:20px;height:20px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:16px;color:var(--color-text);">Account Recovery Assistance</h3>
                    <div style="font-size:12px;color:var(--color-text-muted);">ZPPSU Institutional Password Policy</div>
                </div>
            </div>

            <div style="font-size:13px;color:var(--color-text-muted);line-height:1.6;margin-bottom:20px;">
                <p style="margin:0 0 10px;">
                    Under university information security guidelines, credentials for <strong>Faculty</strong>, <strong>Staff</strong>, <strong>Evaluators</strong>, and <strong>Administrators</strong> are managed directly by the University IPO Office.
                </p>
                <p style="margin:0;">
                    If you have forgotten your password or need your account unlocked, please coordinate with the <strong>IPO System Administrator</strong> or email <code style="font-family:var(--font-mono);font-size:12px;color:var(--color-primary-600);">ipo@zppsu.edu.ph</code> with your official ID.
                </p>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeHelpModal()">
                    Understood
                </button>
            </div>
        </div>
    </div>

    <!-- 5. Client Scripts -->
    <script>
        // Render Lucide icons
        lucide.createIcons();

        // 1. Password Visibility Toggle
        var passwordInput = document.getElementById('password');
        var togglePasswordBtn = document.getElementById('togglePasswordBtn');
        var togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePasswordIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                lucide.createIcons();
            });
        }

        // 2. Theme Toggle Controller
        var themeToggleBtn = document.getElementById('themeToggleBtn');
        var themeIcon = document.getElementById('themeIcon');
        var themeText = document.getElementById('themeText');

        function updateThemeIcon() {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (themeIcon) {
                themeIcon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
                lucide.createIcons();
            }
            if (themeText) {
                themeText.textContent = isDark ? 'Light' : 'Dark';
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

        // 3. Assistance Modal Handlers
        var helpModal = document.getElementById('helpModal');
        function openHelpModal() {
            if (helpModal) helpModal.style.display = 'flex';
        }
        function closeHelpModal() {
            if (helpModal) helpModal.style.display = 'none';
        }
        if (helpModal) {
            helpModal.addEventListener('click', function(e) {
                if (e.target === helpModal) closeHelpModal();
            });
        }
    </script>
</body>
</html>
