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
    var btnPerformed = document.getElementById('markPerformed');

    function openAppt(id) {
        currentId = id;
        elErr.classList.add('d-none');
        elServices.innerHTML = 'Загрузка…';
        elProtoArea.innerHTML = '';
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
                btnPerformed.classList.toggle('d-none', !a.can_perform);

                elServices.innerHTML = '';
                d.services.forEach(function (s) {
                    var id = 'svc_' + s.id;
                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    var cb = document.createElement('input');
                    cb.className = 'form-check-input svc-cb';
                    cb.type = 'checkbox'; cb.id = id; cb.value = s.id; cb.checked = s.checked;
                    var lb = document.createElement('label');
                    lb.className = 'form-check-label svc-toggle'; lb.htmlFor = id;
                    lb.textContent = s.name + ' (' + s.price + ')';
                    wrap.appendChild(cb); wrap.appendChild(lb);
                    elServices.appendChild(wrap);
                });

                if (a.has_protocol) {
                    var view = document.createElement('a');
                    view.className = 'btn btn-orange';
                    view.href = '/protocol_view.php?appointment_id=' + a.id;
                    view.target = '_blank';
                    view.textContent = 'Открыть протокол приёма';
                    elProtoArea.appendChild(view);
                } else {
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'btn btn-orange';
                    add.textContent = 'Добавить протокол приёма';
                    add.addEventListener('click', function () { saveServicesThen('/protocol_edit.php?appointment_id=' + a.id); });
                    elProtoArea.appendChild(add);
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

    function collectServiceIds() {
        return Array.prototype.slice.call(elServices.querySelectorAll('.svc-cb:checked')).map(function (cb) { return cb.value; });
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

    btnPerformed.addEventListener('click', function () {
        post('/api/doctor_set_status.php', { appointment_id: currentId }).then(function (d) {
            if (d.ok) { location.reload(); }
            else { elErr.textContent = d.error || 'Не удалось изменить статус.'; elErr.classList.remove('d-none'); }
        });
    });

    var params = new URLSearchParams(location.search);
    if (params.has('appt')) {
        openAppt(params.get('appt'));
    }
})();
