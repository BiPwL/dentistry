<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/upload.php';

Auth::requireRole('admin');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$service = $id > 0 ? DB::one("SELECT * FROM service WHERE id = :id", ['id' => $id]) : null;
if ($id > 0 && $service === null) { http_response_code(404); header('Location: /services.php'); exit; }

$_pageTitle = $service ? 'Редактирование услуги' : 'Новая услуга';
$errors = [];
$form = [
    'name'        => $service['name'] ?? '',
    'description' => $service['description'] ?? '',
    'price'       => $service['price'] ?? '',
];
$photo = $service['image_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['name']        = trim((string) ($_POST['name'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));
    $form['price']       = trim((string) ($_POST['price'] ?? ''));

    if ($form['name'] === '') $errors['name'] = 'Введите название.';
    if (!is_numeric($form['price']) || (float) $form['price'] < 0) $errors['price'] = 'Введите корректную цену.';

    $newPhoto = $photo;
    if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = image_upload_validate($_FILES['image']);
        if ($err !== null) $errors['image'] = $err;
        else {
            $saved = image_save($_FILES['image'], __DIR__ . '/../uploads/services', 'uploads/services', 'service');
            if ($saved === null) $errors['image'] = 'Не удалось сохранить файл.';
            else { if (!empty($photo) && is_file(__DIR__ . '/../' . $photo)) @unlink(__DIR__ . '/../' . $photo); $newPhoto = $saved; }
        }
    }

    if (empty($errors)) {
        if ($service) {
            DB::exec("UPDATE service SET name=:n, description=:d, price=:p, image_path=:i WHERE id=:id",
                ['n'=>$form['name'],'d'=>$form['description'],'p'=>(float)$form['price'],'i'=>$newPhoto,'id'=>$id]);
        } else {
            DB::exec("INSERT INTO service (name, description, price, image_path) VALUES (:n,:d,:p,:i)",
                ['n'=>$form['name'],'d'=>$form['description'],'p'=>(float)$form['price'],'i'=>$newPhoto]);
        }
        header('Location: /services.php');
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
                    <label class="form-label">Название</label>
                    <input type="text" name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= h($form['name']) ?>">
                    <?php if(isset($errors['name'])): ?><div class="invalid-feedback"><?= h($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Описание</label>
                    <textarea name="description" rows="4" class="form-control"><?= h($form['description']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Цена, ₽</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control<?= isset($errors['price'])?' is-invalid':'' ?>" value="<?= h((string)$form['price']) ?>">
                    <?php if(isset($errors['price'])): ?><div class="invalid-feedback"><?= h($errors['price']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Изображение (JPEG/PNG/WebP, до 2 МБ)</label>
                    <?php if(!empty($photo)): ?><div class="mb-2"><img src="/<?= h($photo) ?>" alt="" style="max-height:90px;border-radius:8px;" onerror="this.style.display='none'"></div><?php endif; ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control<?= isset($errors['image'])?' is-invalid':'' ?>">
                    <?php if(isset($errors['image'])): ?><div class="invalid-feedback"><?= h($errors['image']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-orange">Сохранить</button>
                    <a href="/services.php" class="btn btn-outline-orange">Отмена</a>
                    <?php if($service): ?>
                        <button type="submit" form="delForm" class="btn btn-outline-danger ms-auto">Удалить</button>
                    <?php endif; ?>
                </div>
            </form>
            <?php if($service): ?>
                <form id="delForm" method="post" action="/admin/service_delete.php" onsubmit="return confirm('Удалить услугу?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
