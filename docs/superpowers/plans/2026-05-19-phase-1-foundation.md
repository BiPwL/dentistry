# Стоматологическая клиника — Фаза 1: Фундамент и публичный сайт

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Поднять фундамент проекта (конфиг, полная схема БД со seed-данными, набор хелперов, общий layout, пастельная оранжевая тема) и публичные страницы (главная, каталог услуг, контакты, блог) — посетитель-гость может бродить по сайту. Авторизация в Фазе 2; кнопки «Регистрация»/«Вход» в шапке ведут на временные заглушки.

**Architecture:** Плоский PHP без фреймворка под OpenServer. Каждая публичная страница — отдельный `.php` в корне; общая шапка/подвал подключаются через `templates/header.php` и `templates/footer.php`. Доступ к БД — через PDO-синглтон (`lib/db.php`). Bootstrap 5.3 через CDN + локальная `theme.css` с пастельной оранжевой палитрой. Схема покрывает **всю** систему (включая будущие фазы), чтобы избежать миграций. Seed-данные содержат всех актёров и записи во всех статусах.

**Tech Stack:** PHP 8.3 (Open Server, синтаксически совместим с 8.2), MySQL 8.2, Bootstrap 5.3 (CDN), нативный JS, PDO.

---

## Общий roadmap (все 7 фаз)

| # | Фаза | Что выходит |
|---|---|---|
| **1** | **Фундамент + публичный сайт** | Этот план. Схема БД, seed, хелперы, тема, главная/услуги/контакты/блог |
| 2 | Аутентификация | Регистрация (email-код), вход, восстановление пароля, PHPMailer/SMTP, logout |
| 3 | Кабинет пациента | Профиль, история записей, запись через мастер, предупреждение о санкциях |
| 4 | Кабинет врача | Расписание-календарь, модалка записи, услуги-чекбоксы, форма протокола |
| 5 | Кабинет регистратора | Расписание с выбором врача, запись пациента, статусы, оплата + чек |
| 6 | Кабинет администратора | Управление ролями, CRUD блога и каталога услуг, статистика |
| 7 | PDF + email-напоминания | Протокол/чек/мед.карта в PDF, фоновое email-напоминание за день до приёма |

Каждая последующая фаза будет оформлена отдельным планом после завершения предыдущей.

---

## Подход к тестированию (адаптация TDD)

PHP-приложение под OpenServer без composer/PHPUnit — формальная PHPUnit-инфраструктура избыточна. Применяю прагматичный TDD:

- **Чистая логика** (например, генерация слотов расписания, валидация надёжности пароля) — будут unit-тесты в Фазах 3–4 через простые PHP-CLI-скрипты в `tests/` (`assert()` + ручной запуск).
- **Хелперы Фазы 1** (CSRF, sanitize, DB-обёртка) проверяются smoke-проверками: после написания запускаем мини-скрипт, который вызывает функцию и печатает результат.
- **HTML/шаблоны** — ручная проверка в браузере (открыть страницу, убедиться, что данные из БД отображаются).
- **Схема и seed** — проверка через `SELECT COUNT(*)` и `SHOW TABLES` после импорта.

После каждой задачи — явный шаг verification + commit. Шаги «verify» нельзя пропускать.

---

## Структура файлов после Фазы 1

```
dentistry.local/
├── config.php                  # DB, SMTP-плейсхолдеры, расписание, TTL
├── index.php                   # Главная
├── services.php                # Каталог услуг
├── contacts.php                # Контакты и график
├── blog.php                    # Список статей
├── login.php                   # Заглушка («скоро»)
├── register.php                # Заглушка («скоро»)
├── lib/
│   ├── db.php                  # PDO-синглтон + helpers
│   ├── session.php             # Старт сессии, current_user()
│   ├── csrf.php                # csrf_token(), csrf_check()
│   ├── sanitize.php            # h() = htmlspecialchars
│   └── auth.php                # is_authenticated(), require_role()
├── templates/
│   ├── header.php              # <html>…<header>
│   └── footer.php              # <footer>…</html>
├── assets/
│   ├── css/theme.css           # Пастельные оранжевые оверрайды
│   └── js/main.js              # Пустой
├── uploads/
│   ├── services/               # Картинки услуг (с примерами)
│   └── blog/                   # Картинки статей (с примерами)
├── db/
│   ├── schema.sql              # Полная схема (все фазы)
│   └── seed.sql                # Полный seed
└── docs/superpowers/plans/     # Этот план
```

---

## Параметры окружения (OpenServer, defaults)

- PHP CLI: `C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe`
- MySQL: `127.0.0.1:3306`, root без пароля (типовой OpenServer)
- phpMyAdmin: обычно `http://127.0.0.1/openserver/phpmyadmin/`
- Локальный домен: `http://dentistry.local`
- Корневая папка проекта: `C:\all-stuff\soft\OpenServer\home\dentistry.local`

---

## Задачи

---

### Task 1: Структура каталогов и `config.php`

**Files:**
- Create: `config.php`
- Create directories: `lib/`, `templates/`, `assets/css/`, `assets/js/`, `uploads/services/`, `uploads/blog/`, `db/`, `tests/`

- [ ] **Step 1: Создать директории**

Run:
```powershell
New-Item -ItemType Directory -Force -Path lib, templates, assets/css, assets/js, uploads/services, uploads/blog, db, tests | Out-Null
```

Expected: команды отрабатывают без ошибок, директории появляются.

- [ ] **Step 2: Создать `config.php`**

```php
<?php
declare(strict_types=1);

// ───── База данных ─────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'dentistry');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ───── SMTP (плейсхолдеры — заполнить в Фазе 2) ─────
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@dentistry.local');
define('SMTP_FROM_NAME', 'Стоматологическая клиника');

// ───── Сайт ─────
define('SITE_URL', 'http://dentistry.local');
define('SITE_NAME', 'Стоматологическая клиника «Улыбка»');
define('CLINIC_ADDRESS', 'г. Москва, ул. Примерная, д. 1');
define('CLINIC_PHONE', '+7 (495) 123-45-67');
define('CLINIC_EMAIL', 'info@dentistry.local');

// ───── Расписание врачей ─────
define('WORK_START', '10:00');     // начало рабочего дня
define('WORK_END',   '18:00');     // конец рабочего дня
define('BREAK_START','14:00');     // начало перерыва
define('BREAK_END',  '15:00');     // конец перерыва
define('SLOT_MINUTES', 60);        // длительность одного слота

// ───── Регистрация / восстановление пароля ─────
define('EMAIL_CODE_TTL_MIN', 15);  // TTL кода подтверждения в минутах
define('EMAIL_CODE_LENGTH', 6);    // длина кода

// ───── Санкции за неявку ─────
define('NOSHOW_BAN_DAYS', 7);      // запрет онлайн-записи после неявки

// ───── Пути ─────
define('ROOT_DIR', __DIR__);
define('LIB_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'lib');
define('TEMPLATES_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'templates');
```

- [ ] **Step 3: Проверить синтаксис**

Run:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l config.php
```
Expected: `No syntax errors detected in config.php`.

- [ ] **Step 4: Commit**

```powershell
git add config.php; git commit -m "feat: project config and directory skeleton"
```

---

### Task 2: Полная схема БД (`db/schema.sql`)

**Files:**
- Create: `db/schema.sql`

Схема покрывает все 7 фаз — в дальнейшем миграций не будет, только данные.

- [ ] **Step 1: Написать `db/schema.sql`**

```sql
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS appointment_service;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS protocol;
DROP TABLE IF EXISTS appointment;
DROP TABLE IF EXISTS service;
DROP TABLE IF EXISTS blog_article;
DROP TABLE IF EXISTS email_code;
DROP TABLE IF EXISTS user;
DROP TABLE IF EXISTS role;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE role (
    id   TINYINT UNSIGNED PRIMARY KEY,
    code VARCHAR(20)  NOT NULL UNIQUE,
    name VARCHAR(40)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    last_name         VARCHAR(60)  NOT NULL,
    first_name        VARCHAR(60)  NOT NULL,
    middle_name       VARCHAR(60)  NOT NULL DEFAULT '',
    email             VARCHAR(190) NOT NULL UNIQUE,
    password_hash     VARCHAR(255) NOT NULL,
    role_id           TINYINT UNSIGNED NOT NULL,
    booking_ban_until DATE NULL COMMENT 'Запрет онлайн-записи до даты (после неявки)',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES role(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_code (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(190) NOT NULL,
    code         CHAR(6)      NOT NULL,
    purpose      ENUM('register','reset') NOT NULL,
    payload_json JSON NULL COMMENT 'Данные регистрации, ожидающие подтверждения',
    expires_at   DATETIME NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ix_email_purpose (email, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    description TEXT         NOT NULL,
    image_path  VARCHAR(255) NULL,
    price       DECIMAL(10,2) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_article (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    image_path VARCHAR(255) NULL,
    author_id  INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES user(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT UNSIGNED NOT NULL,
    doctor_id        INT UNSIGNED NOT NULL,
    slot_start       DATETIME NOT NULL,
    slot_end         DATETIME NOT NULL,
    status           ENUM('created','confirmed','performed','completed','noshow') NOT NULL DEFAULT 'created',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reminder_sent_at DATETIME NULL COMMENT 'Когда отправлено email-напоминание',
    CONSTRAINT fk_appt_patient FOREIGN KEY (patient_id) REFERENCES user(id),
    CONSTRAINT fk_appt_doctor  FOREIGN KEY (doctor_id)  REFERENCES user(id),
    UNIQUE KEY uq_doctor_slot (doctor_id, slot_start),
    INDEX ix_patient_slot (patient_id, slot_start),
    INDEX ix_doctor_slot  (doctor_id, slot_start),
    INDEX ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointment_service (
    appointment_id INT UNSIGNED NOT NULL,
    service_id     INT UNSIGNED NOT NULL,
    price_at_time  DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (appointment_id, service_id),
    CONSTRAINT fk_as_appt    FOREIGN KEY (appointment_id) REFERENCES appointment(id) ON DELETE CASCADE,
    CONSTRAINT fk_as_service FOREIGN KEY (service_id)     REFERENCES service(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE protocol (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id  INT UNSIGNED NOT NULL UNIQUE,
    protocol_text   TEXT NOT NULL,
    recommendations TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_proto_appt FOREIGN KEY (appointment_id) REFERENCES appointment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL UNIQUE,
    method         ENUM('cash','card') NOT NULL,
    total_amount   DECIMAL(10,2) NOT NULL,
    paid_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_appt FOREIGN KEY (appointment_id) REFERENCES appointment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role (id, code, name) VALUES
    (1, 'admin',     'Администратор'),
    (2, 'registrar', 'Регистратор'),
    (3, 'doctor',    'Врач'),
    (4, 'patient',   'Пациент');
```

- [ ] **Step 2: Создать БД и импортировать схему**

В phpMyAdmin (`http://127.0.0.1/openserver/phpmyadmin/`):
1. Создать БД `dentistry` с charset `utf8mb4_unicode_ci`.
2. Выбрать БД → вкладка «Импорт» → загрузить `db/schema.sql` → «Вперёд».

Альтернатива через CLI:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot -e "CREATE DATABASE IF NOT EXISTS dentistry DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Get-Content db\schema.sql | & 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry
```

- [ ] **Step 3: Проверить таблицы**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SHOW TABLES;"
```
Expected: 9 таблиц — `appointment`, `appointment_service`, `blog_article`, `email_code`, `payment`, `protocol`, `role`, `service`, `user`.

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT * FROM role;"
```
Expected: 4 строки (admin/registrar/doctor/patient).

- [ ] **Step 4: Commit**

```powershell
git add db/schema.sql; git commit -m "feat: full database schema for all phases"
```

---

### Task 3: Seed-данные (`db/seed.sql`)

**Files:**
- Create: `db/seed.sql`

Пароль всех тестовых пользователей: **`Password1!`**. Хеш bcrypt (cost = 12) ниже сгенерирован и проверен — `password_verify('Password1!', …)` возвращает `true`.

Seed покрывает все статусы записей: «Создана», «Подтверждена», «Исполнена», «Завершена», «Не явка» — чтобы будущие фазы могли отрисовать каждый сценарий.

- [ ] **Step 1: Написать `db/seed.sql`**

```sql
-- Pwd для всех тестовых: "Password1!" (bcrypt, cost=12)
SET @PWD := '$2y$12$ywBZvKk7iZ/ZexW9fAzwy.kJISv7OUAyL4F7xOIEDJ7b44p3HSQa2';

-- ───── Пользователи ─────
INSERT INTO user (id, last_name, first_name, middle_name, email, password_hash, role_id) VALUES
    (1, 'Соколов',   'Анатолий',  'Петрович',  'admin@dentistry.local',     @PWD, 1),
    (2, 'Иванова',   'Мария',     'Сергеевна', 'registrar@dentistry.local', @PWD, 2),
    (3, 'Петров',    'Дмитрий',   'Александрович', 'petrov@dentistry.local',  @PWD, 3),
    (4, 'Кузнецова', 'Елена',     'Викторовна',    'kuznetsova@dentistry.local', @PWD, 3),
    (5, 'Смирнов',   'Алексей',   'Иванович',  'smirnov@example.com',  @PWD, 4),
    (6, 'Орлова',    'Наталья',   'Дмитриевна','orlova@example.com',   @PWD, 4),
    (7, 'Васильев',  'Игорь',     'Михайлович','vasilev@example.com',  @PWD, 4),
    (8, 'Новикова',  'Анна',      'Олеговна',  'novikova@example.com', @PWD, 4);

-- ───── Услуги ─────
INSERT INTO service (id, name, description, image_path, price) VALUES
    (1, 'Профессиональная чистка зубов',
        'Снятие зубного камня и налёта ультразвуком, полировка, фторирование эмали.',
        'uploads/services/cleaning.jpg', 3500.00),
    (2, 'Лечение кариеса',
        'Удаление поражённых тканей, установка светоотверждаемой пломбы, шлифовка и полировка.',
        'uploads/services/caries.jpg', 4200.00),
    (3, 'Удаление зуба',
        'Безболезненное удаление под анестезией с последующими рекомендациями.',
        'uploads/services/extraction.jpg', 2800.00),
    (4, 'Художественная реставрация',
        'Восстановление формы и цвета зуба композитными материалами.',
        'uploads/services/restoration.jpg', 6500.00),
    (5, 'Отбеливание зубов',
        'Профессиональная процедура осветления эмали до 5 тонов.',
        'uploads/services/whitening.jpg', 12000.00),
    (6, 'Консультация стоматолога',
        'Осмотр, диагностика и составление плана лечения.',
        'uploads/services/consult.jpg', 800.00);

-- ───── Блог ─────
INSERT INTO blog_article (id, title, body, image_path, author_id) VALUES
    (1, 'Как правильно чистить зубы',
        'Зубная щётка должна располагаться под углом 45° к десне. Движения — выметающие, от десны к режущему краю. Чистите зубы минимум 2 минуты дважды в день, не забывайте про язык и межзубные промежутки. Меняйте щётку каждые 3 месяца.',
        'uploads/blog/brushing.jpg', 1),
    (2, 'Чем опасен зубной камень',
        'Минерализованный налёт под десной приводит к воспалению, кровоточивости и в итоге — к потере зуба. Профессиональная чистка раз в 6 месяцев — лучшая профилактика пародонтита.',
        'uploads/blog/calculus.jpg', 1),
    (3, 'Мифы об отбеливании',
        'Активированный уголь и сода не отбеливают эмаль, а царапают её. Профессиональное отбеливание под контролем врача безопасно и эффективно — главное соблюдать диету первые 48 часов.',
        'uploads/blog/whitening-myths.jpg', 1);

-- ───── Записи на приём ─────
-- Используем даты относительно «сегодня»: завтра, через неделю, в прошлом
-- (заполняем все 5 статусов)
INSERT INTO appointment (id, patient_id, doctor_id, slot_start, slot_end, status) VALUES
    -- "Создана" — будущая запись Смирнова к Петрову
    (1, 5, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 11 HOUR,
              DATE_ADD(CURDATE(), INTERVAL 2 DAY) + INTERVAL 12 HOUR, 'created'),
    -- "Создана" — будущая запись Орловой к Кузнецовой
    (2, 6, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 13 HOUR,
              DATE_ADD(CURDATE(), INTERVAL 3 DAY) + INTERVAL 14 HOUR, 'created'),
    -- "Подтверждена" — сегодня, пациент пришёл (Васильев у Петрова)
    (3, 7, 3, CURDATE() + INTERVAL 10 HOUR, CURDATE() + INTERVAL 11 HOUR, 'confirmed'),
    -- "Исполнена" — вчера, приём закончен но не оплачен (Новикова у Кузнецовой)
    (4, 8, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 12 HOUR,
              DATE_SUB(CURDATE(), INTERVAL 1 DAY) + INTERVAL 13 HOUR, 'performed'),
    -- "Завершена" — неделю назад, всё оплачено (Смирнов у Петрова)
    (5, 5, 3, DATE_SUB(CURDATE(), INTERVAL 7 DAY) + INTERVAL 15 HOUR,
              DATE_SUB(CURDATE(), INTERVAL 7 DAY) + INTERVAL 16 HOUR, 'completed'),
    -- "Завершена" — две недели назад (Орлова у Кузнецовой)
    (6, 6, 4, DATE_SUB(CURDATE(), INTERVAL 14 DAY) + INTERVAL 16 HOUR,
              DATE_SUB(CURDATE(), INTERVAL 14 DAY) + INTERVAL 17 HOUR, 'completed'),
    -- "Не явка" — три дня назад (Васильев у Петрова)
    (7, 7, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 17 HOUR,
              DATE_SUB(CURDATE(), INTERVAL 3 DAY) + INTERVAL 18 HOUR, 'noshow');

-- ───── Оказанные услуги по записям ─────
INSERT INTO appointment_service (appointment_id, service_id, price_at_time) VALUES
    (4, 1, 3500.00), (4, 2, 4200.00),    -- Исполнена: чистка + кариес
    (5, 2, 4200.00),                      -- Завершена: кариес
    (6, 1, 3500.00), (6, 4, 6500.00);    -- Завершена: чистка + реставрация

-- ───── Протоколы приёма (для исполненных/завершённых) ─────
INSERT INTO protocol (id, appointment_id, protocol_text, recommendations) VALUES
    (1, 4, 'Произведена профессиональная гигиена полости рта, удаление зубных отложений ультразвуком. Обнаружен кариес зуба 36 — установлена пломба из светоотверждаемого композита.',
           'Рекомендуется чистка дважды в день, использование зубной нити, контрольный осмотр через 6 месяцев.'),
    (2, 5, 'Лечение кариеса зуба 16. Удалены поражённые ткани, установлена пломба.',
           'Не нагружать зуб 24 часа, наблюдение через 6 месяцев.'),
    (3, 6, 'Профессиональная чистка ультразвуком. Художественная реставрация зуба 11 после скола.',
           'Избегать красящих продуктов 48 часов, осмотр через 6 месяцев.');

-- ───── Оплаты (для завершённых) ─────
INSERT INTO payment (appointment_id, method, total_amount) VALUES
    (5, 'card', 4200.00),
    (6, 'cash', 10000.00);
```

- [ ] **Step 2: Импортировать seed**

В phpMyAdmin: БД `dentistry` → «Импорт» → `db/seed.sql`. Или CLI:
```powershell
Get-Content db\seed.sql | & 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry
```

- [ ] **Step 3: Проверить количество данных**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\MySQL-8.2\bin\mysql.exe' -uroot dentistry -e "SELECT (SELECT COUNT(*) FROM user WHERE role_id=1) AS admins, (SELECT COUNT(*) FROM user WHERE role_id=2) AS registrars, (SELECT COUNT(*) FROM user WHERE role_id=3) AS doctors, (SELECT COUNT(*) FROM user WHERE role_id=4) AS patients, (SELECT COUNT(*) FROM service) AS services, (SELECT COUNT(*) FROM blog_article) AS articles, (SELECT COUNT(*) FROM appointment) AS appts;"
```
Expected: `admins=1, registrars=1, doctors=2, patients=4, services=6, articles=3, appts=7`.

- [ ] **Step 4: Smoke-проверка bcrypt**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -r "var_dump(password_verify('Password1!', '\$2y\$12\$ywBZvKk7iZ/ZexW9fAzwy.kJISv7OUAyL4F7xOIEDJ7b44p3HSQa2'));"
```
Expected: `bool(true)`.

- [ ] **Step 5: Commit**

```powershell
git add db/seed.sql; git commit -m "feat: seed data covering all roles and appointment statuses"
```

---

### Task 4: PDO-хелпер (`lib/db.php`)

**Files:**
- Create: `lib/db.php`
- Create: `tests/smoke_db.php`

- [ ] **Step 1: Написать `lib/db.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
```

- [ ] **Step 2: Smoke-тест соединения**

Создать `tests/smoke_db.php`:
```php
<?php
require_once __DIR__ . '/../lib/db.php';

$roles = DB::all('SELECT * FROM role ORDER BY id');
assert(count($roles) === 4, 'Expected 4 roles');

$admin = DB::one('SELECT * FROM user WHERE role_id = 1');
assert($admin !== null, 'Admin must exist');
assert($admin['email'] === 'admin@dentistry.local', 'Admin email mismatch');

echo "DB smoke: OK\n";
```

Run:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' tests\smoke_db.php
```
Expected: `DB smoke: OK`.

- [ ] **Step 3: Commit**

```powershell
git add lib/db.php tests/smoke_db.php; git commit -m "feat: PDO helper with prepared-statement wrappers + smoke test"
```

---

### Task 5: Хелпер сессии (`lib/session.php`)

**Files:**
- Create: `lib/session.php`

- [ ] **Step 1: Написать `lib/session.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function userId(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::userId();
        if ($id === null) return null;
        $user = DB::one(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
               FROM user u JOIN role r ON r.id = u.role_id
              WHERE u.id = :id',
            ['id' => $id]
        );
        return $user;
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
```

- [ ] **Step 2: Smoke-проверка**

Run:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l lib\session.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```powershell
git add lib/session.php; git commit -m "feat: session helper with login/logout"
```

---

### Task 6: CSRF-хелпер (`lib/csrf.php`)

**Files:**
- Create: `lib/csrf.php`
- Create: `tests/smoke_csrf.php`

- [ ] **Step 1: Написать `lib/csrf.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function check(?string $token): bool
    {
        Session::start();
        if (!is_string($token) || empty($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::KEY], $token);
    }

    /** HTML-сниппет <input> для форм. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Прерывает выполнение с 403, если токен невалиден. Использовать в обработчиках POST. */
    public static function requireValid(): void
    {
        if (!self::check($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('CSRF token mismatch');
        }
    }
}
```

- [ ] **Step 2: Smoke-тест**

`tests/smoke_csrf.php`:
```php
<?php
require_once __DIR__ . '/../lib/csrf.php';

$t1 = Csrf::token();
$t2 = Csrf::token();
assert($t1 === $t2, 'Token must be stable within session');
assert(strlen($t1) === 64, 'Token must be 64 hex chars');
assert(Csrf::check($t1) === true, 'Valid token must pass');
assert(Csrf::check('wrong') === false, 'Invalid token must fail');
assert(Csrf::check(null) === false, 'Null token must fail');

echo "CSRF smoke: OK\n";
```

Run:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' tests\smoke_csrf.php
```
Expected: `CSRF smoke: OK`.

- [ ] **Step 3: Commit**

```powershell
git add lib/csrf.php tests/smoke_csrf.php; git commit -m "feat: CSRF token helper with smoke test"
```

---

### Task 7: Sanitize-хелпер (`lib/sanitize.php`)

**Files:**
- Create: `lib/sanitize.php`

- [ ] **Step 1: Написать `lib/sanitize.php`**

```php
<?php
declare(strict_types=1);

/** Экранирование для вставки в HTML (защита от XSS). */
function h(mixed $value): string
{
    if ($value === null) return '';
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Сокращение ФИО до «Фамилия И.О.». */
function fio_short(string $last, string $first, string $middle = ''): string
{
    $fi = mb_substr($first, 0, 1);
    $mi = $middle !== '' ? mb_substr($middle, 0, 1) . '.' : '';
    return $last . ' ' . $fi . '.' . $mi;
}

/** Форматирование DATETIME из БД в «дд.мм.гггг чч:мм». */
function fmt_dt(string $sqlDt): string
{
    return date('d.m.Y H:i', strtotime($sqlDt));
}

function fmt_price(float|string $amount): string
{
    return number_format((float) $amount, 2, ',', ' ') . ' ₽';
}
```

- [ ] **Step 2: Проверка**

Run:
```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -r "require 'lib/sanitize.php'; echo h('<script>alert(1)</script>') . PHP_EOL; echo fio_short('Иванов','Иван','Иванович') . PHP_EOL;"
```
Expected:
```
&lt;script&gt;alert(1)&lt;/script&gt;
Иванов И.И.
```

- [ ] **Step 3: Commit**

```powershell
git add lib/sanitize.php; git commit -m "feat: sanitize helpers (h, fio_short, fmt_dt, fmt_price)"
```

---

### Task 8: Auth-хелпер (`lib/auth.php`)

**Files:**
- Create: `lib/auth.php`

- [ ] **Step 1: Написать `lib/auth.php`**

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

final class Auth
{
    public static function isAuthenticated(): bool
    {
        return Session::userId() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return Session::user();
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u === null ? null : (string) $u['role_code'];
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, $roles, true);
    }

    /** Перенаправляет на /login.php если не аутентифицирован, и на главную если роль не подходит. */
    public static function requireRole(string ...$roles): void
    {
        if (!self::isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            header('Location: /');
            exit;
        }
    }
}
```

- [ ] **Step 2: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l lib\auth.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```powershell
git add lib/auth.php; git commit -m "feat: auth helper with role gating"
```

---

### Task 9: Общий layout (`templates/header.php`, `templates/footer.php`)

**Files:**
- Create: `templates/header.php`
- Create: `templates/footer.php`

Шапка содержит логотип-ссылку «домой», навигацию (Главная / Услуги / Блог / Контакты) и правый блок: для гостя — «Вход» + «Регистрация», для авторизованного — кнопка с инициалами «Фамилия И.О.», ведущая на `/profile.php`.

- [ ] **Step 1: `templates/header.php`**

```php
<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';

$_pageTitle = $_pageTitle ?? SITE_NAME;
$_user = Auth::user();
$_currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
function _nav_active(string $href, string $current): string {
    return ($href === $current || ($href !== '/' && str_starts_with($current, $href)))
        ? ' active' : '';
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($_pageTitle) ?> — <?= h(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg sticky-top shadow-sm clinic-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <span class="brand-mark">🦷</span> <?= h(SITE_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?= _nav_active('/', $_currentPath) ?>" href="/">Главная</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/services.php', $_currentPath) ?>" href="/services.php">Услуги</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/blog.php', $_currentPath) ?>" href="/blog.php">Блог</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/contacts.php', $_currentPath) ?>" href="/contacts.php">Контакты</a></li>
            </ul>
            <div class="d-flex gap-2">
                <?php if ($_user === null): ?>
                    <a href="/login.php" class="btn btn-outline-orange">Вход</a>
                    <a href="/register.php" class="btn btn-orange">Регистрация</a>
                <?php else: ?>
                    <a href="/profile.php" class="btn btn-orange">
                        <?= h(fio_short($_user['last_name'], $_user['first_name'], $_user['middle_name'])) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="flex-grow-1 py-4">
<div class="container">
```

- [ ] **Step 2: `templates/footer.php`**

```php
</div>
</main>
<footer class="clinic-footer py-3 mt-4">
    <div class="container d-flex flex-column flex-md-row justify-content-between text-muted small">
        <div>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?></div>
        <div><?= h(CLINIC_PHONE) ?> · <?= h(CLINIC_EMAIL) ?></div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
```

- [ ] **Step 3: Проверка синтаксиса**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\header.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' -l templates\footer.php
```
Expected: оба «No syntax errors detected».

- [ ] **Step 4: Commit**

```powershell
git add templates; git commit -m "feat: shared header/footer layout with role-aware top-right area"
```

---

### Task 10: Пастельная оранжевая тема (`assets/css/theme.css`)

**Files:**
- Create: `assets/css/theme.css`
- Create: `assets/js/main.js` (пустой)

- [ ] **Step 1: `assets/css/theme.css`**

```css
:root {
    --clinic-bg:        #FFF8F1;
    --clinic-surface:   #FFFFFF;
    --clinic-primary:   #F4A261;
    --clinic-primary-2: #E76F51;
    --clinic-accent:    #FFE0C2;
    --clinic-soft:      #FFF1E0;
    --clinic-ink:       #4A3B2A;
    --clinic-muted:     #8A7964;
    --clinic-border:    #F0DCC4;
    --clinic-success:   #6FB098;
    --clinic-danger:    #D88080;
}

html, body { background: var(--clinic-bg); color: var(--clinic-ink); }
body { font-family: system-ui, "Segoe UI", Tahoma, Arial, sans-serif; }

a { color: var(--clinic-primary-2); }
a:hover { color: var(--clinic-primary); }

/* Navbar */
.clinic-navbar {
    background: var(--clinic-surface);
    border-bottom: 1px solid var(--clinic-border);
}
.clinic-navbar .navbar-brand { color: var(--clinic-primary-2); }
.clinic-navbar .nav-link { color: var(--clinic-ink); }
.clinic-navbar .nav-link.active,
.clinic-navbar .nav-link:hover { color: var(--clinic-primary-2); }
.brand-mark { font-size: 1.2em; }

/* Buttons */
.btn-orange {
    background: var(--clinic-primary);
    border-color: var(--clinic-primary);
    color: #fff;
}
.btn-orange:hover {
    background: var(--clinic-primary-2);
    border-color: var(--clinic-primary-2);
    color: #fff;
}
.btn-outline-orange {
    background: transparent;
    border: 1px solid var(--clinic-primary);
    color: var(--clinic-primary-2);
}
.btn-outline-orange:hover {
    background: var(--clinic-soft);
    color: var(--clinic-primary-2);
}

/* Cards (услуги, статьи) */
.clinic-card {
    background: var(--clinic-surface);
    border: 1px solid var(--clinic-border);
    border-radius: 14px;
    overflow: hidden;
    transition: transform .15s ease, box-shadow .15s ease;
    height: 100%;
}
.clinic-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(244, 162, 97, .12);
}
.clinic-card .card-img-top {
    aspect-ratio: 16/10;
    object-fit: cover;
    background: var(--clinic-accent);
}
.clinic-card .card-body { color: var(--clinic-ink); }
.clinic-card .price-badge {
    display: inline-block;
    background: var(--clinic-soft);
    color: var(--clinic-primary-2);
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    font-weight: 600;
}

/* Hero */
.clinic-hero {
    background: linear-gradient(135deg, var(--clinic-soft) 0%, var(--clinic-accent) 100%);
    border-radius: 18px;
    padding: 3rem 2rem;
    margin-bottom: 2rem;
}
.clinic-hero h1 { color: var(--clinic-primary-2); }

/* Footer */
.clinic-footer {
    background: var(--clinic-surface);
    border-top: 1px solid var(--clinic-border);
}

/* Badges для статусов записей (понадобится в Фазе 3) */
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 500;
}
.status-created   { background: #C8E6C9; color: #2E7D32; }
.status-confirmed { background: #B7CEC1; color: #335E4D; }
.status-performed { background: #B7B7AE; color: #3A3A33; }
.status-completed { background: #C7C7C7; color: #404040; }
.status-noshow    { background: #F5B7B7; color: #8A2D2D; }
```

- [ ] **Step 2: `assets/js/main.js` — пустой плейсхолдер**

```js
// Site-wide JS — пока пусто; будет дополняться в Фазах 2+.
```

- [ ] **Step 3: Commit**

```powershell
git add assets; git commit -m "feat: pastel-orange theme + js placeholder"
```

---

### Task 11: Главная страница (`index.php`)

**Files:**
- Create: `index.php`

- [ ] **Step 1: Написать `index.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Главная';
$services = DB::all('SELECT * FROM service ORDER BY id LIMIT 3');

require __DIR__ . '/templates/header.php';
?>

<section class="clinic-hero">
    <h1 class="display-5 fw-bold mb-3">Заботимся о вашей улыбке</h1>
    <p class="lead mb-4">Современная стоматология с индивидуальным подходом. Профилактика, лечение и эстетика — под одной крышей.</p>
    <a href="/services.php" class="btn btn-orange btn-lg">Посмотреть услуги</a>
    <a href="/contacts.php" class="btn btn-outline-orange btn-lg ms-2">Как нас найти</a>
</section>

<h2 class="mb-4">Популярные услуги</h2>
<div class="row g-4">
    <?php foreach ($services as $s): ?>
        <div class="col-md-4">
            <div class="clinic-card">
                <?php if (!empty($s['image_path'])): ?>
                    <img class="card-img-top" src="/<?= h($s['image_path']) ?>" alt="<?= h($s['name']) ?>" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="card-body">
                    <h5 class="card-title"><?= h($s['name']) ?></h5>
                    <p class="card-text small"><?= h(mb_strimwidth($s['description'], 0, 120, '…')) ?></p>
                    <span class="price-badge"><?= h(fmt_price($s['price'])) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="text-center mt-4">
    <a href="/services.php" class="btn btn-outline-orange">Весь каталог</a>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Открыть в браузере**

В OpenServer убедиться, что домен `dentistry.local` обслуживается из этой папки (стандартная настройка — он подхватывается автоматически). Открыть `http://dentistry.local/`.

Expected: видна шапка с пастельным оранжевым акцентом, hero-секция, 3 карточки услуг с ценой, кнопка «Весь каталог».

- [ ] **Step 3: Commit**

```powershell
git add index.php; git commit -m "feat: home page with hero and featured services"
```

---

### Task 12: Каталог услуг (`services.php`)

**Files:**
- Create: `services.php`

В Фазе 1 страница — только для чтения. В Фазе 6 (админ) на неё будет добавлен CRUD.

- [ ] **Step 1: Написать `services.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';

$_pageTitle = 'Каталог услуг';
$services = DB::all('SELECT * FROM service ORDER BY name');
$isAdmin = Auth::hasRole('admin');

require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Услуги клиники</h1>
    <?php if ($isAdmin): ?>
        <a href="/admin/service_edit.php" class="btn btn-orange">+ Добавить услугу</a>
    <?php endif; ?>
</div>

<?php if (empty($services)): ?>
    <p class="text-muted">Пока нет ни одной услуги.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($services as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="clinic-card h-100">
                    <?php if (!empty($s['image_path'])): ?>
                        <img class="card-img-top" src="/<?= h($s['image_path']) ?>" alt="<?= h($s['name']) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= h($s['name']) ?></h5>
                        <p class="card-text flex-grow-1"><?= h($s['description']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="price-badge"><?= h(fmt_price($s['price'])) ?></span>
                            <?php if ($isAdmin): ?>
                                <span class="text-muted small">
                                    <a href="/admin/service_edit.php?id=<?= (int)$s['id'] ?>">редактировать</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Проверка в браузере**

Open `http://dentistry.local/services.php`.
Expected: 6 карточек услуг, у каждой название, описание, цена. Картинки могут не отображаться (`onerror` их скрывает) — это норма, реальные изображения добавляются в `uploads/services/` отдельно.

- [ ] **Step 3: Commit**

```powershell
git add services.php; git commit -m "feat: services catalog (read-only)"
```

---

### Task 13: Контакты (`contacts.php`)

**Files:**
- Create: `contacts.php`

Статическая страница с расписанием, адресом, контактами.

- [ ] **Step 1: Написать `contacts.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Контакты';
require __DIR__ . '/templates/header.php';
?>

<h1 class="mb-4">Контакты и график</h1>

<div class="row g-4">
    <div class="col-md-6">
        <div class="clinic-card p-4">
            <h5>Адрес</h5>
            <p class="mb-3"><?= h(CLINIC_ADDRESS) ?></p>

            <h5>Телефон</h5>
            <p class="mb-3"><a href="tel:<?= h(preg_replace('/\D+/', '', CLINIC_PHONE)) ?>"><?= h(CLINIC_PHONE) ?></a></p>

            <h5>Email</h5>
            <p class="mb-0"><a href="mailto:<?= h(CLINIC_EMAIL) ?>"><?= h(CLINIC_EMAIL) ?></a></p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="clinic-card p-4">
            <h5>График работы клиники</h5>
            <table class="table table-borderless mb-0">
                <tbody>
                    <tr><td>Понедельник – пятница</td><td class="text-end">10:00 — 18:00</td></tr>
                    <tr><td>Суббота</td><td class="text-end">10:00 — 16:00</td></tr>
                    <tr><td>Воскресенье</td><td class="text-end text-muted">Выходной</td></tr>
                </tbody>
            </table>
            <p class="small text-muted mt-3 mb-0">Перерыв на обед: 14:00 — 15:00. Длительность одного приёма — 1 час.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Проверка в браузере**

Open `http://dentistry.local/contacts.php`.
Expected: две карточки рядом, видны адрес/телефон/email и таблица графика.

- [ ] **Step 3: Commit**

```powershell
git add contacts.php; git commit -m "feat: contacts page with hours"
```

---

### Task 14: Блог (`blog.php`)

**Files:**
- Create: `blog.php`

В Фазе 1 — read-only. CRUD для администратора добавится в Фазе 6.

- [ ] **Step 1: Написать `blog.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/sanitize.php';
require_once __DIR__ . '/lib/auth.php';

$_pageTitle = 'Блог';
$articles = DB::all('SELECT * FROM blog_article ORDER BY created_at DESC');
$isAdmin = Auth::hasRole('admin');

require __DIR__ . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Блог</h1>
    <?php if ($isAdmin): ?>
        <a href="/admin/article_edit.php" class="btn btn-orange">+ Новая статья</a>
    <?php endif; ?>
</div>

<?php if (empty($articles)): ?>
    <p class="text-muted">Пока нет ни одной статьи.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($articles as $a): ?>
            <div class="col-md-6">
                <div class="clinic-card">
                    <?php if (!empty($a['image_path'])): ?>
                        <img class="card-img-top" src="/<?= h($a['image_path']) ?>" alt="<?= h($a['title']) ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= h($a['title']) ?></h5>
                        <p class="card-text"><?= h($a['body']) ?></p>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><?= h(date('d.m.Y', strtotime($a['created_at']))) ?></span>
                            <?php if ($isAdmin): ?>
                                <span>
                                    <a href="/admin/article_edit.php?id=<?= (int)$a['id'] ?>">редактировать</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: Проверка в браузере**

Open `http://dentistry.local/blog.php`.
Expected: 3 статьи в виде карточек с заголовком, текстом, датой.

- [ ] **Step 3: Commit**

```powershell
git add blog.php; git commit -m "feat: blog list (read-only)"
```

---

### Task 15: Заглушки `login.php` и `register.php`

**Files:**
- Create: `login.php`
- Create: `register.php`

В Фазе 1 — заглушки с текстом «скоро». Реальная функциональность — в Фазе 2. Это нужно, чтобы навигация в шапке не вела на 404.

- [ ] **Step 1: `login.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Вход';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="clinic-card p-4 text-center">
            <h1 class="h3 mb-3">Вход</h1>
            <p class="text-muted">Форма входа появится в ближайшем обновлении.</p>
            <a href="/" class="btn btn-orange mt-2">На главную</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 2: `register.php`**

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/sanitize.php';

$_pageTitle = 'Регистрация';
require __DIR__ . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="clinic-card p-4 text-center">
            <h1 class="h3 mb-3">Регистрация</h1>
            <p class="text-muted">Форма регистрации появится в ближайшем обновлении.</p>
            <a href="/" class="btn btn-orange mt-2">На главную</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/templates/footer.php'; ?>
```

- [ ] **Step 3: Проверка в браузере**

Open `http://dentistry.local/login.php` и `http://dentistry.local/register.php`.
Expected: на каждой странице — карточка-заглушка с заголовком и текстом «появится в ближайшем обновлении».

- [ ] **Step 4: Commit**

```powershell
git add login.php register.php; git commit -m "feat: login/register placeholder pages"
```

---

### Task 16: Финальная сквозная проверка Фазы 1

- [ ] **Step 1: Просмотр всех страниц**

Открыть и проверить каждый URL:
1. `http://dentistry.local/` — главная, hero, 3 услуги.
2. `http://dentistry.local/services.php` — 6 услуг.
3. `http://dentistry.local/blog.php` — 3 статьи.
4. `http://dentistry.local/contacts.php` — адрес, телефон, график.
5. `http://dentistry.local/login.php` — заглушка.
6. `http://dentistry.local/register.php` — заглушка.

Expected на каждой странице:
- Шапка: логотип-ссылка → главная, навигация (активная подсвечена), справа кнопки «Вход» + «Регистрация».
- Подвал: копирайт + телефон/email.
- Адаптив: при сужении окна меню сворачивается в гамбургер (Bootstrap).

- [ ] **Step 2: Запустить все smoke-тесты**

```powershell
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' tests\smoke_db.php
& 'C:\all-stuff\soft\OpenServer\modules\PHP-8.3\PHP\php.exe' tests\smoke_csrf.php
```
Expected: оба выводят `OK`.

- [ ] **Step 3: Проверить отсутствие PHP-ошибок в логах OpenServer**

Просмотреть `C:\all-stuff\soft\OpenServer\logs\` на свежие записи об ошибках. При наличии — исправить, повторить.

- [ ] **Step 4: Финальный commit-тег**

```powershell
git tag phase-1-complete
git log --oneline -20
```

Expected: видны коммиты по каждому Task, последний — `phase-1-complete` тег.

---

## Чек-лист соответствия спецификации (Фаза 1)

| Требование из PROMPT.md | Где реализовано |
|---|---|
| Открытый функционал гостя (главная, услуги, контакты, блог) | Task 11–14 |
| Каталог услуг: название, описание, картинка, цена | Task 12, схема `service` |
| Контакты + график работы | Task 13 |
| Блог: заголовок, текст, картинка | Task 14, схема `blog_article` |
| Кнопки «Вход» и «Регистрация» справа сверху для гостя | Task 9 (header.php) |
| Bootstrap 5.3, адаптив | Task 9, 10 (CDN + theme.css) |
| Пастельный светлый оранжевый | Task 10 (`--clinic-*` переменные) |
| Структура БД (все будущие сущности) | Task 2 |
| Seed (1 админ, 1 регистратор, 2 врача, 4 пациента, 6 услуг, 3 статьи, 7 записей со всеми статусами) | Task 3 |
| Bcrypt cost=12 для паролей | Task 3 (хеш в seed); валидация в Фазе 2 |
| PDO + подготовленные выражения | Task 4 |
| CSRF-инфраструктура | Task 6 (используется в Фазах 2+) |
| `htmlspecialchars()` для всех выводов | Task 7 (`h()`); все шаблоны используют |
| Проверка ролей на сервере | Task 8 (`Auth::requireRole`); используется в Фазах 3+ |

Не относится к Фазе 1 (будет в следующих):
- Регистрация / вход / восстановление пароля / SMTP / PHPMailer → Фаза 2
- Профили ролей (пациент / врач / регистратор / админ) → Фазы 3–6
- PDF и email-напоминания → Фаза 7

---

**Итог Фазы 1:** работающий публичный сайт стоматологии с базой, темой, шапкой/подвалом, всеми хелперами безопасности, готовыми к использованию в последующих фазах. После приёмки — переход к плану Фазы 2 (аутентификация).
