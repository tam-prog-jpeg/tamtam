<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------
 * Opens the HTML document + the authenticated dashboard shell
 * (sidebar + topbar + page-body). Pair with includes/footer.php.
 * -----------------------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle ?? 'Dashboard') ?> · ZPPSU IPO Management</title>
    
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Core Design System Stylesheet (with auto cache-busting) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
    <?php if (!empty($extraStyles)): foreach ($extraStyles as $style): ?>
    <link rel="stylesheet" href="<?= BASE_URL . $style ?>?v=<?= @filemtime(__DIR__ . '/../' . $style) ?: time() ?>">
    <?php endforeach; endif; ?>

    <!-- Lucide Icons Library (Clean outline icons matching reference design) -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="app-shell">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/topbar.php'; ?>
        <div class="page-body">
