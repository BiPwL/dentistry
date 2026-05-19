<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/password.php';
require_once __DIR__ . '/../lib/email_code.php';

if (Auth::isAuthenticated()) {
    header('Location: /profile.php');
    exit;
}

$_pageTitle = 'Новый пароль';
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $code            = trim((string) ($_POST['code'] ?? ''));
    $password        =        (string) ($_POST['password'] ?? '');
    $passwordConfirm =        (string) ($_POST['password_confirm'] ?? '');

    if ($email === '') $errors['email'] = 'Не указан email.';
    if ($code === '')  $errors['code'] = 'Введите код.';
    if ($password === '') {
        $errors['password'] = 'Введите новый пароль.';
    } elseif (password_strength($password) < PASSWORD_MIN_SCORE) {
        $errors['password'] = 'Слишком слабый пароль.';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Пароли не совпадают.';
    }

    if (empty($errors)) {
        $payload = EmailCode::consume($email, 'reset', $code);
        if ($payload === null) {
            $errors['code'] = 'Код неверный или истёк.';
        } else {
            $user = DB::one('SELECT id FROM user WHERE email = :e', ['e' => $email]);
            if ($user === null) {
                $errors['email'] = 'Пользователь не найден.';
            } else {
                DB::exec(
                    'UPDATE user SET password_hash = :ph WHERE id = :id',
                    ['ph' => password_make_hash($password), 'id' => $user['id']]
                );
                Session::login((int) $user['id']);
                header('Location: /profile.php');
                exit;
            }
        }
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4 text-center">Новый пароль</h1>

            <p class="text-muted small">
                Введите код из письма и новый пароль. Код действителен <?= (int) EMAIL_CODE_TTL_MIN ?> минут.
            </p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?= h($err) ?></div>
            <?php endforeach; ?>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="email" value="<?= h($email) ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?= h($email) ?>" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Код</label>
                    <input type="text" name="code" maxlength="6" pattern="\d{6}" class="form-control text-center" required autofocus inputmode="numeric">
                </div>

                <div class="mb-3">
                    <label class="form-label">Новый пароль</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                    <div class="progress mt-2" style="height: 6px;">
                        <div id="pwd-strength" class="progress-bar" style="width: 0%;"></div>
                    </div>
                    <small id="pwd-strength-label" class="text-muted"></small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Повторите пароль</label>
                    <input type="password" name="password_confirm" class="form-control" required>
                </div>

                <button type="submit" id="register-submit" class="btn btn-orange w-100" disabled>Сохранить пароль</button>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
