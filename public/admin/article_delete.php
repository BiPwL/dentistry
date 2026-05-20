<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /blog.php'); exit; }
Csrf::requireValid();

$id = (int) ($_POST['id'] ?? 0);
$article = DB::one("SELECT image_path FROM blog_article WHERE id = :id", ['id' => $id]);
if ($article !== null) {
    if (!empty($article['image_path']) && is_file(__DIR__ . '/../' . $article['image_path'])) @unlink(__DIR__ . '/../' . $article['image_path']);
    DB::exec("DELETE FROM blog_article WHERE id = :id", ['id' => $id]);
}
header('Location: /blog.php');
exit;
