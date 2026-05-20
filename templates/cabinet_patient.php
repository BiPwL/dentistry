<?php
/** @var array $user  текущий пациент (из profile.php) */
$pid = (int) $user['id'];

$appointments = DB::all(
    "SELECT a.id, a.slot_start, a.status,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user d ON d.id = a.doctor_id
      WHERE a.patient_id = :p
      ORDER BY a.slot_start DESC",
    ['p' => $pid]
);

$completedIds = array_values(array_map(
    fn($a) => (int) $a['id'],
    array_filter($appointments, fn($a) => $a['status'] === 'completed')
));
$servicesByAppt = [];
if (!empty($completedIds)) {
    $in  = implode(',', array_fill(0, count($completedIds), '?'));
    $rows = DB::all(
        "SELECT aps.appointment_id, s.name, aps.price_at_time
           FROM appointment_service aps
           JOIN service s ON s.id = aps.service_id
          WHERE aps.appointment_id IN ($in)",
        $completedIds
    );
    foreach ($rows as $r) {
        $servicesByAppt[(int) $r['appointment_id']][] = $r;
    }
}

$hasCreated = (bool) array_filter($appointments, fn($a) => $a['status'] === 'created');
$banUntil = patient_booking_ban_until($pid);
$banned = $banUntil !== null;

$doctors = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 3 ORDER BY last_name");
?>

<div id="patient-cabinet" data-csrf="<?= h(Csrf::token()) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Мои записи</h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-orange" id="openBooking" <?= $banned ? 'disabled' : '' ?>>Записаться</button>
            <a href="/med_card.php" target="_blank" class="btn btn-outline-orange">Мед. карта</a>
            <form method="post" action="/logout.php" class="m-0">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-link text-muted">Выйти</button>
            </form>
        </div>
    </div>

    <?php if ($hasCreated): ?>
        <div class="alert alert-danger py-2 small">
            Внимание: при неявке по записи онлайн-запись будет ограничена на <?= (int) NOSHOW_BAN_DAYS ?> дней.
        </div>
    <?php endif; ?>
    <?php if ($banned): ?>
        <div class="alert alert-danger py-2 small">
            Самостоятельная онлайн-запись заблокирована до <?= h($banUntil) ?> из-за неявки (на неделю с даты неявки). Для записи обратитесь к регистратору.
        </div>
    <?php endif; ?>

    <?php if (empty($appointments)): ?>
        <p class="text-muted">У вас пока нет записей. Нажмите «Записаться», чтобы выбрать врача и время.</p>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($appointments as $a): ?>
                <?php
                    $fio = fio_short($a['d_last'], $a['d_first'], $a['d_mid']);
                    $isCompleted = $a['status'] === 'completed';
                    $isCreated   = $a['status'] === 'created';
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center<?= $isCompleted ? ' appt-completed' : '' ?>"
                     <?= $isCompleted ? 'role="button" data-appt-id="' . (int) $a['id'] . '"' : '' ?>>
                    <div>
                        <div class="fw-semibold"><?= h(fmt_dt($a['slot_start'])) ?></div>
                        <div class="text-muted small"><?= h($fio) ?></div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="status-badge <?= h(appt_status_class($a['status'])) ?>"><?= h(appt_status_label($a['status'])) ?></span>
                        <?php if ($isCreated): ?>
                            <button type="button" class="btn-close appt-cancel" aria-label="Отменить"
                                    data-id="<?= (int) $a['id'] ?>" title="Отменить запись"></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($servicesByAppt as $aid => $list): ?>
        <script type="application/json" class="appt-services" data-appt-id="<?= (int) $aid ?>">
            <?= json_encode(array_map(fn($r) => ['name' => $r['name'], 'price' => fmt_price($r['price_at_time'])], $list), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        </script>
    <?php endforeach; ?>

    <script type="application/json" id="doctors-data">
        <?= json_encode(array_map(fn($d) => ['id' => (int) $d['id'], 'fio' => fio_short($d['last_name'], $d['first_name'], $d['middle_name'])], $doctors), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
    </script>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Запись на приём</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Врач</label>
          <select class="form-select" id="bookingDoctor"></select>
        </div>
        <div id="bookingDays" class="mb-3"></div>
        <div id="bookingSlots"></div>
        <div id="bookingMsg" class="text-muted small"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmBookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Подтверждение</h5></div>
      <div class="modal-body"><p id="confirmBookingText" class="mb-0"></p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-orange" id="confirmBookingYes">Да, записаться</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Отмена записи</h5></div>
      <div class="modal-body"><p class="mb-0">Отменить эту запись?</p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-orange" id="cancelYes">Да, отменить</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="completedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Оказанные услуги</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <ul id="completedServices" class="list-unstyled mb-3"></ul>
        <div class="d-flex gap-2">
          <a id="completedProtocol" class="btn btn-outline-orange btn-sm" target="_blank" rel="noopener" href="#">Протокол приёма</a>
          <a id="completedReceipt" class="btn btn-outline-orange btn-sm" target="_blank" rel="noopener" href="#">Чек</a>
        </div>
      </div>
    </div>
  </div>
</div>
