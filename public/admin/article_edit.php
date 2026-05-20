<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/upload.php';

Auth::requireRole('admin');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$article = $id > 0 ? DB::one("SELECT * FROM blog_article WHERE id = :id", ['id' => $id]) : null;
if ($id > 0 && $article === null) { http_response_code(404); header('Location: /blog.php'); exit; }

$_pageTitle = $article ? 'Редактирование статьи' : 'Новая статья';
$errors = [];
$form = ['title' => $article['title'] ?? '', 'body' => $article['body'] ?? ''];
$photo = $article['image_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['body']  = trim((string) ($_POST['body'] ?? ''));
    if ($form['title'] === '') $errors['title'] = 'Введите заголовок.';
    if ($form['body'] === '')  $errors['body'] = 'Введите текст статьи.';

    $newPhoto = $photo;
    if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = image_upload_validate($_FILES['image']);
        if ($err !== null) $errors['image'] = $err;
        else {
            $saved = image_save($_FILES['image'], __DIR__ . '/../uploads/blog', 'uploads/blog', 'article');
            if ($saved === null) $errors['image'] = 'Не удалось сохранить файл.';
            else { if (!empty($photo) && is_file(__DIR__ . '/../' . $photo)) @unlink(__DIR__ . '/../' . $photo); $newPhoto = $saved; }
        }
    }

    if (empty($errors)) {
        if ($article) {
            DB::exec("UPDATE blog_article SET title=:t, body=:b, image_path=:i WHERE id=:id",
                ['t'=>$form['title'],'b'=>$form['body'],'i'=>$newPhoto,'id'=>$id]);
        } else {
            DB::exec("INSERT INTO blog_article (title, body, image_path, author_id) VALUES (:t,:b,:i,:a)",
                ['t'=>$form['title'],'b'=>$form['body'],'i'=>$newPhoto,'a'=>(int)Auth::user()['id']]);
        }
        header('Location: /blog.php');
        exit;
    }
}

require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4"><?= h($_pageTitle) ?></h1>
            <form method="post" enctype="multipart/form-data" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <div class="mb-3">
                    <label class="form-label">Заголовок</label>
                    <input type="text" name="title" class="form-control<?= isset($errors['title'])?' is-invalid':'' ?>" value="<?= h($form['title']) ?>">
                    <?php if(isset($errors['title'])): ?><div class="invalid-feedback"><?= h($errors['title']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Текст</label>
                    <textarea name="body" rows="6" class="form-control<?= isset($errors['body'])?' is-invalid':'' ?>"><?= h($form['body']) ?></textarea>
                    <?php if(isset($errors['body'])): ?><div class="invalid-feedback"><?= h($errors['body']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Изображение (JPEG/PNG/WebP, до 2 МБ)</label>
                    <?php if(!empty($photo)): ?><div class="mb-2"><img src="/<?= h($photo) ?>" alt="" style="max-height:90px;border-radius:8px;" onerror="this.style.display='none'"></div><?php endif; ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control<?= isset($errors['image'])?' is-invalid':'' ?>">
                    <?php if(isset($errors['image'])): ?><div class="invalid-feedback"><?= h($errors['image']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-orange">Сохранить</button>
                    <a href="/blog.php" class="btn btn-outline-orange">Отмена</a>
                    <?php if($article): ?><button type="submit" form="delForm" class="btn btn-outline-danger ms-auto">Удалить</button><?php endif; ?>
                </div>
            </form>
            <?php if($article): ?>
                <form id="delForm" method="post" action="/admin/article_delete.php" onsubmit="return confirm('Удалить статью?');">
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
