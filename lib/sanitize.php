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
