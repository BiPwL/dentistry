<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Вход';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="clinic-card p-4 text-center">
            <h1 class="h3 mb-3">Вход</h1>
            <p class="text-muted">Форма входа появится в ближайшем обновлении.</p>
            <a href="/" class="btn btn-orange mt-2">На главную</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
