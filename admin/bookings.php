<?php
/**
 * admin/bookings.php
 * Danh sách toàn bộ đặt bàn — hàng đợi duyệt (approval queue) của admin.
 * Pending hiển thị trước tiên theo mặc định. Admin duyệt (confirmed) / từ
 * chối (rejected) các đơn đang pending, và đánh dấu completed / no_show cho
 * các đơn confirmed mà giờ đặt đã trôi qua.
 *
 * Phase P6: chỉ danh sách + hành động trạng thái. Tìm kiếm/lọc/sắp xếp/phân
 * trang (FR-09) là phạm vi Phase P7 - CHƯA làm ở đây, theo đúng lộ trình
 * trong CLAUDE.md.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_admin();

$admin_id = current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/admin/bookings.php');
    }

    $reservation_id = (int) ($_POST['reservation_id'] ?? 0);
    // Vietnamese: anh xa hanh dong tren giao dien -> trang thai dich, roi di
    // qua DUY NHAT change_reservation_status() (dung can_transition() ben
    // trong) - khong tu UPDATE truc tiep o day, de vong doi trang thai luon
    // duoc kiem tra o MOT noi thay vi lap lai logic o tung hanh dong.
    $action_to_status = [
        'approve'        => 'confirmed',
        'reject'         => 'rejected',
        'mark_completed' => 'completed',
        'mark_no_show'   => 'no_show',
    ];
    $action = (string) ($_POST['action'] ?? '');

    if (!isset($action_to_status[$action])) {
        set_flash('danger', 'Unknown action.');
        redirect('/admin/bookings.php');
    }

    $result = change_reservation_status($pdo, $reservation_id, $action_to_status[$action], $admin_id);
    set_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect('/admin/bookings.php');
}

// Vietnamese: pending len dau theo MAC DINH (yeu cau cua STEP 4) - dung bieu
// thuc dieu kien (r.status = 'pending') tra ve 1/0 trong MySQL/MariaDB, sap
// DESC de 1 (pending) len truoc; trong cung nhom, sap theo ngay/gio dat GAN
// NHAT len truoc de admin xu ly theo thu tu can kip nhat.
$stmt = $pdo->query('
    SELECT r.id, r.reservation_date, r.party_size, r.notes, r.status, r.created_at,
           t.table_code, t.area, ts.start_time, ts.end_time,
           u.full_name AS customer_name
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    ORDER BY (r.status = \'pending\') DESC, r.reservation_date ASC, ts.start_time ASC
');
$reservations = $stmt->fetchAll();

$now = new DateTimeImmutable('now');

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Manage Bookings</h1>

<?php if (empty($reservations)): ?>
    <div class="alert alert-warning">No bookings in the system yet.</div>
<?php else: ?>
    <?php // Vietnamese: boc bang trong .table-responsive theo quy uoc §6 - admin can thay DU cot, cuon ngang thay vi an bot cot o man hinh hep. ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Table</th>
                    <th>Area</th>
                    <th>Date</th>
                    <th>Slot</th>
                    <th>Guests</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                    <?php
                    $slot_end   = new DateTimeImmutable($r['reservation_date'] . ' ' . $r['end_time']);
                    $has_passed = $slot_end <= $now;
                    ?>
                    <tr>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= e($r['table_code']) ?></td>
                        <td><?= e(format_area_label($r['area'])) ?></td>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e((string) $r['party_size']) ?></td>
                        <td><?= $r['notes'] !== null ? e($r['notes']) : '<span class="text-muted">&mdash;</span>' ?></td>
                        <td><?= status_badge_html($r['status']) ?></td>
                        <td><?= e($r['created_at']) ?></td>
                        <td class="text-nowrap">
                            <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" action="<?= BASE_URL ?>/admin/bookings.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="post" action="<?= BASE_URL ?>/admin/bookings.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button
                                        type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Reject this booking? The customer will be notified and cannot be re-approved afterwards.">
                                        Reject
                                    </button>
                                </form>
                            <?php elseif ($r['status'] === 'confirmed' && $has_passed): ?>
                                <form method="post" action="<?= BASE_URL ?>/admin/bookings.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <button type="submit" class="btn btn-sm btn-info">Mark Completed</button>
                                </form>
                                <form method="post" action="<?= BASE_URL ?>/admin/bookings.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="mark_no_show">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Mark No-show</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
