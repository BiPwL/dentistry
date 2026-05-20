-- ===== Phase 9: +6 таблиц (10 -> 16) =====

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
ALTER TABLE payment MODIFY method_id TINYINT UNSIGNED NOT NULL;
SET @has_fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name='payment' AND constraint_name='fk_pay_method');
SET @sql := IF(@has_fk=0, 'ALTER TABLE payment ADD CONSTRAINT fk_pay_method FOREIGN KEY (method_id) REFERENCES payment_method(id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
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
