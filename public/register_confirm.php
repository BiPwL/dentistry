<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/email_code.php';

if (Auth::isAuthenticated()) {
    header('Location: /profile.php');
    exit;
}

$_pageTitle = 'Подтверждение регистрации';
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $code = trim((string) ($_POST['code'] ?? ''));

    if ($email === '' || $code === '') {
        $error = 'Введите email и код.';
    } else {
        $payload = EmailCode::consume($email, 'register', $code);
        if ($payload === null) {
            $error = 'Код неверный или истёк. Попробуйте зарегистрироваться заново.';
        } else {
            $exists = DB::one('SELECT 1 FROM user WHERE email = :e', ['e' => $email]);
            if ($exists !== null) {
                $error = 'Пользователь с таким email уже зарегистрирован.';
            } else {
                DB::exec(
                    'INSERT INTO user (last_name, first_name, middle_name, email, password_hash, role_id)
                     VALUES (:ln, :fn, :mn, :e, :ph, 4)',
                    [
                        'ln' => $payload['last_name'],
                        'fn' => $payload['first_name'],
                        'mn' => $payload['middle_name'],
                        'e'  => $email,
                        'ph' => $payload['password_hash'],
                    ]
                );
                Session::login(DB::lastId());
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
            <h1 class="h3 mb-4 text-center">Подтверждение регистрации</h1>

            <p class="text-muted small">
                Мы отправили шестизначный код на <?= h($email !== '' ? $email : 'указанный email') ?>.
                Введите его в течение <?= (int) EMAIL_CODE_TTL_MIN ?> минут.
            </p>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="email" value="<?= h($email) ?>">
                <div class="mb-3">
                    <label class="form-label">Код подтверждения</label>
                    <input type="text" name="code" maxlength="6" pattern="\d{6}" class="form-control text-center" required autofocus
                           autocomplete="one-time-code" inputmode="numeric">
                </div>
                <button type="submit" class="btn btn-orange w-100">Подтвердить</button>
            </form>

            <div class="text-center small text-muted mt-3">
                Не получили код? <a href="/register.php">Зарегистрироваться заново</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
