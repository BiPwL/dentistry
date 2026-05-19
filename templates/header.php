<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';

$_pageTitle = $_pageTitle ?? SITE_NAME;
$_user = Auth::user();
$_currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
function _nav_active(string $href, string $current): string {
    return ($href === $current || ($href !== '/' && str_starts_with($current, $href)))
        ? ' active' : '';
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($_pageTitle) ?> — <?= h(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg sticky-top shadow-sm clinic-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <span class="brand-mark">🦷</span> <?= h(SITE_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?= _nav_active('/', $_currentPath) ?>" href="/">Главная</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/services.php', $_currentPath) ?>" href="/services.php">Услуги</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/blog.php', $_currentPath) ?>" href="/blog.php">Блог</a></li>
                <li class="nav-item"><a class="nav-link<?= _nav_active('/contacts.php', $_currentPath) ?>" href="/contacts.php">Контакты</a></li>
            </ul>
            <div class="d-flex gap-2">
                <?php if ($_user === null): ?>
                    <a href="/login.php" class="btn btn-outline-orange">Вход</a>
                    <a href="/register.php" class="btn btn-orange">Регистрация</a>
                <?php else: ?>
                    <a href="/profile.php" class="btn btn-orange">
                        <?= h(fio_short($_user['last_name'], $_user['first_name'], $_user['middle_name'])) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="flex-grow-1 py-4">
<div class="container">
