<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/email_code.php';
require_once __DIR__ . '/../lib/mailer.php';

if (Auth::isAuthenticated()) {
    header('Location: /profile.php');
    exit;
}

$_pageTitle = 'Восстановление пароля';
$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email.';
    } else {
        $user = DB::one('SELECT id FROM user WHERE email = :e', ['e' => $email]);
        if ($user !== null) {
            $code = EmailCode::issue($email, 'reset', null);
            $subject = 'Восстановление пароля';
            $body    = "Здравствуйте!\n\nВаш код для восстановления пароля: $code\n"
                     . "Код действителен " . EMAIL_CODE_TTL_MIN . " минут.\n\n"
                     . "Если вы не запрашивали восстановление — проигнорируйте это письмо.";
            Mailer::send($email, $subject, $body);
        }
        header('Location: /password_reset_confirm.php?email=' . urlencode($email));
        exit;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4 text-center">Восстановление пароля</h1>

            <p class="text-muted small">
                Мы пришлём код для восстановления на указанный email.
            </p>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($email) ?>" required autofocus>
                </div>
                <button type="submit" class="btn btn-orange w-100">Прислать код</button>
            </form>

            <div class="text-center small text-muted mt-3">
                <a href="/login.php">Вспомнили пароль? Войти</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
