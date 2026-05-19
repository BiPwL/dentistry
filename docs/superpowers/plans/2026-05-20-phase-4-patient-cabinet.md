# Стоматологическая клиника — Фаза 4: Кабинет пациента

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Личный кабинет пациента: история посещений со статусами (градиент зелёный→серый, «Не явка» — красный), отмена записи в статусе «Создана», мастер онлайн-записи к врачу по свободным слотам, предупреждение о санкциях за неявку.

**Architecture:** `public/profile.php` становится роутером по роли; для пациента подключает `templates/cabinet_patient.php`. Логика расписания/статусов вынесена в `lib/appointment.php` (чистые функции, тестируемые). Мастер записи — Bootstrap-модалка на нативном JS (`public/assets/js/booking.js`), которая ходит в JSON-эндпоинты `public/api/*` (свободные слоты, создание, отмена). Слоты и валидация считаются на сервере; клиент ничему не доверяется. PDF-зависимые элементы (Мед.карта, Протокол/Чек) — заглушены до Фазы 7.

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3 (modals), нативный JS (fetch). Хелперы Фаз 1–3 (DB, Auth, Csrf, sanitize).

---

## Что приходит с Фаз 1–3

- Webroot `public/`; точки входа подключают `__DIR__ . '/../...'`; эндпоинты в `public/api/` будут подключать `__DIR__ . '/../../...'`.
- `lib/db.php` (DB::all/one/exec/lastId/pdo), `lib/auth.php` (Auth::requireRole/user/hasRole; user() → row с id, last_name, first_name, middle_name, role_code, booking_ban_until), `lib/csrf.php` (token/field/requireValid), `lib/sanitize.php` (h/fio_short/fmt_dt/fmt_price).
- `templates/header.php` (+ `$_pageTitle`), `templates/footer.php` (грузит `/assets/js/main.js`).
- CSS статусов уже есть в `public/assets/css/theme.css`: `.status-badge`, `.status-created/confirmed/performed/completed/noshow`.
- Таблицы: `appointment(id, patient_id, doctor_id, slot_start, slot_end, status ENUM('created','confirmed','performed','completed','noshow'), created_at, reminder_sent_at)`, `appointment_service(appointment_id, service_id, price_at_time)`, `service`, `user.booking_ban_until DATE NULL`. UNIQUE `uq_doctor_slot(doctor_id, slot_start)`.
- Конфиг: WORK_START='10:00', WORK_END='18:00', BREAK_START='14:00', BREAK_END='15:00', SLOT_MINUTES=60, NOSHOW_BAN_DAYS=7.
- Seed: пациент Смирнов (id5) имеет записи в статусах created/completed/…; врачи id3, id4.

---

## Структура файлов после Фазы 4

```
lib/appointment.php                 # статусы + расписание (новый)
templates/footer.php                # поддержка $_pageScripts (правка)
public/profile.php                  # роутер по роли + кабинет пациента (правка)
templates/cabinet_patient.php       # разметка кабинета пациента (новый)
public/api/booking_slots.php        # GET свободные слоты врача (новый)
public/api/book_appointment.php     # POST создать запись (новый)
public/api/cancel_appointment.php   # POST отменить запись (новый)
public/assets/js/booking.js         # мастер записи + отмена (новый)
public/assets/css/theme.css         # стили кабинета (правка)
tests/smoke_appointment.php         # тест расписания/статусов (новый)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- Тестовый пациент: `smirnov@example.com` / `Password1!` (id=5)

---

## Подход к тестированию

- **Логика расписания/статусов** (`lib/appointment.php`) — `tests/smoke_appointment.php` (slot_hours, метки, is_valid_slot для будней/перерыва/прошлого/выходных).
- **Эндпоинты + флоу** — функциональные тесты через curl с куками: логин пациентом → запросить слоты → создать запись → проверить в БД → отменить → проверить удаление. Плюс негативные кейсы (чужой/невалидный слот, занятый слот, CSRF).
- **Регрессия** — публичные/auth-страницы 200.

---

## Задачи

---

### Task P4-1: Хелпер расписания и статусов (`lib/appointment.php`)

**Files:**
- Create: `lib/appointment.php`
- Create: `tests/smoke_appointment.php`

- [ ] **Step 1: Написать `lib/appointment.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** Все статусы записи в порядке жизненного цикла. */
const APPT_STATUSES = ['created', 'confirmed', 'performed', 'completed', 'noshow'];

/** Человекочитаемая метка статуса. */
function appt_status_label(string $status): string
{
    return [
        'created'   => 'Создана',
        'confirmed' => 'Подтверждена',
        'performed' => 'Исполнена',
        'completed' => 'Завершена',
        'noshow'    => 'Не явка',
    ][$status] ?? $status;
}

/** CSS-класс бейджа статуса (см. theme.css). */
function appt_status_class(string $status): string
{
    return 'status-' . $status;
}

/** Часы начала 1-часовых слотов в рамках рабочего дня минус перерыв. Напр. [10,11,12,13,15,16,17]. */
function slot_hours(): array
{
    $start  = (int) substr(WORK_START, 0, 2);
    $end    = (int) substr(WORK_END, 0, 2);
    $bStart = (int) substr(BREAK_START, 0, 2);
    $bEnd   = (int) substr(BREAK_END, 0, 2);
    $hours = [];
    for ($h = $start; $h < $end; $h++) {
        if ($h >= $bStart && $h < $bEnd) continue;
        $hours[] = $h;
    }
    return $hours;
}

/** Метка слота вида "10:00–11:00". */
function slot_label(int $hour): string
{
    return sprintf('%02d:00–%02d:00', $hour, $hour + 1);
}

/** Конец слота (DATETIME 'Y-m-d H:i:s') = начало + 1 час. */
function slot_end_for(string $slotStart): string
{
    return date('Y-m-d H:i:s', strtotime($slotStart) + 3600);
}

/** Даты, доступные для записи: сегодня..+$daysAhead, только Пн–Пт. Возвращает 'Y-m-d'. */
function booking_dates(int $daysAhead = 14): array
{
    $dates = [];
    $base  = strtotime('today');
    for ($i = 0; $i <= $daysAhead; $i++) {
        $ts  = strtotime("+$i day", $base);
        $dow = (int) date('N', $ts);
        if ($dow >= 6) continue; // 6=Сб, 7=Вс
        $dates[] = date('Y-m-d', $ts);
    }
    return $dates;
}

/** Русская метка даты "Пн, 21 мая". */
function ru_date_label(string $date): string
{
    $months = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
               7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
    $days   = [1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];
    $ts = strtotime($date);
    return $days[(int) date('N', $ts)] . ', ' . (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)];
}

/** Валиден ли слот для записи: будущее, Пн–Пт, ровно на часе, рабочий час, в пределах окна. */
function is_valid_slot(string $slotStart, int $daysAhead = 14): bool
{
    $ts = strtotime($slotStart);
    if ($ts === false) return false;
    if ($ts <= time()) return false;
    if ((int) date('N', $ts) >= 6) return false;
    if ((int) date('i', $ts) !== 0) return false;
    if (!in_array((int) date('G', $ts), slot_hours(), true)) return false;
    $maxTs = strtotime('today +' . ($daysAhead + 1) . ' day');
    if ($ts >= $maxTs) return false;
    return true;
}
```

- [ ] **Step 2: Написать `tests/smoke_appointment.php`**

```php
<?php
require_once __DIR__ . '/../lib/appointment.php';

// Слоты: 10..17 без 14
assert(slot_hours() === [10, 11, 12, 13, 15, 16, 17], 'slot hours with lunch break');
assert(slot_label(10) === '10:00–11:00', 'slot label');
assert(slot_end_for('2026-05-21 10:00:00') === '2026-05-21 11:00:00', 'slot end +1h');

// Статусы
assert(appt_status_label('created') === 'Создана');
assert(appt_status_label('noshow') === 'Не явка');
assert(appt_status_class('completed') === 'status-completed');

// booking_dates — только будни, без выходных
foreach (booking_dates(14) as $d) {
    $dow = (int) date('N', strtotime($d));
    assert($dow <= 5, "booking date $d must be a weekday");
}

// is_valid_slot
$nextMon = date('Y-m-d', strtotime('next monday'));
assert(is_valid_slot($nextMon . ' 10:00:00') === true, 'valid future Monday 10:00');
assert(is_valid_slot($nextMon . ' 14:00:00') === false, 'lunch break invalid');
assert(is_valid_slot($nextMon . ' 09:00:00') === false, 'before work hours invalid');
assert(is_valid_slot($nextMon . ' 10:30:00') === false, 'half-hour invalid');
assert(is_valid_slot(date('Y-m-d', strtotime('next sunday')) . ' 10:00:00') === false, 'sunday invalid');
assert(is_valid_slot('2020-01-01 10:00:00') === false, 'past invalid');

echo "Appointment smoke: OK\n";
```

- [ ] **Step 3: Запустить smoke-тест**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_appointment.php
```
Expected: `Appointment smoke: OK`. Если упадёт ассерт — BLOCKED, не подгонять.

- [ ] **Step 4: Commit**

```powershell
git add lib/appointment.php tests/smoke_appointment.php
git commit -m "feat: appointment status + schedule helpers with smoke test"
```

---

### Task P4-2: Поддержка постраничных скриптов в `templates/footer.php`

**Files:**
- Modify: `templates/footer.php`

Чтобы кабинет пациента мог подключить `booking.js` только на своей странице.

- [ ] **Step 1: Прочитать `templates/footer.php`.** Текущее содержимое заканчивается так:

```php
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

Заменить эти две строки `<script ...>` + `</body></html>` на:

```php
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
<?php foreach (($_pageScripts ?? []) as $_src): ?>
<script src="<?= h($_src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
```

(`h()` уже доступна — header.php её подключил. `$_pageScripts` — необязательный массив URL, задаётся страницей до подключения footer.)

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\footer.php
```
Expected: No syntax errors.

- [ ] **Step 3: Регрессия — главная всё ещё рендерится (docroot=public)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8810','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { (Invoke-WebRequest -Uri 'http://127.0.0.1:8810/' -UseBasicParsing).StatusCode } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200.

- [ ] **Step 4: Commit**

```powershell
git add templates/footer.php
git commit -m "feat: optional per-page scripts via \$_pageScripts in footer"
```

---

### Task P4-3: Эндпоинт свободных слотов (`public/api/booking_slots.php`)

**Files:**
- Create: `public/api/booking_slots.php`

- [ ] **Step 1: Написать `public/api/booking_slots.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для пациентов.']);
    exit;
}

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$doctor = DB::one('SELECT id FROM user WHERE id = :id AND role_id = 3', ['id' => $doctorId]);
if ($doctor === null) {
    echo json_encode(['ok' => false, 'error' => 'Врач не найден.']);
    exit;
}

$dates = booking_dates(14);
if (empty($dates)) {
    echo json_encode(['ok' => true, 'days' => []]);
    exit;
}
$from = $dates[0] . ' 00:00:00';
$to   = end($dates) . ' 23:59:59';

$rows = DB::all(
    'SELECT slot_start FROM appointment WHERE doctor_id = :d AND slot_start BETWEEN :f AND :t',
    ['d' => $doctorId, 'f' => $from, 't' => $to]
);
$booked = [];
foreach ($rows as $r) {
    $booked[date('Y-m-d H:i:s', strtotime($r['slot_start']))] = true;
}

$days = [];
foreach ($dates as $date) {
    $slots = [];
    foreach (slot_hours() as $h) {
        $start = sprintf('%s %02d:00:00', $date, $h);
        if (strtotime($start) <= time()) continue;       // прошедшие
        if (isset($booked[$start])) continue;            // занятые
        $slots[] = ['start' => $start, 'label' => slot_label($h)];
    }
    if (!empty($slots)) {
        $days[] = ['date' => $date, 'label' => ru_date_label($date), 'slots' => $slots];
    }
}

echo json_encode(['ok' => true, 'days' => $days]);
```

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\booking_slots.php
```

- [ ] **Step 3: Smoke — гость получает 403 (docroot=public)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8811','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8811/api/booking_slots.php?doctor_id=3' -UseBasicParsing -SkipHttpErrorCheck
    "guest: $($r.StatusCode), body: $($r.Content)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 403, JSON с `"ok":false`.

- [ ] **Step 4: Commit**

```powershell
git add public/api/booking_slots.php
git commit -m "feat: booking slots JSON endpoint (patient-gated)"
```

---

### Task P4-4: Эндпоинт создания записи (`public/api/book_appointment.php`)

**Files:**
- Create: `public/api/book_appointment.php`

- [ ] **Step 1: Написать `public/api/book_appointment.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для пациентов.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$patient   = Auth::user();
$patientId = (int) $patient['id'];

// Блокировка при бане за неявку
if (!empty($patient['booking_ban_until']) && $patient['booking_ban_until'] >= date('Y-m-d')) {
    echo json_encode(['ok' => false, 'error' => 'Онлайн-запись временно ограничена до ' . $patient['booking_ban_until'] . ' из-за неявки.']);
    exit;
}

$doctorId  = (int) ($_POST['doctor_id'] ?? 0);
$slotStart = trim((string) ($_POST['slot_start'] ?? ''));

if (DB::one('SELECT id FROM user WHERE id = :id AND role_id = 3', ['id' => $doctorId]) === null) {
    echo json_encode(['ok' => false, 'error' => 'Врач не найден.']);
    exit;
}
if (!is_valid_slot($slotStart)) {
    echo json_encode(['ok' => false, 'error' => 'Недопустимое время записи.']);
    exit;
}
if (DB::one('SELECT id FROM appointment WHERE doctor_id = :d AND slot_start = :s', ['d' => $doctorId, 's' => $slotStart]) !== null) {
    echo json_encode(['ok' => false, 'error' => 'Это время уже занято. Выберите другое.']);
    exit;
}

try {
    DB::exec(
        'INSERT INTO appointment (patient_id, doctor_id, slot_start, slot_end, status)
         VALUES (:p, :d, :s, :e, \'created\')',
        ['p' => $patientId, 'd' => $doctorId, 's' => $slotStart, 'e' => slot_end_for($slotStart)]
    );
} catch (PDOException $e) {
    // гонка по UNIQUE(doctor_id, slot_start)
    echo json_encode(['ok' => false, 'error' => 'Это время уже занято. Выберите другое.']);
    exit;
}

echo json_encode(['ok' => true, 'appointment_id' => DB::lastId()]);
```

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\book_appointment.php
```

- [ ] **Step 3: Commit**

```powershell
git add public/api/book_appointment.php
git commit -m "feat: create appointment endpoint (validation, ban check, CSRF)"
```

---

### Task P4-5: Эндпоинт отмены записи (`public/api/cancel_appointment.php`)

**Files:**
- Create: `public/api/cancel_appointment.php`

- [ ] **Step 1: Написать `public/api/cancel_appointment.php`**

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для пациентов.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$patientId = (int) Auth::user()['id'];
$apptId    = (int) ($_POST['appointment_id'] ?? 0);

// Отменить можно только свою запись в статусе «Создана»
$appt = DB::one(
    "SELECT id FROM appointment WHERE id = :id AND patient_id = :p AND status = 'created'",
    ['id' => $apptId, 'p' => $patientId]
);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись нельзя отменить.']);
    exit;
}

DB::exec('DELETE FROM appointment WHERE id = :id', ['id' => $apptId]);
echo json_encode(['ok' => true]);
```

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\api\cancel_appointment.php
```

- [ ] **Step 3: Commit**

```powershell
git add public/api/cancel_appointment.php
git commit -m "feat: cancel appointment endpoint (own + created only, CSRF)"
```

---

### Task P4-6: Кабинет пациента (`profile.php` роутер + `templates/cabinet_patient.php`)

**Files:**
- Modify: `public/profile.php`
- Create: `templates/cabinet_patient.php`
- Modify: `public/assets/css/theme.css`

- [ ] **Step 1: Переписать `public/profile.php` как роутер по роли**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/appointment.php';

Auth::requireRole('admin', 'registrar', 'doctor', 'patient');
$user = Auth::user();
$role = $user['role_code'];

$_pageTitle = 'Личный кабинет';
if ($role === 'patient') {
    $_pageScripts = ['/assets/js/booking.js'];
}

require __DIR__ . '/../templates/header.php';

if ($role === 'patient') {
    require __DIR__ . '/../templates/cabinet_patient.php';
} else {
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
                <?php if ($role === 'doctor'): ?>
                    <a href="/doctor_profile_edit.php" class="btn btn-orange mb-3">Редактировать профиль врача</a>
                <?php endif; ?>
                <p class="text-muted small">Полноценный кабинет вашей роли появится в следующих обновлениях.</p>
                <form method="post" action="/logout.php" class="mt-3">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-outline-orange">Выйти</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}

require __DIR__ . '/../templates/footer.php';
```

- [ ] **Step 2: Создать `templates/cabinet_patient.php`**

```php
<?php
/** @var array $user  текущий пациент (из profile.php) */
$pid = (int) $user['id'];

// История записей (новые сверху) + ФИО врача
$appointments = DB::all(
    "SELECT a.id, a.slot_start, a.status,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user d ON d.id = a.doctor_id
      WHERE a.patient_id = :p
      ORDER BY a.slot_start DESC",
    ['p' => $pid]
);

// Услуги по завершённым записям (для модалки) — одним запросом
$completedIds = array_values(array_map(
    fn($a) => (int) $a['id'],
    array_filter($appointments, fn($a) => $a['status'] === 'completed')
));
$servicesByAppt = [];
if (!empty($completedIds)) {
    $in  = implode(',', array_fill(0, count($completedIds), '?'));
    $rows = DB::all(
        "SELECT aps.appointment_id, s.name, aps.price_at_time
           FROM appointment_service aps
           JOIN service s ON s.id = aps.service_id
          WHERE aps.appointment_id IN ($in)",
        $completedIds
    );
    foreach ($rows as $r) {
        $servicesByAppt[(int) $r['appointment_id']][] = $r;
    }
}

$hasCreated = (bool) array_filter($appointments, fn($a) => $a['status'] === 'created');
$banned = !empty($user['booking_ban_until']) && $user['booking_ban_until'] >= date('Y-m-d');

// Список врачей для мастера записи
$doctors = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 3 ORDER BY last_name");
?>

<div id="patient-cabinet" data-csrf="<?= h(Csrf::token()) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Мои записи</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-orange" id="openBooking" <?= $banned ? 'disabled' : '' ?>>Записаться</button>
            <button type="button" class="btn btn-outline-orange" disabled title="Появится позже">Мед. карта</button>
            <form method="post" action="/logout.php" class="m-0">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-link text-muted">Выйти</button>
            </form>
        </div>
    </div>

    <?php if ($hasCreated): ?>
        <div class="alert alert-danger py-2 small">
            Внимание: при неявке по записи онлайн-запись будет ограничена на <?= (int) NOSHOW_BAN_DAYS ?> дней.
        </div>
    <?php endif; ?>
    <?php if ($banned): ?>
        <div class="alert alert-warning py-2 small">
            Онлайн-запись ограничена до <?= h($user['booking_ban_until']) ?> из-за неявки.
        </div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
        <p class="text-muted">У вас пока нет записей. Нажмите «Записаться», чтобы выбрать врача и время.</p>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($appointments as $a): ?>
                <?php
                    $fio = fio_short($a['d_last'], $a['d_first'], $a['d_mid']);
                    $isCompleted = $a['status'] === 'completed';
                    $isCreated   = $a['status'] === 'created';
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center<?= $isCompleted ? ' appt-completed' : '' ?>"
                     <?= $isCompleted ? 'role="button" data-appt-id="' . (int) $a['id'] . '"' : '' ?>>
                    <div>
                        <div class="fw-semibold"><?= h(fmt_dt($a['slot_start'])) ?></div>
                        <div class="text-muted small"><?= h($fio) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-badge <?= h(appt_status_class($a['status'])) ?>"><?= h(appt_status_label($a['status'])) ?></span>
                        <?php if ($isCreated): ?>
                            <button type="button" class="btn-close appt-cancel" aria-label="Отменить"
                                    data-id="<?= (int) $a['id'] ?>" title="Отменить запись"></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Скрытые данные услуг для завершённых записей -->
    <?php foreach ($servicesByAppt as $aid => $list): ?>
        <script type="application/json" class="appt-services" data-appt-id="<?= (int) $aid ?>">
            <?= json_encode(array_map(fn($r) => ['name' => $r['name'], 'price' => fmt_price($r['price_at_time'])], $list), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        </script>
    <?php endforeach; ?>

    <!-- Врачи для мастера записи -->
    <script type="application/json" id="doctors-data">
        <?= json_encode(array_map(fn($d) => ['id' => (int) $d['id'], 'fio' => fio_short($d['last_name'], $d['first_name'], $d['middle_name'])], $doctors), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
    </script>
</div>

<!-- Модалка мастера записи -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Запись на приём</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Врач</label>
          <select class="form-select" id="bookingDoctor"></select>
        </div>
        <div id="bookingDays" class="mb-3"></div>
        <div id="bookingSlots"></div>
        <div id="bookingMsg" class="text-muted small"></div>
      </div>
    </div>
  </div>
</div>

<!-- Модалка подтверждения записи -->
<div class="modal fade" id="confirmBookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Подтверждение</h5></div>
      <div class="modal-body"><p id="confirmBookingText" class="mb-0"></p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-orange" id="confirmBookingYes">Да, записаться</button>
      </div>
    </div>
  </div>
</div>

<!-- Модалка подтверждения отмены -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Отмена записи</h5></div>
      <div class="modal-body"><p class="mb-0">Отменить эту запись?</p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-orange" id="cancelYes">Да, отменить</button>
      </div>
    </div>
  </div>
</div>

<!-- Модалка завершённой записи -->
<div class="modal fade" id="completedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Оказанные услуги</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <ul id="completedServices" class="list-unstyled mb-3"></ul>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-orange btn-sm" disabled title="Появится позже">Протокол приёма</button>
          <button type="button" class="btn btn-outline-orange btn-sm" disabled title="Появится позже">Чек</button>
        </div>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Добавить стили в `public/assets/css/theme.css`** (в конец):

```css
/* Patient cabinet */
.appt-completed { cursor: pointer; }
.appt-completed:hover { background: var(--clinic-soft); }
.booking-day-btn, .booking-slot-btn { margin: 0.15rem; }
#bookingDays .btn.active, #bookingSlots .btn.active {
    background: var(--clinic-primary);
    border-color: var(--clinic-primary);
    color: #fff;
}
```

- [ ] **Step 4: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\profile.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\cabinet_patient.php
```
Expected: оба без ошибок.

- [ ] **Step 5: Commit**

```powershell
git add public/profile.php templates/cabinet_patient.php public/assets/css/theme.css
git commit -m "feat: patient cabinet (history, no-show warning, booking modals markup)"
```

---

### Task P4-7: Мастер записи на JS (`public/assets/js/booking.js`)

**Files:**
- Create: `public/assets/js/booking.js`

- [ ] **Step 1: Написать `public/assets/js/booking.js`**

```js
(function () {
    'use strict';

    var root = document.getElementById('patient-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    // ─── Отмена записи ───
    var cancelId = null;
    var cancelModalEl = document.getElementById('cancelModal');
    var cancelModal = cancelModalEl ? new bootstrap.Modal(cancelModalEl) : null;
    document.querySelectorAll('.appt-cancel').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            cancelId = btn.dataset.id;
            if (cancelModal) cancelModal.show();
        });
    });
    var cancelYes = document.getElementById('cancelYes');
    if (cancelYes) {
        cancelYes.addEventListener('click', function () {
            if (!cancelId) return;
            post('/api/cancel_appointment.php', { appointment_id: cancelId }).then(function (d) {
                if (d.ok) { location.reload(); }
                else { alert(d.error || 'Не удалось отменить.'); }
            });
        });
    }

    // ─── Модалка завершённой записи ───
    var completedModalEl = document.getElementById('completedModal');
    var completedModal = completedModalEl ? new bootstrap.Modal(completedModalEl) : null;
    document.querySelectorAll('.appt-completed').forEach(function (row) {
        row.addEventListener('click', function () {
            var aid = row.dataset.apptId;
            var holder = document.querySelector('.appt-services[data-appt-id="' + aid + '"]');
            var list = document.getElementById('completedServices');
            list.innerHTML = '';
            if (holder) {
                JSON.parse(holder.textContent).forEach(function (s) {
                    var li = document.createElement('li');
                    li.className = 'd-flex justify-content-between border-bottom py-1';
                    var n = document.createElement('span'); n.textContent = s.name;
                    var p = document.createElement('span'); p.className = 'text-muted'; p.textContent = s.price;
                    li.appendChild(n); li.appendChild(p);
                    list.appendChild(li);
                });
            }
            if (completedModal) completedModal.show();
        });
    });

    // ─── Мастер записи ───
    var bookingModalEl = document.getElementById('bookingModal');
    if (!bookingModalEl) return;
    var bookingModal = new bootstrap.Modal(bookingModalEl);
    var confirmModalEl = document.getElementById('confirmBookingModal');
    var confirmModal = new bootstrap.Modal(confirmModalEl);
    var doctorSel = document.getElementById('bookingDoctor');
    var daysBox = document.getElementById('bookingDays');
    var slotsBox = document.getElementById('bookingSlots');
    var msgBox = document.getElementById('bookingMsg');
    var pending = null; // {doctorId, slotStart, label}

    // Заполнить врачей
    var doctors = JSON.parse(document.getElementById('doctors-data').textContent);
    doctorSel.innerHTML = '<option value="">— выберите врача —</option>';
    doctors.forEach(function (d) {
        var o = document.createElement('option');
        o.value = d.id; o.textContent = d.fio;
        doctorSel.appendChild(o);
    });

    document.getElementById('openBooking').addEventListener('click', function () {
        doctorSel.value = '';
        daysBox.innerHTML = '';
        slotsBox.innerHTML = '';
        msgBox.textContent = '';
        bookingModal.show();
    });

    doctorSel.addEventListener('change', function () {
        daysBox.innerHTML = '';
        slotsBox.innerHTML = '';
        msgBox.textContent = '';
        var docId = doctorSel.value;
        if (!docId) return;
        msgBox.textContent = 'Загрузка расписания…';
        fetch('/api/booking_slots.php?doctor_id=' + encodeURIComponent(docId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                msgBox.textContent = '';
                if (!d.ok) { msgBox.textContent = d.error || 'Ошибка.'; return; }
                if (!d.days.length) { msgBox.textContent = 'Нет свободных слотов в ближайшие 2 недели.'; return; }
                d.days.forEach(function (day) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'btn btn-outline-orange btn-sm booking-day-btn';
                    b.textContent = day.label;
                    b.addEventListener('click', function () {
                        daysBox.querySelectorAll('.btn').forEach(function (x) { x.classList.remove('active'); });
                        b.classList.add('active');
                        renderSlots(day.slots);
                    });
                    daysBox.appendChild(b);
                });
            })
            .catch(function () { msgBox.textContent = 'Ошибка загрузки.'; });
    });

    function renderSlots(slots) {
        slotsBox.innerHTML = '';
        slots.forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-outline-orange btn-sm booking-slot-btn';
            b.textContent = s.label;
            b.addEventListener('click', function () {
                pending = { doctorId: doctorSel.value, slotStart: s.start, label: s.label };
                var docName = doctorSel.options[doctorSel.selectedIndex].textContent;
                document.getElementById('confirmBookingText').textContent =
                    'Записаться к ' + docName + ' на ' + s.label + '?';
                confirmModal.show();
            });
            slotsBox.appendChild(b);
        });
    }

    document.getElementById('confirmBookingYes').addEventListener('click', function () {
        if (!pending) return;
        post('/api/book_appointment.php', { doctor_id: pending.doctorId, slot_start: pending.slotStart })
            .then(function (d) {
                if (d.ok) { location.reload(); }
                else {
                    confirmModal.hide();
                    msgBox.textContent = d.error || 'Не удалось записаться.';
                }
            });
    });
})();
```

- [ ] **Step 2: Базовая проверка (страница рендерится с подключённым скриптом)**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8812','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8812/assets/js/booking.js' -UseBasicParsing
    "booking.js: $($r.StatusCode), $($r.Content.Length)"
    if ($r.Content -match 'book_appointment' -and $r.Content -match 'cancel_appointment') { 'js wired OK' }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 200, `js wired OK`.

- [ ] **Step 3: Commit**

```powershell
git add public/assets/js/booking.js
git commit -m "feat: patient booking wizard + cancel (vanilla JS, AJAX)"
```

---

### Task P4-8: Сквозная проверка Фазы 4 + тег

- [ ] **Step 1: Все smoke-тесты**

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
foreach ($t in @('smoke_db','smoke_csrf','smoke_password','smoke_upload','smoke_appointment')) {
    & $php -d zend.assertions=1 -d assert.exception=1 "tests/$t.php"
}
```
Expected: 5 строк «… OK».

- [ ] **Step 2: Функциональный тест: запись → проверка → отмена**

Логин пациентом Смирнов (id=5), записываемся к врачу id=4 (Кузнецова — у неё в seed только прошлые записи, будущие слоты свободны), проверяем создание, затем отменяем.

```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8813','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    # login
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/login.php' -SessionVariable sess -UseBasicParsing
    $tok = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8813/login.php' -WebSession $sess -Method POST -Body @{ _csrf=$tok; email='smirnov@example.com'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    # page CSRF token
    $prof = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/profile.php' -WebSession $sess -UseBasicParsing
    $pageCsrf = ([regex]'data-csrf="([a-f0-9]+)"').Match($prof.Content).Groups[1].Value
    if ($prof.Content -match 'Мои записи') { 'patient cabinet renders OK' } else { 'CABINET MISSING' }
    # slots for doctor 4
    $slots = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/api/booking_slots.php?doctor_id=4' -WebSession $sess -UseBasicParsing
    $slotsJson = $slots.Content | ConvertFrom-Json
    $firstSlot = $slotsJson.days[0].slots[0].start
    "first free slot: $firstSlot"
    # book it
    $book = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/api/book_appointment.php' -WebSession $sess -Method POST -UseBasicParsing -Body @{ _csrf=$pageCsrf; doctor_id='4'; slot_start=$firstSlot }
    "book: $($book.Content)"
    $apptId = ($book.Content | ConvertFrom-Json).appointment_id
    # verify in DB
    & $mysql -uroot dentistry -e "SELECT id, patient_id, doctor_id, slot_start, status FROM appointment WHERE id=$apptId;"
    # double-book same slot → must fail
    $dup = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/api/book_appointment.php' -WebSession $sess -Method POST -UseBasicParsing -Body @{ _csrf=$pageCsrf; doctor_id='4'; slot_start=$firstSlot }
    if (($dup.Content | ConvertFrom-Json).ok -eq $false) { 'double-book rejected OK' } else { 'DOUBLE BOOK ALLOWED!' }
    # cancel
    $cancel = Invoke-WebRequest -Uri 'http://127.0.0.1:8813/api/cancel_appointment.php' -WebSession $sess -Method POST -UseBasicParsing -Body @{ _csrf=$pageCsrf; appointment_id=$apptId }
    "cancel: $($cancel.Content)"
    $left = (& $mysql -uroot dentistry -N -e "SELECT COUNT(*) FROM appointment WHERE id=$apptId;").Trim()
    "rows after cancel (expect 0): $left"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: cabinet renders OK; first free slot is a future weekday hour; book returns `{"ok":true,...}`; DB row exists (status=created, patient_id=5, doctor_id=4); double-book rejected OK; cancel `{"ok":true}`; rows after cancel = 0.

- [ ] **Step 3: Негативный — гость не может создать запись**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8814','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8814/api/book_appointment.php' -Method POST -Body @{ doctor_id='4'; slot_start='2030-01-01 10:00:00' } -UseBasicParsing -SkipHttpErrorCheck
    "guest book: $($r.StatusCode)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 403.

- [ ] **Step 4: Регрессия — публичные/auth/doctors страницы 200**

```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8815','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    foreach ($u in @('/', '/services.php', '/blog.php', '/contacts.php', '/doctors.php', '/login.php', '/register.php')) {
        "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8815$u" -UseBasicParsing).StatusCode))  $u"
    }
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: все 200.

- [ ] **Step 5: Убедиться, что тест не оставил мусора в БД и git-дереве**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT COUNT(*) AS appts FROM appointment;"
git status --short
```
Expected: appts = 7 (исходные seed-записи; тестовая создана и отменена), git status — чисто (никаких tmp.log).

- [ ] **Step 6: Тег**

```powershell
git tag phase-4-complete
git tag --list 'phase-*'
git log --oneline -10
```

---

## Чек-лист соответствия спецификации (Фаза 4)

| Требование PROMPT.md | Где |
|---|---|
| Кнопка с инициалами → профиль (есть с Фазы 2) | header.php |
| Список истории посещений сверху вниз (новые→старые) | P4-6 (ORDER BY slot_start DESC) |
| Запись: дата/время, ФИО врача, статус | P4-6 |
| Цвета статусов (градиент зелёный→серый, красный noshow) | theme.css + appt_status_class (Фаза 1 + P4-1) |
| Крестик отмены у «Создана» + модалка подтверждения | P4-6 + P4-7 + P4-5 |
| Клик по «Завершена» → модалка со списком услуг + Протокол/Чек | P4-6 + P4-7 (PDF-кнопки заглушены до Фазы 7) |
| Кнопки «Записаться» и «Мед. карта» над списком | P4-6 («Мед.карта» заглушена до Фазы 7) |
| Мастер записи: врач → день → слот → подтверждение → запись | P4-3/4 + P4-6/7 |
| Красная надпись о санкциях при наличии «Создана» | P4-6 (`$hasCreated`) |
| Слоты 10–18 с перерывом 14–15, 1 час | P4-1 (slot_hours) |

Отложено (зависит от других фаз):
- Реальные PDF (Мед.карта, Протокол, Чек) → Фаза 7.
- Подтверждение/исполнение/завершение записи и статус «Не явка» ставят регистратор/врач → Фазы 5–6.
- Email-напоминание за день → Фаза 7 (cron).

---

**Итог Фазы 4:** пациент видит свою историю со статусами, отменяет «Создана», записывается к врачу через мастер по свободным слотам; есть предупреждение о санкциях за неявку и блок записи при бане. Дальше — Фаза 5 (кабинет врача: расписание-календарь, отметка услуг, протокол приёма).
