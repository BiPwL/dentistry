<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/auth.php';

$_pageTitle = 'Блог';
$articles = DB::all('SELECT * FROM blog_article ORDER BY created_at DESC');
$isAdmin = Auth::hasRole('admin');

require __DIR__ . '/../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Блог</h1>
    <?php if ($isAdmin): ?>
        <a href="/admin/article_edit.php" class="btn btn-orange">+ Новая статья</a>
    <?php endif; ?>
</div>

<?php if (empty($articles)): ?>
    <p class="text-muted">Пока нет ни одной статьи.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($articles as $a): ?>
            <div class="col-md-6">
                <div class="clinic-card">
                    <?php if (!empty($a['image_path'])): ?>
                        <img class="card-img-top" src="/<?= h($a['image_path']) ?>" alt="<?= h($a['title']) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= h($a['title']) ?></h5>
                        <p class="card-text"><?= h($a['body']) ?></p>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><?= h(date('d.m.Y', strtotime($a['created_at']))) ?></span>
                            <?php if ($isAdmin): ?>
                                <span>
                                    <a href="/admin/article_edit.php?id=<?= (int)$a['id'] ?>">редактировать</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
