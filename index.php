<?php
/**
 * index.php
 * Trang chủ (homepage) của Golden Lotus Restaurant. Đây là điểm vào đầu tiên khi
 * người dùng truy cập ứng dụng: giới thiệu nhà hàng và dẫn tới đăng nhập/đăng ký.
 *
 * Luồng dữ liệu (sẽ hoàn thiện ở Phase P5): nếu người dùng đã đăng nhập,
 * chuyển hướng tới customer/dashboard.php hoặc admin/dashboard.php theo vai trò.
 */

require_once __DIR__ . '/includes/auth.php';

// TODO (Phase P5): kiểm tra session, redirect theo vai trò nếu đã đăng nhập.

// Vietnamese: xac dinh dich cua nut CTA chinh "Book a Table" theo trang thai
// dang nhap — chua dang nhap thi qua trang login kem ?redirect= de quay lai
// thang trang dat ban sau khi dang nhap thanh cong (auth/login.php se doc
// $_GET['redirect'] va redirect() ve day khi Phase P5 trien khai form login);
// da dang nhap la customer thi vao thang trang dat ban; la admin thi CTA nay
// khong phu hop (admin khong dat ban) nen dua ve dashboard cua admin.
if (!is_logged_in()) {
    $book_cta_href = BASE_URL . '/auth/login.php?redirect=' . urlencode('/customer/book.php');
} elseif (current_user()['role'] === 'admin') {
    $book_cta_href = BASE_URL . '/admin/dashboard.php';
} else {
    $book_cta_href = BASE_URL . '/customer/book.php';
}

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 bg-gl-primary text-white rounded-3 text-center">
    <h1 class="mb-4">Authentic Vietnamese dining &mdash; reserve your table in seconds.</h1>

    <?php // Vietnamese: mot CTA chinh duy nhat (§1 "one primary action per screen") ?>
    <a class="btn btn-light btn-lg px-5" href="<?= e($book_cta_href) ?>">Book a Table</a>

    <?php if (!is_logged_in()): ?>
        <?php // Vietnamese: Login/Register van hien nhung o dang phu (link nho hon, khong phai nut lon) ?>
        <div class="mt-3">
            <a class="link-light small me-3" href="<?= BASE_URL ?>/auth/login.php">Login</a>
            <a class="link-light small" href="<?= BASE_URL ?>/auth/register.php">Register</a>
        </div>
    <?php else: ?>
        <p class="mt-3 mb-0">Welcome back, <?= e(current_user()['full_name']) ?>!</p>
    <?php endif; ?>
</div>

<div class="gl-info-band text-center">
    <div class="row g-4">
        <div class="col-md-6">
            <h3>Opening Hours</h3>
            <p class="mb-0">Daily, 11:00&ndash;22:00</p>
        </div>
        <div class="col-md-6">
            <h3>Our Areas</h3>
            <p class="mb-0">Indoor Main &middot; Terrace &middot; Garden &middot; VIP Room</p>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/includes/footer.php';
