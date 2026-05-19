# Стоматологическая клиника — Фаза 5: Кабинет врача

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Кабинет врача: недельное расписание-сетка со всеми слотами (занятые выделены цветом статуса и ФИО пациента), модалка приёма (статус с переводом «Подтверждена»→«Исполнена», чекбоксы оказанных услуг, кнопка протокола), страница написания протокола приёма и его просмотр.

**Architecture:** `public/profile.php` (роутер по роли из Фазы 4) для врача подключает `templates/cabinet_doctor.php` — таблица Пн–Пт × часовые слоты для выбранной недели (`?w` смещение). Занятые ячейки кликабельны → модалка, которую наполняет JSON-эндпоинт `api/doctor_appointment.php`. Изменения (отметка услуг, статус→Исполнена) идут в POST-эндпоинты с проверкой владения (appointment.doctor_id == текущий врач). Протокол пишется на `protocol_edit.php` (поля автоподставляются), просматривается на `protocol_view.php` (HTML; PDF в Фазе 8).

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3 (modals/table), нативный JS (fetch). Хелперы Фаз 1–4.

---

## Что приходит с Фаз 1–4

- `public/profile.php` — роутер по роли: пациент → `cabinet_patient.php`; прочие → дженерик-карточка (врач имеет ссылку «Редактировать профиль врача» из Фазы 3). Сюда добавим ветку врача.
- `lib/appointment.php` — `appt_status_label`, `appt_status_class`, `slot_hours`, `slot_label`, `slot_end_for`, `ru_date_label`.
- `lib/db.php`, `lib/auth.php` (Auth::requireRole/hasRole/user; user() → id, last_name, first_name, middle_name, role_code), `lib/csrf.php` (token/field/check), `lib/sanitize.php` (h, fio_short, fmt_dt, fmt_price).
- `templates/footer.php` — поддерживает `$_pageScripts`.
- Таблицы: `appointment(status ENUM created/confirmed/performed/completed/noshow, doctor_id, patient_id, slot_start, slot_end)`, `service(id,name,price)`, `appointment_service(appointment_id, service_id, price_at_time)` PK(appointment_id,service_id), `protocol(id, appointment_id UNIQUE, protocol_text, recommendations, created_at)`.
- Seed: врач Петров (id3) — appt id3 confirmed (сегодня, пациент Васильев), appt id5 completed, appt id7 noshow, appt id1 created (будущая). Врач Кузнецова (id4) — appt id4 performed (вчера, протокол есть), appt id6 completed (протокол есть), appt id2 created.
- CSS статусов `.status-*` уже есть.

---

## Структура файлов после Фазы 5

```
public/profile.php                  # + ветка doctor (правка)
templates/cabinet_doctor.php        # недельная сетка + модалка (новый)
public/api/doctor_appointment.php   # GET детали записи (новый)
public/api/doctor_save_services.php # POST отметка услуг (новый)
public/api/doctor_set_status.php    # POST статус confirmed→performed (новый)
public/protocol_edit.php            # форма протокола (новый)
public/protocol_view.php            # просмотр протокола HTML (новый)
public/assets/js/doctor.js          # модалка/услуги/статус/протокол (новый)
public/assets/css/theme.css         # стили сетки (правка)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- Тестовые врачи: `petrov@dentistry.local` (id3), `kuznetsova@dentistry.local` (id4) / `Password1!`

---

## Подход к тестированию

- **Доступ/владение** — функциональные тесты curl с куками: врач видит свою сетку; чужую запись не открыть/не изменить; статус меняется только confirmed→performed; гость/пациент получают 403/redirect.
- **Протокол** — E2E: врач отмечает услуги → создаёт протокол → запись в `protocol` + `appointment_service` → просмотр рендерит данные.
- **Регрессия** — публичные/пациентские страницы 200; данные seed не повреждены.

---

## Задачи

---

### Task P5-1: Ветка врача в `profile.php` + недельная сетка (`templates/cabinet_doctor.php`)

**Files:**
- Modify: `public/profile.php`
- Create: `templates/cabinet_doctor.php`
- Modify: `public/assets/css/theme.css`

- [ ] **Step 1: В `public/profile.php` добавить ветку врача.** Найти блок роутинга:

```php
if ($role === 'patient') {
    require __DIR__ . '/../templates/cabinet_patient.php';
} else {
```
заменить на:
```php
if ($role === 'patient') {
    require __DIR__ . '/../templates/cabinet_patient.php';
} elseif ($role === 'doctor') {
    require __DIR__ . '/../templates/cabinet_doctor.php';
} else {
```

И в начале, где задаётся `$_pageScripts` для пациента, добавить врача:
```php
if ($role === 'patient') {
    $_pageScripts = ['/assets/js/booking.js'];
}
```
заменить на:
```php
if ($role === 'patient') {
    $_pageScripts = ['/assets/js/booking.js'];
} elseif ($role === 'doctor') {
    $_pageScripts = ['/assets/js/doctor.js'];
}
```

- [ ] **Step 2: Создать `templates/cabinet_doctor.php`**

```php
<?php
/** @var array $user текущий врач (из profile.php) */
$docId = (int) $user['id'];
$w = (int) ($_GET['w'] ?? 0);

$mondayTs = strtotime('monday this week') + $w * 7 * 86400;
$days = [];
for ($i = 0; $i < 5; $i++) {
    $days[] = date('Y-m-d', $mondayTs + $i * 86400);
}
$rangeStart = $days[0] . ' 00:00:00';
$rangeEnd   = date('Y-m-d', $mondayTs + 5 * 86400) . ' 00:00:00'; // суббота 00:00 (исключая)

$rows = DB::all(
    "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
      WHERE a.doctor_id = :d AND a.slot_start >= :s AND a.slot_start < :e",
    ['d' => $docId, 's' => $rangeStart, 'e' => $rangeEnd]
);
$byKey = [];
foreach ($rows as $r) {
    $byKey[date('Y-m-d H:00:00', strtotime($r['slot_start']))] = $r;
}
?>

<div id="doctor-cabinet" data-csrf="<?= h(Csrf::token()) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Моё расписание</h1>
        <form method="post" action="/logout.php" class="m-0">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-link text-muted">Выйти</button>
        </form>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?w=<?= $w - 1 ?>">← Пред. неделя</a>
        <span class="fw-semibold"><?= h(ru_date_label($days[0])) ?> — <?= h(ru_date_label($days[4])) ?></span>
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?w=<?= $w + 1 ?>">След. неделя →</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center schedule-table mb-0">
            <thead>
                <tr>
                    <th style="width:80px;"></th>
                    <?php foreach ($days as $d): ?>
                        <th><?= h(ru_date_label($d)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (slot_hours() as $h): ?>
                    <tr>
                        <th class="text-muted small"><?= h(slot_label($h)) ?></th>
                        <?php foreach ($days as $d): ?>
                            <?php $key = sprintf('%s %02d:00:00', $d, $h); ?>
                            <?php if (isset($byKey[$key])): ?>
                                <?php $a = $byKey[$key]; ?>
                                <td class="appt-cell <?= h(appt_status_class($a['status'])) ?>" role="button"
                                    data-appt-id="<?= (int) $a['id'] ?>" title="<?= h(appt_status_label($a['status'])) ?>">
                                    <div class="small fw-semibold"><?= h(fio_short($a['last_name'], $a['first_name'], $a['middle_name'])) ?></div>
                                    <div class="x-small"><?= h(appt_status_label($a['status'])) ?></div>
                                </td>
                            <?php else: ?>
                                <td class="text-muted small">—</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модалка приёма -->
<div class="modal fade" id="apptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="apptPatient"></h5>
          <div class="small text-muted"><span id="apptWhen"></span> · <span id="apptStatus" class="fw-semibold"></span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="apptError" class="alert alert-danger d-none"></div>

        <button type="button" class="btn btn-orange btn-sm mb-3 d-none" id="markPerformed">Завершить приём (Исполнена)</button>

        <h6>Оказанные услуги</h6>
        <div id="apptServices" class="mb-3"></div>

        <div id="protocolArea"></div>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Добавить стили в `public/assets/css/theme.css`** (в конец):

```css
/* Doctor schedule */
.schedule-table th, .schedule-table td { vertical-align: middle; }
.appt-cell { cursor: pointer; }
.appt-cell:hover { filter: brightness(0.97); }
.x-small { font-size: 0.72rem; }
.svc-toggle { cursor: pointer; user-select: none; }
```

- [ ] **Step 4: Syntax check + рендер сетки врачом**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\profile.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\cabinet_doctor.php
```
Both no errors. Затем:
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8820','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8820/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8820/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='petrov@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $p = Invoke-WebRequest -Uri 'http://127.0.0.1:8820/profile.php' -WebSession $s -UseBasicParsing
    "doctor profile: $($p.StatusCode), $($p.Content.Length)"
    if ($p.Content -match 'Моё расписание' -and $p.Content -match 'schedule-table' -and $p.Content -match 'doctor.js') { 'schedule renders OK' } else { 'SCHEDULE ISSUE' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200; schedule renders OK. (doctor.js будет 404 до Task P5-6 — это нормально, страница всё равно рендерится.)

- [ ] **Step 5: Commit**

```powershell
git add public/profile.php templates/cabinet_doctor.php public/assets/css/theme.css
git commit -m "feat: doctor weekly schedule grid + appointment modal skeleton"
```

---

### Task P5-2: Эндпоинт деталей записи (`public/api/doctor_appointment.php`)

**Files:**
- Create: `public/api/doctor_appointment.php`

- [ ] **Step 1: Написать `public/api/doctor_appointment.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('doctor')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для врачей.']);
    exit;
}

$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_GET['id'] ?? 0);

$a = DB::one(
    "SELECT a.id, a.slot_start, a.status,
            p.last_name, p.first_name, p.middle_name
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
      WHERE a.id = :id AND a.doctor_id = :d",
    ['id' => $apptId, 'd' => $docId]
);
if ($a === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена.']);
    exit;
}

// Все услуги клиники + отметка «оказана» для этой записи
$provided = [];
foreach (DB::all('SELECT service_id FROM appointment_service WHERE appointment_id = :id', ['id' => $apptId]) as $r) {
    $provided[(int) $r['service_id']] = true;
}
$services = [];
foreach (DB::all('SELECT id, name, price FROM service ORDER BY name') as $s) {
    $services[] = [
        'id'      => (int) $s['id'],
        'name'    => $s['name'],
        'price'   => fmt_price($s['price']),
        'checked' => isset($provided[(int) $s['id']]),
    ];
}

$hasProtocol = DB::one('SELECT id FROM protocol WHERE appointment_id = :id', ['id' => $apptId]) !== null;

echo json_encode([
    'ok' => true,
    'appointment' => [
        'id'            => (int) $a['id'],
        'patient_fio'   => $a['last_name'] . ' ' . $a['first_name'] . ' ' . $a['middle_name'],
        'status'        => $a['status'],
        'status_label'  => appt_status_label($a['status']),
        'when'          => fmt_dt($a['slot_start']),
        'can_perform'   => $a['status'] === 'confirmed',
        'has_protocol'  => $hasProtocol,
    ],
    'services' => $services,
], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 2: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\doctor_appointment.php
```

- [ ] **Step 3: Commit**

```powershell
git add public/api/doctor_appointment.php
git commit -m "feat: doctor appointment detail endpoint (patient, status, services, protocol flag)"
```

---

### Task P5-3: Эндпоинты отметки услуг и статуса

**Files:**
- Create: `public/api/doctor_save_services.php`
- Create: `public/api/doctor_set_status.php`

- [ ] **Step 1: Написать `public/api/doctor_save_services.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('doctor')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для врачей.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_POST['appointment_id'] ?? 0);

$appt = DB::one('SELECT id FROM appointment WHERE id = :id AND doctor_id = :d', ['id' => $apptId, 'd' => $docId]);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена.']);
    exit;
}

$ids = $_POST['service_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_map('intval', $ids)));

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    DB::exec('DELETE FROM appointment_service WHERE appointment_id = :id', ['id' => $apptId]);
    if (!empty($ids)) {
        // только реально существующие услуги; цена фиксируется на момент отметки
        $in = implode(',', array_fill(0, count($ids), '?'));
        $valid = DB::all("SELECT id, price FROM service WHERE id IN ($in)", $ids);
        foreach ($valid as $s) {
            DB::exec(
                'INSERT INTO appointment_service (appointment_id, service_id, price_at_time) VALUES (:a, :s, :p)',
                ['a' => $apptId, 's' => (int) $s['id'], 'p' => $s['price']]
            );
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить услуги.']);
    exit;
}

echo json_encode(['ok' => true]);
```

- [ ] **Step 2: Написать `public/api/doctor_set_status.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('doctor')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для врачей.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_POST['appointment_id'] ?? 0);

// Врач может перевести только из «Подтверждена» в «Исполнена»
$appt = DB::one(
    "SELECT id FROM appointment WHERE id = :id AND doctor_id = :d AND status = 'confirmed'",
    ['id' => $apptId, 'd' => $docId]
);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Статус нельзя изменить.']);
    exit;
}

DB::exec("UPDATE appointment SET status = 'performed' WHERE id = :id", ['id' => $apptId]);
echo json_encode(['ok' => true]);
```

- [ ] **Step 3: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\doctor_save_services.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\doctor_set_status.php
```

- [ ] **Step 4: Commit**

```powershell
git add public/api/doctor_save_services.php public/api/doctor_set_status.php
git commit -m "feat: doctor save-services + set-status (confirmed->performed) endpoints"
```

---

### Task P5-4: Страница протокола приёма (`public/protocol_edit.php`)

**Files:**
- Create: `public/protocol_edit.php`

- [ ] **Step 1: Написать `public/protocol_edit.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/appointment.php';

Auth::requireRole('doctor');
$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

$appt = DB::one(
    "SELECT a.id, a.slot_start,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id AND a.doctor_id = :d",
    ['id' => $apptId, 'd' => $docId]
);
if ($appt === null) {
    http_response_code(404);
    header('Location: /profile.php');
    exit;
}

$services = DB::all(
    "SELECT s.name, aps.price_at_time
       FROM appointment_service aps JOIN service s ON s.id = aps.service_id
      WHERE aps.appointment_id = :id ORDER BY s.name",
    ['id' => $apptId]
);
$protocol = DB::one('SELECT * FROM protocol WHERE appointment_id = :id', ['id' => $apptId]);

$_pageTitle = 'Протокол приёма';
$errors = [];
$form = [
    'protocol_text'   => $protocol['protocol_text'] ?? '',
    'recommendations' => $protocol['recommendations'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['protocol_text']   = trim((string) ($_POST['protocol_text'] ?? ''));
    $form['recommendations'] = trim((string) ($_POST['recommendations'] ?? ''));
    if ($form['protocol_text'] === '') $errors['protocol_text'] = 'Заполните протокол приёма.';

    if (empty($errors)) {
        DB::exec(
            'INSERT INTO protocol (appointment_id, protocol_text, recommendations)
             VALUES (:a, :t, :r)
             ON DUPLICATE KEY UPDATE protocol_text = VALUES(protocol_text), recommendations = VALUES(recommendations)',
            ['a' => $apptId, 't' => $form['protocol_text'], 'r' => $form['recommendations']]
        );
        header('Location: /profile.php?appt=' . $apptId);
        exit;
    }
}

$patientFio = $appt['p_last'] . ' ' . $appt['p_first'] . ' ' . $appt['p_mid'];
$doctorFio  = $appt['d_last'] . ' ' . $appt['d_first'] . ' ' . $appt['d_mid'];

require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4">Протокол приёма</h1>

            <dl class="row mb-3">
                <dt class="col-sm-3">Пациент</dt><dd class="col-sm-9"><?= h($patientFio) ?></dd>
                <dt class="col-sm-3">Врач</dt><dd class="col-sm-9"><?= h($doctorFio) ?></dd>
                <dt class="col-sm-3">Дата и время</dt><dd class="col-sm-9"><?= h(fmt_dt($appt['slot_start'])) ?></dd>
                <dt class="col-sm-3">Услуги</dt>
                <dd class="col-sm-9">
                    <?php if (empty($services)): ?>
                        <span class="text-muted">не отмечены</span>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($services as $s): ?>
                                <li><?= h($s['name']) ?> — <?= h(fmt_price($s['price_at_time'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </dd>
            </dl>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="appointment_id" value="<?= (int) $apptId ?>">
                <div class="mb-3">
                    <label class="form-label">Протокол приёма</label>
                    <textarea name="protocol_text" rows="6" class="form-control<?= isset($errors['protocol_text']) ? ' is-invalid' : '' ?>"><?= h($form['protocol_text']) ?></textarea>
                    <?php if (isset($errors['protocol_text'])): ?><div class="invalid-feedback"><?= h($errors['protocol_text']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Рекомендации</label>
                    <textarea name="recommendations" rows="4" class="form-control"><?= h($form['recommendations']) ?></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-orange">Сохранить</button>
                    <a href="/profile.php?appt=<?= (int) $apptId ?>" class="btn btn-outline-orange">Назад к расписанию</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

- [ ] **Step 2: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\protocol_edit.php
```

- [ ] **Step 3: Smoke — гость → редирект на login**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8821','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8821/protocol_edit.php?appointment_id=3' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
    "guest: $($r.StatusCode) -> $($r.Headers.Location)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 302 → /login.php.

- [ ] **Step 4: Commit**

```powershell
git add public/protocol_edit.php
git commit -m "feat: protocol edit page (auto-filled, upsert protocol, doctor-owned)"
```

---

### Task P5-5: Просмотр протокола (`public/protocol_view.php`)

**Files:**
- Create: `public/protocol_view.php`

HTML-версия (в Фазе 8 станет PDF). Доступ: врач-владелец записи ИЛИ пациент-владелец.

- [ ] **Step 1: Написать `public/protocol_view.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/appointment.php';

if (!Auth::isAuthenticated()) {
    header('Location: /login.php');
    exit;
}
$user   = Auth::user();
$uid    = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id, a.doctor_id,
            pr.protocol_text, pr.recommendations, pr.created_at,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) {
    http_response_code(404);
    echo 'Протокол не найден.';
    exit;
}
// Доступ: врач этой записи, пациент этой записи, либо админ
$allowed = ($uid === (int) $row['doctor_id']) || ($uid === (int) $row['patient_id']) || ($user['role_code'] === 'admin');
if (!$allowed) {
    http_response_code(403);
    echo 'Нет доступа.';
    exit;
}

$services = DB::all(
    "SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name",
    ['id' => $apptId]
);

$_pageTitle = 'Протокол приёма';
require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="clinic-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Протокол приёма</h1>
                <button class="btn btn-outline-orange btn-sm" onclick="window.print()">Печать</button>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-3">Клиника</dt><dd class="col-sm-9"><?= h(SITE_NAME) ?></dd>
                <dt class="col-sm-3">Пациент</dt><dd class="col-sm-9"><?= h($row['p_last'] . ' ' . $row['p_first'] . ' ' . $row['p_mid']) ?></dd>
                <dt class="col-sm-3">Врач</dt><dd class="col-sm-9"><?= h($row['d_last'] . ' ' . $row['d_first'] . ' ' . $row['d_mid']) ?></dd>
                <dt class="col-sm-3">Дата приёма</dt><dd class="col-sm-9"><?= h(fmt_dt($row['slot_start'])) ?></dd>
            </dl>

            <?php if (!empty($services)): ?>
                <h6>Оказанные услуги</h6>
                <ul>
                    <?php foreach ($services as $s): ?>
                        <li><?= h($s['name']) ?> — <?= h(fmt_price($s['price_at_time'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h6>Протокол</h6>
            <p style="white-space: pre-wrap;"><?= h($row['protocol_text']) ?></p>

            <?php if (!empty($row['recommendations'])): ?>
                <h6>Рекомендации</h6>
                <p style="white-space: pre-wrap;"><?= h($row['recommendations']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

- [ ] **Step 2: Syntax check**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\protocol_view.php
```

- [ ] **Step 3: Commit**

```powershell
git add public/protocol_view.php
git commit -m "feat: protocol view (HTML, owner/patient/admin access; PDF in Phase 8)"
```

---

### Task P5-6: JS кабинета врача (`public/assets/js/doctor.js`)

**Files:**
- Create: `public/assets/js/doctor.js`

- [ ] **Step 1: Написать `public/assets/js/doctor.js`**

```js
(function () {
    'use strict';

    var root = document.getElementById('doctor-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;
    var modalEl = document.getElementById('apptModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var currentId = null;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) {
            if (Array.isArray(data[k])) {
                data[k].forEach(function (v) { body.append(k + '[]', v); });
            } else {
                body.set(k, data[k]);
            }
        });
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    var elPatient = document.getElementById('apptPatient');
    var elWhen = document.getElementById('apptWhen');
    var elStatus = document.getElementById('apptStatus');
    var elErr = document.getElementById('apptError');
    var elServices = document.getElementById('apptServices');
    var elProtoArea = document.getElementById('protocolArea');
    var btnPerformed = document.getElementById('markPerformed');

    function openAppt(id) {
        currentId = id;
        elErr.classList.add('d-none');
        elServices.innerHTML = 'Загрузка…';
        elProtoArea.innerHTML = '';
        fetch('/api/doctor_appointment.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { elErr.textContent = d.error || 'Ошибка.'; elErr.classList.remove('d-none'); elServices.innerHTML = ''; modal.show(); return; }
                var a = d.appointment;
                elPatient.textContent = a.patient_fio;
                elWhen.textContent = a.when;
                elStatus.textContent = a.status_label;
                btnPerformed.classList.toggle('d-none', !a.can_perform);

                // услуги
                elServices.innerHTML = '';
                d.services.forEach(function (s) {
                    var id = 'svc_' + s.id;
                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    var cb = document.createElement('input');
                    cb.className = 'form-check-input svc-cb';
                    cb.type = 'checkbox'; cb.id = id; cb.value = s.id; cb.checked = s.checked;
                    var lb = document.createElement('label');
                    lb.className = 'form-check-label svc-toggle'; lb.htmlFor = id;
                    lb.textContent = s.name + ' (' + s.price + ')';
                    wrap.appendChild(cb); wrap.appendChild(lb);
                    elServices.appendChild(wrap);
                });

                // протокол
                if (a.has_protocol) {
                    var view = document.createElement('a');
                    view.className = 'btn btn-orange';
                    view.href = '/protocol_view.php?appointment_id=' + a.id;
                    view.target = '_blank';
                    view.textContent = 'Открыть протокол приёма';
                    elProtoArea.appendChild(view);
                } else {
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'btn btn-orange';
                    add.textContent = 'Добавить протокол приёма';
                    add.addEventListener('click', function () { saveServicesThen('/protocol_edit.php?appointment_id=' + a.id); });
                    elProtoArea.appendChild(add);
                }
                modal.show();
            })
            .catch(function () { elErr.textContent = 'Ошибка загрузки.'; elErr.classList.remove('d-none'); elServices.innerHTML = ''; modal.show(); });
    }

    function collectServiceIds() {
        return Array.prototype.slice.call(elServices.querySelectorAll('.svc-cb:checked')).map(function (cb) { return cb.value; });
    }

    function saveServices() {
        return post('/api/doctor_save_services.php', { appointment_id: currentId, service_ids: collectServiceIds() });
    }

    function saveServicesThen(url) {
        saveServices().then(function (d) {
            if (d.ok) { window.location = url; }
            else { elErr.textContent = d.error || 'Не удалось сохранить услуги.'; elErr.classList.remove('d-none'); }
        });
    }

    document.querySelectorAll('.appt-cell').forEach(function (cell) {
        cell.addEventListener('click', function () { openAppt(cell.dataset.apptId); });
    });

    btnPerformed.addEventListener('click', function () {
        post('/api/doctor_set_status.php', { appointment_id: currentId }).then(function (d) {
            if (d.ok) { location.reload(); }
            else { elErr.textContent = d.error || 'Не удалось изменить статус.'; elErr.classList.remove('d-none'); }
        });
    });

    // Автооткрытие модалки после возврата со страницы протокола (?appt=ID)
    var params = new URLSearchParams(location.search);
    if (params.has('appt')) {
        openAppt(params.get('appt'));
    }
})();
```

- [ ] **Step 2: Проверка раздачи скрипта**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8822','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8822/assets/js/doctor.js' -UseBasicParsing
    "doctor.js: $($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'doctor_appointment' -and $r.Content -match 'doctor_save_services' -and $r.Content -match 'doctor_set_status') { 'js wired OK' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, js wired OK.

- [ ] **Step 3: Commit**

```powershell
git add public/assets/js/doctor.js
git commit -m "feat: doctor cabinet JS (appointment modal, services, status, protocol nav)"
```

---

### Task P5-7: Сквозная проверка Фазы 5 + тег

- [ ] **Step 1: Smoke-тесты**

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
foreach ($t in @('smoke_db','smoke_csrf','smoke_password','smoke_upload','smoke_appointment')) {
    & $php -d zend.assertions=1 -d assert.exception=1 "tests/$t.php"
}
```
Expected: 5 строк «… OK».

- [ ] **Step 2: Функциональный E2E — врач Петров: статус confirmed→performed, отметка услуг, протокол**

Петров (id3) имеет appt id3 (confirmed, сегодня, пациент Васильев). Переводим в Исполнена, отмечаем услуги, создаём протокол, проверяем.

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8823','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8823/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8823/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='petrov@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $prof = Invoke-WebRequest -Uri 'http://127.0.0.1:8823/profile.php' -WebSession $s -UseBasicParsing
    $pc = ([regex]'data-csrf="([a-f0-9]+)"').Match($prof.Content).Groups[1].Value
    # detail of appt 3 (his confirmed)
    $det = (Invoke-WebRequest -Uri 'http://127.0.0.1:8823/api/doctor_appointment.php?id=3' -WebSession $s -UseBasicParsing).Content
    "detail: $det"
    # ownership: appt 4 belongs to Kuznetsova -> must be not found
    $other = (Invoke-WebRequest -Uri 'http://127.0.0.1:8823/api/doctor_appointment.php?id=4' -WebSession $s -UseBasicParsing).Content
    if (($other | ConvertFrom-Json).ok -eq $false) { 'cross-doctor detail blocked OK' } else { 'IDOR! foreign appt readable' }
    # set performed
    $sp = (Invoke-WebRequest -Uri 'http://127.0.0.1:8823/api/doctor_set_status.php' -WebSession $s -Method POST -UseBasicParsing -Body @{ _csrf=$pc; appointment_id='3' }).Content
    "set status: $sp"
    # save services 1 and 2
    $ss = Invoke-WebRequest -Uri 'http://127.0.0.1:8823/api/doctor_save_services.php' -WebSession $s -Method POST -UseBasicParsing -Body "_csrf=$pc&appointment_id=3&service_ids%5B%5D=1&service_ids%5B%5D=2"
    "save services: $($ss.Content)"
    & $mysql -uroot dentistry -e "SELECT appointment_id, service_id FROM appointment_service WHERE appointment_id=3 ORDER BY service_id;"
    # create protocol
    Invoke-WebRequest -Uri 'http://127.0.0.1:8823/protocol_edit.php' -WebSession $s -Method POST -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue -Body @{ _csrf=$pc; appointment_id='3'; protocol_text='Тестовый протокол приёма'; recommendations='Тестовые рекомендации' } | Out-Null
    & $mysql -uroot dentistry -e "SELECT appointment_id, LEFT(protocol_text,20) AS t FROM protocol WHERE appointment_id=3;"
    # status now performed?
    & $mysql -uroot dentistry -e "SELECT id, status FROM appointment WHERE id=3;"
    # protocol view renders
    $pv = Invoke-WebRequest -Uri 'http://127.0.0.1:8823/protocol_view.php?appointment_id=3' -WebSession $s -UseBasicParsing
    if ($pv.Content -match 'Тестовый протокол') { 'protocol view OK' } else { 'PROTOCOL VIEW MISSING' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: detail ok with patient Васильев + 6 services; cross-doctor detail blocked OK; set status ok; appointment_service has rows (3,1)(3,2); protocol row created; appointment 3 status=performed; protocol view OK.

- [ ] **Step 3: Восстановить seed (откат тестовых правок appt 3)**

```powershell
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
& $mysql -uroot dentistry -e "DELETE FROM protocol WHERE appointment_id=3; DELETE FROM appointment_service WHERE appointment_id=3; UPDATE appointment SET status='confirmed' WHERE id=3;"
& $mysql -uroot dentistry -e "SELECT id, status FROM appointment WHERE id=3; SELECT COUNT(*) AS proto FROM protocol; SELECT COUNT(*) AS aps FROM appointment_service;"
```
Expected: appt 3 status=confirmed; proto=3 (исходные); aps=5 (исходные).

- [ ] **Step 4: Негатив — пациент/гость не имеют доступа к врач-эндпоинтам**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8824','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    # guest
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8824/api/doctor_appointment.php?id=3' -UseBasicParsing -SkipHttpErrorCheck
    "guest doctor_appointment: $($r.StatusCode)"
    # patient
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8824/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8824/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='smirnov@example.com'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $r2 = Invoke-WebRequest -Uri 'http://127.0.0.1:8824/api/doctor_appointment.php?id=3' -WebSession $s -UseBasicParsing -SkipHttpErrorCheck
    "patient doctor_appointment: $($r2.StatusCode)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: both 403.

- [ ] **Step 5: Регрессия + чистота**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8825','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    foreach ($u in @('/', '/doctors.php', '/login.php')) { "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8825$u" -UseBasicParsing).StatusCode))  $u" }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
git status --short
```
Expected: все 200; git status чисто.

- [ ] **Step 6: Тег**

```powershell
git tag phase-5-complete
git tag --list 'phase-*'
git log --oneline -10
```

---

## Чек-лист соответствия спецификации (Фаза 5)

| Требование PROMPT.md | Где |
|---|---|
| Страница врача = интерактивное расписание со слотами | P5-1 (недельная сетка) |
| Занятый слот выделен цветом + ФИО пациента | P5-1 (цвет статуса + fio_short) |
| Клик по занятому слоту → модалка записи | P5-1 + P5-6 |
| В модалке: ФИО пациента, статус | P5-2 + P5-6 |
| Статус Подтверждена → Исполнена врачом | P5-3 (doctor_set_status) + P5-6 |
| Все услуги клиники чекбоксами, по умолчанию выкл. | P5-2 (checked-флаги) + P5-6 |
| Клик по названию услуги переключает чекбокс | P5-6 (label htmlFor) |
| Кнопка «Добавить протокол приёма» → страница протокола | P5-6 + P5-4 |
| Автоподстановка ФИО/услуг/даты в протокол | P5-4 |
| Поля «Протокол приёма» и «Рекомендации» + сохранить | P5-4 |
| Возврат на расписание с открытой модалкой | P5-4 (redirect ?appt=) + P5-6 (автооткрытие) |
| После сохранения — кнопка «Открыть протокол приёма» | P5-2 (has_protocol) + P5-6 |
| Протокол открывается (HTML; PDF в Фазе 8) | P5-5 |

Отложено: PDF-рендер протокола → Фаза 8.

---

**Итог Фазы 5:** врач видит недельное расписание, открывает приём, отмечает оказанные услуги, переводит статус в «Исполнена», пишет протокол и открывает его. Дальше — Фаза 6 (регистратор: расписание с выбором врача, запись пациентов, статусы, оплата + чек).
