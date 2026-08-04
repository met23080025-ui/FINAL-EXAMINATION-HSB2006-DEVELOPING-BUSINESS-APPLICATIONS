<?php
/**
 * includes/header.php
 * Phần đầu trang dùng chung cho mọi trang (khai báo Bootstrap 5 CDN, thanh
 * điều hướng thay đổi theo vai trò, hiển thị flash message). Mỗi trang .php
 * hiển thị giao diện include file này ngay sau khi xử lý logic xong (sau mọi
 * lệnh redirect() có thể xảy ra, vì file này đã in ra HTML).
 *
 * Có thể đặt biến $page_title trước khi include để đổi tiêu đề trang.
 */

require_once __DIR__ . '/auth.php';

$current_user  = current_user();
$active_title  = isset($page_title) ? $page_title . ' - Golden Lotus Restaurant' : 'Golden Lotus Restaurant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($active_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-gl-primary">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">Golden Lotus</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto">
                <?php if ($current_user === null): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/register.php">Register</a></li>
                <?php elseif ($current_user['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/bookings.php">Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/tables.php">Tables</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/timeslots.php">Time Slots</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/reports.php">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/logout.php">Logout (<?= e($current_user['full_name']) ?>)</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/customer/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/customer/book.php">Book a Table</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/customer/my-bookings.php">My Bookings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/customer/profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/logout.php">Logout (<?= e($current_user['full_name']) ?>)</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-3">
    <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
</div>

<main class="container my-4">
