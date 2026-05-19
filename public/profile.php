<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';

Auth::requireRole('admin', 'registrar', 'doctor', 'patient');
$user = Auth::user();

$_pageTitle = 'Личный кабинет';
require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4">Личный кабинет</h1>

            <dl class="row mb-4">
                <dt class="col-sm-4">ФИО</dt>
                <dd class="col-sm-8"><?= h($user['last_name'] . ' ' . $user['first_name'] . ' ' . $user['middle_name']) ?></dd>

                <dt class="col-sm-4">Email</dt>
                <dd class="col-sm-8"><?= h($user['email']) ?></dd>

                <dt class="col-sm-4">Роль</dt>
                <dd class="col-sm-8"><?= h($user['role_name']) ?></dd>
            </dl>

            <?php if (($user['role_code'] ?? '') === 'doctor'): ?>
                <a href="/doctor_profile_edit.php" class="btn btn-orange mb-3">Редактировать профиль врача</a>
            <?php endif; ?>

            <p class="text-muted small">
                Полноценный кабинет вашей роли появится в следующих обновлениях.
            </p>

            <form method="post" action="/logout.php" class="mt-3">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-orange">Выйти</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
