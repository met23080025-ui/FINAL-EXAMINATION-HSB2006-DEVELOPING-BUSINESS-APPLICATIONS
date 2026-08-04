<?php
/**
 * admin/dashboard.php
 * Trang tổng quan cho admin: số đặt bàn hôm nay, số lượng đang chờ duyệt (pending),
 * tỷ lệ huỷ (cancellation rate), khung giờ đông khách nhất (busiest time slot).
 * Yêu cầu đăng nhập với role = 'admin'.
 *
 * Phase P5: chỉ là trang placeholder tối thiểu để chứng minh require_admin()
 * chặn đúng người không phải admin và luồng chuyển hướng theo vai trò từ
 * auth/login.php hoạt động đúng. Các số liệu thống kê thật (COUNT, GROUP BY)
 * sẽ được xây ở Phase P6/P7.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Vietnamese: chan trang neu chua dang nhap HOAC da dang nhap nhung khong
// phai admin (vd mot customer co gang vao thang URL nay) - day la "bang
// chung song" rang role middleware phan biet dung hai vai tro.
require_admin();

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Admin Dashboard</h1>
<p class="text-muted">Welcome, <?= e(current_user()['full_name']) ?>. Booking statistics (today's bookings, pending count, cancellation rate, busiest slot) arrive in Phase P6/P7.</p>

<?php require __DIR__ . '/../includes/footer.php'; ?>
