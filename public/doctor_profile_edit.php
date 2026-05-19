<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/upload.php';

Auth::requireRole('doctor');
$user = Auth::user();
$uid  = (int) $user['id'];

$_pageTitle = 'Профиль врача';
$errors  = [];
$success = false;

$profile = DB::one('SELECT * FROM doctor_profile WHERE user_id = :id', ['id' => $uid]);
$form = [
    'specialization' => $profile['specialization'] ?? '',
    'bio'            => $profile['bio'] ?? '',
];
$currentPhoto = $profile['photo_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['specialization'] = trim((string) ($_POST['specialization'] ?? ''));
    $form['bio']            = trim((string) ($_POST['bio'] ?? ''));

    $newPhotoPath = $currentPhoto;

    if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = image_upload_validate($_FILES['photo']);
        if ($err !== null) {
            $errors['photo'] = $err;
        } else {
            $saved = image_save(
                $_FILES['photo'],
                __DIR__ . '/uploads/doctors',
                'uploads/doctors',
                'doctor_' . $uid
            );
            if ($saved === null) {
                $errors['photo'] = 'Не удалось сохранить файл.';
            } else {
                if (!empty($currentPhoto)) {
                    $old = __DIR__ . '/' . $currentPhoto;
                    if (is_file($old)) @unlink($old);
                }
                $newPhotoPath = $saved;
            }
        }
    }

    if (empty($errors)) {
        DB::exec(
            'INSERT INTO doctor_profile (user_id, specialization, bio, photo_path)
             VALUES (:id, :s, :b, :p)
             ON DUPLICATE KEY UPDATE specialization = VALUES(specialization),
                                     bio = VALUES(bio),
                                     photo_path = VALUES(photo_path)',
            ['id' => $uid, 's' => $form['specialization'], 'b' => $form['bio'], 'p' => $newPhotoPath]
        );
        $currentPhoto = $newPhotoPath;
        $success = true;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4">Мой профиль врача</h1>
            <p class="text-muted small">Эти данные видят посетители на странице «Врачи клиники».</p>

            <?php if ($success): ?>
                <div class="alert alert-success">Профиль сохранён.</div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <?php if (!empty($currentPhoto)): ?>
                    <img src="/<?= h($currentPhoto) ?>" alt="Фото" class="doctor-photo" onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="doctor-photo doctor-photo--placeholder mx-auto">
                        <?= h(mb_substr($user['last_name'], 0, 1) . mb_substr($user['first_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" novalidate>
                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label class="form-label">Специализация</label>
                    <input type="text" name="specialization" class="form-control" maxlength="150"
                           value="<?= h($form['specialization']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">О себе</label>
                    <textarea name="bio" class="form-control" rows="5"><?= h($form['bio']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Фотография (JPEG/PNG/WebP, до 2 МБ)</label>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           class="form-control<?= isset($errors['photo']) ? ' is-invalid' : '' ?>">
                    <?php if (isset($errors['photo'])): ?><div class="invalid-feedback"><?= h($errors['photo']) ?></div><?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-orange">Сохранить</button>
                    <a href="/doctors.php" class="btn btn-outline-orange">Посмотреть страницу «Врачи»</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
