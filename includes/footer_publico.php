</main>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<script>
  window.FLASH = <?= json_encode($f, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
<script src="assets/js/app.js?v=5"></script>
</body>
</html>
