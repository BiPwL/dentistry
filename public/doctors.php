<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sanitize.php';

$_pageTitle = 'Врачи клиники';
$doctors = DB::all(
    "SELECT u.id, u.last_name, u.first_name, u.middle_name,
            sp.name AS specialization, dp.bio, dp.photo_path,
            (SELECT ROUND(AVG(rating),1) FROM review r WHERE r.doctor_id=u.id) AS avg_rating,
            (SELECT COUNT(*) FROM review r WHERE r.doctor_id=u.id) AS reviews_count
       FROM user u
       LEFT JOIN doctor_profile dp ON dp.user_id = u.id
       LEFT JOIN specialization sp ON sp.id = dp.specialization_id
      WHERE u.role_id = 3
      ORDER BY u.last_name, u.first_name"
);

require __DIR__ . '/../templates/header.php';
?>

<h1 class="mb-4">Наши врачи</h1>

<?php if (empty($doctors)): ?>
    <p class="text-muted">Информация о врачах пока не добавлена.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($doctors as $d): ?>
            <?php $fio = trim($d['last_name'] . ' ' . $d['first_name'] . ' ' . $d['middle_name']); ?>
            <div class="col-md-6 col-lg-4">
                <a href="/doctor.php?id=<?= (int) $d['id'] ?>" class="clinic-card h-100 text-center p-4 d-block text-reset text-decoration-none">
                    <?php if (!empty($d['photo_path'])): ?>
                        <img src="/<?= h($d['photo_path']) ?>" alt="<?= h($fio) ?>"
                             class="doctor-photo mb-3" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="doctor-photo doctor-photo--placeholder mb-3">
                            <?= h(mb_substr($d['last_name'], 0, 1) . mb_substr($d['first_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <h5 class="mb-1"><?= h($fio) ?></h5>
                    <?php if (!empty($d['specialization'])): ?>
                        <div class="text-orange-2 mb-2"><?= h($d['specialization']) ?></div>
                    <?php endif; ?>
                    <?php if ((int)$d['reviews_count'] > 0): ?>
                        <div class="text-warning mb-2"><small>★ <?= h((string)$d['avg_rating']) ?> (<?= (int)$d['reviews_count'] ?> отзывов)</small></div>
                    <?php endif; ?>
                    <?php if (!empty($d['bio'])): ?>
                        <p class="text-muted small mb-0"><?= h($d['bio']) ?></p>
                    <?php endif; ?>
                    <div class="small text-orange-2 mt-2">Подробнее →</div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
