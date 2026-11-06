<?php
/**
 * index.php
 * -----------------------------------------------------------------
 * Public landing page. Logged-in users are sent straight to their
 * role dashboard; visitors see the hero, About, Services (pulled
 * live from application_types), audience cards, and Contact.
 *
 * CUSTOMIZING THE HERO PHOTO:
 * Drop your own photo in at assets/images/hero.jpg (recommended
 * ~1200x900px, landscape). If that file doesn't exist yet, a
 * placeholder pattern is shown instead so the page never looks
 * broken — just add the file whenever you're ready, no code changes
 * needed.
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Logged-in users normally get bounced straight to their dashboard here —
// EXCEPT when they deliberately clicked the logo/brand (sidebar or public
// nav), which links here with ?view=home to intentionally show the public
// landing page content without logging them out.
if (is_logged_in() && !isset($_GET['view'])) {
    redirect(role_home($_SESSION['role_name']));
}

// Application types, pulled live so this section never goes stale.
$serviceTypes = mysqli_query($conn, "SELECT type_name, description FROM application_types WHERE is_active = 1 ORDER BY type_name");

// Published announcements for the landing page
$landingAnnouncements = mysqli_query($conn, "
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a 
    JOIN users u ON u.user_id = a.posted_by 
    WHERE a.is_active = 1 
    ORDER BY a.posted_at DESC 
    LIMIT 6
");

$typeIcons = [
    'Patent' => '💡', 'Utility Model' => '⚙️', 'Industrial Design' => '🎨',
    'Trademark' => '™️', 'Copyright' => '📖',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZPPSU Intellectual Property Office</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        .landing-section { max-width: 1080px; margin: 0 auto; padding: 64px 32px; }
        .landing-eyebrow { font-family: var(--font-mono); letter-spacing:.08em; text-transform:uppercase; color: var(--color-primary-600); font-size:11.5px; font-weight:600; }
        .landing-h2 { font-size: 24px; margin: 6px 0 12px; }
        .landing-lead { color: var(--color-text-muted); font-size: 14px; max-width: 620px; line-height:1.7; }

        /* ---- Hero ---- */
        .hero { background: linear-gradient(180deg, var(--color-primary-900), var(--color-primary-700)); color:#fff; }
        .hero-inner { max-width: 1080px; margin: 0 auto; padding: 64px 32px; display:grid; grid-template-columns: 1.1fr 1fr; gap: 40px; align-items:center; }
        .hero-tag { font-family: var(--font-mono); letter-spacing:.08em; text-transform:uppercase; color: var(--color-gold); font-size:12px; }
        .hero-inner h1 { font-family: var(--font-heading); font-size: 32px; line-height:1.3; margin: 12px 0 16px; color: #fff; }
        .hero-inner p { color:#AFC2E3; font-size:14px; line-height:1.7; margin-bottom: 26px; max-width: 480px; }
        .hero-actions { display:flex; gap:14px; }

        .hero-image-frame {
            position: relative; border-radius: 16px; overflow:hidden; aspect-ratio: 4/3;
            border: 2px solid rgba(200,155,60,0.35); box-shadow: var(--shadow-md);
        }
        .hero-image-frame img { width:100%; height:100%; object-fit:cover; display:block; }
        .hero-image-frame.no-image {
            background:
                radial-gradient(circle at 30% 20%, rgba(200,155,60,0.25), transparent 55%),
                repeating-linear-gradient(135deg, rgba(255,255,255,0.05) 0 2px, transparent 2px 22px),
                linear-gradient(160deg, var(--color-primary-700), var(--color-primary-900));
            display:flex; align-items:center; justify-content:center; text-align:center; padding: 24px;
        }
        .hero-image-frame.no-image::after {
            content: "Add your own photo:\A assets/images/hero.jpg";
            white-space: pre-line;
            color:#AFC2E3; font-size:12.5px; font-family: var(--font-mono); line-height:1.6;
            border: 1px dashed rgba(200,155,60,0.5); padding: 18px 22px; border-radius: 10px;
        }

        /* ---- Audience cards ---- */
        .audience-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-top: 28px; }
        .audience-card { background:#fff; border:1px solid var(--color-border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow-sm); }
        .audience-card .emoji { font-size: 26px; margin-bottom: 10px; display:block; }
        .audience-card h3 { font-size: 15.5px; margin-bottom: 6px; }
        .audience-card p { color: var(--color-text-muted); font-size: 12.5px; line-height:1.6; margin-bottom: 14px; }

        /* ---- Services ---- */
        .services-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 26px; }
        .service-card { border:1px solid var(--color-border); border-radius: var(--radius); padding: 20px; background:#fff; }
        .service-card .emoji { font-size:22px; }
        .service-card h4 { font-size:14px; margin: 8px 0 4px; }
        .service-card p { font-size:12px; color:var(--color-text-muted); line-height:1.6; }

        /* ---- Steps ---- */
        .steps-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 26px; }

        /* ---- Announcements ---- */
        .announcements-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 28px; }
        .announcement-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            background: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        .announcement-img-wrap {
            width: 100%;
            height: 200px;
            background: #f1f5f9;
            overflow: hidden;
            position: relative;
        }
        .announcement-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .announcement-body {
            padding: 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .announcement-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: var(--color-text-muted);
            margin-bottom: 8px;
        }
        .announcement-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--color-text);
            margin: 0 0 10px;
            line-height: 1.4;
        }
        .announcement-text {
            font-size: 13px;
            color: var(--color-text-muted);
            line-height: 1.6;
            margin: 0;
            flex: 1;
        }

        /* ---- Contact ---- */
        .contact-section { background: var(--color-bg); }
        .contact-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 20px; }
        .contact-item { background:#fff; border:1px solid var(--color-border); border-radius: var(--radius); padding:18px; }
        .contact-item .label { font-size:11.5px; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:.05em; }
        .contact-item .value { font-size:14px; font-weight:600; color:var(--color-primary-900); margin-top:4px; }

        @media (max-width: 860px) {
            .hero-inner { grid-template-columns: 1fr; }
            .audience-grid, .steps-grid, .contact-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body style="margin:0;">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <!-- ================= HERO ================= -->
    <section class="hero">
        <div class="hero-inner">
            <div>
                <span class="hero-tag">Zamboanga Peninsula Polytechnic State University</span>
                <h1>Protecting the University's Intellectual Property, from idea to registration.</h1>
                <p>Submit patents, trademarks, copyrights, industrial designs, and utility models — and track every step of the review process online, from your first draft to final approval.</p>
                <div class="hero-actions">
                    <a href="<?= BASE_URL ?>register.php" class="btn btn-primary">Get Started</a>
                    <a href="<?= BASE_URL ?>login.php" class="btn btn-outline" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.3);">Log In</a>
                </div>
            </div>
            <div class="hero-image-frame" id="heroFrame">
                <img src="<?= BASE_URL ?>assets/images/hero.jpg" alt="ZPPSU Intellectual Property Office"
                     onerror="this.remove(); document.getElementById('heroFrame').classList.add('no-image');">
            </div>
        </div>
    </section>

    <!-- ================= ABOUT ================= -->
    <section class="landing-section" id="about">
        <span class="landing-eyebrow">About the Office</span>
        <h2 class="landing-h2">A single home for every IP application at ZPPSU</h2>
        <p class="landing-lead">The Intellectual Property Office manages the university's inventions, creative works, and brand assets — from first submission through verification, evaluation, and final approval — so researchers, faculty, and students always know exactly where their application stands.</p>

        <div id="mission" class="audience-grid" style="grid-template-columns:1fr 1fr;margin-top:24px;">
            <div class="audience-card">
                <span class="emoji">🎯</span>
                <h3>Our Mission</h3>
                <p>To safeguard and promote the intellectual creations of the ZPPSU community through efficient, transparent, and accessible IP registration services.</p>
            </div>
            <div class="audience-card">
                <span class="emoji">🔭</span>
                <h3>Our Vision</h3>
                <p>A university where every innovation, from classroom research to campus-wide inventions, is protected, valued, and given the chance to grow.</p>
            </div>
        </div>
    </section>

    <!-- ================= AUDIENCE CARDS ================= -->
    <section class="landing-section" style="padding-top:0;">
        <span class="landing-eyebrow">Who This Is For</span>
        <h2 class="landing-h2">Built for every kind of applicant</h2>
        <div class="audience-grid">
            <div class="audience-card">
                <span class="emoji">🎓</span>
                <h3>Students &amp; Researchers</h3>
                <p>Filing a thesis invention, a design, or a creative work? Track it from submission to approval without the paperwork runaround.</p>
                <a href="<?= BASE_URL ?>register.php" class="btn btn-outline btn-sm">Register as Student</a>
            </div>
            <div class="audience-card">
                <span class="emoji">🧑‍🏫</span>
                <h3>Faculty &amp; Staff</h3>
                <p>Protect research outputs, teaching materials, and institutional projects with a clear, auditable review process.</p>
                <a href="<?= BASE_URL ?>register.php" class="btn btn-outline btn-sm">Register as Faculty</a>
            </div>
            <div class="audience-card">
                <span class="emoji">🚀</span>
                <h3>External Innovators</h3>
                <p>Partnering with ZPPSU on a project or venture? Submit and monitor your application alongside university-based applicants.</p>
                <a href="<?= BASE_URL ?>register.php" class="btn btn-outline btn-sm">Register as External</a>
            </div>
        </div>
    </section>

    <!-- ================= SERVICES (live from DB) ================= -->
    <section class="landing-section" id="services" style="padding-top:0;">
        <span class="landing-eyebrow">Services</span>
        <h2 class="landing-h2">What you can register</h2>
        <p class="landing-lead">Every application type below goes through the same transparent pipeline: verification by IPO staff, evaluation by a subject examiner, and final approval by the office head.</p>

        <div class="services-grid">
            <?php if (mysqli_num_rows($serviceTypes) === 0): ?>
                <p style="color:var(--color-text-muted);font-size:13px;">Application types will appear here once configured by the Administrator.</p>
            <?php else: while ($t = mysqli_fetch_assoc($serviceTypes)): ?>
                <div class="service-card">
                    <span class="emoji"><?= $typeIcons[$t['type_name']] ?? '📄' ?></span>
                    <h4><?= clean($t['type_name']) ?></h4>
                    <p><?= clean($t['description'] ?: 'File and track this application type online.') ?></p>
                </div>
            <?php endwhile; endif; ?>
        </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="landing-section" style="padding-top:0;">
        <span class="landing-eyebrow">Process</span>
        <h2 class="landing-h2">From submission to approval</h2>
        <div class="steps-grid">
            <div class="card"><div class="card-body">
                <h3 style="font-size:15px;">1. Submit</h3>
                <p style="color:var(--color-text-muted);font-size:13px;">Create an account and file your IP application with supporting documents.</p>
            </div></div>
            <div class="card"><div class="card-body">
                <h3 style="font-size:15px;">2. Track</h3>
                <p style="color:var(--color-text-muted);font-size:13px;">Follow your application through verification, evaluation, and final approval.</p>
            </div></div>
            <div class="card"><div class="card-body">
                <h3 style="font-size:15px;">3. Get Approved</h3>
                <p style="color:var(--color-text-muted);font-size:13px;">Receive notifications at every stage and download your approval once granted.</p>
            </div></div>
        </div>
    </section>

    <!-- ================= ANNOUNCEMENTS & CAMPUS BROADCASTS ================= -->
    <section class="landing-section" id="announcements" style="background:#f8fafc;border-top:1px solid var(--color-border);border-bottom:1px solid var(--color-border);max-width:100%;padding:64px 32px;">
        <div style="max-width:1080px;margin:0 auto;">
            <span class="landing-eyebrow">Campus Broadcasts</span>
            <h2 class="landing-h2">Latest Announcements &amp; Updates</h2>
            <p class="landing-lead">Stay informed about university intellectual property workshops, filing deadlines, policy advisories, and commercialization programs.</p>

            <?php if (mysqli_num_rows($landingAnnouncements) === 0): ?>
                <div style="background:#fff;border:1px dashed var(--color-border);border-radius:var(--radius-lg);padding:36px;text-align:center;margin-top:24px;">
                    <p style="color:var(--color-text-muted);font-size:14px;margin:0;">No official announcements have been published at this time. Check back soon for university IP notices.</p>
                </div>
            <?php else: ?>
                <div class="announcements-grid">
                    <?php while ($ann = mysqli_fetch_assoc($landingAnnouncements)): ?>
                        <article class="announcement-card">
                            <?php if (!empty($ann['image_path'])): ?>
                                <div class="announcement-img-wrap">
                                    <img src="<?= BASE_URL . clean($ann['image_path']) ?>" alt="<?= clean($ann['title']) ?>" class="announcement-img">
                                </div>
                            <?php endif; ?>
                            <div class="announcement-body">
                                <div class="announcement-meta">
                                    <span style="font-family:var(--font-mono);font-size:11.5px;color:var(--color-primary-600);font-weight:600;">
                                        <?= format_date($ann['posted_at'], 'M d, Y') ?>
                                    </span>
                                    <span>&middot;</span>
                                    <span>Posted by <?= clean($ann['first_name'] . ' ' . $ann['last_name']) ?></span>
                                </div>
                                <h3 class="announcement-title"><?= clean($ann['title']) ?></h3>
                                <div class="announcement-text">
                                    <?= nl2br(clean($ann['content'])) ?>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ================= CONTACT ================= -->
    <section class="landing-section contact-section" id="contact">
        <span class="landing-eyebrow">Contact</span>
        <h2 class="landing-h2">Get in touch with the IPO</h2>
        <p class="landing-lead">Have a question before you register? Reach the office directly.</p>
        <div class="contact-grid">
            <div class="contact-item"><div class="label">Email</div><div class="value">ipo@zppsu.edu.ph</div></div>
            <div class="contact-item"><div class="label">Office Hours</div><div class="value">Monday–Friday, 8:00 AM–5:00 PM</div></div>
        </div>
    </section>

    <footer style="text-align:center;padding:24px;color:var(--color-text-muted);font-size:12px;">
        &copy; <?= date('Y') ?> ZPPSU Intellectual Property Office. All rights reserved.
    </footer>
</body>
</html>
