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

-- ───── Оплаты (для завершённых) ─────  method_id: 1=наличными, 2=картой
INSERT INTO payment (appointment_id, method_id, total_amount) VALUES
    (5, 2, 4200.00),
    (6, 1, 10000.00);

-- ───── Профили врачей ─────  specialization_id: 1=терапевт, 2=хирург/ортопед
INSERT INTO doctor_profile (user_id, specialization_id, bio) VALUES
    (3, 1,
        'Стаж 12 лет. Специализируется на лечении кариеса, пульпита и эстетической реставрации. Бережный подход и безболезненное лечение.'),
    (4, 2,
        'Стаж 9 лет. Удаление зубов любой сложности, протезирование, имплантология. Кандидат медицинских наук.');

-- ───── Отзывы (демо, к завершённым записям) ─────
INSERT INTO review (appointment_id, patient_id, doctor_id, rating, body) VALUES
    (5, 5, 3, 5, 'Отличный врач, всё безболезненно.'),
    (6, 6, 4, 4, 'Хорошо, но пришлось немного подождать.');
