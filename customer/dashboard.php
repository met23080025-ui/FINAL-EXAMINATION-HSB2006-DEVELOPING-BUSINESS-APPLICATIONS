<?php
/**
 * customer/dashboard.php
 * Trang chính sau khi khách hàng đăng nhập: lối tắt tới đặt bàn mới và xem lịch sử đặt bàn.
 * Yêu cầu đăng nhập với role = 'customer' (kiểm tra qua includes/auth.php).
 *
 * Phase P5: chỉ là trang placeholder tối thiểu để chứng minh require_login()
 * và luồng chuyển hướng theo vai trò từ auth/login.php hoạt động đúng. Nội
 * dung thật (tổng quan đặt bàn, lối tắt tới book.php/my-reservations.php) sẽ
 * được xây ở Phase P6.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Vietnamese: chan trang neu chua dang nhap - day la "bang chung song" rang
// role middleware (Phase P4b) hoat dong dung tren mot trang that.
require_login();

$page_title = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Welcome, <?= e(current_user()['full_name']) ?></h1>
<p class="text-muted">This is your customer dashboard. Booking and reservation-history features arrive in Phase P6.</p>

<a class="btn btn-gl-primary me-2" href="<?= BASE_URL ?>/customer/profile.php">Edit Profile</a>

<?php require __DIR__ . '/../includes/footer.php'; ?>
