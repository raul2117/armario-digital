  </main>
</div>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<script type="application/json" id="flash"><?= json_encode($f, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js?v=<?= SWAL_VERSION ?>"></script>
<script src="assets/js/app.js?v=6"></script>
</body>
</html>
