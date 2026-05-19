<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';

$_pageTitle = 'Каталог услуг';
$services = DB::all('SELECT * FROM service ORDER BY name');
$isAdmin = Auth::hasRole('admin');

require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Услуги клиники</h1>
    <?php if ($isAdmin): ?>
        <a href="/admin/service_edit.php" class="btn btn-orange">+ Добавить услугу</a>
    <?php endif; ?>
</div>

<?php if (empty($services)): ?>
    <p class="text-muted">Пока нет ни одной услуги.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($services as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="clinic-card h-100">
                    <?php if (!empty($s['image_path'])): ?>
                        <img class="card-img-top" src="/<?= h($s['image_path']) ?>" alt="<?= h($s['name']) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= h($s['name']) ?></h5>
                        <p class="card-text flex-grow-1"><?= h($s['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="price-badge"><?= h(fmt_price($s['price'])) ?></span>
                            <?php if ($isAdmin): ?>
                                <span class="text-muted small">
                                    <a href="/admin/service_edit.php?id=<?= (int)$s['id'] ?>">редактировать</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
