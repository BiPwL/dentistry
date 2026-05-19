</div>
</main>
<footer class="clinic-footer py-3 mt-4">
    <div class="container d-flex flex-column flex-md-row justify-content-between text-muted small">
        <div>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?></div>
        <div><?= h(CLINIC_PHONE) ?> · <?= h(CLINIC_EMAIL) ?></div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
<?php foreach (($_pageScripts ?? []) as $_src): ?>
<script src="<?= h($_src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
