# Стоматологическая клиника — Фаза 6: Кабинет регистратора

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Кабинет регистратора: расписание с выбором врача; запись пациента на свободный слот; модалка записи со сменой статуса, способом оплаты, отменой и оплатой (с формированием чека и автопереводом записи в «Завершена»).

**Architecture:** `public/profile.php` для роли registrar подключает `templates/cabinet_registrar.php` — селектор врача (`?doctor_id`) + недельная сетка (`?w`), как у врача, но свободные будущие слоты кликабельны (модалка записи пациента), занятые — модалка управления. Действия идут в POST-эндпоинты (`registrar_*`) с проверкой роли и CSRF. Чек — HTML-страница `receipt.php` (PDF в Фазе 8); оплата фиксируется в `payment` и переводит запись в `completed`.

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3, нативный JS (fetch). Хелперы Фаз 1–5 (`lib/appointment.php`: slot_hours, is_valid_slot, slot_end_for, ru_date_label, appt_status_label/class, APPT_STATUSES).

---

## Что приходит с Фаз 1–5

- `public/profile.php` — роутер: patient→cabinet_patient, doctor→cabinet_doctor, иначе дженерик. Добавим registrar.
- `lib/appointment.php` (см. функции выше), `lib/db.php`, `lib/auth.php`, `lib/csrf.php`, `lib/sanitize.php` (h, fio_short, fmt_dt, fmt_price).
- Таблицы: `appointment(patient_id,doctor_id,slot_start,slot_end,status)`, `appointment_service(appointment_id,service_id,price_at_time)`, `payment(id,appointment_id UNIQUE,method ENUM('cash','card'),total_amount,paid_at)`, `protocol`, `service`, `user`.
- Статусная модель: created→confirmed→performed→completed, плюс noshow. Оплата ставится только для performed → completed.
- config: SITE_NAME, CLINIC_ADDRESS, CLINIC_PHONE, CLINIC_EMAIL.
- Сетка/модалки врача (`templates/cabinet_doctor.php`, `public/assets/js/doctor.js`) — образец для регистратора.

---

## Структура файлов после Фазы 6

```
public/profile.php                      # + ветка registrar (правка)
templates/cabinet_registrar.php         # селектор врача + сетка + 2 модалки (новый)
public/api/registrar_book.php           # POST создать запись (новый)
public/api/registrar_appointment.php    # GET детали (новый)
public/api/registrar_set_status.php     # POST статус (новый)
public/api/registrar_cancel.php         # POST отмена/удаление (новый)
public/api/registrar_pay.php            # POST оплата → completed + receipt url (новый)
public/receipt.php                      # HTML-чек (новый)
public/assets/js/registrar.js           # логика кабинета (новый)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- Регистратор: `registrar@dentistry.local` / `Password1!`. Врачи id 3,4. Пациенты id 5–8.

---

## Подход к тестированию

- **Доступ/CSRF** — функциональные curl-тесты: не-регистратор → 403; POST без CSRF → 400.
- **Запись** — registrar бронирует свободный будущий слот выбранного врача → строка appointment(status=created); повторная бронь того же слота отклоняется.
- **Оплата** — для performed-записи с услугами: оплата создаёт payment, переводит в completed, чек открывается и содержит сумму; «оплатить» недоступна для не-performed.
- **Отмена** — удаляет запись (и связанные строки каскадом).
- ⚠️ ASCII в curl-тестах для текстовых полей (кириллица искажается в командной строке; приложение кириллицу хранит корректно).
- Все правки тестовых данных откатываются; регрессия публичных/врачебных/пациентских страниц.

---

## Задачи

---

### Task P6-1: Ветка регистратора + кабинет (`templates/cabinet_registrar.php`)

**Files:**
- Modify: `public/profile.php`
- Create: `templates/cabinet_registrar.php`

- [ ] **Step 1: В `public/profile.php` добавить ветку registrar.** Найти:
```php
} elseif ($role === 'doctor') {
    $_pageScripts = ['/assets/js/doctor.js'];
}
```
заменить на:
```php
} elseif ($role === 'doctor') {
    $_pageScripts = ['/assets/js/doctor.js'];
} elseif ($role === 'registrar') {
    $_pageScripts = ['/assets/js/registrar.js'];
}
```
И найти:
```php
} elseif ($role === 'doctor') {
    require __DIR__ . '/../templates/cabinet_doctor.php';
} else {
```
заменить на:
```php
} elseif ($role === 'doctor') {
    require __DIR__ . '/../templates/cabinet_doctor.php';
} elseif ($role === 'registrar') {
    require __DIR__ . '/../templates/cabinet_registrar.php';
} else {
```

- [ ] **Step 2: Создать `templates/cabinet_registrar.php`**

```php
<?php
/** @var array $user регистратор */
$doctors = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 3 ORDER BY last_name");
$patients = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 4 ORDER BY last_name");

$selDoctor = (int) ($_GET['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
$w = (int) ($_GET['w'] ?? 0);
$mondayTs = strtotime('monday this week') + $w * 7 * 86400;
$days = [];
for ($i = 0; $i < 5; $i++) $days[] = date('Y-m-d', $mondayTs + $i * 86400);
$rangeStart = $days[0] . ' 00:00:00';
$rangeEnd   = date('Y-m-d', $mondayTs + 5 * 86400) . ' 00:00:00';

$byKey = [];
if ($selDoctor > 0) {
    $rows = DB::all(
        "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
           FROM appointment a JOIN user p ON p.id = a.patient_id
          WHERE a.doctor_id = :d AND a.slot_start >= :s AND a.slot_start < :e",
        ['d' => $selDoctor, 's' => $rangeStart, 'e' => $rangeEnd]
    );
    foreach ($rows as $r) $byKey[date('Y-m-d H:00:00', strtotime($r['slot_start']))] = $r;
}
?>

<div id="registrar-cabinet" data-csrf="<?= h(Csrf::token()) ?>" data-doctor="<?= (int) $selDoctor ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Расписание</h1>
        <form method="post" action="/logout.php" class="m-0">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-link text-muted">Выйти</button>
        </form>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label class="form-label mb-0 small">Врач</label>
            <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $d['id'] == $selDoctor ? 'selected' : '' ?>>
                        <?= h($d['last_name'] . ' ' . $d['first_name'] . ' ' . $d['middle_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="w" value="<?= (int) $w ?>">
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?doctor_id=<?= (int) $selDoctor ?>&w=<?= $w - 1 ?>">← Пред. неделя</a>
        <span class="fw-semibold"><?= h(ru_date_label($days[0])) ?> — <?= h(ru_date_label($days[4])) ?></span>
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?doctor_id=<?= (int) $selDoctor ?>&w=<?= $w + 1 ?>">След. неделя →</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center schedule-table mb-0">
            <thead>
                <tr><th style="width:80px;"></th><?php foreach ($days as $d): ?><th><?= h(ru_date_label($d)) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach (slot_hours() as $hh): ?>
                    <tr>
                        <th class="text-muted small"><?= h(slot_label($hh)) ?></th>
                        <?php foreach ($days as $d): ?>
                            <?php $key = sprintf('%s %02d:00:00', $d, $hh); ?>
                            <?php if (isset($byKey[$key])): ?>
                                <?php $a = $byKey[$key]; ?>
                                <td class="appt-cell <?= h(appt_status_class($a['status'])) ?>" role="button" data-appt-id="<?= (int) $a['id'] ?>" title="<?= h(appt_status_label($a['status'])) ?>">
                                    <div class="small fw-semibold"><?= h(fio_short($a['last_name'], $a['first_name'], $a['middle_name'])) ?></div>
                                    <div class="x-small"><?= h(appt_status_label($a['status'])) ?></div>
                                </td>
                            <?php elseif (is_valid_slot($key)): ?>
                                <td class="free-cell" role="button" data-slot="<?= h($key) ?>">+ записать</td>
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

<!-- Модалка записи пациента (свободный слот) -->
<div class="modal fade" id="bookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Запись пациента</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="bookError" class="alert alert-danger d-none"></div>
        <p class="small text-muted mb-2">Слот: <span id="bookSlot"></span></p>
        <label class="form-label">Пациент</label>
        <select id="bookPatient" class="form-select mb-3">
            <?php foreach ($patients as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= h($p['last_name'] . ' ' . $p['first_name'] . ' ' . $p['middle_name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-orange" id="bookConfirm">Записать</button>
      </div>
    </div>
  </div>
</div>

<!-- Модалка управления записью (занятый слот) -->
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title mb-0" id="mgPatient"></h5><div class="small text-muted" id="mgWhen"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="mgError" class="alert alert-danger d-none"></div>

        <label class="form-label">Статус</label>
        <div class="input-group mb-3">
            <select id="mgStatus" class="form-select">
                <option value="created">Создана</option>
                <option value="confirmed">Подтверждена</option>
                <option value="performed">Исполнена</option>
                <option value="noshow">Не явка</option>
            </select>
            <button type="button" class="btn btn-outline-orange" id="mgStatusSave">Сохранить</button>
        </div>

        <h6>Оказанные услуги</h6>
        <ul id="mgServices" class="small mb-2"></ul>
        <p class="fw-semibold mb-3">Итого: <span id="mgTotal"></span></p>

        <label class="form-label">Способ оплаты</label>
        <select id="mgPayMethod" class="form-select mb-3">
            <option value="">— выберите —</option>
            <option value="cash">Наличными</option>
            <option value="card">Картой</option>
        </select>

        <div id="mgPaidInfo" class="alert alert-success d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto" id="mgCancelBtn">Отменить запись</button>
        <button type="button" class="btn btn-orange" id="mgPayBtn" disabled>Оплатить</button>
      </div>
    </div>
  </div>
</div>

<!-- Подтверждение отмены -->
<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Отмена записи</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Удалить эту запись? Действие необратимо.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-danger" id="cancelConfirmYes">Да, удалить</button>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Syntax check + рендер**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\profile.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\cabinet_registrar.php
```
Both clean. Then login as registrar and confirm:
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8840','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8840/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8840/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='registrar@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $p = Invoke-WebRequest -Uri 'http://127.0.0.1:8840/profile.php' -WebSession $s -UseBasicParsing
    "registrar: $($p.StatusCode), $($p.Content.Length)"
    if ($p.Content -match 'Расписание' -and $p.Content -match 'name="doctor_id"' -and $p.Content -match 'registrar.js' -and $p.Content -match 'free-cell|appt-cell') { 'registrar cabinet OK' } else { 'CABINET ISSUE' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200; registrar cabinet OK.

- [ ] **Step 4: Commit**
```powershell
git add public/profile.php templates/cabinet_registrar.php
git commit -m "feat: registrar cabinet (doctor selector, schedule grid, book/manage/cancel modals)"
```

---

### Task P6-2: Эндпоинты записи и деталей

**Files:**
- Create: `public/api/registrar_book.php`
- Create: `public/api/registrar_appointment.php`

- [ ] **Step 1: `public/api/registrar_book.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$doctorId  = (int) ($_POST['doctor_id'] ?? 0);
$patientId = (int) ($_POST['patient_id'] ?? 0);
$slot      = (string) ($_POST['slot_start'] ?? '');

$doc = DB::one("SELECT id FROM user WHERE id = :id AND role_id = 3", ['id' => $doctorId]);
$pat = DB::one("SELECT id FROM user WHERE id = :id AND role_id = 4", ['id' => $patientId]);
if ($doc === null || $pat === null) { echo json_encode(['ok'=>false,'error'=>'Врач или пациент не найден.']); exit; }
if (!is_valid_slot($slot)) { echo json_encode(['ok'=>false,'error'=>'Недопустимый слот.']); exit; }

$taken = DB::one("SELECT id FROM appointment WHERE doctor_id = :d AND slot_start = :s", ['d'=>$doctorId,'s'=>$slot]);
if ($taken !== null) { echo json_encode(['ok'=>false,'error'=>'Слот уже занят.']); exit; }

DB::exec(
    "INSERT INTO appointment (patient_id, doctor_id, slot_start, slot_end, status) VALUES (:p,:d,:s,:e,'created')",
    ['p'=>$patientId,'d'=>$doctorId,'s'=>$slot,'e'=>slot_end_for($slot)]
);
echo json_encode(['ok'=>true]);
```

- [ ] **Step 2: `public/api/registrar_appointment.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }

$apptId = (int) ($_GET['id'] ?? 0);
$a = DB::one(
    "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
       FROM appointment a JOIN user p ON p.id = a.patient_id WHERE a.id = :id",
    ['id' => $apptId]
);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }

$services = [];
$total = 0.0;
foreach (DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id'=>$apptId]) as $s) {
    $services[] = ['name' => $s['name'], 'price' => fmt_price($s['price_at_time'])];
    $total += (float) $s['price_at_time'];
}
$payment = DB::one("SELECT method FROM payment WHERE appointment_id = :id", ['id'=>$apptId]);

echo json_encode([
    'ok' => true,
    'appointment' => [
        'id'           => (int) $a['id'],
        'patient_fio'  => $a['last_name'].' '.$a['first_name'].' '.$a['middle_name'],
        'status'       => $a['status'],
        'status_label' => appt_status_label($a['status']),
        'when'         => fmt_dt($a['slot_start']),
        'total'        => fmt_price($total),
        'can_pay'      => ($a['status'] === 'performed' && $payment === null),
        'paid'         => $payment !== null,
        'pay_method'   => $payment['method'] ?? null,
    ],
    'services' => $services,
], JSON_UNESCAPED_UNICODE);
```

- [ ] **Step 3: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\registrar_book.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\registrar_appointment.php
git add public/api/registrar_book.php public/api/registrar_appointment.php
git commit -m "feat: registrar book + appointment detail endpoints"
```

---

### Task P6-3: Эндпоинты статуса, отмены и оплаты

**Files:**
- Create: `public/api/registrar_set_status.php`
- Create: `public/api/registrar_cancel.php`
- Create: `public/api/registrar_pay.php`

- [ ] **Step 1: `public/api/registrar_set_status.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$apptId = (int) ($_POST['appointment_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
// «completed» ставится только через оплату — здесь недоступно
if (!in_array($status, ['created','confirmed','performed','noshow'], true)) { echo json_encode(['ok'=>false,'error'=>'Недопустимый статус.']); exit; }

$a = DB::one("SELECT id FROM appointment WHERE id = :id", ['id'=>$apptId]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }

DB::exec("UPDATE appointment SET status = :st WHERE id = :id", ['st'=>$status,'id'=>$apptId]);
echo json_encode(['ok'=>true]);
```

- [ ] **Step 2: `public/api/registrar_cancel.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$apptId = (int) ($_POST['appointment_id'] ?? 0);
$a = DB::one("SELECT id FROM appointment WHERE id = :id", ['id'=>$apptId]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }

// FK ON DELETE CASCADE удалит appointment_service / protocol / payment
DB::exec("DELETE FROM appointment WHERE id = :id", ['id'=>$apptId]);
echo json_encode(['ok'=>true]);
```

- [ ] **Step 3: `public/api/registrar_pay.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$apptId = (int) ($_POST['appointment_id'] ?? 0);
$method = (string) ($_POST['method'] ?? '');
if (!in_array($method, ['cash','card'], true)) { echo json_encode(['ok'=>false,'error'=>'Выберите способ оплаты.']); exit; }

$a = DB::one("SELECT id, status FROM appointment WHERE id = :id", ['id'=>$apptId]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }
if ($a['status'] !== 'performed') { echo json_encode(['ok'=>false,'error'=>'Оплата возможна только для исполненной записи.']); exit; }
if (DB::one("SELECT id FROM payment WHERE appointment_id = :id", ['id'=>$apptId]) !== null) { echo json_encode(['ok'=>false,'error'=>'Запись уже оплачена.']); exit; }

$total = (float) (DB::one("SELECT COALESCE(SUM(price_at_time),0) AS t FROM appointment_service WHERE appointment_id = :id", ['id'=>$apptId])['t'] ?? 0);

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    DB::exec("INSERT INTO payment (appointment_id, method, total_amount) VALUES (:a,:m,:t)", ['a'=>$apptId,'m'=>$method,'t'=>$total]);
    DB::exec("UPDATE appointment SET status = 'completed' WHERE id = :id", ['id'=>$apptId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'Не удалось провести оплату.']); exit;
}
echo json_encode(['ok'=>true, 'receipt_url' => '/receipt.php?appointment_id=' . $apptId]);
```

- [ ] **Step 4: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\registrar_set_status.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\registrar_cancel.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\registrar_pay.php
git add public/api/registrar_set_status.php public/api/registrar_cancel.php public/api/registrar_pay.php
git commit -m "feat: registrar set-status, cancel, pay endpoints"
```

---

### Task P6-4: Чек (`public/receipt.php`)

**Files:**
- Create: `public/receipt.php`

HTML-чек (PDF в Фазе 8). Доступ: регистратор, админ, либо пациент-владелец.

- [ ] **Step 1: Написать `public/receipt.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user = Auth::user();
$uid  = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id,
            pay.method, pay.total_amount, pay.paid_at,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN payment pay ON pay.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) { http_response_code(404); echo 'Чек не найден.'; exit; }

$allowed = ($user['role_code'] === 'registrar') || ($user['role_code'] === 'admin') || ($uid === (int) $row['patient_id']);
if (!$allowed) { http_response_code(403); echo 'Нет доступа.'; exit; }

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id'=>$apptId]);
$methodLabel = $row['method'] === 'cash' ? 'Наличными' : 'Картой';

$_pageTitle = 'Чек';
require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="clinic-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Кассовый чек</h1>
                <button class="btn btn-outline-orange btn-sm" onclick="window.print()">Печать</button>
            </div>

            <div class="mb-3 small">
                <div class="fw-semibold"><?= h(SITE_NAME) ?></div>
                <div><?= h(CLINIC_ADDRESS) ?></div>
                <div>тел. <?= h(CLINIC_PHONE) ?></div>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-4">Пациент</dt><dd class="col-sm-8"><?= h($row['p_last'].' '.$row['p_first'].' '.$row['p_mid']) ?></dd>
                <dt class="col-sm-4">Врач</dt><dd class="col-sm-8"><?= h($row['d_last'].' '.$row['d_first'].' '.$row['d_mid']) ?></dd>
                <dt class="col-sm-4">Дата приёма</dt><dd class="col-sm-8"><?= h(fmt_dt($row['slot_start'])) ?></dd>
                <dt class="col-sm-4">Дата оплаты</dt><dd class="col-sm-8"><?= h(fmt_dt($row['paid_at'])) ?></dd>
                <dt class="col-sm-4">Способ оплаты</dt><dd class="col-sm-8"><?= h($methodLabel) ?></dd>
            </dl>

            <table class="table">
                <thead><tr><th>Услуга</th><th class="text-end">Стоимость</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr><td><?= h($s['name']) ?></td><td class="text-end"><?= h(fmt_price($s['price_at_time'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td>Итого</td><td class="text-end"><?= h(fmt_price($row['total_amount'])) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
```

- [ ] **Step 2: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\receipt.php
git add public/receipt.php
git commit -m "feat: receipt page (HTML, registrar/admin/patient access; PDF in Phase 8)"
```

---

### Task P6-5: JS кабинета регистратора (`public/assets/js/registrar.js`)

**Files:**
- Create: `public/assets/js/registrar.js`

- [ ] **Step 1: Написать `public/assets/js/registrar.js`**

```js
(function () {
    'use strict';
    var root = document.getElementById('registrar-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;
    var doctorId = root.dataset.doctor;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() }).then(function (r) { return r.json(); });
    }

    var bookModal = new bootstrap.Modal(document.getElementById('bookModal'));
    var manageModal = new bootstrap.Modal(document.getElementById('manageModal'));
    var cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));

    // ----- Запись на свободный слот -----
    var bookSlotEl = document.getElementById('bookSlot');
    var bookErr = document.getElementById('bookError');
    var currentSlot = null;
    document.querySelectorAll('.free-cell').forEach(function (c) {
        c.addEventListener('click', function () {
            currentSlot = c.dataset.slot;
            bookSlotEl.textContent = currentSlot;
            bookErr.classList.add('d-none');
            bookModal.show();
        });
    });
    document.getElementById('bookConfirm').addEventListener('click', function () {
        var pid = document.getElementById('bookPatient').value;
        post('/api/registrar_book.php', { doctor_id: doctorId, patient_id: pid, slot_start: currentSlot }).then(function (d) {
            if (d.ok) { location.reload(); }
            else { bookErr.textContent = d.error || 'Ошибка.'; bookErr.classList.remove('d-none'); }
        });
    });

    // ----- Управление записью -----
    var currentAppt = null;
    var mgErr = document.getElementById('mgError');
    var mgPayBtn = document.getElementById('mgPayBtn');
    var mgPayMethod = document.getElementById('mgPayMethod');
    var mgStatus = document.getElementById('mgStatus');
    var mgPaidInfo = document.getElementById('mgPaidInfo');
    var canPay = false;

    function refreshPayState() {
        mgPayBtn.disabled = !(canPay && mgPayMethod.value !== '');
    }
    mgPayMethod.addEventListener('change', refreshPayState);

    document.querySelectorAll('.appt-cell').forEach(function (c) {
        c.addEventListener('click', function () {
            currentAppt = c.dataset.apptId;
            mgErr.classList.add('d-none');
            mgPaidInfo.classList.add('d-none');
            fetch('/api/registrar_appointment.php?id=' + encodeURIComponent(currentAppt)).then(function (r) { return r.json(); }).then(function (resp) {
                if (!resp.ok) { mgErr.textContent = resp.error || 'Ошибка.'; mgErr.classList.remove('d-none'); manageModal.show(); return; }
                var a = resp.appointment;
                document.getElementById('mgPatient').textContent = a.patient_fio;
                document.getElementById('mgWhen').textContent = a.when + ' · ' + a.status_label;
                mgStatus.value = (a.status === 'completed') ? 'performed' : a.status;
                mgStatus.disabled = a.paid;
                var ul = document.getElementById('mgServices');
                ul.innerHTML = '';
                resp.services.forEach(function (s) { var li = document.createElement('li'); li.textContent = s.name + ' — ' + s.price; ul.appendChild(li); });
                document.getElementById('mgTotal').textContent = a.total;
                canPay = a.can_pay;
                if (a.paid) {
                    mgPayMethod.value = a.pay_method; mgPayMethod.disabled = true;
                    mgPaidInfo.textContent = 'Оплачено (' + (a.pay_method === 'cash' ? 'наличными' : 'картой') + ').';
                    mgPaidInfo.classList.remove('d-none');
                } else {
                    mgPayMethod.disabled = false; mgPayMethod.value = '';
                }
                refreshPayState();
                manageModal.show();
            });
        });
    });

    document.getElementById('mgStatusSave').addEventListener('click', function () {
        post('/api/registrar_set_status.php', { appointment_id: currentAppt, status: mgStatus.value }).then(function (d) {
            if (d.ok) { location.reload(); } else { mgErr.textContent = d.error || 'Ошибка.'; mgErr.classList.remove('d-none'); }
        });
    });

    mgPayBtn.addEventListener('click', function () {
        post('/api/registrar_pay.php', { appointment_id: currentAppt, method: mgPayMethod.value }).then(function (d) {
            if (d.ok) { window.open(d.receipt_url, '_blank'); location.reload(); }
            else { mgErr.textContent = d.error || 'Ошибка.'; mgErr.classList.remove('d-none'); }
        });
    });

    document.getElementById('mgCancelBtn').addEventListener('click', function () { manageModal.hide(); cancelModal.show(); });
    document.getElementById('cancelConfirmYes').addEventListener('click', function () {
        post('/api/registrar_cancel.php', { appointment_id: currentAppt }).then(function (d) {
            if (d.ok) { location.reload(); } else { cancelModal.hide(); mgErr.textContent = d.error || 'Ошибка.'; }
        });
    });
})();
```

- [ ] **Step 2: Проверка раздачи**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8841','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8841/assets/js/registrar.js' -UseBasicParsing; "registrar.js: $($r.StatusCode)"; if ($r.Content -match 'registrar_book' -and $r.Content -match 'registrar_pay' -and $r.Content -match 'registrar_cancel') { 'js wired OK' } } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, js wired OK.

- [ ] **Step 3: Commit**
```powershell
git add public/assets/js/registrar.js
git commit -m "feat: registrar cabinet JS (book, manage, status, cancel, pay)"
```

---

### Task P6-6: Сквозная проверка Фазы 6 + тег

- [ ] **Step 1: Smoke-тесты** (5 файлов) — все «OK».
```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
foreach ($t in @('smoke_db','smoke_csrf','smoke_password','smoke_upload','smoke_appointment')) { & $php -d zend.assertions=1 -d assert.exception=1 "tests/$t.php" }
```

- [ ] **Step 2: E2E регистратора** (использовать ASCII в текстовых полях; здесь текстовых нет, но соблюдать общий принцип). Логин регистратором, бронь свободного слота, смена статуса, оплата.

Заметка: для оплаты нужна performed-запись с услугами. Возьмём appt id=4 (Кузнецова, performed, протокол есть). Добавим услугу, оплатим, проверим чек и переход в completed; затем откатим.

```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'; $mysql='C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
# гарантируем услугу у appt4 для расчёта суммы
& $mysql -uroot dentistry -e "INSERT IGNORE INTO appointment_service (appointment_id, service_id, price_at_time) SELECT 4, 1, price FROM service WHERE id=1;"
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8842','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8842/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8842/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='registrar@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $prof = Invoke-WebRequest -Uri 'http://127.0.0.1:8842/profile.php?doctor_id=4' -WebSession $s -UseBasicParsing
    $pc = ([regex]'data-csrf="([a-f0-9]+)"').Match($prof.Content).Groups[1].Value
    # detail appt4
    $det = (Invoke-WebRequest -Uri 'http://127.0.0.1:8842/api/registrar_appointment.php?id=4' -WebSession $s -UseBasicParsing).Content
    "detail4: $det"
    # pay must work (performed)
    $pay = (Invoke-WebRequest -Uri 'http://127.0.0.1:8842/api/registrar_pay.php' -WebSession $s -Method POST -UseBasicParsing -Body @{ _csrf=$pc; appointment_id='4'; method='card' }).Content
    "pay: $pay"
    & $mysql -uroot dentistry -e "SELECT id,status FROM appointment WHERE id=4; SELECT appointment_id, method, total_amount FROM payment WHERE appointment_id=4;"
    # receipt renders
    $rc = Invoke-WebRequest -Uri 'http://127.0.0.1:8842/receipt.php?appointment_id=4' -WebSession $s -UseBasicParsing
    if ($rc.Content -match 'Кассовый чек' -and $rc.Content -match 'Итого') { 'receipt OK' } else { 'RECEIPT ISSUE' }
    # double-pay rejected
    $pay2 = (Invoke-WebRequest -Uri 'http://127.0.0.1:8842/api/registrar_pay.php' -WebSession $s -Method POST -UseBasicParsing -Body @{ _csrf=$pc; appointment_id='4'; method='card' }).Content
    "double pay (expect ok:false): $pay2"
    # booking a free future slot for doctor 4
    $slot = (Get-Date).AddDays(3).ToString('yyyy-MM-dd') + ' 11:00:00'
    # ensure weekday: if Sat/Sun skip — pick next Monday-ish; for simplicity add days until weekday handled by is_valid_slot, but here trust +3 mostly weekday
    $book = (Invoke-WebRequest -Uri 'http://127.0.0.1:8842/api/registrar_book.php' -WebSession $s -Method POST -UseBasicParsing -Body @{ _csrf=$pc; doctor_id='4'; patient_id='5'; slot_start=$slot }).Content
    "book (may fail if weekend slot): $book"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: detail4 ok with can_pay true; pay ok:true; appt4 status=completed + payment row (card); receipt OK; double pay ok:false; book ok:true (если слот будний и свободный).

- [ ] **Step 3: Откат тестовых правок**
```powershell
$mysql='C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
& $mysql -uroot dentistry -e "DELETE FROM payment WHERE appointment_id=4; UPDATE appointment SET status='performed' WHERE id=4; DELETE FROM appointment_service WHERE appointment_id=4 AND service_id=1 AND NOT EXISTS (SELECT 1 FROM protocol WHERE appointment_id=4 AND 1=0);"
# восстановить исходные услуги appt4 (seed: services 1 и 2)
& $mysql -uroot dentistry -e "INSERT IGNORE INTO appointment_service (appointment_id,service_id,price_at_time) SELECT 4,1,price FROM service WHERE id=1; INSERT IGNORE INTO appointment_service (appointment_id,service_id,price_at_time) SELECT 4,2,price FROM service WHERE id=2;"
# удалить тестовую бронь (doctor 4, patient 5, будущий слот), если создалась
& $mysql -uroot dentistry -e "DELETE FROM appointment WHERE doctor_id=4 AND patient_id=5 AND status='created' AND slot_start > NOW();"
& $mysql -uroot dentistry -e "SELECT id,status FROM appointment WHERE id=4; SELECT COUNT(*) AS pays FROM payment;"
```
Expected: appt4 performed; pays=2 (исходные seed-оплаты по appt5,6).

> Примечание для исполнителя: seed appt4 изначально имеет услуги (1,2) и НЕ имеет оплаты. Точно сверить с `db/seed.sql` и восстановить ровно исходное состояние (appt4: performed, services 1&2, без payment).

- [ ] **Step 4: Негатив — не-регистратор → 403**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8843','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8843/api/registrar_appointment.php?id=4' -UseBasicParsing -SkipHttpErrorCheck
    "guest: $($r.StatusCode)"
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8843/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8843/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='petrov@dentistry.local'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    $r2 = Invoke-WebRequest -Uri 'http://127.0.0.1:8843/api/registrar_pay.php' -WebSession $s -Method POST -UseBasicParsing -SkipHttpErrorCheck -Body @{ appointment_id='4'; method='card' }
    "doctor->registrar_pay: $($r2.StatusCode)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: guest 403; doctor→registrar_pay 403.

- [ ] **Step 5: Регрессия + чистота + тег**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8844','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { foreach ($u in @('/', '/doctors.php', '/services.php', '/login.php')) { "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8844$u" -UseBasicParsing).StatusCode))  $u" } } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
git status --short
git tag phase-6-complete
git tag --list 'phase-*'
```
Expected: pages 200; git clean; tag created.

---

## Чек-лист соответствия спецификации (Фаза 6)

| Требование PROMPT.md | Где |
|---|---|
| Расписание как у врача + выбор врача сверху | P6-1 (селектор + сетка) |
| Клик по свободному слоту → выбор пациента → записать/отмена | P6-1 (book modal) + P6-2 (registrar_book) + P6-5 |
| Клик по занятому → модалка: ФИО, статус (выпадающий), услуги | P6-2 (detail) + P6-1 + P6-5 |
| Способ оплаты (наличные/картой) | P6-1 (mgPayMethod) |
| Кнопка «Отменить запись» + подтверждение | P6-1 (cancel modal) + P6-3 (registrar_cancel) + P6-5 |
| «Оплатить» неактивна без способа оплаты и не для «Исполнена» | P6-5 (refreshPayState) + P6-3 (registrar_pay проверяет performed) |
| Оплата → чек (реквизиты, ФИО, услуги, сумма, способ) | P6-3 (receipt_url) + P6-4 (receipt.php) |
| После оплаты запись → «Завершена» | P6-3 (registrar_pay: status=completed) |

Отложено: PDF-чек → Фаза 8 (сейчас HTML с «Печать»).

---

**Итог Фазы 6:** регистратор ведёт расписание любого врача, записывает пациентов, меняет статусы, отменяет записи и проводит оплату с чеком и автопереводом в «Завершена». Дальше — Фаза 7 (администратор: роли, CRUD блога и услуг, статистика).
