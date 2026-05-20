<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /services.php'); exit; }
Csrf::requireValid();

$id = (int) ($_POST['id'] ?? 0);
$service = DB::one("SELECT image_path FROM service WHERE id = :id", ['id' => $id]);
if ($service === null) { header('Location: /services.php'); exit; }

// Нельзя удалить услугу, использованную в записях (FK RESTRICT) — покажем сообщение
$used = DB::one("SELECT 1 FROM appointment_service WHERE service_id = :id LIMIT 1", ['id' => $id]);
if ($used !== null) {
    $_pageTitle = 'Удаление невозможно';
    require __DIR__ . '/../../templates/header.php';
    echo '<div class="alert alert-danger">Услуга используется в записях пациентов, поэтому её нельзя удалить. Отредактируйте её вместо удаления.</div>';
    echo '<a href="/services.php" class="btn btn-orange">К каталогу</a>';
    require __DIR__ . '/../../templates/footer.php';
    exit;
}

if (!empty($service['image_path']) && is_file(__DIR__ . '/../' . $service['image_path'])) @unlink(__DIR__ . '/../' . $service['image_path']);
DB::exec("DELETE FROM service WHERE id = :id", ['id' => $id]);
header('Location: /services.php');
exit;
