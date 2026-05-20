# Стоматологическая клиника — Фаза 9: Расширение БД до 16 таблиц

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Добавить 6 таблиц (10 → 16): `payment_method`, `specialization`, `appointment_status_history`, `notification_log`, `review`, `doctor_day_off` — с интеграцией в интерфейс по согласованным требованиям, не сломав работающее приложение.

**Architecture:** Аддитивная миграция `db/migrations/002_phase9.sql` (CREATE TABLE + ALTER + backfill) для рабочей БД; те же изменения зеркалятся в `db/schema.sql` и `db/seed.sql` для чистой установки. Два ENUM/текстовых поля нормализуются в справочники: `payment.method`→`payment.method_id`→`payment_method`, `doctor_profile.specialization`→`specialization_id`→`specialization`. Журналирование статусов — через хелпер, вызываемый в точках смены статуса. Лог писем — в `Mailer::send`. Отзывы и выходные врача — новые сущности со своими эндпоинтами и UI.

**Tech Stack:** PHP 8.3, MySQL 8.2 (PDO), Bootstrap 5.3. Хелперы и роли Фаз 1–8.

---

## Согласованные требования к интеграции

- **payment_method** — справочник; `payment.method_id` FK вместо ENUM. Чек/модалка регистратора показывают название из справочника.
- **specialization** — админ ведёт (CRUD в кабинете админа); врач выбирает из выпадающего списка в своём профиле; на странице «Врачи» показывается название.
- **appointment_status_history** — журнал (старый→новый статус, кто, когда); **отображается в окне записи у регистратора**.
- **notification_log** — лог отправленных писем; **просмотр только в кабинете администратора**.
- **review** — пациент оставляет отзыв (1–5 + текст) к завершённой записи; средняя оценка врача на странице «Врачи»; админ может удалять.
- **doctor_day_off** — врач ведёт свои выходные в кабинете; в эти дни слоты недоступны при записи (и пациенту, и регистратору).

---

## Итоговый список таблиц (16)

Существующие (10): role, user, email_code, service, blog_article, appointment, appointment_service, protocol, payment, doctor_profile.
Новые (6): payment_method, specialization, appointment_status_history, notification_log, review, doctor_day_off.

---

## Параметры окружения

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe -uroot dentistry`
- Smoke-сервер: `php -S 127.0.0.1:PORT -t public`
- ⚠️ Кириллица в curl-тестах искажается командной строкой — текстовые поля в тестах задавать ASCII. Приложение кириллицу хранит корректно.
- ⚠️ В рабочей БД есть данные пользователя (аккаунт asmaev.daniil@mail.ru, его записи/протоколы/оплаты) — НЕ удалять.

---

## Задачи

---

### Task P9-1: Схема — 6 новых таблиц (миграция + schema.sql + seed.sql)

**Files:**
- Create: `db/migrations/002_phase9.sql`
- Modify: `db/schema.sql`, `db/seed.sql`

- [ ] **Step 1: Создать `db/migrations/002_phase9.sql`** (для рабочей БД; аддитивно):

```sql
-- ===== Phase 9: +6 таблиц =====

-- 1) Справочник способов оплаты
CREATE TABLE IF NOT EXISTS payment_method (
    id   TINYINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO payment_method (id, code, name) VALUES (1,'cash','Наличными'), (2,'card','Картой');

-- payment.method_id (FK), бэкафилл из старого ENUM, затем удаление ENUM
SET @has_method := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payment' AND column_name='method');
SET @has_method_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payment' AND column_name='method_id');
SET @sql := IF(@has_method_id=0, 'ALTER TABLE payment ADD COLUMN method_id TINYINT UNSIGNED NULL AFTER method', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE payment SET method_id = CASE method WHEN 'cash' THEN 1 WHEN 'card' THEN 2 ELSE method_id END WHERE method_id IS NULL;
-- сделать NOT NULL + FK (если ещё не сделано)
ALTER TABLE payment MODIFY method_id TINYINT UNSIGNED NOT NULL;
SET @has_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='payment' AND constraint_name='fk_pay_method');
SET @sql := IF(@has_fk=0, 'ALTER TABLE payment ADD CONSTRAINT fk_pay_method FOREIGN KEY (method_id) REFERENCES payment_method(id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
-- удалить старый ENUM-столбец
SET @sql := IF(@has_method>0, 'ALTER TABLE payment DROP COLUMN method', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Справочник специализаций
CREATE TABLE IF NOT EXISTS specialization (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO specialization (name) VALUES
    ('Врач-стоматолог-терапевт'),
    ('Стоматолог-хирург, ортопед'),
    ('Стоматолог-ортодонт'),
    ('Детский стоматолог'),
    ('Гигиенист');

-- doctor_profile.specialization_id (FK), бэкафилл по тексту, затем удаление текстового поля
SET @has_spec_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='doctor_profile' AND column_name='specialization_id');
SET @sql := IF(@has_spec_id=0, 'ALTER TABLE doctor_profile ADD COLUMN specialization_id INT UNSIGNED NULL AFTER user_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE doctor_profile dp JOIN specialization s ON s.name = dp.specialization SET dp.specialization_id = s.id WHERE dp.specialization_id IS NULL AND dp.specialization IS NOT NULL AND dp.specialization <> '';
SET @has_spec_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='doctor_profile' AND constraint_name='fk_docprofile_spec');
SET @sql := IF(@has_spec_fk=0, 'ALTER TABLE doctor_profile ADD CONSTRAINT fk_docprofile_spec FOREIGN KEY (specialization_id) REFERENCES specialization(id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @has_spec_txt := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='doctor_profile' AND column_name='specialization');
SET @sql := IF(@has_spec_txt>0, 'ALTER TABLE doctor_profile DROP COLUMN specialization', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Журнал смены статусов записи
CREATE TABLE IF NOT EXISTS appointment_status_history (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    old_status     VARCHAR(20) NULL,
    new_status     VARCHAR(20) NOT NULL,
    changed_by     INT UNSIGNED NULL,
    changed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ix_ash_appt (appointment_id),
    CONSTRAINT fk_ash_appt FOREIGN KEY (appointment_id) REFERENCES appointment(id) ON DELETE CASCADE,
    CONSTRAINT fk_ash_user FOREIGN KEY (changed_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Лог отправленных писем
CREATE TABLE IF NOT EXISTS notification_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient  VARCHAR(190) NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ix_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Отзывы пациентов
CREATE TABLE IF NOT EXISTS review (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL UNIQUE,
    patient_id     INT UNSIGNED NOT NULL,
    doctor_id      INT UNSIGNED NOT NULL,
    rating         TINYINT UNSIGNED NOT NULL,
    body           TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_appt    FOREIGN KEY (appointment_id) REFERENCES appointment(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_patient FOREIGN KEY (patient_id) REFERENCES user(id),
    CONSTRAINT fk_review_doctor  FOREIGN KEY (doctor_id) REFERENCES user(id),
    INDEX ix_review_doctor (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Выходные/отпуска врача
CREATE TABLE IF NOT EXISTS doctor_day_off (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    off_date  DATE NOT NULL,
    reason    VARCHAR(120) NULL,
    UNIQUE KEY uq_doctor_off (doctor_id, off_date),
    CONSTRAINT fk_dayoff_doctor FOREIGN KEY (doctor_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: Применить миграцию к рабочей БД**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot --default-character-set=utf8mb4 dentistry -e "source db/migrations/002_phase9.sql"
```
Проверка:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SHOW TABLES;"
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT COUNT(*) total_tables FROM information_schema.tables WHERE table_schema='dentistry';"
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT id,method_id,total_amount FROM payment; DESC payment;"
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT dp.user_id, dp.specialization_id, sp.name FROM doctor_profile dp LEFT JOIN specialization sp ON sp.id=dp.specialization_id;"
```
Expected: 16 таблиц; payment без столбца `method`, с `method_id` (1/2) заполненным; doctor_profile без `specialization`, с `specialization_id`, у врачей 3 и 4 заполнен.

- [ ] **Step 3: Обновить `db/schema.sql`** для чистой установки. Прочитать файл; внести:
  (a) В блок DROP добавить (рядом с прочими дочерними, до user/appointment): `DROP TABLE IF EXISTS appointment_status_history; DROP TABLE IF EXISTS notification_log; DROP TABLE IF EXISTS review; DROP TABLE IF EXISTS doctor_day_off; DROP TABLE IF EXISTS payment_method; DROP TABLE IF EXISTS specialization;` (notification_log можно дропать в любом месте — нет FK).
  (b) `CREATE TABLE payment_method` — поставить ДО таблицы `payment`. `payment`: заменить столбец `method ENUM('cash','card') NOT NULL` на `method_id TINYINT UNSIGNED NOT NULL` и добавить `CONSTRAINT fk_pay_method FOREIGN KEY (method_id) REFERENCES payment_method(id)`.
  (c) `CREATE TABLE specialization` — ДО `doctor_profile`. В `doctor_profile`: заменить `specialization VARCHAR(150) NOT NULL DEFAULT ''` на `specialization_id INT UNSIGNED NULL` + `CONSTRAINT fk_docprofile_spec FOREIGN KEY (specialization_id) REFERENCES specialization(id)`.
  (d) В конец (после appointment/user) добавить `CREATE TABLE appointment_status_history`, `review`, `doctor_day_off` (как в миграции, но обычный CREATE TABLE без IF NOT EXISTS), и `notification_log`.
  (e) Добавить `INSERT INTO payment_method` (2 строки) и `INSERT INTO specialization` (5 строк) — как в миграции.

- [ ] **Step 4: Обновить `db/seed.sql`** для чистой установки:
  - В INSERT `doctor_profile`: вместо текстовой `specialization` использовать `specialization_id`. Заменить существующий блок на:
    ```sql
    INSERT INTO doctor_profile (user_id, specialization_id, bio) VALUES
        (3, 1, 'Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.'),
        (4, 2, 'Стаж 9 лет. Удаление зубов любой сложности, протезирование, имплантология. Кандидат медицинских наук.');
    ```
    (где 1 = «Врач-стоматолог-терапевт», 2 = «Стоматолог-хирург, ортопед» из specialization-сидов; specialization-таблица создаётся в schema.sql).
  - В INSERT `payment`: заменить `method` на `method_id`:
    ```sql
    INSERT INTO payment (appointment_id, method_id, total_amount) VALUES
        (5, 2, 4200.00),
        (6, 1, 10000.00);
    ```
  - Добавить пару демонстрационных отзывов (к завершённым записям 5 и 6):
    ```sql
    INSERT INTO review (appointment_id, patient_id, doctor_id, rating, body) VALUES
        (5, 5, 3, 5, 'Отличный врач, всё безболезненно.'),
        (6, 6, 4, 4, 'Хорошо, но пришлось подождать.');
    ```

- [ ] **Step 5: Проверка чистой установки на временной БД**
```powershell
$mysql = 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe'
& $mysql -uroot -e "DROP DATABASE IF EXISTS dentistry_test; CREATE DATABASE dentistry_test DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $mysql -uroot --default-character-set=utf8mb4 dentistry_test -e "source db/schema.sql"
& $mysql -uroot --default-character-set=utf8mb4 dentistry_test -e "source db/seed.sql"
& $mysql -uroot dentistry_test -e "SELECT COUNT(*) tables FROM information_schema.tables WHERE table_schema='dentistry_test'; SELECT COUNT(*) reviews FROM review; SELECT method_id FROM payment;"
& $mysql -uroot -e "DROP DATABASE dentistry_test;"
```
Expected: tables=16, reviews=2, payments с method_id, без ошибок.

- [ ] **Step 6: Commit**
```powershell
git add db/migrations/002_phase9.sql db/schema.sql db/seed.sql
git commit -m "feat(db): phase 9 schema — payment_method, specialization, status history, notification log, review, doctor_day_off (16 tables)"
```

---

### Task P9-2: Интеграция payment_method

**Files:** Modify `public/api/registrar_pay.php`, `public/receipt.php`, `public/api/registrar_appointment.php`

- [ ] **Step 1: `registrar_pay.php`** — вместо записи строкового `method` писать `method_id` по коду. Найти блок проверки метода и INSERT. Заменить логику:
  - после `$method = (string)($_POST['method'] ?? '');` и проверки `in_array($method,['cash','card'])` — получить id: `$mid = ($method === 'cash') ? 1 : 2;`
  - INSERT: `INSERT INTO payment (appointment_id, method_id, total_amount) VALUES (:a,:m,:t)` с `'m'=>$mid`.

- [ ] **Step 2: `receipt.php`** — в SELECT заменить `pay.method` на join к payment_method: добавить `JOIN payment_method pm ON pm.id = pay.method_id` и брать `pm.name AS method_label`. Убрать прежний расчёт `$methodLabel` из ENUM — использовать `$row['method_label']` напрямую.

- [ ] **Step 3: `registrar_appointment.php`** — где берётся `$payment = DB::one("SELECT method FROM payment WHERE appointment_id = :id", ...)` заменить на `SELECT pm.code AS method, pm.name AS method_name FROM payment pay JOIN payment_method pm ON pm.id=pay.method_id WHERE pay.appointment_id=:id`. В ответе `pay_method` оставить как `code` (cash/card) — фронт (registrar.js) сравнивает с 'cash'/'card'.

- [ ] **Step 2-3 verify + commit**
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public/api/registrar_pay.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public/receipt.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l public/api/registrar_appointment.php
git add public/api/registrar_pay.php public/receipt.php public/api/registrar_appointment.php
git commit -m "feat: use payment_method lookup (method_id) in pay/receipt/registrar detail"
```
Функционально проверится в P9-8 (чек по оплаченной записи показывает способ оплаты).

---

### Task P9-3: Интеграция specialization (админ CRUD + выбор врачом + страница «Врачи»)

**Files:** Modify `public/doctors.php`, `public/doctor_profile_edit.php`, `templates/cabinet_admin.php`; Create `public/admin/specializations.php`

- [ ] **Step 1: `public/doctors.php`** — в запросе заменить `dp.specialization` на join: `LEFT JOIN specialization sp ON sp.id = dp.specialization_id`, выбирать `sp.name AS specialization`. Остальной рендер (вывод `$d['specialization']`) не меняется.

- [ ] **Step 2: `public/doctor_profile_edit.php`** — заменить текстовое поле специализации на `<select>` из таблицы specialization.
  - В начале (после загрузки профиля) добавить: `$specs = DB::all("SELECT id, name FROM specialization ORDER BY name");` и `$specId = (int)($profile['specialization_id'] ?? 0);`
  - В POST: читать `$specId = (int)($_POST['specialization_id'] ?? 0);` (валидировать, что такая есть, иначе NULL).
  - В UPSERT `doctor_profile` заменить `specialization` на `specialization_id` (значение `$specId ?: null`).
  - В форме заменить `<input name="specialization">` на:
    ```php
    <select name="specialization_id" class="form-select">
        <option value="">— не выбрана —</option>
        <?php foreach ($specs as $sp): ?>
            <option value="<?= (int)$sp['id'] ?>" <?= $sp['id']==$specId?'selected':'' ?>><?= h($sp['name']) ?></option>
        <?php endforeach; ?>
    </select>
    ```
  - Текущий UPSERT (`INSERT ... ON DUPLICATE KEY UPDATE specialization=...`) переписать на `specialization_id`.

- [ ] **Step 3: Создать `public/admin/specializations.php`** — CRUD справочника (админ):
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
$_pageTitle = 'Специализации';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            try { DB::exec("INSERT INTO specialization (name) VALUES (:n)", ['n' => $name]); }
            catch (Throwable $e) { /* дубликат — игнор */ }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0); $name = trim((string)($_POST['name'] ?? ''));
        if ($id > 0 && $name !== '') DB::exec("UPDATE specialization SET name=:n WHERE id=:id", ['n'=>$name,'id'=>$id]);
    } elseif ($action === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        $used = DB::one("SELECT 1 FROM doctor_profile WHERE specialization_id=:id LIMIT 1", ['id'=>$id]);
        if ($used === null) { DB::exec("DELETE FROM specialization WHERE id=:id", ['id'=>$id]); }
        else { $error = 'Нельзя удалить: специализация используется врачом.'; }
    }
    if ($error === null) { header('Location: /admin/specializations.php'); exit; }
}

$rows = DB::all("SELECT s.id, s.name, (SELECT COUNT(*) FROM doctor_profile dp WHERE dp.specialization_id=s.id) AS used FROM specialization s ORDER BY s.name");
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Специализации</h1>
        <a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="d-flex gap-2 mb-3">
        <?= Csrf::field() ?><input type="hidden" name="action" value="add">
        <input type="text" name="name" class="form-control" placeholder="Новая специализация" required>
        <button class="btn btn-orange">Добавить</button>
    </form>
    <table class="table align-middle">
        <thead><tr><th>Название</th><th>Врачей</th><th style="width:230px;"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="text" name="name" class="form-control form-control-sm" value="<?= h($r['name']) ?>">
                </td>
                <td><?= (int)$r['used'] ?></td>
                <td>
                        <button class="btn btn-outline-orange btn-sm">Сохранить</button>
                    </form>
                    <?php if ((int)$r['used'] === 0): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Удалить?');">
                            <?= Csrf::field() ?><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm">Удалить</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
```

- [ ] **Step 4: Ссылка в `templates/cabinet_admin.php`** — рядом с кнопками «Каталог услуг»/«Блог» добавить `<a href="/admin/specializations.php" class="btn btn-outline-orange btn-sm">Специализации</a>`.

- [ ] **Step 5: Verify + commit**
```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
& $php -l public/doctors.php; & $php -l public/doctor_profile_edit.php; & $php -l public/admin/specializations.php; & $php -l templates/cabinet_admin.php
git add public/doctors.php public/doctor_profile_edit.php public/admin/specializations.php templates/cabinet_admin.php
git commit -m "feat: specialization dictionary — admin CRUD, doctor select, doctors page join"
```

---

### Task P9-4: appointment_status_history (журнал + показ регистратору)

**Files:** Modify `lib/appointment.php`, `public/api/book_appointment.php`, `public/api/registrar_book.php`, `public/api/registrar_set_status.php`, `public/api/registrar_pay.php`, `public/protocol_edit.php`, `public/api/registrar_appointment.php`, `templates/cabinet_registrar.php`, `public/assets/js/registrar.js`

- [ ] **Step 1: Хелпер в `lib/appointment.php`** (в конец):
```php
/** Запись в журнал смены статусов записи. */
function appt_log_status(int $appointmentId, ?string $old, string $new, ?int $byUserId): void
{
    DB::exec(
        "INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by)
         VALUES (:a, :o, :n, :u)",
        ['a' => $appointmentId, 'o' => $old, 'n' => $new, 'u' => $byUserId]
    );
}
```

- [ ] **Step 2: Логировать в точках смены статуса.** После каждого создания/смены статуса добавить вызов:
  - `book_appointment.php`: после успешного INSERT — `appt_log_status(DB::lastId(), null, 'created', $patientId);` (учесть, что lastId берётся уже для ответа — сохранить в переменную).
  - `registrar_book.php`: после INSERT — `appt_log_status(DB::lastId(), null, 'created', (int)Auth::user()['id']);`
  - `registrar_set_status.php`: перед UPDATE получить старый статус (`$old = DB::one('SELECT status...')`), после UPDATE — `appt_log_status($apptId, $old['status'], $status, (int)Auth::user()['id']);`
  - `registrar_pay.php`: после `UPDATE ... status='completed'` — `appt_log_status($apptId, 'performed', 'completed', (int)Auth::user()['id']);`
  - `protocol_edit.php`: после `UPDATE ... status='performed' WHERE status='confirmed'` — если строка реально обновилась (проверить `DB::exec` вернул >0), `appt_log_status($apptId, 'confirmed', 'performed', $docId);`

- [ ] **Step 3: `registrar_appointment.php`** — добавить в ответ историю:
```php
$history = DB::all(
    "SELECT h.old_status, h.new_status, h.changed_at,
            u.last_name, u.first_name
       FROM appointment_status_history h
       LEFT JOIN user u ON u.id = h.changed_by
      WHERE h.appointment_id = :id ORDER BY h.changed_at",
    ['id' => $apptId]
);
$histOut = [];
foreach ($history as $hh) {
    $by = $hh['last_name'] ? ($hh['last_name'].' '.mb_substr($hh['first_name'],0,1).'.') : '';
    $histOut[] = [
        'old' => $hh['old_status'] ? appt_status_label($hh['old_status']) : '—',
        'new' => appt_status_label($hh['new_status']),
        'at'  => fmt_dt($hh['changed_at']),
        'by'  => $by,
    ];
}
```
Добавить `'history' => $histOut` в JSON-ответ.

- [ ] **Step 4: `templates/cabinet_registrar.php`** — в модалку управления (после блока услуг/итого, перед кнопками оплаты) добавить контейнер: `<h6 class="mt-3">История статусов</h6><ul id="mgHistory" class="small mb-2"></ul>`.

- [ ] **Step 5: `public/assets/js/registrar.js`** — в обработчике открытия записи, после отрисовки услуг, отрисовать историю:
```js
var hist = document.getElementById('mgHistory');
if (hist) {
    hist.innerHTML = '';
    (resp.history || []).forEach(function (h) {
        var li = document.createElement('li');
        li.textContent = h.at + ': ' + h.old + ' → ' + h.new + (h.by ? ' (' + h.by + ')' : '');
        hist.appendChild(li);
    });
}
```

- [ ] **Step 6: Verify + commit**
```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
for f in book_appointment registrar_book registrar_set_status registrar_pay; do & $php -l "public/api/$f.php"; done
& $php -l public/protocol_edit.php; & $php -l public/api/registrar_appointment.php; & $php -l templates/cabinet_registrar.php
git add lib/appointment.php public/api/book_appointment.php public/api/registrar_book.php public/api/registrar_set_status.php public/api/registrar_pay.php public/protocol_edit.php public/api/registrar_appointment.php templates/cabinet_registrar.php public/assets/js/registrar.js
git commit -m "feat: appointment status history — log on changes, show in registrar appointment modal"
```

---

### Task P9-5: notification_log (лог писем + просмотр у админа)

**Files:** Modify `lib/mailer.php`, `templates/cabinet_admin.php`; Create `public/admin/notifications.php`

- [ ] **Step 1: `lib/mailer.php`** — логировать каждое письмо. В начале добавить `require_once __DIR__ . '/db.php';`. В `send()` после определения результата (и в SMTP, и в fallback) записать лог. Проще всего — в `send()` перед return:
  - Обернуть: получить `$ok` из соответствующего метода, затем
    ```php
    try {
        DB::exec("INSERT INTO notification_log (recipient, subject) VALUES (:r, :s)", ['r' => $to, 's' => $subject]);
    } catch (Throwable $e) { /* не мешать отправке */ }
    ```
  Реализация: переписать `send()` так, чтобы вычислить `$ok`, залогировать, вернуть `$ok`:
  ```php
  public static function send(string $to, string $subject, string $bodyText): bool
  {
      $ok = (SMTP_USER === '') ? self::logToFile($to, $subject, $bodyText) : self::sendSmtp($to, $subject, $bodyText);
      try {
          DB::exec("INSERT INTO notification_log (recipient, subject) VALUES (:r, :s)", ['r' => $to, 's' => $subject]);
      } catch (Throwable $e) { error_log('[notification_log] ' . $e->getMessage()); }
      return $ok;
  }
  ```

- [ ] **Step 2: Создать `public/admin/notifications.php`** (только админ):
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
$_pageTitle = 'Журнал писем';
$rows = DB::all("SELECT recipient, subject, created_at FROM notification_log ORDER BY created_at DESC LIMIT 200");
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-9"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Журнал отправленных писем</h1>
        <a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if (empty($rows)): ?>
        <p class="text-muted">Писем пока нет.</p>
    <?php else: ?>
        <table class="table table-sm">
            <thead><tr><th>Дата</th><th>Получатель</th><th>Тема</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="small"><?= h(fmt_dt($r['created_at'])) ?></td><td class="small"><?= h($r['recipient']) ?></td><td><?= h($r['subject']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
```

- [ ] **Step 3: Ссылка в `templates/cabinet_admin.php`** — добавить `<a href="/admin/notifications.php" class="btn btn-outline-orange btn-sm">Журнал писем</a>` рядом с другими админ-ссылками.

- [ ] **Step 4: Verify + commit**
```powershell
$php='C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe'
& $php -l lib/mailer.php; & $php -l public/admin/notifications.php; & $php -l templates/cabinet_admin.php
git add lib/mailer.php public/admin/notifications.php templates/cabinet_admin.php
git commit -m "feat: notification_log — log every email in Mailer, admin-only viewer"
```

---

### Task P9-6: review (отзывы пациента + средняя оценка + модерация админом)

**Files:** Create `public/api/submit_review.php`, `public/admin/reviews.php`; Modify `templates/cabinet_patient.php`, `public/assets/js/booking.js`, `public/doctors.php`, `templates/cabinet_admin.php`

- [ ] **Step 1: `public/api/submit_review.php`** (пациент, своя завершённая запись, один отзыв):
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');
if (!Auth::hasRole('patient')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ только для пациентов.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$pid    = (int) Auth::user()['id'];
$apptId = (int) ($_POST['appointment_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$body   = trim((string) ($_POST['body'] ?? ''));
if ($rating < 1 || $rating > 5) { echo json_encode(['ok'=>false,'error'=>'Оценка 1–5.']); exit; }

$a = DB::one("SELECT doctor_id FROM appointment WHERE id=:id AND patient_id=:p AND status='completed'", ['id'=>$apptId,'p'=>$pid]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена или не завершена.']); exit; }
if (DB::one("SELECT id FROM review WHERE appointment_id=:id", ['id'=>$apptId]) !== null) { echo json_encode(['ok'=>false,'error'=>'Отзыв уже оставлен.']); exit; }

DB::exec("INSERT INTO review (appointment_id, patient_id, doctor_id, rating, body) VALUES (:a,:p,:d,:r,:b)",
    ['a'=>$apptId,'p'=>$pid,'d'=>(int)$a['doctor_id'],'r'=>$rating,'b'=>($body !== '' ? $body : null)]);
echo json_encode(['ok'=>true]);
```

- [ ] **Step 2: `templates/cabinet_patient.php`** — в модалке завершённой записи (`#completedModal`), под кнопками протокола/чека, добавить блок отзыва (форма видна, если отзыва ещё нет — управляется JS):
```php
<hr>
<div id="reviewArea">
    <div id="reviewExisting" class="d-none small text-muted"></div>
    <form id="reviewForm" class="d-none">
        <label class="form-label mb-1">Ваша оценка приёма</label>
        <select id="reviewRating" class="form-select form-select-sm mb-2">
            <option value="5">5 — отлично</option><option value="4">4 — хорошо</option>
            <option value="3">3 — нормально</option><option value="2">2 — плохо</option><option value="1">1 — ужасно</option>
        </select>
        <textarea id="reviewBody" class="form-control form-control-sm mb-2" rows="2" placeholder="Комментарий (необязательно)"></textarea>
        <button type="button" id="reviewSubmit" class="btn btn-orange btn-sm">Оставить отзыв</button>
        <div id="reviewMsg" class="small text-danger mt-1"></div>
    </form>
</div>
```
  Также в PHP-части шаблона подготовить данные об отзывах завершённых записей (есть/нет, текст): добавить запрос отзывов пациента и встроить как JSON, аналогично услугам:
```php
$reviewsByAppt = [];
if (!empty($completedIds)) {
    $in = implode(',', array_fill(0, count($completedIds), '?'));
    foreach (DB::all("SELECT appointment_id, rating, body FROM review WHERE appointment_id IN ($in)", $completedIds) as $rv) {
        $reviewsByAppt[(int)$rv['appointment_id']] = ['rating'=>(int)$rv['rating'],'body'=>$rv['body']];
    }
}
```
и вывести `<script type="application/json" id="reviews-data"><?= json_encode($reviewsByAppt, ...) ?></script>`.

- [ ] **Step 3: `public/assets/js/booking.js`** — при открытии модалки завершённой записи: если по записи есть отзыв — показать `#reviewExisting` («Ваша оценка: N. текст»), скрыть форму; иначе показать форму. На «Оставить отзыв» — POST `/api/submit_review.php` (appointment_id, rating, body), при ok — reload. Использовать `reviews-data`.

- [ ] **Step 4: `public/doctors.php`** — добавить среднюю оценку: в запрос врачей добавить подзапросы `(SELECT ROUND(AVG(rating),1) FROM review r WHERE r.doctor_id=u.id) AS avg_rating`, `(SELECT COUNT(*) FROM review r WHERE r.doctor_id=u.id) AS reviews_count`. В карточке врача под специализацией показать «★ N (M отзывов)» если reviews_count>0.

- [ ] **Step 5: `public/admin/reviews.php`** (админ — список + удаление):
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    if (($_POST['action'] ?? '') === 'del') { DB::exec("DELETE FROM review WHERE id=:id", ['id'=>(int)($_POST['id'] ?? 0)]); }
    header('Location: /admin/reviews.php'); exit;
}
$_pageTitle = 'Отзывы';
$rows = DB::all(
    "SELECT r.id, r.rating, r.body, r.created_at,
            p.last_name p_last, p.first_name p_first,
            d.last_name d_last, d.first_name d_first
       FROM review r JOIN user p ON p.id=r.patient_id JOIN user d ON d.id=r.doctor_id
      ORDER BY r.created_at DESC"
);
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-9"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Отзывы</h1><a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if (empty($rows)): ?><p class="text-muted">Отзывов пока нет.</p><?php else: ?>
        <table class="table align-middle">
            <thead><tr><th>Дата</th><th>Врач</th><th>Пациент</th><th>Оценка</th><th>Текст</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><?= h(fmt_dt($r['created_at'])) ?></td>
                    <td class="small"><?= h($r['d_last'].' '.mb_substr($r['d_first'],0,1).'.') ?></td>
                    <td class="small"><?= h($r['p_last'].' '.mb_substr($r['p_first'],0,1).'.') ?></td>
                    <td><?= (int)$r['rating'] ?>★</td>
                    <td class="small"><?= h((string)$r['body']) ?></td>
                    <td><form method="post" onsubmit="return confirm('Удалить отзыв?');"><?= Csrf::field() ?><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-danger btn-sm">Удалить</button></form></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    <?php endif; ?>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
```

- [ ] **Step 6: Ссылка в `templates/cabinet_admin.php`** — `<a href="/admin/reviews.php" class="btn btn-outline-orange btn-sm">Отзывы</a>`.

- [ ] **Step 7: Verify + commit** (php -l всех + commit).

---

### Task P9-7: doctor_day_off (врач ведёт выходные + запись их учитывает)

**Files:** Modify `lib/appointment.php`, `public/api/booking_slots.php`, `public/api/book_appointment.php`, `public/api/registrar_book.php`, `templates/cabinet_doctor.php`, `public/assets/js/doctor.js`; Create `public/api/doctor_dayoff_add.php`, `public/api/doctor_dayoff_del.php`

- [ ] **Step 1: Хелпер в `lib/appointment.php`**:
```php
/** Выходной ли у врача в указанную дату ('Y-m-d'). */
function doctor_is_off(int $doctorId, string $date): bool
{
    return DB::one("SELECT 1 FROM doctor_day_off WHERE doctor_id=:d AND off_date=:dt", ['d'=>$doctorId,'dt'=>$date]) !== null;
}
```

- [ ] **Step 2: Учитывать в записи:**
  - `booking_slots.php` (выдача дней/слотов пациенту): исключать дни, где `doctor_is_off($doctorId, $date)`.
  - `book_appointment.php`: после проверки слота — `if (doctor_is_off($doctorId, substr($slotStart,0,10))) { echo json_encode(['ok'=>false,'error'=>'Врач не принимает в этот день.']); exit; }`
  - `registrar_book.php`: то же самое.

- [ ] **Step 3: Эндпоинты управления (врач):**
  `public/api/doctor_dayoff_add.php`:
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
Auth::requireRole('doctor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();
$did = (int) Auth::user()['id'];
$date = trim((string)($_POST['off_date'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    try { DB::exec("INSERT INTO doctor_day_off (doctor_id, off_date, reason) VALUES (:d,:dt,:r)", ['d'=>$did,'dt'=>$date,'r'=>($reason!==''?$reason:null)]); }
    catch (Throwable $e) { /* дубликат — игнор */ }
}
header('Location: /profile.php'); exit;
```
  `public/api/doctor_dayoff_del.php`:
```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
Auth::requireRole('doctor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();
$did = (int) Auth::user()['id'];
DB::exec("DELETE FROM doctor_day_off WHERE id=:id AND doctor_id=:d", ['id'=>(int)($_POST['id'] ?? 0),'d'=>$did]);
header('Location: /profile.php'); exit;
```

- [ ] **Step 4: UI в `templates/cabinet_doctor.php`** — добавить блок «Мои выходные» (под расписанием, до модалок): список будущих выходных врача + форма добавления (дата + причина) + кнопки удаления. Данные подготовить в начале шаблона:
```php
$daysOff = DB::all("SELECT id, off_date, reason FROM doctor_day_off WHERE doctor_id=:d AND off_date >= CURDATE() ORDER BY off_date", ['d'=>$docId]);
```
и блок:
```php
<div class="clinic-card p-3 my-4">
    <h5 class="mb-3">Мои выходные</h5>
    <form method="post" action="/api/doctor_dayoff_add.php" class="row g-2 align-items-end mb-3">
        <?= Csrf::field() ?>
        <div class="col-auto"><label class="form-label mb-0 small">Дата</label><input type="date" name="off_date" class="form-control" required></div>
        <div class="col-auto"><label class="form-label mb-0 small">Причина</label><input type="text" name="reason" class="form-control" placeholder="Отпуск"></div>
        <div class="col-auto"><button class="btn btn-orange">Добавить</button></div>
    </form>
    <?php if (empty($daysOff)): ?><p class="text-muted small mb-0">Выходных не запланировано.</p><?php else: ?>
        <ul class="list-group">
            <?php foreach ($daysOff as $do): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?= h(ru_date_label($do['off_date'])) ?><?= $do['reason'] ? ' — ' . h($do['reason']) : '' ?></span>
                    <form method="post" action="/api/doctor_dayoff_del.php" class="m-0"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$do['id'] ?>"><button class="btn btn-outline-danger btn-sm">Удалить</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
```

- [ ] **Step 5: Verify + commit** (php -l всех + commit).

---

### Task P9-8: Сквозная проверка Фазы 9 + тег

- [ ] **Step 1: Все smoke-тесты** (6) — OK.
- [ ] **Step 2: Таблиц 16** — `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='dentistry';` → 16.
- [ ] **Step 3: Функционально (логины ролей):**
  - Врач: профиль — выбор специализации из списка; добавить/удалить выходной; на день выходного запись недоступна.
  - Страница «Врачи»: показывается специализация + средняя оценка.
  - Регистратор: окно записи показывает «История статусов».
  - Пациент: у завершённой записи можно оставить отзыв (один раз); протокол/чек открываются (регрессия).
  - Чек (PDF) показывает способ оплаты из справочника.
  - Админ: страницы «Специализации», «Журнал писем», «Отзывы» открываются; не-админ → 403/redirect.
  - Отправка кода/напоминания пишет строку в `notification_log`.
- [ ] **Step 4: Регрессия** публичных и ролевых страниц (200).
- [ ] **Step 5: Откатить тестовые правки данных; git status чисто; тег `phase-9-complete`.**

---

## Чек-лист соответствия

| Требование | Где |
|---|---|
| 16 таблиц | P9-1 |
| payment_method (вместо ENUM) | P9-1, P9-2 |
| specialization: админ ведёт, врач выбирает | P9-3 |
| appointment_status_history виден регистратору | P9-4 |
| notification_log только у админа | P9-5 |
| review: пациент, средняя на «Врачи», админ удаляет | P9-6 |
| doctor_day_off: врач ведёт, запись учитывает | P9-7 |

---

**Итог Фазы 9:** БД из 16 таблиц с осмысленной интеграцией каждой новой сущности в интерфейс соответствующих ролей.
