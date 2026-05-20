<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/sanitize.php';

$id = (int) ($_GET['id'] ?? 0);
$doc = DB::one(
    "SELECT u.id, u.last_name, u.first_name, u.middle_name,
            sp.name AS specialization, dp.bio, dp.photo_path,
            (SELECT ROUND(AVG(rating),1) FROM review r WHERE r.doctor_id = u.id) AS avg_rating,
            (SELECT COUNT(*) FROM review r WHERE r.doctor_id = u.id) AS reviews_count
       FROM user u
       LEFT JOIN doctor_profile dp ON dp.user_id = u.id
       LEFT JOIN specialization sp ON sp.id = dp.specialization_id
      WHERE u.id = :id AND u.role_id = 3",
    ['id' => $id]
);

if ($doc === null) {
    http_response_code(404);
    $_pageTitle = 'Врач не найден';
    require __DIR__ . '/../templates/header.php';
    echo '<p class="text-muted">Врач не найден.</p><a href="/doctors.php" class="btn btn-orange">К списку врачей</a>';
    require __DIR__ . '/../templates/footer.php';
    exit;
}

$fio = trim($doc['last_name'] . ' ' . $doc['first_name'] . ' ' . $doc['middle_name']);
$reviews = DB::all(
    "SELECT r.rating, r.body, r.created_at, p.last_name, p.first_name
       FROM review r JOIN user p ON p.id = r.patient_id
      WHERE r.doctor_id = :id ORDER BY r.created_at DESC",
    ['id' => $id]
);

$_pageTitle = $fio;
require __DIR__ . '/../templates/header.php';
?>

<div class="mb-3"><a href="/doctors.php" class="text-decoration-none">← Все врачи</a></div>

<div class="row g-4">
    <div class="col-md-4 text-center">
        <div class="clinic-card p-4">
            <?php if (!empty($doc['photo_path'])): ?>
                <img src="/<?= h($doc['photo_path']) ?>" alt="<?= h($fio) ?>" class="doctor-photo mb-3" onerror="this.style.display='none'">
            <?php else: ?>
                <div class="doctor-photo doctor-photo--placeholder mb-3">
                    <?= h(mb_substr($doc['last_name'], 0, 1) . mb_substr($doc['first_name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <h1 class="h4 mb-1"><?= h($fio) ?></h1>
            <?php if (!empty($doc['specialization'])): ?>
                <div class="text-orange-2 mb-2"><?= h($doc['specialization']) ?></div>
            <?php endif; ?>
            <?php if ((int) $doc['reviews_count'] > 0): ?>
                <div class="text-warning"><small>★ <?= h((string) $doc['avg_rating']) ?> (<?= (int) $doc['reviews_count'] ?> отзывов)</small></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-8">
        <?php if (!empty($doc['bio'])): ?>
            <div class="clinic-card p-4 mb-4">
                <h5 class="mb-2">О враче</h5>
                <p class="mb-0" style="white-space: pre-wrap;"><?= h($doc['bio']) ?></p>
            </div>
        <?php endif; ?>

        <div class="clinic-card p-4">
            <h5 class="mb-3">Отзывы<?= !empty($reviews) ? ' (' . count($reviews) . ')' : '' ?></h5>
            <?php if (empty($reviews)): ?>
                <p class="text-muted small mb-0">Отзывов пока нет.</p>
            <?php else: ?>
                <?php foreach ($reviews as $rv): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-warning"><?= str_repeat('★', (int) $rv['rating']) ?><span class="text-muted"><?= str_repeat('☆', 5 - (int) $rv['rating']) ?></span></span>
                            <span class="text-muted small">
                                <?= h($rv['last_name'] . ' ' . mb_substr($rv['first_name'], 0, 1) . '.') ?>,
                                <?= h(date('d.m.Y', strtotime($rv['created_at']))) ?>
                            </span>
                        </div>
                        <?php if (!empty($rv['body'])): ?>
                            <div class="small mt-1"><?= h($rv['body']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
