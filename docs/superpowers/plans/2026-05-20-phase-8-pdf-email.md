# Стоматологическая клиника — Фаза 8: PDF-документы и email-напоминания

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Превратить протокол приёма и чек в настоящие PDF; добавить мед.карту (PDF со всеми протоколами пациента); реализовать автоматическую отправку email-напоминаний за день до приёма.

**Architecture:** Вендорится Dompdf (bundled, со своим автозагрузчиком и шрифтом DejaVu Sans с кириллицей — без Composer). Хелпер `lib/pdf.php` рендерит чистый HTML (без Bootstrap-layout) в PDF и отдаёт инлайн. `protocol_view.php` и `receipt.php` (из Фаз 5–6) переписываются на PDF-вывод; добавляется `med_card.php`. Напоминания — CLI-скрипт `cli/send_reminders.php` (находит записи на завтра без отметки, шлёт письмо через PHPMailer/Mailer, ставит `reminder_sent_at`); запускается планировщиком ОС.

**Tech Stack:** PHP 8.3, Dompdf 3.1.5 (vendored), MySQL 8.2, PHPMailer (Mailer из Фазы 2). Хелперы Фаз 1–7.

---

## Что приходит с Фаз 1–7

- `public/protocol_view.php` (Фаза 5) — HTML-просмотр протокола (доступ: врач-владелец/пациент/админ). Переписываем на PDF.
- `public/receipt.php` (Фаза 6) — HTML-чек (регистратор/админ/пациент). Переписываем на PDF.
- `lib/mailer.php` (Фаза 2) — `Mailer::send($to,$subject,$body)` (SMTP или лог в data/mail.log).
- `lib/appointment.php` — `appt_status_label`, `fmt_dt`, и т.д. `lib/sanitize.php` — `h`, `fmt_dt`, `fmt_price`.
- Таблица `appointment` имеет `reminder_sent_at DATETIME NULL` (Фаза 1).
- `templates/cabinet_patient.php` (Фаза 4) — кнопка «Мед. карта» (проверить её текущую цель в Task P8-4).
- Уже скачан и лежит (незакоммичен) `lib/dompdf/` — bundled Dompdf 3.1.5 с `autoload.inc.php`. Проверено: рендерит кириллицу в валидный PDF.

---

## Структура файлов после Фазы 8

```
lib/dompdf/                       # вендоренный Dompdf (commit в P8-1)
lib/pdf.php                       # хелпер HTML→PDF (новый)
tests/smoke_pdf.php               # smoke (новый)
public/protocol_view.php          # → PDF (правка)
public/receipt.php                # → PDF (правка)
public/med_card.php               # мед.карта PDF (новый)
templates/cabinet_patient.php     # кнопка «Мед. карта» → /med_card.php (правка, если нужно)
cli/send_reminders.php            # CLI напоминания (новый)
docs/REMINDERS_SETUP.md           # как поставить в планировщик (новый, краткий)
```

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- Логины: см. seed (`Password1!`). Пациент Смирнов id=5 имеет завершённую запись id=5 с протоколом и оплатой.

---

## Подход к тестированию

- **PDF** — запрос отдаёт `Content-Type: application/pdf` и тело начинается с `%PDF-`; доступ по ролям сохранён (чужой протокол/чек → 403).
- **Мед.карта** — для пациента с протоколами PDF содержит данные; для пациента без протоколов — корректная заглушка.
- **Напоминания** — на подготовленной записи «на завтра» CLI шлёт письмо (в dev — строка в `data/mail.log`) и ставит `reminder_sent_at`; повторный запуск не дублирует.
- Регрессия публичных страниц; откат тестовых правок.

---

## Задачи

---

### Task P8-1: Вендоринг Dompdf + хелпер `lib/pdf.php`

**Files:**
- Add (already on disk): `lib/dompdf/**` (bundled Dompdf 3.1.5)
- Create: `lib/pdf.php`
- Create: `tests/smoke_pdf.php`

- [ ] **Step 1: Создать `lib/pdf.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/** Обёртка чистого HTML-документа для PDF (кириллица через DejaVu Sans). */
function pdf_document(string $bodyHtml, string $title = ''): string
{
    $t = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<html><head><meta charset="utf-8"><title>' . $t . '</title><style>'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:12px;color:#222;}'
        . 'h1{font-size:18px;margin:0 0 10px;} h2{font-size:14px;margin:14px 0 6px;}'
        . 'table{width:100%;border-collapse:collapse;margin:8px 0;}'
        . 'td,th{padding:5px 7px;border-bottom:1px solid #ddd;text-align:left;}'
        . '.muted{color:#666;} .right{text-align:right;} .tot{font-weight:bold;}'
        . '.row{margin:3px 0;} .lbl{display:inline-block;width:140px;color:#666;}'
        . '</style></head><body>' . $bodyHtml . '</body></html>';
}

/** Отрендерить HTML в PDF и отдать инлайн (Content-Type: application/pdf), затем exit. */
function pdf_render_inline(string $html, string $filename): void
{
    $opt = new Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($opt);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => false]);
    exit;
}
```

- [ ] **Step 2: Создать `tests/smoke_pdf.php`**

```php
<?php
require_once __DIR__ . '/../lib/pdf.php';

$html = pdf_document('<h1>Протокол приёма</h1><p>Пациент: Иванов И.И. Сумма: 3 500,00 ₽.</p>', 'Тест');
$opt = new Dompdf\Options();
$opt->set('defaultFont', 'DejaVu Sans');
$d = new Dompdf\Dompdf($opt);
$d->loadHtml($html, 'UTF-8');
$d->setPaper('A4');
$d->render();
$out = $d->output();

assert(strlen($out) > 1000, 'PDF must be non-trivial');
assert(substr($out, 0, 5) === '%PDF-', 'Output must be a PDF');

echo "PDF smoke: OK\n";
```

- [ ] **Step 3: Запустить smoke**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -d zend.assertions=1 -d assert.exception=1 tests\smoke_pdf.php
```
Expected: `PDF smoke: OK`.

- [ ] **Step 4: Commit** (Dompdf — большой вендор; коммитим, чтобы проект был самодостаточным)
```powershell
git add lib/dompdf lib/pdf.php tests/smoke_pdf.php
git commit -m "feat: vendor Dompdf 3.1.5 + pdf helper (HTML->PDF, Cyrillic via DejaVu Sans)"
```

---

### Task P8-2: Протокол в PDF (`public/protocol_view.php`)

**Files:**
- Modify: `public/protocol_view.php` (переписать вывод на PDF)

- [ ] **Step 1: Полностью переписать `public/protocol_view.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user   = Auth::user();
$uid    = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id, a.doctor_id,
            pr.protocol_text, pr.recommendations,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) { http_response_code(404); echo 'Протокол не найден.'; exit; }
$allowed = ($uid === (int) $row['doctor_id']) || ($uid === (int) $row['patient_id']) || ($user['role_code'] === 'admin');
if (!$allowed) { http_response_code(403); echo 'Нет доступа.'; exit; }

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id' => $apptId]);

$patientFio = $row['p_last'].' '.$row['p_first'].' '.$row['p_mid'];
$doctorFio  = $row['d_last'].' '.$row['d_first'].' '.$row['d_mid'];

$body  = '<h1>Протокол приёма</h1>';
$body .= '<div class="row"><span class="lbl">Клиника:</span> ' . h(SITE_NAME) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($patientFio) . '</div>';
$body .= '<div class="row"><span class="lbl">Врач:</span> ' . h($doctorFio) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата приёма:</span> ' . h(fmt_dt($row['slot_start'])) . '</div>';
if (!empty($services)) {
    $body .= '<h2>Оказанные услуги</h2><table><tr><th>Услуга</th><th class="right">Стоимость</th></tr>';
    foreach ($services as $s) $body .= '<tr><td>' . h($s['name']) . '</td><td class="right">' . h(fmt_price($s['price_at_time'])) . '</td></tr>';
    $body .= '</table>';
}
$body .= '<h2>Протокол</h2><div>' . nl2br(h($row['protocol_text'])) . '</div>';
if (!empty($row['recommendations'])) {
    $body .= '<h2>Рекомендации</h2><div>' . nl2br(h($row['recommendations'])) . '</div>';
}

pdf_render_inline(pdf_document($body, 'Протокол приёма'), 'protocol_' . $apptId . '.pdf');
```

- [ ] **Step 2: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\protocol_view.php
git add public/protocol_view.php
git commit -m "feat: protocol_view outputs PDF (Dompdf)"
```

---

### Task P8-3: Чек в PDF (`public/receipt.php`)

**Files:**
- Modify: `public/receipt.php` (переписать вывод на PDF)

- [ ] **Step 1: Полностью переписать `public/receipt.php`**

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

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

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id' => $apptId]);
$methodLabel = $row['method'] === 'cash' ? 'Наличными' : 'Картой';

$body  = '<h1>Кассовый чек</h1>';
$body .= '<div class="row tot">' . h(SITE_NAME) . '</div>';
$body .= '<div class="row muted">' . h(CLINIC_ADDRESS) . '</div>';
$body .= '<div class="row muted">тел. ' . h(CLINIC_PHONE) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($row['p_last'].' '.$row['p_first'].' '.$row['p_mid']) . '</div>';
$body .= '<div class="row"><span class="lbl">Врач:</span> ' . h($row['d_last'].' '.$row['d_first'].' '.$row['d_mid']) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата приёма:</span> ' . h(fmt_dt($row['slot_start'])) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата оплаты:</span> ' . h(fmt_dt($row['paid_at'])) . '</div>';
$body .= '<div class="row"><span class="lbl">Способ оплаты:</span> ' . h($methodLabel) . '</div>';
$body .= '<table><tr><th>Услуга</th><th class="right">Стоимость</th></tr>';
foreach ($services as $s) $body .= '<tr><td>' . h($s['name']) . '</td><td class="right">' . h(fmt_price($s['price_at_time'])) . '</td></tr>';
$body .= '<tr class="tot"><td>Итого</td><td class="right">' . h(fmt_price($row['total_amount'])) . '</td></tr></table>';

pdf_render_inline(pdf_document($body, 'Чек'), 'receipt_' . $apptId . '.pdf');
```

- [ ] **Step 2: Syntax check + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\receipt.php
git add public/receipt.php
git commit -m "feat: receipt outputs PDF (Dompdf)"
```

---

### Task P8-4: Мед.карта (`public/med_card.php`) + кнопка пациента

**Files:**
- Create: `public/med_card.php`
- Modify: `templates/cabinet_patient.php` (если кнопка «Мед. карта» ведёт не на `/med_card.php`)

- [ ] **Step 1: Создать `public/med_card.php`** — PDF со всеми протоколами пациента.

Доступ: пациент видит свою карту (без параметра); врач/админ/регистратор — карту пациента по `?patient_id=`.

```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user = Auth::user();
$uid  = (int) $user['id'];
$role = $user['role_code'];

// Пациент — только своя карта; персонал может смотреть по patient_id
$patientId = $uid;
if ($role !== 'patient') {
    $patientId = (int) ($_GET['patient_id'] ?? 0);
    if ($role !== 'admin' && $role !== 'doctor' && $role !== 'registrar') { http_response_code(403); echo 'Нет доступа.'; exit; }
}

$patient = DB::one("SELECT last_name, first_name, middle_name FROM user WHERE id = :id AND role_id = 4", ['id' => $patientId]);
if ($patient === null) { http_response_code(404); echo 'Пациент не найден.'; exit; }
$patientFio = $patient['last_name'].' '.$patient['first_name'].' '.$patient['middle_name'];

$protocols = DB::all(
    "SELECT a.slot_start, pr.protocol_text, pr.recommendations,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.patient_id = :pid
      ORDER BY a.slot_start DESC",
    ['pid' => $patientId]
);

$body  = '<h1>Медицинская карта</h1>';
$body .= '<div class="row"><span class="lbl">Клиника:</span> ' . h(SITE_NAME) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($patientFio) . '</div>';
if (empty($protocols)) {
    $body .= '<p class="muted">Протоколов приёма пока нет.</p>';
} else {
    foreach ($protocols as $p) {
        $docFio = $p['d_last'].' '.$p['d_first'].' '.$p['d_mid'];
        $body .= '<h2>' . h(fmt_dt($p['slot_start'])) . ' — ' . h($docFio) . '</h2>';
        $body .= '<div>' . nl2br(h($p['protocol_text'])) . '</div>';
        if (!empty($p['recommendations'])) {
            $body .= '<div class="muted"><b>Рекомендации:</b> ' . nl2br(h($p['recommendations'])) . '</div>';
        }
    }
}

pdf_render_inline(pdf_document($body, 'Медицинская карта'), 'med_card_' . $patientId . '.pdf');
```

- [ ] **Step 2: Привязать кнопку «Мед. карта».** Прочитать `templates/cabinet_patient.php`, найти кнопку «Мед. карта». Если её ссылка не `/med_card.php` (например, заглушка `#` или старый путь) — заменить `href` на `/med_card.php` и (если открывалась модалкой-заглушкой) сделать обычной ссылкой `target="_blank"`. Если уже ведёт на `/med_card.php` — пропустить правку.

- [ ] **Step 3: Syntax check + smoke (гость → login)**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public\med_card.php
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8860','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8860/med_card.php' -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue; "guest: $($r.StatusCode) -> $($r.Headers.Location)" } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: 302 → /login.php.

- [ ] **Step 4: Commit**
```powershell
git add public/med_card.php templates/cabinet_patient.php
git commit -m "feat: medical card PDF (all patient protocols) + patient button"
```

---

### Task P8-5: Email-напоминания за день (`cli/send_reminders.php`)

**Files:**
- Create: `cli/send_reminders.php`
- Create: `docs/REMINDERS_SETUP.md`

- [ ] **Step 1: Создать `cli/send_reminders.php`** (CLI; запускается планировщиком раз в день).

```php
<?php
declare(strict_types=1);

// Запускать из CLI: php cli/send_reminders.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/sanitize.php';

// Записи на ЗАВТРА, ещё не подтверждённые отметкой, активные статусы
$rows = DB::all(
    "SELECT a.id, a.slot_start,
            p.email, p.last_name, p.first_name, p.middle_name,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE DATE(a.slot_start) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))
        AND a.status IN ('created','confirmed')
        AND a.reminder_sent_at IS NULL"
);

$sent = 0;
foreach ($rows as $r) {
    $docFio = $r['d_last'].' '.$r['d_first'].' '.$r['d_mid'];
    $subject = 'Напоминание о приёме';
    $bodyText = "Здравствуйте, " . $r['last_name'] . ' ' . $r['first_name'] . "!\n\n"
        . "Напоминаем о приёме в " . SITE_NAME . " завтра, " . fmt_dt($r['slot_start']) . ".\n"
        . "Врач: " . $docFio . ".\n\n"
        . "Если планы изменились — отмените запись в личном кабинете.";
    if (Mailer::send($r['email'], $subject, $bodyText)) {
        DB::exec("UPDATE appointment SET reminder_sent_at = NOW() WHERE id = :id", ['id' => (int) $r['id']]);
        $sent++;
    }
}

echo "Reminders processed: " . count($rows) . ", sent: " . $sent . PHP_EOL;
```

- [ ] **Step 2: Создать `docs/REMINDERS_SETUP.md`** (кратко)

```markdown
# Email-напоминания за день до приёма

Скрипт `cli/send_reminders.php` находит записи на завтра (статус «Создана»/«Подтверждена»)
без отметки `reminder_sent_at`, отправляет пациенту письмо и ставит отметку (повторно не дублирует).

## Ручной запуск
```
"C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe" cli\send_reminders.php
```

## Автоматический запуск (Windows, раз в день)
Планировщик заданий → Создать задачу → ежедневно (например, 09:00) →
Действие: запуск программы
- Программа: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- Аргументы: `cli\send_reminders.php`
- Рабочая папка: `C:\all-stuff\soft\OpenServer\home\dentistry.local`

В dev (пустой SMTP в config.php) письма пишутся в `data/mail.log`.
Для реальной отправки заполнить `SMTP_USER`/`SMTP_PASS` в `config.php`.
```

- [ ] **Step 3: Проверка синтаксиса + холостой прогон**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l cli\send_reminders.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' cli\send_reminders.php
```
Expected: `Reminders processed: N, sent: N` (N может быть 0, если на завтра нет записей).

- [ ] **Step 4: Commit**
```powershell
git add cli/send_reminders.php docs/REMINDERS_SETUP.md
git commit -m "feat: daily email reminder CLI (day-before appointments) + setup doc"
```

---

### Task P8-6: Сквозная проверка Фазы 8 + тег

- [ ] **Step 1: Smoke-тесты** (6 файлов, включая smoke_pdf) — все «OK».
```powershell
$php = 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
foreach ($t in @('smoke_db','smoke_csrf','smoke_password','smoke_upload','smoke_appointment','smoke_pdf')) { & $php -d zend.assertions=1 -d assert.exception=1 "tests/$t.php" }
```

- [ ] **Step 2: PDF E2E** — протокол, чек, мед.карта отдают `%PDF-`. Пациент Смирнов (id=5): запись id=5 (completed) имеет протокол id=2 и оплату.
```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
$server = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8861','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try {
    $g = Invoke-WebRequest -Uri 'http://127.0.0.1:8861/login.php' -SessionVariable s -UseBasicParsing
    $t = ([regex]'name="_csrf" value="([a-f0-9]+)"').Match($g.Content).Groups[1].Value
    Invoke-WebRequest -Uri 'http://127.0.0.1:8861/login.php' -WebSession $s -Method POST -Body @{ _csrf=$t; email='smirnov@example.com'; password='Password1!' } -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
    foreach ($u in @('/protocol_view.php?appointment_id=5','/receipt.php?appointment_id=5','/med_card.php')) {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8861$u" -WebSession $s -UseBasicParsing
        $ct = $r.Headers['Content-Type']
        $magic = [System.Text.Encoding]::ASCII.GetString($r.Content[0..4])
        "$u -> $($r.StatusCode), CT=$ct, magic=$magic"
    }
    # чужой протокол (запись 4 — не Смирнова) → 403
    $f = Invoke-WebRequest -Uri 'http://127.0.0.1:8861/protocol_view.php?appointment_id=4' -WebSession $s -UseBasicParsing -SkipHttpErrorCheck
    "foreign protocol: $($f.StatusCode) (expect 403)"
} finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
```
Expected: три URL → 200, CT=application/pdf, magic=%PDF-; чужой протокол → 403.

- [ ] **Step 3: Напоминания E2E** — создать запись на завтра, прогнать скрипт, проверить письмо в `data/mail.log` и `reminder_sent_at`; повторный прогон не дублирует; затем удалить тестовую запись.
```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'; $mysql='C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
# найти завтрашний будний слот; если суббота/воскресенье — взять понедельник (для теста допустимо любое завтра)
& $mysql -uroot dentistry -e "INSERT INTO appointment (patient_id,doctor_id,slot_start,slot_end,status) VALUES (5,3, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 12 HOUR, DATE_ADD(CURDATE(), INTERVAL 1 DAY) + INTERVAL 13 HOUR, 'created');"
$tid = (& $mysql -uroot dentistry -N -e "SELECT id FROM appointment WHERE patient_id=5 AND doctor_id=3 AND status='created' AND DATE(slot_start)=DATE(DATE_ADD(NOW(),INTERVAL 1 DAY)) ORDER BY id DESC LIMIT 1").Trim()
"test appt id: $tid"
if (Test-Path 'data\mail.log') { Clear-Content 'data\mail.log' }
& $php cli\send_reminders.php
"reminder_sent_at set: $((& $mysql -uroot dentistry -N -e "SELECT IF(reminder_sent_at IS NULL,'NO','YES') FROM appointment WHERE id=$tid").Trim())"
"mail.log has reminder: $(if (Select-String -Path 'data\mail.log' -Pattern 'Напоминание о приёме' -Quiet) {'YES'} else {'NO'})"
# повторный прогон — не должно слать снова
$again = & $php cli\send_reminders.php
"second run: $again"
# cleanup
& $mysql -uroot dentistry -e "DELETE FROM appointment WHERE id=$tid;"
"cleanup done"
```
Expected: reminder_sent_at=YES; mail.log содержит «Напоминание о приёме»; второй прогон `sent: 0`; тестовая запись удалена.

- [ ] **Step 4: Регрессия + чистота + тег**
```powershell
$server = Start-Process -FilePath 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -ArgumentList '-S','127.0.0.1:8862','-t','public' -PassThru -NoNewWindow -RedirectStandardError 'tmp.log'
Start-Sleep -Seconds 2
try { foreach ($u in @('/', '/services.php', '/blog.php', '/doctors.php', '/contacts.php', '/login.php')) { "$(((Invoke-WebRequest -Uri "http://127.0.0.1:8862$u" -UseBasicParsing).StatusCode))  $u" } } finally { Stop-Process -Id $server.Id -Force; Remove-Item tmp.log -ErrorAction SilentlyContinue }
git status --short
git tag phase-8-complete
git tag --list 'phase-*'
```
Expected: pages 200; git clean (data/mail.log игнорируется); тег создан.

---

## Чек-лист соответствия спецификации (Фаза 8)

| Требование PROMPT.md | Где |
|---|---|
| «Протокол приёма» открывает PDF | P8-2 |
| «Чек» открывает PDF | P8-3 |
| «Мед. карта» — PDF со всеми протоколами | P8-4 |
| PDF на бэкенде | P8-1 (Dompdf) |
| Email-напоминание за день до приёма, на бэкенде, автоматически | P8-5 (CLI + планировщик) |
| Кириллица в документах | P8-1 (DejaVu Sans) |

---

**Итог Фазы 8 — проект завершён:** все PDF-документы (протокол, чек, мед.карта) и автоматические email-напоминания реализованы. Все 8 фаз закрыты.
