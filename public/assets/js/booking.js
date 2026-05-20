(function () {
    'use strict';

    var root = document.getElementById('patient-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    // ─── Отмена записи ───
    var cancelId = null;
    var cancelModalEl = document.getElementById('cancelModal');
    var cancelModal = cancelModalEl ? new bootstrap.Modal(cancelModalEl) : null;
    document.querySelectorAll('.appt-cancel').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            cancelId = btn.dataset.id;
            if (cancelModal) cancelModal.show();
        });
    });
    var cancelYes = document.getElementById('cancelYes');
    if (cancelYes) {
        cancelYes.addEventListener('click', function () {
            if (!cancelId) return;
            post('/api/cancel_appointment.php', { appointment_id: cancelId }).then(function (d) {
                if (d.ok) { location.reload(); }
                else { alert(d.error || 'Не удалось отменить.'); }
            });
        });
    }

    // ─── Модалка завершённой записи ───
    var completedModalEl = document.getElementById('completedModal');
    var completedModal = completedModalEl ? new bootstrap.Modal(completedModalEl) : null;
    document.querySelectorAll('.appt-completed').forEach(function (row) {
        row.addEventListener('click', function () {
            var aid = row.dataset.apptId;
            var proto = document.getElementById('completedProtocol');
            var receipt = document.getElementById('completedReceipt');
            if (proto) proto.href = '/protocol_view.php?appointment_id=' + aid;
            if (receipt) receipt.href = '/receipt.php?appointment_id=' + aid;
            var holder = document.querySelector('.appt-services[data-appt-id="' + aid + '"]');
            var list = document.getElementById('completedServices');
            list.innerHTML = '';
            if (holder) {
                JSON.parse(holder.textContent).forEach(function (s) {
                    var li = document.createElement('li');
                    li.className = 'd-flex justify-content-between border-bottom py-1';
                    var n = document.createElement('span'); n.textContent = s.name;
                    var p = document.createElement('span'); p.className = 'text-muted'; p.textContent = s.price;
                    li.appendChild(n); li.appendChild(p);
                    list.appendChild(li);
                });
            }
            if (completedModal) completedModal.show();
        });
    });

    // ─── Мастер записи ───
    var bookingModalEl = document.getElementById('bookingModal');
    if (!bookingModalEl) return;
    var bookingModal = new bootstrap.Modal(bookingModalEl);
    var confirmModalEl = document.getElementById('confirmBookingModal');
    var confirmModal = new bootstrap.Modal(confirmModalEl);
    var doctorSel = document.getElementById('bookingDoctor');
    var daysBox = document.getElementById('bookingDays');
    var slotsBox = document.getElementById('bookingSlots');
    var msgBox = document.getElementById('bookingMsg');
    var pending = null; // {doctorId, slotStart, label}

    var doctors = JSON.parse(document.getElementById('doctors-data').textContent);
    doctorSel.innerHTML = '<option value="">— выберите врача —</option>';
    doctors.forEach(function (d) {
        var o = document.createElement('option');
        o.value = d.id; o.textContent = d.fio;
        doctorSel.appendChild(o);
    });

    var openBtn = document.getElementById('openBooking');
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            doctorSel.value = '';
            daysBox.innerHTML = '';
            slotsBox.innerHTML = '';
            msgBox.textContent = '';
            bookingModal.show();
        });
    }

    doctorSel.addEventListener('change', function () {
        daysBox.innerHTML = '';
        slotsBox.innerHTML = '';
        msgBox.textContent = '';
        var docId = doctorSel.value;
        if (!docId) return;
        msgBox.textContent = 'Загрузка расписания…';
        fetch('/api/booking_slots.php?doctor_id=' + encodeURIComponent(docId))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                msgBox.textContent = '';
                if (!d.ok) { msgBox.textContent = d.error || 'Ошибка.'; return; }
                if (!d.days.length) { msgBox.textContent = 'Нет свободных слотов в ближайшие 2 недели.'; return; }
                d.days.forEach(function (day) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'btn btn-outline-orange btn-sm booking-day-btn';
                    b.textContent = day.label;
                    b.addEventListener('click', function () {
                        daysBox.querySelectorAll('.btn').forEach(function (x) { x.classList.remove('active'); });
                        b.classList.add('active');
                        renderSlots(day.slots);
                    });
                    daysBox.appendChild(b);
                });
            })
            .catch(function () { msgBox.textContent = 'Ошибка загрузки.'; });
    });

    function renderSlots(slots) {
        slotsBox.innerHTML = '';
        slots.forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-outline-orange btn-sm booking-slot-btn';
            b.textContent = s.label;
            b.addEventListener('click', function () {
                pending = { doctorId: doctorSel.value, slotStart: s.start, label: s.label };
                var docName = doctorSel.options[doctorSel.selectedIndex].textContent;
                document.getElementById('confirmBookingText').textContent =
                    'Записаться к ' + docName + ' на ' + s.label + '?';
                confirmModal.show();
            });
            slotsBox.appendChild(b);
        });
    }

    document.getElementById('confirmBookingYes').addEventListener('click', function () {
        if (!pending) return;
        post('/api/book_appointment.php', { doctor_id: pending.doctorId, slot_start: pending.slotStart })
            .then(function (d) {
                if (d.ok) { location.reload(); }
                else {
                    confirmModal.hide();
                    msgBox.textContent = d.error || 'Не удалось записаться.';
                }
            });
    });
})();
