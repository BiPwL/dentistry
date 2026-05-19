# Стоматологическая клиника — Фаза 3: Врачи (витрина + профиль врача)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Публичная страница «Врачи клиники» (ФИО, фото, специализация, описание — доступна гостям) и возможность врачу в своём кабинете заполнять/менять эти данные, включая загрузку фотографии.

**Architecture:** Новая 1:1-таблица `doctor_profile` (user_id → специализация/био/фото) — отдельно от `user`, т.к. профиль есть только у врачей. Публичная `public/doctors.php` рендерит LEFT JOIN user+doctor_profile по роли «врач». Врач правит свой профиль на `public/doctor_profile_edit.php` (gated `Auth::requireRole('doctor')`), фото грузится через защищённый хелпер `lib/upload.php` (проверка MIME через finfo, лимит размера, безопасное имя файла) в `public/uploads/doctors/`. Существующая БД мигрируется аддитивным скриптом (CREATE TABLE — без потери данных).

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3, нативный JS. Использует хелперы Фаз 1–2 (DB, Auth, Csrf, sanitize).

---

## Что приходит с Фаз 1–2

- Webroot — `public/`; общие хелперы выше webroot в `lib/`; точки входа в `public/` и подключают `__DIR__ . '/../config.php'`, `__DIR__ . '/../lib/...'`, `__DIR__ . '/../templates/...'`.
- `lib/db.php` (DB::all/one/exec/lastId), `lib/auth.php` (Auth::requireRole/hasRole/user), `lib/csrf.php`, `lib/sanitize.php` (h/fio_short).
- `templates/header.php` — навбар (Главная/Услуги/Блог/Контакты), `templates/footer.php`.
- `public/profile.php` — кабинет (заглушка для всех ролей; врачу добавим ссылку на правку профиля).
- БД заполнена: врачи — id 3 (Петров Дмитрий Александрович), id 4 (Кузнецова Елена Викторовна), role_id=3. Пароль всех — `Password1!`.

---

## Структура файлов после Фазы 3

```
lib/upload.php                          # хелпер загрузки изображений (новый)
db/migrations/001_doctor_profile.sql    # миграция для существующей БД (новый)
db/schema.sql                           # + таблица doctor_profile (правка)
db/seed.sql                             # + профили врачей (правка)
public/doctors.php                      # публичная страница «Врачи» (новый)
public/doctor_profile_edit.php          # правка профиля врачом (новый)
public/uploads/doctors/.gitkeep         # каталог под фото (новый)
public/profile.php                      # + ссылка для врача (правка)
templates/header.php                    # + пункт меню «Врачи» (правка)
tests/smoke_upload.php                  # тест валидации загрузки (новый)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL CLI: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер (docroot = public): `php -S 127.0.0.1:PORT -t public`
- Тестовые врачи: `petrov@dentistry.local`, `kuznetsova@dentistry.local` (пароль `Password1!`)

---

## Подход к тестированию

- **Валидация загрузки** (`lib/upload.php`) — smoke-тест `tests/smoke_upload.php` с реальными временными файлами (валидный PNG, текстовый файл, превышение размера, отсутствие файла).
- **Полный upload-флоу** — функциональный тест через `curl -F` (реальный multipart POST к dev-серверу, где `move_uploaded_file` работает): логин врачом → POST формы с файлом → проверка строки в `doctor_profile` и файла в `public/uploads/doctors/`.
- **Публичная страница** — GET + проверка содержимого (ФИО, специализация врачей).
- **Регрессия** — публичные страницы Фаз 1–2 по-прежнему 200.

---

## Задачи

---

### Task P3-1: Таблица `doctor_profile` (схема + seed + миграция)

**Files:**
- Create: `db/migrations/001_doctor_profile.sql`
- Modify: `db/schema.sql`
- Modify: `db/seed.sql`

- [ ] **Step 1: Создать `db/migrations/001_doctor_profile.sql`**

```sql
-- Миграция для уже существующей БД: добавляет профиль врача (аддитивно, без потери данных).
CREATE TABLE IF NOT EXISTS doctor_profile (
    user_id        INT UNSIGNED PRIMARY KEY,
    specialization VARCHAR(150) NOT NULL DEFAULT '',
    bio            TEXT NULL,
    photo_path     VARCHAR(255) NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_docprofile_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO doctor_profile (user_id, specialization, bio) VALUES
    (3, 'Врач-стоматолог-терапевт',
        'Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.'),
    (4, 'Стоматолог-хирург, ортопед',
        'Стаж 9 лет. Удаление зубов любой сложности, протезирование, имплантология. Кандидат медицинских наук.')
ON DUPLICATE KEY UPDATE
    specialization = VALUES(specialization),
    bio            = VALUES(bio);
```

- [ ] **Step 2: Применить миграцию к существующей БД**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot --default-character-set=utf8mb4 dentistry -e "source db/migrations/001_doctor_profile.sql"
```

Проверка:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT dp.user_id, u.last_name, dp.specialization, dp.photo_path FROM doctor_profile dp JOIN user u ON u.id = dp.user_id;"
```
Expected: 2 строки (Петров — терапевт, Кузнецова — хирург), photo_path = NULL.

- [ ] **Step 3: Отразить таблицу в `db/schema.sql` (для чистой установки)**

В `db/schema.sql` добавить `DROP TABLE IF EXISTS doctor_profile;` в начало (в блок DROP, СРАЗУ после `DROP TABLE IF EXISTS appointment_service;` чтобы дочерние дропались раньше родителя `user`), и добавить определение таблицы ПОСЛЕ `CREATE TABLE user (...)` (т.к. FK ссылается на user). Вставить такой блок сразу после создания таблицы `user`:

```sql
CREATE TABLE doctor_profile (
    user_id        INT UNSIGNED PRIMARY KEY,
    specialization VARCHAR(150) NOT NULL DEFAULT '',
    bio            TEXT NULL,
    photo_path     VARCHAR(255) NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_docprofile_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Также добавить `DROP TABLE IF EXISTS doctor_profile;` в секцию DROP (она обёрнута в `SET FOREIGN_KEY_CHECKS=0; ... SET FOREIGN_KEY_CHECKS=1;`, поэтому порядок дропов некритичен, но для аккуратности — рядом с остальными дочерними таблицами).

- [ ] **Step 4: Отразить seed в `db/seed.sql` (для чистой установки)**

В конец `db/seed.sql` добавить:

```sql
-- ───── Профили врачей ─────
INSERT INTO doctor_profile (user_id, specialization, bio) VALUES
    (3, 'Врач-стоматолог-терапевт',
        'Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.'),
    (4, 'Стоматолог-хирург, ортопед',
        'Стаж 9 лет. Удаление зубов любой сложности, протезирование, имплантология. Кандидат медицинских наук.');
```

- [ ] **Step 5: Проверить чистую переустановку схемы (на временной БД, чтобы не трогать рабочую)**

```powershell
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
& $mysql -uroot -e "CREATE DATABASE IF NOT EXISTS dentistry_test DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $mysql -uroot --default-character-set=utf8mb4 dentistry_test -e "source db/schema.sql"
& $mysql -uroot --default-character-set=utf8mb4 dentistry_test -e "source db/seed.sql"
& $mysql -uroot dentistry_test -e "SELECT COUNT(*) AS profiles FROM doctor_profile;"
& $mysql -uroot -e "DROP DATABASE dentistry_test;"
```
Expected: `profiles = 2`, без ошибок импорта.

- [ ] **Step 6: Commit**

```powershell
git add db/schema.sql db/seed.sql db/migrations/001_doctor_profile.sql
git commit -m "feat: doctor_profile table (schema, seed, migration)"
```

---

### Task P3-2: Хелпер загрузки изображений (`lib/upload.php`)

**Files:**
- Create: `lib/upload.php`
- Create: `tests/smoke_upload.php`

- [ ] **Step 1: Написать `lib/upload.php`**

```php
<?php
declare(strict_types=1);

const IMAGE_MAX_BYTES = 2097152; // 2 МБ

/** Разрешённые MIME → расширение. */
function image_allowed_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

/**
 * Валидация загруженного изображения.
 * @param array<string,mixed> $file элемент из $_FILES
 * @return string|null  текст ошибки или null если всё ок
 */
function image_upload_validate(array $file, int $maxBytes = IMAGE_MAX_BYTES): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return 'Некорректная загрузка файла.';
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Файл не выбран.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Ошибка загрузки файла.';
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return 'Файл слишком большой (максимум 2 МБ).';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(image_allowed_types()[$mime])) {
        return 'Допустимы только изображения JPEG, PNG или WebP.';
    }
    return null;
}

/** Расширение по реальному MIME файла (после успешной валидации). */
function image_ext_for(array $file): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    return image_allowed_types()[$mime] ?? 'bin';
}

/**
 * Сохранить загруженное изображение в каталог.
 * @return string|null публичный относительный путь (например uploads/doctors/doctor_3_ab12cd34.jpg) или null при ошибке
 */
function image_save(array $file, string $destDirAbs, string $publicPrefix, string $basename): ?string
{
    if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0775, true) && !is_dir($destDirAbs)) {
        return null;
    }
    $ext   = image_ext_for($file);
    $fname = $basename . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest  = rtrim($destDirAbs, '/\\') . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return rtrim($publicPrefix, '/') . '/' . $fname;
}
```

- [ ] **Step 2: Написать `tests/smoke_upload.php`**

```php
<?php
require_once __DIR__ . '/../lib/upload.php';

// Валидный 1x1 PNG
$png = tempnam(sys_get_temp_dir(), 'img');
file_put_contents($png, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
));
$ok = ['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png];
assert(image_upload_validate($ok) === null, 'valid PNG must pass');
assert(image_ext_for($ok) === 'png', 'PNG ext must be png');

// Текстовый файл — отклонить (MIME не image/*)
$txt = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($txt, 'this is not an image, just text');
$txtFile = ['error' => UPLOAD_ERR_OK, 'size' => filesize($txt), 'tmp_name' => $txt];
assert(image_upload_validate($txtFile) !== null, 'text file must be rejected');

// Превышение размера
$big = ['error' => UPLOAD_ERR_OK, 'size' => 5_000_000, 'tmp_name' => $png];
assert(image_upload_validate($big, 2097152) !== null, 'oversize must be rejected');

// Файл не выбран
$none = ['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => ''];
assert(image_upload_validate($none) !== null, 'no-file must be rejected');

unlink($png);
unlink($txt);
echo "Upload smoke: OK\n";
```

- [ ] **Step 3: Запустить smoke-тест**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_upload.php
```
Expected: `Upload smoke: OK`.

- [ ] **Step 4: Commit**

```powershell
git add lib/upload.php tests/smoke_upload.php
git commit -m "feat: image upload helper with MIME/size validation + smoke test"
```

---

### Task P3-3: Публичная страница «Врачи» (`public/doctors.php`) + меню + каталог фото

**Files:**
- Create: `public/doctors.php`
- Create: `public/uploads/doctors/.gitkeep`
- Modify: `templates/header.php` (пункт меню «Врачи»)

- [ ] **Step 1: Создать каталог под фото**

```powershell
New-Item -ItemType Directory -Force -Path public\uploads\doctors | Out-Null
New-Item -ItemType File -Force -Path public\uploads\doctors\.gitkeep | Out-Null
```

- [ ] **Step 2: Добавить пункт меню «Врачи» в `templates/header.php`**

Найти строку с пунктом «Услуги»:
```php
                <li class="nav-item"><a class="nav-link<?= _nav_active('/services.php', $_currentPath) ?>" href="/services.php">Услуги</a></li>
```
и сразу ПОСЛЕ неё добавить:
```php
                <li class="nav-item"><a class="nav-link<?= _nav_active('/doctors.php', $_currentPath) ?>" href="/doctors.php">Врачи</a></li>
```

- [ ] **Step 3: Написать `public/doctors.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sanitize.php';

$_pageTitle = 'Врачи клиники';
$doctors = DB::all(
    "SELECT u.last_name, u.first_name, u.middle_name,
            dp.specialization, dp.bio, dp.photo_path
       FROM user u
       LEFT JOIN doctor_profile dp ON dp.user_id = u.id
      WHERE u.role_id = 3
      ORDER BY u.last_name, u.first_name"
);

require __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-4">Наши врачи</h1>

<?php if (empty($doctors)): ?>
    <p class="text-muted">Информация о врачах пока не добавлена.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($doctors as $d): ?>
            <?php $fio = trim($d['last_name'] . ' ' . $d['first_name'] . ' ' . $d['middle_name']); ?>
            <div class="col-md-6 col-lg-4">
                <div class="clinic-card h-100 text-center p-4">
                    <?php if (!empty($d['photo_path'])): ?>
                        <img src="/<?= h($d['photo_path']) ?>" alt="<?= h($fio) ?>"
                             class="doctor-photo mb-3" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="doctor-photo doctor-photo--placeholder mb-3">
                            <?= h(mb_substr($d['last_name'], 0, 1) . mb_substr($d['first_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <h5 class="mb-1"><?= h($fio) ?></h5>
                    <?php if (!empty($d['specialization'])): ?>
                        <div class="text-orange-2 mb-2"><?= h($d['specialization']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($d['bio'])): ?>
                        <p class="text-muted small mb-0"><?= h($d['bio']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

- [ ] **Step 4: Добавить стили для фото врача в `public/assets/css/theme.css`**

В конец `public/assets/css/theme.css` добавить:
```css
/* Doctors */
.doctor-photo {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: auto;
    margin-right: auto;
    display: block;
    border: 3px solid var(--clinic-accent);
    background: var(--clinic-soft);
}
.doctor-photo--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--clinic-primary-2);
    text-transform: uppercase;
}
.text-orange-2 { color: var(--clinic-primary-2); font-weight: 600; }
```

- [ ] **Step 5: Smoke-проверка**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8801','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8801/doctors.php' -UseBasicParsing
    "$($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'Петров' -and $r.Content -match 'Кузнецова' -and $r.Content -match 'терапевт') { 'doctors listed OK' } else { 'DOCTORS MISSING' }
    # меню
    $home = Invoke-WebRequest -Uri 'http://127.0.0.1:8801/' -UseBasicParsing
    if ($home.Content -match 'href="/doctors.php"') { 'nav link OK' } else { 'NAV LINK MISSING' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200; doctors listed OK; nav link OK.

- [ ] **Step 6: Commit**

```powershell
git add public/doctors.php public/uploads/doctors/.gitkeep templates/header.php public/assets/css/theme.css
git commit -m "feat: public doctors page + nav link + photo styles"
```

---

### Task P3-4: Правка профиля врачом (`public/doctor_profile_edit.php`) + ссылка из кабинета

**Files:**
- Create: `public/doctor_profile_edit.php`
- Modify: `public/profile.php` (ссылка для врача)

- [ ] **Step 1: Написать `public/doctor_profile_edit.php`**

```php
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

// Текущий профиль (может отсутствовать)
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

    // Фото опционально: обрабатываем только если файл реально выбран
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
                // Удалить старое фото, если было и отличается
                if (!empty($currentPhoto)) {
                    $old = __DIR__ . '/' . $currentPhoto;
                    if (is_file($old)) @unlink($old);
                }
                $newPhotoPath = $saved;
            }
        }
    }

    if (empty($errors)) {
        // UPSERT профиля
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
```

- [ ] **Step 2: Добавить ссылку для врача в `public/profile.php`**

Найти в `public/profile.php` блок с примечанием:
```php
            <p class="text-muted small">
                Полноценный кабинет вашей роли появится в следующих обновлениях.
            </p>
```
и сразу ПЕРЕД ним вставить (ссылка показывается только врачу):
```php
            <?php if (($user['role_code'] ?? '') === 'doctor'): ?>
                <a href="/doctor_profile_edit.php" class="btn btn-orange mb-3">Редактировать профиль врача</a>
            <?php endif; ?>
```

- [ ] **Step 3: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\doctor_profile_edit.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\profile.php
```
Expected: оба без ошибок.

- [ ] **Step 4: Smoke — гость на странице правки → редирект на login**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8802','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8802/doctor_profile_edit.php' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
    "guest: $($r.StatusCode) -> $($r.Headers.Location)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 302 → /login.php.

- [ ] **Step 5: Commit**

```powershell
git add public/doctor_profile_edit.php public/profile.php
git commit -m "feat: doctor self-service profile edit (bio + photo upload)"
```

---

### Task P3-5: Сквозная проверка Фазы 3 + тег

- [ ] **Step 1: Все smoke-тесты**

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_db.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_csrf.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_password.php
& $php -d zend.assertions=1 -d assert.exception=1 tests\smoke_upload.php
```
Expected: 4 строки «… OK».

- [ ] **Step 2: Функциональный тест полного upload-флоу (curl multipart)**

Логинимся врачом, грузим реальный PNG, проверяем БД и файл, проверяем публичную страницу.

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8803','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
# подготовить тестовый PNG
$png = "$env:TEMP\test_doctor.png"
[IO.File]::WriteAllBytes($png, [Convert]::FromBase64String('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))
try {
    $cj = "$env:TEMP\cj_doc.txt"
    if (Test-Path $cj) { Remove-Item $cj }
    # 1) login petrov
    $tok = (Invoke-WebRequest -Uri 'http://127.0.0.1:8803/login.php' -SessionVariable sess -UseBasicParsing).Content
    $token = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($tok).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8803/login.php' -WebSession $sess -Method POST -Body @{ _csrf=$token; email='petrov@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing | Out-Null
    # 2) GET edit page → token
    $ge = Invoke-WebRequest -Uri 'http://127.0.0.1:8803/doctor_profile_edit.php' -WebSession $sess -UseBasicParsing
    $token2 = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($ge.Content).Groups[1].Value
    # 3) POST multipart with file (use curl.exe for reliable multipart)
    $cookie = ($sess.Cookies.GetCookies('http://127.0.0.1:8803') | ForEach-Object { "$($_.Name)=$($_.Value)" }) -join '; '
    $resp = & curl.exe -s -o NUL -w "%{http_code} %{redirect_url}" -b "$cookie" `
        -F "_csrf=$token2" -F "specialization=Тест-специализация" -F "bio=Тест-описание врача" `
        -F "photo=@$png;type=image/png" `
        'http://127.0.0.1:8803/doctor_profile_edit.php'
    "upload POST: $resp"
    # 4) DB check
    & $mysql -uroot dentistry -e "SELECT specialization, photo_path FROM doctor_profile WHERE user_id=3;"
    # 5) public page shows the new photo path
    $pub = Invoke-WebRequest -Uri 'http://127.0.0.1:8803/doctors.php' -UseBasicParsing
    if ($pub.Content -match 'uploads/doctors/doctor_3_') { 'public page shows uploaded photo OK' } else { 'PHOTO NOT ON PUBLIC PAGE' }
} finally {
    Stop-Process -Id $server.Id -Force
    Remove-Item tmp.log -ErrorAction SilentlyContinue
    Remove-Item $png -ErrorAction SilentlyContinue
}
```
Expected: `upload POST: 200` (страница с alert success рендерится напрямую, без редиректа — это норм) ИЛИ 200 с success; `photo_path` вида `uploads/doctors/doctor_3_xxxx.png`; «public page shows uploaded photo OK».

> Примечание: после теста в `doctor_profile` у врача id=3 останется тестовая специализация и фото. В Step 4 ниже вернём seed-значения, чтобы данные демо были аккуратными.

- [ ] **Step 3: Восстановить seed-значения врача id=3 и удалить тестовое фото**

```powershell
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
# удалить загруженные тестовые фото
Get-ChildItem 'public\uploads\doctors\doctor_3_*' -ErrorAction SilentlyContinue | Remove-Item -Force
& $mysql -uroot dentistry -e "UPDATE doctor_profile SET specialization='Врач-стоматолог-терапевт', bio='Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.', photo_path=NULL WHERE user_id=3;"
& $mysql -uroot dentistry -e "SELECT user_id, specialization, photo_path FROM doctor_profile WHERE user_id=3;"
```
Expected: photo_path = NULL, специализация восстановлена.

- [ ] **Step 4: Регрессия — публичные и auth-страницы живы**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8804','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    foreach ($u in @('/', '/services.php', '/blog.php', '/contacts.php', '/doctors.php', '/login.php', '/register.php')) {
        "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8804$u" -UseBasicParsing).StatusCode))  $u"
    }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: все 200.

- [ ] **Step 5: Тег**

```powershell
git tag phase-3-complete
git tag --list 'phase-*'
git log --oneline -8
```

---

## Чек-лист соответствия требованию

| Требование | Где |
|---|---|
| Публичная страница врачей: ФИО, фото, информация | Task P3-3 (`public/doctors.php`) |
| Хранение профиля врача (специализация, био, фото) | Task P3-1 (`doctor_profile`) |
| Врач сам редактирует свою информацию в кабинете | Task P3-4 (`doctor_profile_edit.php`, ссылка из profile.php) |
| Загрузка/смена фотографии | Task P3-2 (`lib/upload.php`) + Task P3-4 |
| Безопасность загрузки (тип/размер/имя файла) | Task P3-2 (finfo MIME, лимит 2 МБ, случайное имя) |
| Доступ к правке только у врача | Task P3-4 (`Auth::requireRole('doctor')`) |
| Гость видит страницу врачей | Task P3-3 (без авторизации) |

---

**Итог Фазы 3:** публичная витрина врачей с фото и описанием; врач самостоятельно ведёт свой профиль (текст + загрузка фотографии) из личного кабинета. Дальше — Фаза 4 (кабинет пациента: история записей и запись на приём).
