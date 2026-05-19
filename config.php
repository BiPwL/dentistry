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
