<?php
declare(strict_types=1);

/**
 * Оценка надёжности пароля по 5-балльной шкале:
 *   0 — очень слабый / пустой / короче 6 символов
 *   1 — слабый    (1 класс символов)
 *   2 — средний   (2 класса)
 *   3 — хороший   (3 класса)
 *   4 — отличный  (4 класса)
 *
 * Алгоритм: пароль короче 6 символов — всегда 0. Иначе score равен числу
 * использованных классов символов (строчные, прописные, цифры, спецсимволы),
 * максимум 4. Этот же расчёт продублирован на клиенте (assets/js/main.js).
 */
function password_strength(string $pwd): int
{
    if (mb_strlen($pwd) < 6) return 0;

    $score = 0;
    if (preg_match('/[a-z]/', $pwd)) $score++;
    if (preg_match('/[A-Z]/', $pwd)) $score++;
    if (preg_match('/\d/', $pwd))    $score++;
    if (preg_match('/[^A-Za-z0-9]/', $pwd)) $score++;

    return min($score, 4);
}

/** Текстовая метка для индикатора. */
function password_strength_label(int $score): string
{
    return [
        0 => 'Очень слабый',
        1 => 'Слабый',
        2 => 'Средний',
        3 => 'Хороший',
        4 => 'Отличный',
    ][$score] ?? 'Неизвестно';
}

/** Bootstrap-цвет для прогресс-бара. */
function password_strength_color(int $score): string
{
    return [
        0 => 'bg-danger',
        1 => 'bg-danger',
        2 => 'bg-warning',
        3 => 'bg-info',
        4 => 'bg-success',
    ][$score] ?? 'bg-secondary';
}

/** Хеш bcrypt (cost=12). */
function password_make_hash(string $pwd): string
{
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Минимальная допустимая надёжность для регистрации/смены пароля. */
const PASSWORD_MIN_SCORE = 2;
