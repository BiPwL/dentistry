<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sanitize.php';

$_pageTitle = 'Контакты';
require __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-4">Контакты и график</h1>

<div class="row g-4">
    <div class="col-md-6">
        <div class="clinic-card p-4">
            <h5>Адрес</h5>
            <p class="mb-3"><?= h(CLINIC_ADDRESS) ?></p>

            <h5>Телефон</h5>
            <p class="mb-3"><a href="tel:<?= h(preg_replace('/\D+/', '', CLINIC_PHONE)) ?>"><?= h(CLINIC_PHONE) ?></a></p>

            <h5>Email</h5>
            <p class="mb-0"><a href="mailto:<?= h(CLINIC_EMAIL) ?>"><?= h(CLINIC_EMAIL) ?></a></p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="clinic-card p-4">
            <h5>График работы клиники</h5>
            <table class="table table-borderless mb-0">
                <tbody>
                    <tr><td>Понедельник – пятница</td><td class="text-end">10:00 — 18:00</td></tr>
                    <tr><td>Суббота</td><td class="text-end">10:00 — 16:00</td></tr>
                    <tr><td>Воскресенье</td><td class="text-end text-muted">Выходной</td></tr>
                </tbody>
            </table>
            <p class="small text-muted mt-3 mb-0">Перерыв на обед: 14:00 — 15:00. Длительность одного приёма — 1 час.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
