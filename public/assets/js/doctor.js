(function () {
    'use strict';

    var root = document.getElementById('doctor-cabinet');
    if (!root) return;
    var csrf = root.dataset.csrf;
    var modalEl = document.getElementById('apptModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);
    var currentId = null;

    function post(url, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf);
        Object.keys(data).forEach(function (k) {
            if (Array.isArray(data[k])) {
                data[k].forEach(function (v) { body.append(k + '[]', v); });
            } else {
                body.set(k, data[k]);
            }
        });
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    var elPatient = document.getElementById('apptPatient');
    var elWhen = document.getElementById('apptWhen');
    var elStatus = document.getElementById('apptStatus');
    var elErr = document.getElementById('apptError');
    var elServices = document.getElementById('apptServices');
    var elProtoArea = document.getElementById('protocolArea');
    var elMedCard = document.getElementById('apptMedCard');

    var canEditServices = false;
    var addProtoBtn = null;

    function collectServiceIds() {
        return Array.prototype.slice.call(elServices.querySelectorAll('.svc-cb:checked')).map(function (cb) { return cb.value; });
    }

    // Кнопка «Добавить протокол» активна только если запись «Подтверждена» и выбрана хотя бы одна услуга
    function updateProtoBtn() {
        if (!addProtoBtn) return;
        addProtoBtn.disabled = !(canEditServices && collectServiceIds().length > 0);
    }

    function openAppt(id) {
        currentId = id;
        elErr.classList.add('d-none');
        elServices.innerHTML = 'Загрузка…';
        elProtoArea.innerHTML = '';
        addProtoBtn = null;
        fetch('/api/doctor_appointment.php?id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    elErr.textContent = d.error || 'Ошибка.';
                    elErr.classList.remove('d-none');
                    elServices.innerHTML = '';
                    modal.show();
                    return;
                }
                var a = d.appointment;
                elPatient.textContent = a.patient_fio;
                elWhen.textContent = a.when;
                elStatus.textContent = a.status_label;
                canEditServices = a.can_edit_services;
                if (elMedCard) elMedCard.href = '/med_card.php?patient_id=' + a.patient_id;

                // Услуги: отмечать можно только когда статус «Подтверждена»
                elServices.innerHTML = '';
                d.services.forEach(function (s) {
                    var sid = 'svc_' + s.id;
                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    var cb = document.createElement('input');
                    cb.className = 'form-check-input svc-cb';
                    cb.type = 'checkbox'; cb.id = sid; cb.value = s.id; cb.checked = s.checked;
                    cb.disabled = !canEditServices;
                    cb.addEventListener('change', updateProtoBtn);
                    var lb = document.createElement('label');
                    lb.className = 'form-check-label svc-toggle'; lb.htmlFor = sid;
                    lb.textContent = s.name + ' (' + s.price + ')';
                    wrap.appendChild(cb); wrap.appendChild(lb);
                    elServices.appendChild(wrap);
                });
                if (!canEditServices) {
                    var hint = document.createElement('div');
                    hint.className = 'text-muted small mt-1';
                    hint.textContent = 'Услуги можно отмечать только когда запись «Подтверждена».';
                    elServices.appendChild(hint);
                }

                if (a.has_protocol) {
                    var view = document.createElement('a');
                    view.className = 'btn btn-orange';
                    view.href = '/protocol_view.php?appointment_id=' + a.id;
                    view.target = '_blank';
                    view.textContent = 'Открыть протокол приёма';
                    elProtoArea.appendChild(view);
                } else {
                    addProtoBtn = document.createElement('button');
                    addProtoBtn.type = 'button';
                    addProtoBtn.className = 'btn btn-orange';
                    addProtoBtn.textContent = 'Добавить протокол приёма';
                    addProtoBtn.addEventListener('click', function () { saveServicesThen('/protocol_edit.php?appointment_id=' + a.id); });
                    elProtoArea.appendChild(addProtoBtn);
                    updateProtoBtn();
                }
                modal.show();
            })
            .catch(function () {
                elErr.textContent = 'Ошибка загрузки.';
                elErr.classList.remove('d-none');
                elServices.innerHTML = '';
                modal.show();
            });
    }

    function saveServices() {
        return post('/api/doctor_save_services.php', { appointment_id: currentId, service_ids: collectServiceIds() });
    }

    function saveServicesThen(url) {
        saveServices().then(function (d) {
            if (d.ok) { window.location = url; }
            else { elErr.textContent = d.error || 'Не удалось сохранить услуги.'; elErr.classList.remove('d-none'); }
        });
    }

    document.querySelectorAll('.appt-cell').forEach(function (cell) {
        cell.addEventListener('click', function () { openAppt(cell.dataset.apptId); });
    });

    var params = new URLSearchParams(location.search);
    if (params.has('appt')) {
        openAppt(params.get('appt'));
    }
})();
