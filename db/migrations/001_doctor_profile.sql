-- Миграция для уже существующей БД: добавляет профиль врача (аддитивно, без потери данных).
CREATE TABLE IF NOT EXISTS doctor_profile (
    user_id        INT UNSIGNED PRIMARY KEY,
    specialization VARCHAR(150) NOT NULL DEFAULT '',
    bio            TEXT NULL,
    photo_path     VARCHAR(255) NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_docprofile_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO doctor_profile (user_id, specialization, bio) VALUES
    (3, 'Врач-стоматолог-терапевт',
        'Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.'),
    (4, 'Стоматолог-хирург, ортопед',
        'Стаж 9 лет. Удаление зубов любой сложности, протезирование, имплантология. Кандидат медицинских наук.')
ON DUPLICATE KEY UPDATE
    specialization = VALUES(specialization),
    bio            = VALUES(bio);
