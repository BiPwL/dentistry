<?php
/** @var array $user текущий врач (из profile.php) */
$docId = (int) $user['id'];
$w = (int) ($_GET['w'] ?? 0);

$mondayTs = strtotime('monday this week') + $w * 7 * 86400;
$days = [];
for ($i = 0; $i < 5; $i++) {
    $days[] = date('Y-m-d', $mondayTs + $i * 86400);
}
$rangeStart = $days[0] . ' 00:00:00';
$rangeEnd   = date('Y-m-d', $mondayTs + 5 * 86400) . ' 00:00:00';

$rows = DB::all(
    "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
      WHERE a.doctor_id = :d AND a.slot_start >= :s AND a.slot_start < :e",
    ['d' => $docId, 's' => $rangeStart, 'e' => $rangeEnd]
);
$byKey = [];
foreach ($rows as $r) {
    $byKey[date('Y-m-d H:00:00', strtotime($r['slot_start']))] = $r;
}
?>

<div id="doctor-cabinet" data-csrf="<?= h(Csrf::token()) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Моё расписание</h1>
        <div class="d-flex gap-2 align-items-center">
            <a href="/doctor_profile_edit.php" class="btn btn-outline-orange btn-sm">Профиль врача</a>
            <form method="post" action="/logout.php" class="m-0">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-link text-muted">Выйти</button>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?w=<?= $w - 1 ?>">← Пред. неделя</a>
        <span class="fw-semibold"><?= h(ru_date_label($days[0])) ?> — <?= h(ru_date_label($days[4])) ?></span>
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?w=<?= $w + 1 ?>">След. неделя →</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center schedule-table mb-0">
            <thead>
                <tr>
                    <th style="width:80px;"></th>
                    <?php foreach ($days as $d): ?>
                        <th><?= h(ru_date_label($d)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (slot_hours() as $h): ?>
                    <tr>
                        <th class="text-muted small"><?= h(slot_label($h)) ?></th>
                        <?php foreach ($days as $d): ?>
                            <?php $key = sprintf('%s %02d:00:00', $d, $h); ?>
                            <?php if (isset($byKey[$key])): ?>
                                <?php $a = $byKey[$key]; ?>
                                <td class="appt-cell <?= h(appt_status_class($a['status'])) ?>" role="button"
                                    data-appt-id="<?= (int) $a['id'] ?>" title="<?= h(appt_status_label($a['status'])) ?>">
                                    <div class="small fw-semibold"><?= h(fio_short($a['last_name'], $a['first_name'], $a['middle_name'])) ?></div>
                                    <div class="x-small"><?= h(appt_status_label($a['status'])) ?></div>
                                </td>
                            <?php else: ?>
                                <td class="text-muted small">—</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="apptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="apptPatient"></h5>
          <div class="small text-muted"><span id="apptWhen"></span> · <span id="apptStatus" class="fw-semibold"></span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div id="apptError" class="alert alert-danger d-none"></div>
        <button type="button" class="btn btn-orange btn-sm mb-3 d-none" id="markPerformed">Завершить приём (Исполнена)</button>
        <h6>Оказанные услуги</h6>
        <div id="apptServices" class="mb-3"></div>
        <div id="protocolArea"></div>
      </div>
    </div>
  </div>
</div>
