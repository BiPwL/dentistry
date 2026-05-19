<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/password.php';
require_once __DIR__ . '/lib/email_code.php';
require_once __DIR__ . '/lib/mailer.php';

if (Auth::isAuthenticated()) {
    header('Location: /profile.php');
    exit;
}

$_pageTitle = 'Регистрация';
$errors = [];
$form = [
    'last_name'   => '',
    'first_name'  => '',
    'middle_name' => '',
    'email'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['last_name']   = trim((string) ($_POST['last_name']   ?? ''));
    $form['first_name']  = trim((string) ($_POST['first_name']  ?? ''));
    $form['middle_name'] = trim((string) ($_POST['middle_name'] ?? ''));
    $form['email']       = trim((string) ($_POST['email']       ?? ''));
    $password            =        (string) ($_POST['password']         ?? '');
    $passwordConfirm     =        (string) ($_POST['password_confirm'] ?? '');

    if ($form['last_name'] === '')  $errors['last_name'] = 'Введите фамилию.';
    if ($form['first_name'] === '') $errors['first_name'] = 'Введите имя.';
    if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    } else {
        $exists = DB::one('SELECT 1 FROM user WHERE email = :e', ['e' => $form['email']]);
        if ($exists !== null) $errors['email'] = 'Пользователь с таким email уже зарегистрирован.';
    }
    if ($password === '') {
        $errors['password'] = 'Введите пароль.';
    } elseif (password_strength($password) < PASSWORD_MIN_SCORE) {
        $errors['password'] = 'Слишком слабый пароль. Используйте 8+ символов разных классов.';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Пароли не совпадают.';
    }

    if (empty($errors)) {
        $payload = [
            'last_name'     => $form['last_name'],
            'first_name'    => $form['first_name'],
            'middle_name'   => $form['middle_name'],
            'password_hash' => password_make_hash($password),
        ];
        $code = EmailCode::issue($form['email'], 'register', $payload);

        $subject = 'Подтверждение регистрации';
        $body    = "Здравствуйте!\n\nВаш код подтверждения регистрации: $code\n"
                 . "Код действителен " . EMAIL_CODE_TTL_MIN . " минут.\n\n"
                 . "Если вы не регистрировались — проигнорируйте это письмо.";
        Mailer::send($form['email'], $subject, $body);

        header('Location: /register_confirm.php?email=' . urlencode($form['email']));
        exit;
    }
}

require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4 text-center">Регистрация</h1>

            <form method="post" novalidate>
                <?= Csrf::field() ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Фамилия</label>
                        <input type="text" name="last_name" class="form-control<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>" value="<?= h($form['last_name']) ?>" required>
                        <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= h($errors['last_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Имя</label>
                        <input type="text" name="first_name" class="form-control<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>" value="<?= h($form['first_name']) ?>" required>
                        <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= h($errors['first_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Отчество</label>
                        <input type="text" name="middle_name" class="form-control" value="<?= h($form['middle_name']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" value="<?= h($form['email']) ?>" required>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= h($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Пароль</label>
                    <input type="password" name="password" id="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" required>
                    <div class="progress mt-2" style="height: 6px;">
                        <div id="pwd-strength" class="progress-bar" style="width: 0%;"></div>
                    </div>
                    <small id="pwd-strength-label" class="text-muted"></small>
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= h($errors['password']) ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Повторите пароль</label>
                    <input type="password" name="password_confirm" class="form-control<?= isset($errors['password_confirm']) ? ' is-invalid' : '' ?>" required>
                    <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= h($errors['password_confirm']) ?></div><?php endif; ?>
                </div>

                <button type="submit" id="register-submit" class="btn btn-orange w-100" disabled>Зарегистрироваться</button>
            </form>

            <div class="text-center small text-muted mt-3">
                Уже есть аккаунт? <a href="/login.php">Войти</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
