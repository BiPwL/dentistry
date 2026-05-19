# Стоматологическая клиника — Фаза 2: Аутентификация

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Реализовать полный цикл аутентификации поверх Фазы 1: регистрация с email-кодом (TTL 15 мин), вход, восстановление пароля, выход. Отправка писем через PHPMailer (SMTP) с fallback-логированием в файл, если SMTP не настроен.

**Architecture:** Каждая форма — самостоятельный PHP-файл, который POST-ит сам в себя; CSRF на всех формах через `Csrf::field()`. Регистрация работает в два шага: на первом данные не пишутся в `user`, а сериализуются в `email_code.payload_json` вместе с уже захешированным паролем; только после ввода корректного кода в течение 15 мин строка `user` создаётся и пользователь логинится. Восстановление пароля — аналогичный двухшаговый поток, только с обновлением `password_hash`.

**Tech Stack:** PHP 8.3, PHPMailer 6.x (вендорится без composer), MySQL 8.2 (PDO из Фазы 1), Bootstrap 5.3 (CDN, через layout из Фазы 1), нативный JS для индикатора надёжности пароля.

---

## Что приходит с Фазы 1 (уже доступно)

- `lib/db.php` — `DB::all/one/exec/lastId/pdo`
- `lib/session.php` — `Session::start/userId/user/login/logout`
- `lib/csrf.php` — `Csrf::token/check/field/requireValid`
- `lib/sanitize.php` — `h/fio_short/fmt_dt/fmt_price`
- `lib/auth.php` — `Auth::isAuthenticated/user/role/hasRole/requireRole`
- `templates/header.php`, `templates/footer.php` — общий layout
- `assets/css/theme.css`, `assets/js/main.js`
- `db/schema.sql` — таблица `email_code` уже есть: `(email, code CHAR(6), purpose ENUM('register','reset'), payload_json JSON NULL, expires_at, created_at)`
- `config.php` — константы `EMAIL_CODE_TTL_MIN=15`, `EMAIL_CODE_LENGTH=6`, `SMTP_*` (плейсхолдеры)

---

## Структура файлов после Фазы 2

```
lib/
├── PHPMailer/                # вендоренный (Task 1)
│   ├── Exception.php
│   ├── PHPMailer.php
│   └── SMTP.php
├── mailer.php                # обёртка (Task 3)
├── email_code.php            # выдача/проверка/гашение кодов (Task 4)
└── password.php              # оценка надёжности (Task 5)

data/
├── .gitkeep                  # директория для mail.log
└── mail.log                  # gitignored

login.php                     # переписать (Task 7)
logout.php                    # новый (Task 6)
register.php                  # переписать (Task 9)
register_confirm.php          # новый (Task 10)
password_reset.php            # новый (Task 11)
password_reset_confirm.php    # новый (Task 12)
profile.php                   # минимальная заглушка (Task 8)

assets/js/main.js             # расширить (Task 13)

tests/
├── smoke_mailer.php          # Task 3
├── smoke_email_code.php      # Task 4
└── smoke_password.php        # Task 5
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL CLI: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер PHP: `php -S 127.0.0.1:8780 -t .`
- DB заполнена seed-ом; тестовый пароль для всех = `Password1!`
- SMTP в `config.php` пока пустой — Mailer будет писать в `data/mail.log`. Для реальной отправки заполнить `SMTP_USER`/`SMTP_PASS` после Фазы 2.

---

## Подход к тестированию

- **Логика** (Mailer fallback, EmailCode жизненный цикл, password_strength) — smoke-скрипты в `tests/`, запускаются с `-d zend.assertions=1 -d assert.exception=1`.
- **Формы и потоки** — ручная проверка через встроенный PHP-сервер: открыть страницу, заполнить, проверить редирект и БД-эффект.
- **БД-эффекты** проверяются `SELECT`-запросами.
- **Email** в dev-режиме читается из `data/mail.log`.

---

## Задачи

---

### Task 1: Вендоринг PHPMailer

**Files:**
- Create: `lib/PHPMailer/Exception.php`, `lib/PHPMailer/PHPMailer.php`, `lib/PHPMailer/SMTP.php` (скачиваются с GitHub)

- [ ] **Step 1: Скачать архив PHPMailer**

```powershell
$tmp = "$env:TEMP\phpmailer.zip"
Invoke-WebRequest -Uri 'https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip' -OutFile $tmp -UseBasicParsing
Expand-Archive -Path $tmp -DestinationPath "$env:TEMP\phpmailer_extracted" -Force
```

- [ ] **Step 2: Скопировать три нужных файла**

```powershell
$src = Get-ChildItem "$env:TEMP\phpmailer_extracted\PHPMailer-*" -Directory | Select-Object -First 1
Copy-Item "$($src.FullName)\src\PHPMailer.php" lib\PHPMailer\PHPMailer.php
Copy-Item "$($src.FullName)\src\SMTP.php"      lib\PHPMailer\SMTP.php
Copy-Item "$($src.FullName)\src\Exception.php" lib\PHPMailer\Exception.php
Remove-Item "$env:TEMP\phpmailer.zip"
Remove-Item "$env:TEMP\phpmailer_extracted" -Recurse -Force
```

- [ ] **Step 3: Проверить, что классы подгружаются**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -r "require 'lib/PHPMailer/Exception.php'; require 'lib/PHPMailer/PHPMailer.php'; require 'lib/PHPMailer/SMTP.php'; \$m = new PHPMailer\PHPMailer\PHPMailer(); echo 'PHPMailer version: ' . \$m::VERSION . PHP_EOL;"
```
Expected: `PHPMailer version: 6.x.x`.

- [ ] **Step 4: Commit**

```powershell
git add lib/PHPMailer; git commit -m "feat: vendor PHPMailer 6.x library"
```

---

### Task 2: Каталог `data/` и `.gitignore`

**Files:**
- Create: `data/.gitkeep`
- Modify: `.gitignore`

- [ ] **Step 1: Создать data/ с .gitkeep**

```powershell
New-Item -ItemType Directory -Force -Path data | Out-Null
New-Item -ItemType File -Force -Path data\.gitkeep | Out-Null
```

- [ ] **Step 2: Дополнить `.gitignore`**

Дописать к существующему `.gitignore` две строки (использовать редактор):

```gitignore
data/mail.log
data/*.log
```

Итоговый файл `.gitignore` должен содержать:
```
.claude/
*.log
data/mail.log
data/*.log
```

- [ ] **Step 3: Commit**

```powershell
git add data/.gitkeep .gitignore; git commit -m "feat: data/ directory for dev mail log + ignore rules"
```

---

### Task 3: Mailer-хелпер (`lib/mailer.php`)

**Files:**
- Create: `lib/mailer.php`
- Create: `tests/smoke_mailer.php`

- [ ] **Step 1: Написать `lib/mailer.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class Mailer
{
    /**
     * Отправка письма. Возвращает true при успехе.
     * Если SMTP не настроен (пустой SMTP_USER) — пишет в data/mail.log
     * и тоже возвращает true.
     */
    public static function send(string $to, string $subject, string $bodyText): bool
    {
        if (SMTP_USER === '') {
            return self::logToFile($to, $subject, $bodyText);
        }
        return self::sendSmtp($to, $subject, $bodyText);
    }

    private static function sendSmtp(string $to, string $subject, string $bodyText): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $bodyText;
            $mail->isHTML(false);

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }

    private static function logToFile(string $to, string $subject, string $bodyText): bool
    {
        $logPath = __DIR__ . '/../data/mail.log';
        $entry  = '=== ' . date('Y-m-d H:i:s') . ' ===' . PHP_EOL;
        $entry .= "TO: $to" . PHP_EOL;
        $entry .= "SUBJECT: $subject" . PHP_EOL;
        $entry .= '---' . PHP_EOL;
        $entry .= $bodyText . PHP_EOL . PHP_EOL;
        return file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
```

- [ ] **Step 2: Написать `tests/smoke_mailer.php`**

```php
<?php
require_once __DIR__ . '/../lib/mailer.php';

$logFile = __DIR__ . '/../data/mail.log';
// Очистить лог если есть
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
}

$ok = Mailer::send('test@example.com', 'Test subject', 'Test body line 1');
assert($ok === true, 'Mailer::send must return true in fallback mode');
assert(file_exists($logFile), 'data/mail.log must exist after send');

$contents = file_get_contents($logFile);
assert(str_contains($contents, 'TO: test@example.com'), 'log must contain TO');
assert(str_contains($contents, 'SUBJECT: Test subject'), 'log must contain SUBJECT');
assert(str_contains($contents, 'Test body line 1'), 'log must contain body');

echo "Mailer smoke: OK\n";
```

- [ ] **Step 3: Запустить smoke-тест**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_mailer.php
```
Expected: `Mailer smoke: OK`.

- [ ] **Step 4: Commit**

```powershell
git add lib/mailer.php tests/smoke_mailer.php; git commit -m "feat: mailer wrapper (PHPMailer SMTP with file-log fallback)"
```

---

### Task 4: EmailCode-хелпер (`lib/email_code.php`)

**Files:**
- Create: `lib/email_code.php`
- Create: `tests/smoke_email_code.php`

Класс инкапсулирует жизненный цикл кодов в `email_code`: выдать (с TTL и опциональным payload), проверить, погасить (удалить после использования).

- [ ] **Step 1: Написать `lib/email_code.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class EmailCode
{
    /**
     * Выдать новый код. Удаляет предыдущие коды того же (email, purpose),
     * чтобы пользователь мог перезапросить код, и в системе оставался ровно один действующий.
     *
     * @param array<string,mixed>|null $payload  опциональный payload (для регистрации)
     * @return string  шестизначный код
     */
    public static function issue(string $email, string $purpose, ?array $payload = null): string
    {
        self::assertPurpose($purpose);
        DB::exec(
            'DELETE FROM email_code WHERE email = :e AND purpose = :p',
            ['e' => $email, 'p' => $purpose]
        );

        $code = str_pad((string) random_int(0, 999999), EMAIL_CODE_LENGTH, '0', STR_PAD_LEFT);
        DB::exec(
            'INSERT INTO email_code (email, code, purpose, payload_json, expires_at)
             VALUES (:e, :c, :p, :j, DATE_ADD(NOW(), INTERVAL :t MINUTE))',
            [
                'e' => $email,
                'c' => $code,
                'p' => $purpose,
                'j' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                't' => EMAIL_CODE_TTL_MIN,
            ]
        );
        return $code;
    }

    /**
     * Найти неистёкший код. Возвращает строку из БД (с payload_json) или null.
     *
     * @return array<string,mixed>|null
     */
    public static function find(string $email, string $purpose, string $code): ?array
    {
        self::assertPurpose($purpose);
        return DB::one(
            'SELECT * FROM email_code
              WHERE email = :e AND purpose = :p AND code = :c AND expires_at > NOW()',
            ['e' => $email, 'p' => $purpose, 'c' => $code]
        );
    }

    /**
     * Найти и удалить код. Возвращает payload (array) если код валиден, null иначе.
     * Гарантирует одноразовое использование.
     *
     * @return array<string,mixed>|null  payload (или пустой массив, если payload не был задан)
     */
    public static function consume(string $email, string $purpose, string $code): ?array
    {
        $row = self::find($email, $purpose, $code);
        if ($row === null) return null;

        DB::exec('DELETE FROM email_code WHERE id = :id', ['id' => $row['id']]);
        if (empty($row['payload_json'])) return [];
        $payload = json_decode($row['payload_json'], true);
        return is_array($payload) ? $payload : [];
    }

    /** Удалить просроченные коды (можно вызывать периодически). */
    public static function purgeExpired(): int
    {
        return DB::exec('DELETE FROM email_code WHERE expires_at <= NOW()');
    }

    private static function assertPurpose(string $purpose): void
    {
        if (!in_array($purpose, ['register', 'reset'], true)) {
            throw new InvalidArgumentException("Unknown purpose: $purpose");
        }
    }
}
```

- [ ] **Step 2: Написать `tests/smoke_email_code.php`**

```php
<?php
require_once __DIR__ . '/../lib/email_code.php';

$email   = 'codecheck@example.com';
$purpose = 'register';

// Очистка перед тестом
DB::exec('DELETE FROM email_code WHERE email = :e', ['e' => $email]);

// Выдача
$payload = ['last_name' => 'Тест', 'pwd' => 'hash'];
$code = EmailCode::issue($email, $purpose, $payload);
assert(strlen($code) === 6, 'Code must be 6 chars');
assert(ctype_digit($code), 'Code must be all digits');

// Поиск валидного
$row = EmailCode::find($email, $purpose, $code);
assert($row !== null, 'Issued code must be found');
assert($row['email'] === $email);
assert($row['purpose'] === $purpose);

// Неверный код не находится
assert(EmailCode::find($email, $purpose, '000000') === null || $code === '000000', 'Wrong code must not be found');

// Consume возвращает payload и удаляет
$out = EmailCode::consume($email, $purpose, $code);
assert($out !== null, 'Consume must return payload');
assert($out['last_name'] === 'Тест', 'Payload must round-trip');
assert(EmailCode::find($email, $purpose, $code) === null, 'After consume, code must be gone');

// Повторный consume того же кода → null
assert(EmailCode::consume($email, $purpose, $code) === null, 'Second consume must return null');

// purgeExpired не падает
EmailCode::purgeExpired();

echo "EmailCode smoke: OK\n";
```

- [ ] **Step 3: Запуск smoke-теста**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_email_code.php
```
Expected: `EmailCode smoke: OK`.

- [ ] **Step 4: Commit**

```powershell
git add lib/email_code.php tests/smoke_email_code.php; git commit -m "feat: email code lifecycle helper (issue/find/consume/purge)"
```

---

### Task 5: Password-хелпер (`lib/password.php`)

**Files:**
- Create: `lib/password.php`
- Create: `tests/smoke_password.php`

- [ ] **Step 1: Написать `lib/password.php`**

```php
<?php
declare(strict_types=1);

/**
 * Оценка надёжности пароля по 5-балльной шкале:
 *   0 — очень слабый / пустой
 *   1 — слабый
 *   2 — средний
 *   3 — хороший
 *   4 — отличный
 *
 * Алгоритм: длина даёт базовый score, каждый класс символов (нижний, верхний,
 * цифры, спецсимволы) добавляет +1. Минимальная длина для пароля «допустимый» — 8.
 */
function password_strength(string $pwd): int
{
    $len = mb_strlen($pwd);
    if ($len < 6) return 0;

    $score = 0;
    if ($len >= 8)  $score++;
    if ($len >= 12) $score++;
    if (preg_match('/[a-z]/', $pwd)) $score++;
    if (preg_match('/[A-Z]/', $pwd)) $score++;
    if (preg_match('/\d/', $pwd))    $score++;
    if (preg_match('/[^A-Za-z0-9]/', $pwd)) $score++;

    return min($score, 4);
}

/** Текстовая метка для индикатора. */
function password_strength_label(int $score): string
{
    return [
        0 => 'Очень слабый',
        1 => 'Слабый',
        2 => 'Средний',
        3 => 'Хороший',
        4 => 'Отличный',
    ][$score] ?? 'Неизвестно';
}

/** Bootstrap-цвет для прогресс-бара. */
function password_strength_color(int $score): string
{
    return [
        0 => 'bg-danger',
        1 => 'bg-danger',
        2 => 'bg-warning',
        3 => 'bg-info',
        4 => 'bg-success',
    ][$score] ?? 'bg-secondary';
}

/** Хеш bcrypt (cost=12). */
function password_make_hash(string $pwd): string
{
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Минимальная допустимая надёжность для регистрации/смены пароля. */
const PASSWORD_MIN_SCORE = 2;
```

- [ ] **Step 2: Написать `tests/smoke_password.php`**

```php
<?php
require_once __DIR__ . '/../lib/password.php';

assert(password_strength('') === 0, 'empty → 0');
assert(password_strength('abc') === 0, 'too short → 0');
assert(password_strength('aaaaaaaa') === 1, 'lowercase only, 8 chars → 1');
assert(password_strength('Aaaaaaaa') === 2, 'lower+upper, 8 chars → 2');
assert(password_strength('Aaaaaaa1') === 3, 'lower+upper+digit → 3');
assert(password_strength('Password1!') >= 4, 'strong password → 4');
assert(password_strength('VeryStrongPwd123!') === 4, 'capped at 4');

assert(password_strength_label(0) === 'Очень слабый');
assert(password_strength_label(4) === 'Отличный');

assert(password_strength_color(0) === 'bg-danger');
assert(password_strength_color(4) === 'bg-success');

$hash = password_make_hash('Password1!');
assert(str_starts_with($hash, '$2y$12$'), 'bcrypt cost=12');
assert(password_verify('Password1!', $hash), 'verify own hash');

echo "Password smoke: OK\n";
```

- [ ] **Step 3: Запуск smoke-теста**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_password.php
```
Expected: `Password smoke: OK`.

- [ ] **Step 4: Commit**

```powershell
git add lib/password.php tests/smoke_password.php; git commit -m "feat: password strength scoring + bcrypt hash helper"
```

---

### Task 6: Logout (`logout.php`)

**Files:**
- Create: `logout.php`

- [ ] **Step 1: Написать `logout.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/csrf.php';

// Только POST, защищённый CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /');
    exit;
}
Csrf::requireValid();
Session::logout();
header('Location: /');
exit;
```

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l logout.php
```

- [ ] **Step 3: Commit**

```powershell
git add logout.php; git commit -m "feat: logout endpoint (POST + CSRF)"
```

---

### Task 7: Login form (`login.php` — переписать)

**Files:**
- Modify: `login.php` (полностью переписать заглушку)

- [ ] **Step 1: Переписать `login.php`**

```php
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
```

- [ ] **Step 2: Smoke-проверка**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8781','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8781/login.php' -UseBasicParsing
    "GET login.php: $($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'name="_csrf"') { 'csrf field present' } else { 'CSRF MISSING' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, csrf field present.

Также проверить руками (опционально) через браузер: войти как `admin@dentistry.local` / `Password1!` → редирект на /profile.php (которого ещё нет, но это будет в Task 8).

- [ ] **Step 3: Commit**

```powershell
git add login.php; git commit -m "feat: login form with CSRF and password verify"
```

---

### Task 8: Profile-заглушка (`profile.php`)

**Files:**
- Create: `profile.php`

В Фазе 2 это минимальный landing для авторизованного пользователя: ФИО, email, роль, кнопка выхода. В Фазах 3–6 каждая роль получит свой полноценный кабинет, который заменит/расширит это.

- [ ] **Step 1: Написать `profile.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/sanitize.php';

Auth::requireRole('admin', 'registrar', 'doctor', 'patient');
$user = Auth::user();

$_pageTitle = 'Личный кабинет';
require __DIR__ . '/templates/header.php';
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

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l profile.php
```

- [ ] **Step 3: Smoke (анонимом → редирект на login)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8782','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8782/profile.php' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
    if ($r.StatusCode -eq 302 -and $r.Headers.Location -match 'login.php') { 'guest redirects to /login.php OK' }
    else { "Unexpected status: $($r.StatusCode), Location: $($r.Headers.Location)" }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: `guest redirects to /login.php OK`.

- [ ] **Step 4: Commit**

```powershell
git add profile.php; git commit -m "feat: minimal authenticated profile stub (logout button)"
```

---

### Task 9: Register form (`register.php` — переписать)

**Files:**
- Modify: `register.php` (заменить заглушку)

Двухшаговый поток: сабмит формы → код по email → ввод кода на `register_confirm.php`. На этом шаге пользователь ещё не создан в `user`; пароль уже захеширован и сохранён в `email_code.payload_json`.

- [ ] **Step 1: Переписать `register.php`**

```php
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
```

- [ ] **Step 2: Smoke**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8783','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8783/register.php' -UseBasicParsing
    "GET register.php: $($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'pwd-strength' -and $r.Content -match 'name="_csrf"') { 'form ok' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, form ok.

- [ ] **Step 3: Commit**

```powershell
git add register.php; git commit -m "feat: registration form (validates, hashes, issues email code)"
```

---

### Task 10: Registration confirm (`register_confirm.php`)

**Files:**
- Create: `register_confirm.php`

- [ ] **Step 1: Написать `register_confirm.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/email_code.php';

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
            // Доп. защита: email мог быть зарегистрирован после issue (гонка)
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

require __DIR__ . '/templates/header.php';
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

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Smoke**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8784','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8784/register_confirm.php?email=test%40example.com' -UseBasicParsing
    "$($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'name="code"' -and $r.Content -match 'test@example.com') { 'form ok' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, form ok.

- [ ] **Step 3: Commit**

```powershell
git add register_confirm.php; git commit -m "feat: registration confirmation (consume code, create user, login)"
```

---

### Task 11: Password reset request (`password_reset.php`)

**Files:**
- Create: `password_reset.php`

- [ ] **Step 1: Написать `password_reset.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/email_code.php';
require_once __DIR__ . '/lib/mailer.php';

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
        // Редиректим всегда — не палим существование email
        header('Location: /password_reset_confirm.php?email=' . urlencode($email));
        exit;
    }
}

require __DIR__ . '/templates/header.php';
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

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Smoke**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8785','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8785/password_reset.php' -UseBasicParsing
    "$($r.StatusCode), $($r.Content.Length)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, > 1500 bytes.

- [ ] **Step 3: Commit**

```powershell
git add password_reset.php; git commit -m "feat: password reset request (email code, no enumeration)"
```

---

### Task 12: Password reset confirm (`password_reset_confirm.php`)

**Files:**
- Create: `password_reset_confirm.php`

- [ ] **Step 1: Написать `password_reset_confirm.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/password.php';
require_once __DIR__ . '/lib/email_code.php';

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

require __DIR__ . '/templates/header.php';
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

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Smoke**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8786','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8786/password_reset_confirm.php?email=test%40example.com' -UseBasicParsing
    "$($r.StatusCode), $($r.Content.Length)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, > 1500 bytes.

- [ ] **Step 3: Commit**

```powershell
git add password_reset_confirm.php; git commit -m "feat: password reset confirm (consume code, update pwd, login)"
```

---

### Task 13: Password strength UI (`assets/js/main.js`)

**Files:**
- Modify: `assets/js/main.js`

JS должен:
1. Считать score по тем же правилам, что серверная `password_strength()`.
2. Обновлять прогресс-бар `#pwd-strength` и подпись `#pwd-strength-label`.
3. Включать кнопку `#register-submit` только если score ≥ 2.
4. Активироваться, если в DOM есть `#password`. Иначе — ничего не делает.

- [ ] **Step 1: Расширить `assets/js/main.js`**

```js
// Site-wide JS

(function () {
    'use strict';

    function pwdStrength(pwd) {
        if (!pwd || pwd.length < 6) return 0;
        var s = 0;
        if (pwd.length >= 8)  s++;
        if (pwd.length >= 12) s++;
        if (/[a-z]/.test(pwd)) s++;
        if (/[A-Z]/.test(pwd)) s++;
        if (/\d/.test(pwd))    s++;
        if (/[^A-Za-z0-9]/.test(pwd)) s++;
        return Math.min(s, 4);
    }

    var labels = ['Очень слабый', 'Слабый', 'Средний', 'Хороший', 'Отличный'];
    var colors = ['bg-danger', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];

    function initPasswordStrength() {
        var input  = document.getElementById('password');
        var bar    = document.getElementById('pwd-strength');
        var label  = document.getElementById('pwd-strength-label');
        var submit = document.getElementById('register-submit');
        if (!input || !bar || !label) return;

        function update() {
            var score = pwdStrength(input.value);
            bar.style.width = (25 * score) + '%';
            bar.className = 'progress-bar ' + colors[score];
            label.textContent = input.value === '' ? '' : labels[score];
            if (submit) submit.disabled = score < 2;
        }

        input.addEventListener('input', update);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordStrength);
    } else {
        initPasswordStrength();
    }
})();
```

- [ ] **Step 2: Smoke (render и проверка наличия скрипта)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8787','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8787/assets/js/main.js' -UseBasicParsing
    "$($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'pwdStrength' -and $r.Content -match 'pwd-strength') { 'JS contains strength logic' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, JS contains strength logic.

- [ ] **Step 3: Commit**

```powershell
git add assets/js/main.js; git commit -m "feat: client-side password strength indicator"
```

---

### Task 14: Сквозная проверка Фазы 2 + тег

- [ ] **Step 1: Все smoke-тесты**

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_db.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_csrf.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_mailer.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_email_code.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_password.php
```
Expected: 5 строк «… OK».

- [ ] **Step 2: Все аутентификационные URL отдают 200 (GET-проверка)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8790','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $urls = @('/login.php', '/register.php', '/register_confirm.php?email=x%40x', '/password_reset.php', '/password_reset_confirm.php?email=x%40x')
    foreach ($u in $urls) {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8790$u" -UseBasicParsing
        "$u -> $($r.StatusCode), $($r.Content.Length)"
    }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: все URL → 200, length > 1500.

- [ ] **Step 3: Регрессия Фазы 1 — публичные страницы по-прежнему живы**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8791','-t','.' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    foreach ($u in @('/', '/services.php', '/blog.php', '/contacts.php')) {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8791$u" -UseBasicParsing
        "$u -> $($r.StatusCode)"
    }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: четыре «200».

- [ ] **Step 4: Тег фазы**

```powershell
git tag phase-2-complete
git tag --list 'phase-*'
git log --oneline -25
```
Expected: видны все коммиты Фазы 2, два тега `phase-1-complete` и `phase-2-complete`.

- [ ] **Step 5 (опционально, dev-flow вручную):**

Запустить dev-сервер на 8080 и пройти полный сценарий регистрации:
1. Открыть `/register.php`, ввести `new@example.com` + надёжный пароль → редирект на `/register_confirm.php`.
2. Открыть `data/mail.log` → скопировать 6-значный код.
3. Ввести код → должно создаться пользователя (`SELECT * FROM user WHERE email = 'new@example.com'`) и логин редиректит на `/profile.php`.
4. Профиль показывает ФИО, email, роль «Пациент», кнопку «Выйти».
5. Выйти → редирект на `/`, шапка снова с «Вход»/«Регистрация».
6. На `/login.php` войти как `admin@dentistry.local` / `Password1!` → профиль администратора.
7. Восстановление пароля: `/password_reset.php` → код в `data/mail.log` → новый пароль → автологин.

---

## Чек-лист соответствия спецификации (Фаза 2)

| Требование PROMPT.md | Где |
|---|---|
| Регистрация: фамилия, имя, отчество, email, пароль, повтор пароля | Task 9 |
| Проверка уникальности email (без стирания формы) | Task 9 (`is-invalid` + сохранение `$form`) |
| Индикатор надёжности пароля, блокировка submit при слабом | Task 9 + 13 (`#register-submit disabled`, `PASSWORD_MIN_SCORE`) |
| Отправка кода подтверждения на email | Task 9 → 3 (Mailer) |
| Код 15 минут | EMAIL_CODE_TTL_MIN из config, Task 4 |
| Подтверждение кода → создание аккаунта + сессия | Task 10 |
| Роль «Пациент» по умолчанию | Task 10 (`role_id=4`) |
| Авторизация: email + пароль, общая ошибка | Task 7 |
| Восстановление пароля: email → код | Task 11 |
| Новый пароль с повтором → сохранение + автологин | Task 12 |
| PHPMailer + SMTP | Task 1 + Task 3 |
| Bcrypt cost=12 | Task 5 (`password_make_hash`) |
| CSRF на формах | Все Task 7, 9, 10, 11, 12 — `Csrf::field()` + `requireValid()` |
| `htmlspecialchars()` всегда | Все шаблоны через `h()` |
| Все запросы через подготовленные выражения | Через `DB::all/one/exec` |
| Logout | Task 6 |

---

**Итог Фазы 2:** работающая аутентификация — гость может зарегистрироваться (через email-код), войти, выйти, восстановить пароль. После приёмки — Фаза 3 (кабинет пациента: история записей, мастер записи на приём).
