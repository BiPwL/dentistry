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
