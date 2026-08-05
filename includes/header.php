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
require_once __DIR__ . '/icons.php';

$current_user  = current_user();
$active_title  = isset($page_title) ? $page_title . ' - Golden Lotus Restaurant' : 'Golden Lotus Restaurant';

// Vietnamese: duong dan script hien tai (vd "/golden-lotus/admin/dashboard.php")
// - dung de xac dinh lien ket nao trong navbar la "trang hien tai" (Phase
// "Navbar refinement"). So sanh CA DUONG DAN DAY DU (khong chi ten file) vi
// admin/dashboard.php va customer/dashboard.php trung ten file voi nhau -
// so basename() se sai neu mot ngay nao do ca hai xuat hien cung luc.
$current_script_path = $_SERVER['SCRIPT_NAME'] ?? '';

/**
 * Sinh san chuoi thuoc tinh class + aria-current cho MOT the <a> dieu huong,
 * dua tren so sanh $target_path (duong dan tuong doi, vd "/admin/bookings.php")
 * voi $current_script_path. Tra ve san "class=... [aria-current=...]" de dan
 * thang vao the <a>, giu moi dong lien ket trong markup ben duoi ngan gon.
 */
function gl_nav_attrs(string $target_path, string $current_script_path): string
{
    $is_active = $current_script_path === (BASE_URL . $target_path);
    $class     = 'nav-link gl-nav-link' . ($is_active ? ' active' : '');
    $aria      = $is_active ? ' aria-current="page"' : '';
    return 'class="' . $class . '"' . $aria;
}
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
        <a class="navbar-brand gl-navbar-brand" href="<?= BASE_URL ?>/index.php">
            <?= svg_lotus_motif('gl-navbar-brand-icon', '1.75rem') ?>
            <span class="gl-navbar-brand-word">Golden Lotus</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($current_user === null): ?>
                    <li class="nav-item"><a <?= gl_nav_attrs('/index.php', $current_script_path) ?> href="<?= BASE_URL ?>/index.php">Home</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/auth/login.php', $current_script_path) ?> href="<?= BASE_URL ?>/auth/login.php">Login</a></li>
                    <?php // Vietnamese: Register la hanh dong chinh cua khach chua co tai khoan - lam noi bat thanh nut nho mau accent thay vi mot link chu binh thuong nhu Login. ?>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-sm gl-btn-nav-cta" href="<?= BASE_URL ?>/auth/register.php">Register</a>
                    </li>
                <?php elseif ($current_user['role'] === 'admin'): ?>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/dashboard.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/bookings.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/bookings.php">Bookings</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/tables.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/tables.php">Tables</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/timeslots.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/timeslots.php">Time Slots</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/users.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/admin/reports.php', $current_script_path) ?> href="<?= BASE_URL ?>/admin/reports.php">Reports</a></li>
                    <li class="nav-item"><a class="nav-link gl-nav-link" href="<?= BASE_URL ?>/auth/logout.php">Logout<span class="gl-nav-logout-name"> (<?= e($current_user['full_name']) ?>)</span></a></li>
                <?php else: ?>
                    <li class="nav-item"><a <?= gl_nav_attrs('/customer/dashboard.php', $current_script_path) ?> href="<?= BASE_URL ?>/customer/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/customer/book.php', $current_script_path) ?> href="<?= BASE_URL ?>/customer/book.php">Book a Table</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/customer/my-reservations.php', $current_script_path) ?> href="<?= BASE_URL ?>/customer/my-reservations.php">My Reservations</a></li>
                    <li class="nav-item"><a <?= gl_nav_attrs('/customer/profile.php', $current_script_path) ?> href="<?= BASE_URL ?>/customer/profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link gl-nav-link" href="<?= BASE_URL ?>/auth/logout.php">Logout<span class="gl-nav-logout-name"> (<?= e($current_user['full_name']) ?>)</span></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php
// Vietnamese: doi tu alert tinh (o dau trang) sang "toast" truot vao goc
// tren-phai (Phase "Polish pass") - co che luu/lay flash BEN DUOI khong doi
// gi ca (van la $_SESSION['flash'] qua get_flashes(), Phase P4b), day chi la
// lop trinh bay moi. public/js/main.js xu ly tu dong tat (success/info/
// warning sau 4s kem thanh tien trinh) va nut dong thu cong (danger o lai
// den khi nguoi dung tu bam) - xem CSS .gl-toast* trong public/css/style.css.
$flashes = get_flashes();
?>
<?php if (!empty($flashes)): ?>
    <div class="gl-toast-region" aria-live="polite" aria-atomic="true">
        <?php foreach ($flashes as $flash): ?>
            <div class="gl-toast gl-toast-<?= e($flash['type']) ?>" role="status">
                <div class="gl-toast-body"><?= e($flash['message']) ?></div>
                <button type="button" class="gl-toast-close" aria-label="Dismiss notification">&times;</button>
                <div class="gl-toast-progress"></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="container my-4">
