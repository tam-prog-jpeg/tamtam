<?php
/**
 * includes/navbar.php
 * -----------------------------------------------------------------
 * Public navigation bar for pages OUTSIDE the authenticated dashboard
 * shell: index.php, login.php, register.php. Not used inside
 * admin/staff/evaluator/manager/applicant folders (they use topbar.php).
 *
 * Includes dropdown menus (About, Services, Contact). The Services
 * dropdown is populated LIVE from the application_types table, so
 * adding a new IP type in the database automatically shows up here.
 * -----------------------------------------------------------------
 */
$navServiceTypes = [];
if (isset($conn)) {
    $navTypesResult = mysqli_query($conn, "SELECT type_name FROM application_types WHERE is_active = 1 ORDER BY type_name");
    if ($navTypesResult) {
        while ($t = mysqli_fetch_assoc($navTypesResult)) { $navServiceTypes[] = $t['type_name']; }
    }
}

// If the viewer is already logged in, every link back to index.php needs
// ?view=home so it shows the public page instead of bouncing them to
// their dashboard (see the bypass check at the top of index.php).
$homeLink = BASE_URL . 'index.php' . (is_logged_in() ? '?view=home' : '');
$homeAnchor = fn($hash) => $homeLink . '#' . $hash; // browsers handle "?view=home#about" fine
?>
<nav class="public-nav">
    <a href="<?= $homeLink ?>" class="public-nav-brand">
        <div class="public-nav-seal">IPO</div>
        <span>ZPPSU Intellectual Property Office</span>
    </a>

    <ul class="public-nav-menu">
        <li><a href="<?= $homeLink ?>">Home</a></li>
        <li class="has-dropdown">
            <a href="#about">About <span class="caret">▾</span></a>
            <div class="public-dropdown">
                <a href="<?= $homeAnchor('about') ?>">About the Office</a>
                <a href="<?= $homeAnchor('mission') ?>">Mission &amp; Vision</a>
            </div>
        </li>
        <li class="has-dropdown">
            <a href="#services">Services <span class="caret">▾</span></a>
            <div class="public-dropdown">
                <?php if (empty($navServiceTypes)): ?>
                    <a href="<?= $homeAnchor('services') ?>">IP Application Types</a>
                <?php else: foreach ($navServiceTypes as $typeName): ?>
                    <a href="<?= BASE_URL ?>register.php"><?= clean($typeName) ?></a>
                <?php endforeach; endif; ?>
            </div>
        </li>
        <li><a href="<?= $homeAnchor('contact') ?>">Contact</a></li>
    </ul>

    <div class="public-nav-actions">
        <?php if (is_logged_in()): ?>
            <a href="<?= BASE_URL . role_home($_SESSION['role_name']) ?>" class="public-nav-register">Go to Dashboard</a>
            <a href="<?= BASE_URL ?>logout.php" class="public-nav-login">Log Out</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php" class="public-nav-login">Log in</a>
            <a href="<?= BASE_URL ?>register.php" class="public-nav-register">Register</a>
        <?php endif; ?>
        <button class="public-nav-toggle" id="publicNavToggle" aria-label="Toggle menu">☰</button>
    </div>
</nav>

<style>
    .public-nav { display:flex; align-items:center; justify-content:space-between; padding:14px 32px; background:var(--color-primary-900); position:relative; z-index:60; }
    .public-nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
    .public-nav-seal { width:36px; height:36px; border-radius:50%; border:2px solid var(--color-gold); display:flex; align-items:center; justify-content:center; color:var(--color-gold); font-family:var(--font-heading); font-weight:700; font-size:12px; flex-shrink:0; }
    .public-nav-brand span { color:#fff; font-family:var(--font-heading); font-weight:600; font-size:15px; }

    .public-nav-menu { list-style:none; display:flex; gap:6px; margin:0; padding:0; }
    .public-nav-menu > li { position:relative; }
    .public-nav-menu > li > a { display:block; padding:10px 14px; color:#DCE6F7; font-size:13.5px; font-weight:500; text-decoration:none; border-radius:6px; }
    .public-nav-menu > li > a:hover { background:rgba(255,255,255,0.08); text-decoration:none; }
    .public-nav-menu .caret { font-size:10px; opacity:.8; }

    .public-dropdown { display:none; position:absolute; top:calc(100% + 4px); left:0; background:#fff; border-radius:8px; box-shadow:var(--shadow-md); min-width:200px; padding:6px; }
    .public-dropdown a { display:block; padding:9px 12px; font-size:13px; color:var(--color-text); border-radius:6px; text-decoration:none; }
    .public-dropdown a:hover { background:var(--color-bg); text-decoration:none; }
    .has-dropdown:hover .public-dropdown, .has-dropdown.open .public-dropdown { display:block; }

    .public-nav-actions { display:flex; align-items:center; gap:14px; }
    .public-nav-login { color:#DCE6F7; font-size:13.5px; text-decoration:none; }
    .public-nav-register { color:var(--color-gold); font-size:13.5px; font-weight:600; text-decoration:none; }
    .public-nav-toggle { display:none; background:none; border:none; color:#fff; font-size:18px; cursor:pointer; }

    @media (max-width: 860px) {
        .public-nav-menu { display:none; }
        .public-nav-toggle { display:inline-block; }
        .public-nav-menu.mobile-open {
            display:flex; flex-direction:column; position:absolute; top:100%; left:0; right:0;
            background:var(--color-primary-900); padding:10px; gap:2px; box-shadow:var(--shadow-md);
        }
        .public-dropdown { position:static; box-shadow:none; background:rgba(255,255,255,0.06); margin:4px 0 8px; }
        .public-dropdown a { color:#DCE6F7; }
        .public-dropdown a:hover { background:rgba(255,255,255,0.1); }
        .has-dropdown .public-dropdown { display:block; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('publicNavToggle');
        const menu = document.querySelector('.public-nav-menu');
        if (toggle && menu) {
            toggle.addEventListener('click', () => menu.classList.toggle('mobile-open'));
        }
        // Tap-to-open dropdowns on touch devices (hover doesn't apply there).
        document.querySelectorAll('.has-dropdown > a').forEach((link) => {
            link.addEventListener('click', function (e) {
                if (window.matchMedia('(max-width: 860px)').matches) return; // let mobile menu handle it inline
                if (this.getAttribute('href') === '#about' || this.getAttribute('href') === '#services') {
                    e.preventDefault();
                    this.closest('.has-dropdown').classList.toggle('open');
                }
            });
        });
    });
</script>
