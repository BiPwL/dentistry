<?php
/** @var array $user регистратор */
$doctors = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 3 ORDER BY last_name");
$patients = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 4 ORDER BY last_name");

$selDoctor = (int) ($_GET['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
$w = (int) ($_GET['w'] ?? 0);
$mondayTs = strtotime('monday this week') + $w * 7 * 86400;
$days = [];
for ($i = 0; $i < 5; $i++) $days[] = date('Y-m-d', $mondayTs + $i * 86400);
$rangeStart = $days[0] . ' 00:00:00';
$rangeEnd   = date('Y-m-d', $mondayTs + 5 * 86400) . ' 00:00:00';

$byKey = [];
if ($selDoctor > 0) {
    $rows = DB::all(
        "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
           FROM appointment a JOIN user p ON p.id = a.patient_id
          WHERE a.doctor_id = :d AND a.slot_start >= :s AND a.slot_start < :e",
        ['d' => $selDoctor, 's' => $rangeStart, 'e' => $rangeEnd]
    );
    foreach ($rows as $r) $byKey[date('Y-m-d H:00:00', strtotime($r['slot_start']))] = $r;
}
?>

<div id="registrar-cabinet" data-csrf="<?= h(Csrf::token()) ?>" data-doctor="<?= (int) $selDoctor ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h3 mb-0">Расписание</h1>
        <form method="post" action="/logout.php" class="m-0">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-link text-muted">Выйти</button>
        </form>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label class="form-label mb-0 small">Врач</label>
            <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $d['id'] == $selDoctor ? 'selected' : '' ?>>
                        <?= h($d['last_name'] . ' ' . $d['first_name'] . ' ' . $d['middle_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="w" value="<?= (int) $w ?>">
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?doctor_id=<?= (int) $selDoctor ?>&w=<?= $w - 1 ?>">← Пред. неделя</a>
        <span class="fw-semibold"><?= h(ru_date_label($days[0])) ?> — <?= h(ru_date_label($days[4])) ?></span>
        <a class="btn btn-outline-orange btn-sm" href="/profile.php?doctor_id=<?= (int) $selDoctor ?>&w=<?= $w + 1 ?>">След. неделя →</a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center schedule-table mb-0">
            <thead>
                <tr><th style="width:80px;"></th><?php foreach ($days as $d): ?><th><?= h(ru_date_label($d)) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach (slot_hours() as $hh): ?>
                    <tr>
                        <th class="text-muted small"><?= h(slot_label($hh)) ?></th>
                        <?php foreach ($days as $d): ?>
                            <?php $key = sprintf('%s %02d:00:00', $d, $hh); ?>
                            <?php if (isset($byKey[$key])): ?>
                                <?php $a = $byKey[$key]; ?>
                                <td class="appt-cell <?= h(appt_status_class($a['status'])) ?>" role="button" data-appt-id="<?= (int) $a['id'] ?>" title="<?= h(appt_status_label($a['status'])) ?>">
                                    <div class="small fw-semibold"><?= h(fio_short($a['last_name'], $a['first_name'], $a['middle_name'])) ?></div>
                                    <div class="x-small"><?= h(appt_status_label($a['status'])) ?></div>
                                </td>
                            <?php elseif (is_valid_slot($key)): ?>
                                <td class="free-cell" role="button" data-slot="<?= h($key) ?>">+ записать</td>
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

<div class="modal fade" id="bookModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Запись пациента</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="bookError" class="alert alert-danger d-none"></div>
        <p class="small text-muted mb-2">Слот: <span id="bookSlot"></span></p>
        <label class="form-label">Пациент</label>
        <select id="bookPatient" class="form-select mb-3">
            <?php foreach ($patients as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= h($p['last_name'] . ' ' . $p['first_name'] . ' ' . $p['middle_name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-orange" id="bookConfirm">Записать</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title mb-0" id="mgPatient"></h5><div class="small text-muted" id="mgWhen"></div></div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="mgError" class="alert alert-danger d-none"></div>
        <label class="form-label">Статус</label>
        <div class="input-group mb-3">
            <select id="mgStatus" class="form-select">
                <option value="created">Создана</option>
                <option value="confirmed">Подтверждена</option>
                <option value="performed">Исполнена</option>
                <option value="completed">Завершена</option>
                <option value="noshow">Не явка</option>
            </select>
            <button type="button" class="btn btn-outline-orange" id="mgStatusSave">Сохранить</button>
        </div>
        <h6>Оказанные услуги</h6>
        <ul id="mgServices" class="small mb-2"></ul>
        <p class="fw-semibold mb-3">Итого: <span id="mgTotal"></span></p>
        <label class="form-label">Способ оплаты</label>
        <select id="mgPayMethod" class="form-select mb-3">
            <option value="">— выберите —</option>
            <option value="cash">Наличными</option>
            <option value="card">Картой</option>
        </select>
        <div id="mgPaidInfo" class="alert alert-success d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto" id="mgCancelBtn">Отменить запись</button>
        <button type="button" class="btn btn-orange" id="mgPayBtn" disabled>Оплатить</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Отмена записи</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">Удалить эту запись? Действие необратимо.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-orange" data-bs-dismiss="modal">Нет</button>
        <button type="button" class="btn btn-danger" id="cancelConfirmYes">Да, удалить</button>
      </div>
    </div>
  </div>
</div>
