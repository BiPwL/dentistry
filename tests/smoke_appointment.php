<?php
require_once __DIR__ . '/../lib/appointment.php';

assert(slot_hours() === [10, 11, 12, 13, 15, 16, 17], 'slot hours with lunch break');
assert(slot_label(10) === '10:00–11:00', 'slot label');
assert(slot_end_for('2026-05-21 10:00:00') === '2026-05-21 11:00:00', 'slot end +1h');

assert(appt_status_label('created') === 'Создана');
assert(appt_status_label('noshow') === 'Не явка');
assert(appt_status_class('completed') === 'status-completed');

foreach (booking_dates(14) as $d) {
    $dow = (int) date('N', strtotime($d));
    assert($dow <= 5, "booking date $d must be a weekday");
}

$nextMon = date('Y-m-d', strtotime('next monday'));
assert(is_valid_slot($nextMon . ' 10:00:00') === true, 'valid future Monday 10:00');
assert(is_valid_slot($nextMon . ' 14:00:00') === false, 'lunch break invalid');
assert(is_valid_slot($nextMon . ' 09:00:00') === false, 'before work hours invalid');
assert(is_valid_slot($nextMon . ' 10:30:00') === false, 'half-hour invalid');
assert(is_valid_slot(date('Y-m-d', strtotime('next sunday')) . ' 10:00:00') === false, 'sunday invalid');
assert(is_valid_slot('2020-01-01 10:00:00') === false, 'past invalid');

echo "Appointment smoke: OK\n";
