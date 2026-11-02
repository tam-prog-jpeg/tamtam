<?php
/**
 * includes/footer.php
 * -----------------------------------------------------------------
 * Closes the .page-body / .main-content / .app-shell divs opened by
 * each dashboard page, prints the queued flash message as JS so
 * main.js can render it as a toast, and loads the shared script.
 * -----------------------------------------------------------------
 */
$flash = get_flash();
?>
        </div><!-- /.page-body -->
    </div><!-- /.main-content -->
</div><!-- /.app-shell -->

<?php if ($flash): ?>
<script>
    window.FLASH = <?= json_encode($flash) ?>;
</script>
<?php endif; ?>
<script src="<?= BASE_URL ?>assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../assets/js/main.js') ?: time() ?>"></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $script): ?>
<script src="<?= BASE_URL . $script ?>?v=<?= @filemtime(__DIR__ . '/../' . $script) ?: time() ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
