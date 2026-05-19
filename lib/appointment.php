<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** Все статусы записи в порядке жизненного цикла. */
const APPT_STATUSES = ['created', 'confirmed', 'performed', 'completed', 'noshow'];

/** Человекочитаемая метка статуса. */
function appt_status_label(string $status): string
{
    return [
        'created'   => 'Создана',
        'confirmed' => 'Подтверждена',
        'performed' => 'Исполнена',
        'completed' => 'Завершена',
        'noshow'    => 'Не явка',
    ][$status] ?? $status;
}

/** CSS-класс бейджа статуса (см. theme.css). */
function appt_status_class(string $status): string
{
    return 'status-' . $status;
}

/** Часы начала 1-часовых слотов в рамках рабочего дня минус перерыв. Напр. [10,11,12,13,15,16,17]. */
function slot_hours(): array
{
    $start  = (int) substr(WORK_START, 0, 2);
    $end    = (int) substr(WORK_END, 0, 2);
    $bStart = (int) substr(BREAK_START, 0, 2);
    $bEnd   = (int) substr(BREAK_END, 0, 2);
    $hours = [];
    for ($h = $start; $h < $end; $h++) {
        if ($h >= $bStart && $h < $bEnd) continue;
        $hours[] = $h;
    }
    return $hours;
}

/** Метка слота вида "10:00–11:00". */
function slot_label(int $hour): string
{
    return sprintf('%02d:00–%02d:00', $hour, $hour + 1);
}

/** Конец слота (DATETIME 'Y-m-d H:i:s') = начало + 1 час. */
function slot_end_for(string $slotStart): string
{
    return date('Y-m-d H:i:s', strtotime($slotStart) + 3600);
}

/** Даты, доступные для записи: сегодня..+$daysAhead, только Пн–Пт. Возвращает 'Y-m-d'. */
function booking_dates(int $daysAhead = 14): array
{
    $dates = [];
    $base  = strtotime('today');
    for ($i = 0; $i <= $daysAhead; $i++) {
        $ts  = strtotime("+$i day", $base);
        $dow = (int) date('N', $ts);
        if ($dow >= 6) continue; // 6=Сб, 7=Вс
        $dates[] = date('Y-m-d', $ts);
    }
    return $dates;
}

/** Русская метка даты "Пн, 21 мая". */
function ru_date_label(string $date): string
{
    $months = [1=>'января',2=>'февраля',3=>'марта',4=>'апреля',5=>'мая',6=>'июня',
               7=>'июля',8=>'августа',9=>'сентября',10=>'октября',11=>'ноября',12=>'декабря'];
    $days   = [1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];
    $ts = strtotime($date);
    return $days[(int) date('N', $ts)] . ', ' . (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)];
}

/** Валиден ли слот для записи: будущее, Пн–Пт, ровно на часе, рабочий час, в пределах окна. */
function is_valid_slot(string $slotStart, int $daysAhead = 14): bool
{
    $ts = strtotime($slotStart);
    if ($ts === false) return false;
    if ($ts <= time()) return false;
    if ((int) date('N', $ts) >= 6) return false;
    if ((int) date('i', $ts) !== 0) return false;
    if (!in_array((int) date('G', $ts), slot_hours(), true)) return false;
    $maxTs = strtotime('today +' . ($daysAhead + 1) . ' day');
    if ($ts >= $maxTs) return false;
    return true;
}
