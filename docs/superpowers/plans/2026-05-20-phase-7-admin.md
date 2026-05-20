# Стоматологическая клиника — Фаза 7: Кабинет администратора

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Кабинет администратора: управление ролями пользователей и статистика; CRUD каталога услуг и блога (создание/редактирование/удаление, с загрузкой изображений).

**Architecture:** `public/profile.php` для роли admin подключает `templates/cabinet_admin.php` (таблица пользователей с per-row сменой роли + блок статистики). Смена роли — POST на `api/admin_set_role.php`. CRUD услуг/статей — страницы в `public/admin/` (`service_edit.php`/`service_delete.php`, `article_edit.php`/`article_delete.php`), на которые уже ведут кнопки из Фазы 1 (видны только админу). Загрузка картинок — через `lib/upload.php` (Фаза 3).

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3. Хелперы Фаз 1–6 (DB, Auth, Csrf, sanitize, upload, appointment).

---

## Что приходит с Фаз 1–6

- `public/profile.php` — роутер ролей (patient/doctor/registrar + дженерик). Добавим admin.
- `public/services.php`, `public/blog.php` — для админа уже рендерят кнопки на `/admin/service_edit.php` и `/admin/article_edit.php` (создание + `?id=` редактирование).
- `lib/upload.php` — `image_upload_validate`, `image_save` (jpeg/png/webp, 2МБ, безопасное имя).
- `lib/db`, `lib/auth` (Auth::requireRole/hasRole/user), `lib/csrf`, `lib/sanitize` (h, fmt_price, fmt_dt).
- Таблицы: `user(role_id→role)`, `service(name,description,image_path,price)`, `blog_article(title,body,image_path,author_id)`, `appointment(status)`, `payment(total_amount)`, `appointment_service(service_id …)`.
- Роли: 1 admin, 2 registrar, 3 doctor, 4 patient (`role` таблица).
- `public/uploads/services/`, `public/uploads/blog/` существуют.

---

## Структура файлов после Фазы 7

```
public/profile.php                  # + ветка admin (правка)
templates/cabinet_admin.php         # роли пользователей + статистика (новый)
public/api/admin_set_role.php       # POST смена роли (новый)
public/admin/service_edit.php       # создать/редактировать услугу (новый)
public/admin/service_delete.php     # удалить услугу (новый)
public/admin/article_edit.php       # создать/редактировать статью (новый)
public/admin/article_delete.php     # удалить статью (новый)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- Админ: `admin@dentistry.local` / `Password1!` (id=1)

---

## Подход к тестированию

- **Доступ** — не-админ → 403/redirect на все админ-страницы и эндпоинт.
- **Роли** — смена роли пользователя сохраняется; админ не может сменить роль самому себе (защита от потери доступа).
- **Услуги/блог** — создание/редактирование/удаление меняет БД; удаление услуги, используемой в записях, отклоняется с сообщением.
- ⚠️ Текстовые поля в curl-тестах — ASCII (кириллица искажается в командной строке; приложение хранит её корректно).
- Все правки тестовых данных откатываются; регрессия публичных страниц.

---

## Задачи

---

### Task P7-1: Ветка админа + кабинет (`templates/cabinet_admin.php`)

**Files:**
- Modify: `public/profile.php`
- Create: `templates/cabinet_admin.php`

- [ ] **Step 1: В `public/profile.php` добавить ветку admin для include.** Найти:
```php
} elseif ($role === 'registrar') {
    require __DIR__ . '/../templates/cabinet_registrar.php';
} else {
```
заменить на:
```php
} elseif ($role === 'registrar') {
    require __DIR__ . '/../templates/cabinet_registrar.php';
} elseif ($role === 'admin') {
    require __DIR__ . '/../templates/cabinet_admin.php';
} else {
```
(Скрипты админу не нужны — формы обычные POST.)

- [ ] **Step 2: Создать `templates/cabinet_admin.php`**

```php
<?php
/** @var array $user админ */
$users = DB::all(
    "SELECT u.id, u.last_name, u.first_name, u.middle_name, u.email, u.role_id
       FROM user u ORDER BY u.role_id, u.last_name"
);
$roles = DB::all("SELECT id, name FROM role ORDER BY id");

// Статистика
$stat = [
    'patients'   => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=4")['c'],
    'doctors'    => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=3")['c'],
    'services'   => (int) DB::one("SELECT COUNT(*) c FROM service")['c'],
    'articles'   => (int) DB::one("SELECT COUNT(*) c FROM blog_article")['c'],
    'appts'      => (int) DB::one("SELECT COUNT(*) c FROM appointment")['c'],
    'revenue'    => (float) DB::one("SELECT COALESCE(SUM(total_amount),0) s FROM payment")['s'],
];
$byStatus = [];
foreach (DB::all("SELECT status, COUNT(*) c FROM appointment GROUP BY status") as $r) {
    $byStatus[$r['status']] = (int) $r['c'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Панель администратора</h1>
    <form method="post" action="/logout.php" class="m-0"><?= Csrf::field() ?><button class="btn btn-link text-muted">Выйти</button></form>
</div>

<!-- Статистика -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Пациенты', $stat['patients']],
        ['Врачи', $stat['doctors']],
        ['Услуги', $stat['services']],
        ['Статьи блога', $stat['articles']],
        ['Записей всего', $stat['appts']],
        ['Выручка', fmt_price($stat['revenue'])],
    ];
    foreach ($cards as [$label, $val]): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="clinic-card p-3 text-center h-100">
                <div class="h4 mb-0 text-orange-2"><?= h((string) $val) ?></div>
                <div class="small text-muted"><?= h($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="clinic-card p-3 mb-4">
    <h5 class="mb-3">Записи по статусам</h5>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (APPT_STATUSES as $st): ?>
            <span class="status-badge <?= h(appt_status_class($st)) ?>"><?= h(appt_status_label($st)) ?>: <?= (int) ($byStatus[$st] ?? 0) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- Управление ролями -->
<div class="clinic-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Пользователи и роли</h5>
        <div class="d-flex gap-2">
            <a href="/services.php" class="btn btn-outline-orange btn-sm">Каталог услуг</a>
            <a href="/blog.php" class="btn btn-outline-orange btn-sm">Блог</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>ФИО</th><th>Email</th><th style="width:260px;">Роль</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= h($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['middle_name']) ?></td>
                        <td class="small"><?= h($u['email']) ?></td>
                        <td>
                            <?php if ((int) $u['id'] === (int) $user['id']): ?>
                                <span class="text-muted small">— вы (роль не меняется) —</span>
                            <?php else: ?>
                                <form method="post" action="/api/admin_set_role.php" class="d-flex gap-2">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="role_id" class="form-select form-select-sm">
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= (int) $r['id'] ?>" <?= $r['id'] == $u['role_id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-orange btn-sm">OK</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 3: Syntax check + рендер**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\profile.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\cabinet_admin.php
```
Both clean. Then:
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8850','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8850/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8850/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='admin@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $p = Invoke-WebRequest -Uri 'http://127.0.0.1:8850/profile.php' -WebSession $s -UseBasicParsing
    "admin: $($p.StatusCode), $($p.Content.Length)"
    if ($p.Content -match 'Панель администратора' -and $p.Content -match 'Пользователи и роли' -and $p.Content -match 'admin_set_role') { 'admin cabinet OK' } else { 'CABINET ISSUE' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200; admin cabinet OK.

- [ ] **Step 4: Commit**
```powershell
git add public/profile.php templates/cabinet_admin.php
git commit -m "feat: admin cabinet (statistics + user role management UI)"
```

---

### Task P7-2: Эндпоинт смены роли (`public/api/admin_set_role.php`)

**Files:**
- Create: `public/api/admin_set_role.php`

- [ ] **Step 1: Написать `public/api/admin_set_role.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();

$adminId = (int) Auth::user()['id'];
$userId  = (int) ($_POST['user_id'] ?? 0);
$roleId  = (int) ($_POST['role_id'] ?? 0);

// Нельзя менять роль самому себе (защита от потери доступа)
if ($userId === $adminId) { header('Location: /profile.php'); exit; }
// Роль должна существовать, пользователь — тоже
$roleOk = DB::one("SELECT id FROM role WHERE id = :id", ['id' => $roleId]);
$userOk = DB::one("SELECT id FROM user WHERE id = :id", ['id' => $userId]);
if ($roleOk !== null && $userOk !== null) {
    DB::exec("UPDATE user SET role_id = :r WHERE id = :u", ['r' => $roleId, 'u' => $userId]);
}
header('Location: /profile.php');
exit;
```

- [ ] **Step 2: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\admin_set_role.php
git add public/api/admin_set_role.php
git commit -m "feat: admin set-role endpoint (self-change blocked)"
```

---

### Task P7-3: CRUD услуг (`public/admin/service_edit.php`, `service_delete.php`)

**Files:**
- Create: `public/admin/service_edit.php`
- Create: `public/admin/service_delete.php`

- [ ] **Step 1: `public/admin/service_edit.php`** (создание при отсутствии id, иначе редактирование). Пути к корню — `__DIR__ . '/../../'`.

```php
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
```

- [ ] **Step 2: `public/admin/service_delete.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /services.php'); exit; }
Csrf::requireValid();

$id = (int) ($_POST['id'] ?? 0);
$service = DB::one("SELECT image_path FROM service WHERE id = :id", ['id' => $id]);
if ($service === null) { header('Location: /services.php'); exit; }

// Нельзя удалить услугу, использованную в записях (FK RESTRICT) — покажем сообщение
$used = DB::one("SELECT 1 FROM appointment_service WHERE service_id = :id LIMIT 1", ['id' => $id]);
if ($used !== null) {
    $_pageTitle = 'Удаление невозможно';
    require __DIR__ . '/../../templates/header.php';
    echo '<div class="alert alert-danger">Услуга используется в записях пациентов, поэтому её нельзя удалить. Отредактируйте её вместо удаления.</div>';
    echo '<a href="/services.php" class="btn btn-orange">К каталогу</a>';
    require __DIR__ . '/../../templates/footer.php';
    exit;
}

if (!empty($service['image_path']) && is_file(__DIR__ . '/../' . $service['image_path'])) @unlink(__DIR__ . '/../' . $service['image_path']);
DB::exec("DELETE FROM service WHERE id = :id", ['id' => $id]);
header('Location: /services.php');
exit;
```

- [ ] **Step 3: Syntax check + smoke (гость → login) + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\admin\service_edit.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\admin\service_delete.php
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8851','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8851/admin/service_edit.php' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue; "guest service_edit: $($r.StatusCode) -> $($r.Headers.Location)" } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
git add public/admin/service_edit.php public/admin/service_delete.php
git commit -m "feat: admin services CRUD (create/edit/delete with image, FK-safe delete)"
```
Expected: guest → 302 /login.php.

---

### Task P7-4: CRUD блога (`public/admin/article_edit.php`, `article_delete.php`)

**Files:**
- Create: `public/admin/article_edit.php`
- Create: `public/admin/article_delete.php`

- [ ] **Step 1: `public/admin/article_edit.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/upload.php';

Auth::requireRole('admin');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$article = $id > 0 ? DB::one("SELECT * FROM blog_article WHERE id = :id", ['id' => $id]) : null;
if ($id > 0 && $article === null) { http_response_code(404); header('Location: /blog.php'); exit; }

$_pageTitle = $article ? 'Редактирование статьи' : 'Новая статья';
$errors = [];
$form = ['title' => $article['title'] ?? '', 'body' => $article['body'] ?? ''];
$photo = $article['image_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['body']  = trim((string) ($_POST['body'] ?? ''));
    if ($form['title'] === '') $errors['title'] = 'Введите заголовок.';
    if ($form['body'] === '')  $errors['body'] = 'Введите текст статьи.';

    $newPhoto = $photo;
    if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = image_upload_validate($_FILES['image']);
        if ($err !== null) $errors['image'] = $err;
        else {
            $saved = image_save($_FILES['image'], __DIR__ . '/../uploads/blog', 'uploads/blog', 'article');
            if ($saved === null) $errors['image'] = 'Не удалось сохранить файл.';
            else { if (!empty($photo) && is_file(__DIR__ . '/../' . $photo)) @unlink(__DIR__ . '/../' . $photo); $newPhoto = $saved; }
        }
    }

    if (empty($errors)) {
        if ($article) {
            DB::exec("UPDATE blog_article SET title=:t, body=:b, image_path=:i WHERE id=:id",
                ['t'=>$form['title'],'b'=>$form['body'],'i'=>$newPhoto,'id'=>$id]);
        } else {
            DB::exec("INSERT INTO blog_article (title, body, image_path, author_id) VALUES (:t,:b,:i,:a)",
                ['t'=>$form['title'],'b'=>$form['body'],'i'=>$newPhoto,'a'=>(int)Auth::user()['id']]);
        }
        header('Location: /blog.php');
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
                    <label class="form-label">Заголовок</label>
                    <input type="text" name="title" class="form-control<?= isset($errors['title'])?' is-invalid':'' ?>" value="<?= h($form['title']) ?>">
                    <?php if(isset($errors['title'])): ?><div class="invalid-feedback"><?= h($errors['title']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Текст</label>
                    <textarea name="body" rows="6" class="form-control<?= isset($errors['body'])?' is-invalid':'' ?>"><?= h($form['body']) ?></textarea>
                    <?php if(isset($errors['body'])): ?><div class="invalid-feedback"><?= h($errors['body']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Изображение (JPEG/PNG/WebP, до 2 МБ)</label>
                    <?php if(!empty($photo)): ?><div class="mb-2"><img src="/<?= h($photo) ?>" alt="" style="max-height:90px;border-radius:8px;" onerror="this.style.display='none'"></div><?php endif; ?>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control<?= isset($errors['image'])?' is-invalid':'' ?>">
                    <?php if(isset($errors['image'])): ?><div class="invalid-feedback"><?= h($errors['image']) ?></div><?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-orange">Сохранить</button>
                    <a href="/blog.php" class="btn btn-outline-orange">Отмена</a>
                    <?php if($article): ?><button type="submit" form="delForm" class="btn btn-outline-danger ms-auto">Удалить</button><?php endif; ?>
                </div>
            </form>
            <?php if($article): ?>
                <form id="delForm" method="post" action="/admin/article_delete.php" onsubmit="return confirm('Удалить статью?');">
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
```

- [ ] **Step 2: `public/admin/article_delete.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /blog.php'); exit; }
Csrf::requireValid();

$id = (int) ($_POST['id'] ?? 0);
$article = DB::one("SELECT image_path FROM blog_article WHERE id = :id", ['id' => $id]);
if ($article !== null) {
    if (!empty($article['image_path']) && is_file(__DIR__ . '/../' . $article['image_path'])) @unlink(__DIR__ . '/../' . $article['image_path']);
    DB::exec("DELETE FROM blog_article WHERE id = :id", ['id' => $id]);
}
header('Location: /blog.php');
exit;
```

- [ ] **Step 3: Syntax check + smoke + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\admin\article_edit.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\admin\article_delete.php
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8852','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8852/admin/article_edit.php' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue; "guest article_edit: $($r.StatusCode) -> $($r.Headers.Location)" } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
git add public/admin/article_edit.php public/admin/article_delete.php
git commit -m "feat: admin blog CRUD (create/edit/delete with image)"
```
Expected: guest → 302 /login.php.

---

### Task P7-5: Сквозная проверка Фазы 7 + тег

- [ ] **Step 1: Smoke-тесты** (5 файлов) — все «OK».

- [ ] **Step 2: E2E админа** (ASCII в полях). Логин админом; создать услугу; проверить в каталоге; отредактировать; удалить; сменить роль пользователя и вернуть; создать/удалить статью.

```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'; $mysql='C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8853','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8853/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='admin@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $sc = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match((Invoke-WebRequest -Uri 'http://127.0.0.1:8853/admin/service_edit.php' -WebSession $s -UseBasicParsing).Content).Groups[1].Value
    # create service
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/admin/service_edit.php' -WebSession $s -Method POST -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing -Body @{ _csrf=$sc; id='0'; name='TEST SERVICE'; description='desc'; price='1234.50' } | Out-Null
    & $mysql -uroot dentistry -e "SELECT id,name,price FROM service WHERE name='TEST SERVICE';"
    $newId = (& $mysql -uroot dentistry -N -e "SELECT id FROM service WHERE name='TEST SERVICE' LIMIT 1").Trim()
    "new service id: $newId"
    # delete it
    $dc = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match((Invoke-WebRequest -Uri "http://127.0.0.1:8853/admin/service_edit.php?id=$newId" -WebSession $s -UseBasicParsing).Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/admin/service_delete.php' -WebSession $s -Method POST -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing -Body @{ _csrf=$dc; id=$newId } | Out-Null
    "service after delete: $((& $mysql -uroot dentistry -N -e "SELECT COUNT(*) FROM service WHERE id=$newId").Trim())"
    # delete-protected: service 1 is used in appointment_service
    $dc2 = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match((Invoke-WebRequest -Uri 'http://127.0.0.1:8853/admin/service_edit.php?id=1' -WebSession $s -UseBasicParsing).Content).Groups[1].Value
    $prot = Invoke-WebRequest -Uri 'http://127.0.0.1:8853/admin/service_delete.php' -WebSession $s -Method POST -UseBasicParsing -Body @{ _csrf=$dc2; id='1' }
    if ($prot.Content -match 'нельзя удалить') { 'used-service delete blocked OK' } else { 'PROTECTION FAILED' }
    & $mysql -uroot dentistry -N -e "SELECT CONCAT('svc1_exists=',COUNT(*)) FROM service WHERE id=1;"
    # change role: patient 6 -> doctor, then back
    $rc = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match((Invoke-WebRequest -Uri 'http://127.0.0.1:8853/profile.php' -WebSession $s -UseBasicParsing).Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/api/admin_set_role.php' -WebSession $s -Method POST -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing -Body @{ _csrf=$rc; user_id='6'; role_id='3' } | Out-Null
    "user6 role now: $((& $mysql -uroot dentistry -N -e "SELECT role_id FROM user WHERE id=6").Trim()) (expect 3)"
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/api/admin_set_role.php' -WebSession $s -Method POST -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing -Body @{ _csrf=$rc; user_id='6'; role_id='4' } | Out-Null
    "user6 role restored: $((& $mysql -uroot dentistry -N -e "SELECT role_id FROM user WHERE id=6").Trim()) (expect 4)"
    # self-role-change blocked: admin id=1 try set to patient
    Invoke-WebRequest -Uri 'http://127.0.0.1:8853/api/admin_set_role.php' -WebSession $s -Method POST -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing -Body @{ _csrf=$rc; user_id='1'; role_id='4' } | Out-Null
    "admin self role (expect 1): $((& $mysql -uroot dentistry -N -e "SELECT role_id FROM user WHERE id=1").Trim())"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: TEST SERVICE created then deleted (count 0); used-service delete blocked OK + svc1_exists=1; user6 role 3 then back to 4; admin self role stays 1.

- [ ] **Step 3: Негатив — не-админ → 403/redirect**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8854','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8854/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8854/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='petrov@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8854/admin/service_edit.php' -WebSession $s -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
    "doctor->service_edit: $($r.StatusCode) -> $($r.Headers.Location)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 302 → / (requireRole перенаправляет не-админа на главную).

- [ ] **Step 4: Регрессия + чистота + тег**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8855','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { foreach ($u in @('/', '/services.php', '/blog.php', '/doctors.php')) { "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8855$u" -UseBasicParsing).StatusCode))  $u" } } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT COUNT(*) AS services FROM service; SELECT COUNT(*) AS articles FROM blog_article;"
git status --short
git tag phase-7-complete
git tag --list 'phase-*'
```
Expected: pages 200; services=6, articles=3 (исходные); git clean; тег создан.

---

## Чек-лист соответствия спецификации (Фаза 7)

| Требование PROMPT.md | Где |
|---|---|
| Админ меняет роли пользователей | P7-1 (UI) + P7-2 (admin_set_role) |
| Просмотр статистики | P7-1 (карточки + записи по статусам + выручка) |
| Блог: создавать/редактировать/удалять статьи | P7-4 (article_edit/delete), кнопки из Фазы 1 |
| Каталог: добавлять/редактировать/удалять услуги | P7-3 (service_edit/delete), кнопки из Фазы 1 |
| Загрузка изображений услуг/статей | P7-3, P7-4 (lib/upload.php) |
| Доступ только админу | requireRole('admin') во всех файлах |

---

**Итог Фазы 7:** администратор управляет ролями, видит статистику, ведёт каталог услуг и блог (с картинками). Остаётся Фаза 8 — PDF-документы (протокол, чек, мед.карта) и автоматические email-напоминания за день до приёма.
