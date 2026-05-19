<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';

// Если уже залогинен — на профиль
if (Auth::isAuthenticated()) {
    header('Location: /profile.php');
    exit;
}

$_pageTitle = 'Вход';
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password =        (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Заполните email и пароль.';
    } else {
        $row = DB::one('SELECT id, password_hash FROM user WHERE email = :e', ['e' => $email]);
        if ($row !== null && password_verify($password, $row['password_hash'])) {
            Session::login((int) $row['id']);
            header('Location: /profile.php');
            exit;
        }
        $error = 'Неверный email или пароль.';
    }
}

require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4 text-center">Вход</h1>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           value="<?= h($email) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Пароль</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-orange w-100">Войти</button>
            </form>

            <div class="text-center mt-3">
                <a href="/password_reset.php" class="small">Забыли пароль?</a>
            </div>
            <hr>
            <div class="text-center small text-muted">
                Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
