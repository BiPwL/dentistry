(function () {
    'use strict';
    var root = document.getElementById('registrar-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;
    var doctorId = root.dataset.doctor;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() }).then(function (r) { return r.json(); });
    }

    var bookModal = new bootstrap.Modal(document.getElementById('bookModal'));
    var manageModal = new bootstrap.Modal(document.getElementById('manageModal'));
    var cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));

    // ----- Запись на свободный слот -----
    var bookSlotEl = document.getElementById('bookSlot');
    var bookErr = document.getElementById('bookError');
    var currentSlot = null;
    document.querySelectorAll('.free-cell').forEach(function (c) {
        c.addEventListener('click', function () {
            currentSlot = c.dataset.slot;
            bookSlotEl.textContent = currentSlot;
            bookErr.classList.add('d-none');
            bookModal.show();
        });
    });
    document.getElementById('bookConfirm').addEventListener('click', function () {
        var pid = document.getElementById('bookPatient').value;
        post('/api/registrar_book.php', { doctor_id: doctorId, patient_id: pid, slot_start: currentSlot }).then(function (d) {
            if (d.ok) { location.reload(); }
            else { bookErr.textContent = d.error || 'Ошибка.'; bookErr.classList.remove('d-none'); }
        });
    });

    // ----- Управление записью -----
    var currentAppt = null;
    var mgErr = document.getElementById('mgError');
    var mgPayBtn = document.getElementById('mgPayBtn');
    var mgPayMethod = document.getElementById('mgPayMethod');
    var mgStatus = document.getElementById('mgStatus');
    var mgPaidInfo = document.getElementById('mgPaidInfo');
    var canPay = false;

    function refreshPayState() {
        mgPayBtn.disabled = !(canPay && mgPayMethod.value !== '');
    }
    mgPayMethod.addEventListener('change', refreshPayState);

    document.querySelectorAll('.appt-cell').forEach(function (c) {
        c.addEventListener('click', function () {
            currentAppt = c.dataset.apptId;
            mgErr.classList.add('d-none');
            mgPaidInfo.classList.add('d-none');
            fetch('/api/registrar_appointment.php?id=' + encodeURIComponent(currentAppt)).then(function (r) { return r.json(); }).then(function (resp) {
                if (!resp.ok) { mgErr.textContent = resp.error || 'Ошибка.'; mgErr.classList.remove('d-none'); manageModal.show(); return; }
                var a = resp.appointment;
                document.getElementById('mgPatient').textContent = a.patient_fio;
                document.getElementById('mgWhen').textContent = a.when + ' · ' + a.status_label;
                mgStatus.value = a.status;
                // После оплаты запись финализирована: статус, сохранение и отмена заблокированы
                mgStatus.disabled = a.paid;
                document.getElementById('mgStatusSave').disabled = a.paid;
                document.getElementById('mgCancelBtn').disabled = a.paid;
                var ul = document.getElementById('mgServices');
                ul.innerHTML = '';
                resp.services.forEach(function (s) { var li = document.createElement('li'); li.textContent = s.name + ' — ' + s.price; ul.appendChild(li); });
                document.getElementById('mgTotal').textContent = a.total;
                canPay = a.can_pay;
                if (a.paid) {
                    mgPayMethod.value = a.pay_method; mgPayMethod.disabled = true;
                    mgPaidInfo.textContent = 'Оплачено (' + (a.pay_method === 'cash' ? 'наличными' : 'картой') + ').';
                    mgPaidInfo.classList.remove('d-none');
                } else {
                    mgPayMethod.disabled = false; mgPayMethod.value = '';
                }
                refreshPayState();
                manageModal.show();
            });
        });
    });

    document.getElementById('mgStatusSave').addEventListener('click', function () {
        post('/api/registrar_set_status.php', { appointment_id: currentAppt, status: mgStatus.value }).then(function (d) {
            if (d.ok) { location.reload(); } else { mgErr.textContent = d.error || 'Ошибка.'; mgErr.classList.remove('d-none'); }
        });
    });

    mgPayBtn.addEventListener('click', function () {
        post('/api/registrar_pay.php', { appointment_id: currentAppt, method: mgPayMethod.value }).then(function (d) {
            if (d.ok) { window.open(d.receipt_url, '_blank'); location.reload(); }
            else { mgErr.textContent = d.error || 'Ошибка.'; mgErr.classList.remove('d-none'); }
        });
    });

    document.getElementById('mgCancelBtn').addEventListener('click', function () { manageModal.hide(); cancelModal.show(); });
    document.getElementById('cancelConfirmYes').addEventListener('click', function () {
        post('/api/registrar_cancel.php', { appointment_id: currentAppt }).then(function (d) {
            if (d.ok) { location.reload(); } else { cancelModal.hide(); mgErr.textContent = d.error || 'Ошибка.'; }
        });
    });
})();
