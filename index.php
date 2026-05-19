<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Главная';
$services = DB::all('SELECT * FROM service ORDER BY id LIMIT 3');

require __DIR__ . '/templates/header.php';
?>

<section class="clinic-hero">
    <h1 class="display-5 fw-bold mb-3">Заботимся о вашей улыбке</h1>
    <p class="lead mb-4">Современная стоматология с индивидуальным подходом. Профилактика, лечение и эстетика — под одной крышей.</p>
    <a href="/services.php" class="btn btn-orange btn-lg">Посмотреть услуги</a>
    <a href="/contacts.php" class="btn btn-outline-orange btn-lg ms-2">Как нас найти</a>
</section>

<h2 class="mb-4">Популярные услуги</h2>
<div class="row g-4">
    <?php foreach ($services as $s): ?>
        <div class="col-md-4">
            <div class="clinic-card">
                <?php if (!empty($s['image_path'])): ?>
                    <img class="card-img-top" src="/<?= h($s['image_path']) ?>" alt="<?= h($s['name']) ?>" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?= h($s['name']) ?></h5>
                    <p class="card-text small"><?= h(mb_strimwidth($s['description'], 0, 120, '…')) ?></p>
                    <span class="price-badge"><?= h(fmt_price($s['price'])) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="text-center mt-4">
    <a href="/services.php" class="btn btn-outline-orange">Весь каталог</a>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
