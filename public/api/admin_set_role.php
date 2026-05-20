<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();

$adminId = (int) Auth::user()['id'];
$userId  = (int) ($_POST['user_id'] ?? 0);
$roleId  = (int) ($_POST['role_id'] ?? 0);

if ($userId === $adminId) { header('Location: /profile.php'); exit; }
$roleOk = DB::one("SELECT id FROM role WHERE id = :id", ['id' => $roleId]);
$userOk = DB::one("SELECT id FROM user WHERE id = :id", ['id' => $userId]);
if ($roleOk !== null && $userOk !== null) {
    DB::exec("UPDATE user SET role_id = :r WHERE id = :u", ['r' => $roleId, 'u' => $userId]);
}
header('Location: /profile.php');
exit;
