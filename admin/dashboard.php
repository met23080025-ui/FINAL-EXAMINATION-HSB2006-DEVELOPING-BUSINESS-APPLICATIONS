<?php
/**
 * admin/dashboard.php
 * Trang tổng quan cho admin (FR-08, wireframe §4.5): 4 ô số liệu (hôm nay,
 * đang chờ, tỷ lệ huỷ, khung giờ đông nhất) + xem trước 5 đơn pending gần
 * nhất kèm nút duyệt/từ chối nhanh. MỌI con số đều lấy từ SQL thật, không có
 * giá trị gán cứng.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_admin();

$admin_id = current_user()['id'];

// Vietnamese: xu ly nhanh Approve/Reject ngay tren dashboard (khong can nhay
// sang bookings.php) - dung LAI change_reservation_status() nhu moi noi
// khac, khong tu viet UPDATE rieng.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/admin/dashboard.php');
    }

    $reservation_id   = (int) ($_POST['reservation_id'] ?? 0);
    $action_to_status = ['approve' => 'confirmed', 'reject' => 'rejected'];
    $action            = (string) ($_POST['action'] ?? '');

    if (!isset($action_to_status[$action])) {
        set_flash('danger', 'Unknown action.');
        redirect('/admin/dashboard.php');
    }

    $result = change_reservation_status($pdo, $reservation_id, $action_to_status[$action], $admin_id);
    set_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect('/admin/dashboard.php');
}

// -----------------------------------------------------------------------
// 4 o so lieu tong quan.
// -----------------------------------------------------------------------

// "Hom nay": don dang con y nghia (khong tinh cancelled/rejected) co ngay dat la hom nay.
$today_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM reservations
    WHERE reservation_date = CURDATE() AND status NOT IN ('cancelled', 'rejected')
");
$today_stmt->execute();
$today_count = (int) $today_stmt->fetchColumn();

// "Dang cho": TOAN BO don pending tren he thong (khong gioi han hom nay) - day
// chinh la hang doi admin can xu ly, dung y nghia luong "clear the pending
// queue" trong docs/design-process.md §3.
$pending_count = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();

// "Ty le huy": ty le (%) don co status = 'cancelled' tren TONG SO don tung
// duoc tao (all-time) - phep do don gian, de giai thich khi vien.
$total_reservations = (int) $pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
$cancelled_count    = (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'cancelled'")->fetchColumn();
$cancellation_rate  = $total_reservations > 0 ? round(($cancelled_count / $total_reservations) * 100) : 0;

// "Khung gio dong nhat": khung gio co nhieu don CON Y NGHIA nhat (khong tinh
// cancelled/rejected), tinh tren toan bo lich su.
$busiest_stmt = $pdo->query("
    SELECT ts.start_time, ts.end_time, COUNT(*) AS cnt
    FROM reservations r
    JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.status NOT IN ('cancelled', 'rejected')
    GROUP BY r.time_slot_id, ts.start_time, ts.end_time
    ORDER BY cnt DESC
    LIMIT 1
");
$busiest_slot = $busiest_stmt->fetch();

// -----------------------------------------------------------------------
// Xem truoc hang doi pending (5 don cho LAU NHAT len truoc - urgent nhat).
// -----------------------------------------------------------------------
$queue_stmt = $pdo->query("
    SELECT r.id, r.reservation_date, r.party_size, r.created_at,
           t.table_code, ts.start_time, ts.end_time, u.full_name AS customer_name
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
    LIMIT 5
");
$pending_preview = $queue_stmt->fetchAll();

$page_title = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Admin Dashboard</h1>
<p class="text-muted">Welcome, <?= e(current_user()['full_name']) ?>.</p>

<?php
/*
 * Vietnamese: moi tile so lieu dung data-count-target de main.js chay hoat
 * hinh "dem tang dan" (~650ms, easeOutCubic, tu dong bo qua neu
 * prefers-reduced-motion) - noi dung TEXT ben trong van la con so PHP render
 * san (khong phai "0"), nen neu JS tat hoac bi loi, so hien DUNG ngay tu dau,
 * khong bao gio phu thuoc JS moi doc duoc.
 */
?>
<div class="gl-tile-row">
    <div class="gl-tile gl-tile-today">
        <div class="gl-tile-icon"><?= svg_icon_calendar() ?></div>
        <div class="gl-tile-value" data-count-target="<?= e((string) $today_count) ?>"><?= e((string) $today_count) ?></div>
        <div class="gl-tile-label">Today's Bookings</div>
    </div>
    <div class="gl-tile gl-tile-pending">
        <div class="gl-tile-icon"><?= svg_icon_hourglass() ?></div>
        <div class="gl-tile-value" data-count-target="<?= e((string) $pending_count) ?>"><?= e((string) $pending_count) ?></div>
        <div class="gl-tile-label">Pending Approval</div>
    </div>
    <div class="gl-tile gl-tile-cancel">
        <div class="gl-tile-icon"><?= svg_icon_cancel_circle() ?></div>
        <div class="gl-tile-value" data-count-target="<?= e((string) $cancellation_rate) ?>" data-count-suffix="%"><?= e((string) $cancellation_rate) ?>%</div>
        <div class="gl-tile-label">Cancellation Rate</div>
    </div>
    <div class="gl-tile gl-tile-busiest">
        <div class="gl-tile-icon"><?= svg_icon_flame() ?></div>
        <div class="gl-tile-value">
            <?= $busiest_slot !== false
                ? e(substr($busiest_slot['start_time'], 0, 5) . '-' . substr($busiest_slot['end_time'], 0, 5))
                : 'N/A' ?>
        </div>
        <div class="gl-tile-label">Busiest Slot</div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Pending Queue Preview</h2>
    <a href="<?= BASE_URL ?>/admin/bookings.php?status=pending" class="btn btn-sm btn-outline-secondary">Go to full list &rarr;</a>
</div>

<?php if (empty($pending_preview)): ?>
    <?php // Vietnamese: cau chu chinh xac lay tu docs/design-process.md §7 ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon"><?= svg_lotus_motif() ?></div>
        <p class="mb-0">No pending bookings right now. You're all caught up.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Requested</th>
                    <th>Customer</th>
                    <th>Table</th>
                    <th>Date</th>
                    <th>Slot</th>
                    <th>Guests</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_preview as $r): ?>
                    <tr>
                        <td><?= e(substr($r['created_at'], 11, 5)) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e($r['table_code']) ?></td>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e((string) $r['party_size']) ?></td>
                        <td class="text-nowrap">
                            <form method="post" action="<?= BASE_URL ?>/admin/dashboard.php" class="d-inline js-disable-on-submit">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" action="<?= BASE_URL ?>/admin/dashboard.php" class="d-inline js-disable-on-submit">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Reject this booking? The customer will be notified and cannot be re-approved afterwards.">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
