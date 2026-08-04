<?php
/**
 * customer/my-reservations.php
 * Xem lịch sử đặt bàn của chính khách hàng, có thể lọc theo trạng thái
 * (pending/confirmed/completed/no_show/cancelled/rejected) và huỷ đặt bàn
 * (chỉ khi trạng thái còn là pending hoặc confirmed VÀ thời điểm đặt còn ở
 * tương lai).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_login();

$user_id = current_user()['id'];

$valid_statuses = ['pending', 'confirmed', 'completed', 'no_show', 'cancelled', 'rejected'];
$status_filter  = (string) ($_GET['status'] ?? '');
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'cancel_reservation') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/customer/my-reservations.php');
    }

    $reservation_id     = (int) ($_POST['reservation_id'] ?? 0);
    $redirect_status    = (string) ($_POST['status_filter'] ?? '');
    $redirect_query     = in_array($redirect_status, $valid_statuses, true) ? '?status=' . urlencode($redirect_status) : '';

    // Vietnamese: kiem tra lai TOAN BO dieu kien o server - khong chi dua vao
    // viec nut Cancel co dang hien tren giao dien hay khong (JS/HTML co the
    // bi khach tu sua): phai la chu so huu, va thoi diem dat (ngay + gio bat
    // dau khung gio) phai con o TUONG LAI.
    $stmt = $pdo->prepare('
        SELECT r.id, r.user_id, r.status, r.reservation_date, ts.start_time
        FROM reservations r
        JOIN time_slots ts ON ts.id = r.time_slot_id
        WHERE r.id = ?
    ');
    $stmt->execute([$reservation_id]);
    $target = $stmt->fetch();

    if ($target === false || (int) $target['user_id'] !== $user_id) {
        set_flash('danger', 'Reservation not found.');
    } else {
        $slot_start = new DateTimeImmutable($target['reservation_date'] . ' ' . $target['start_time']);
        if ($slot_start <= new DateTimeImmutable('now')) {
            set_flash('danger', 'This reservation can no longer be cancelled because its date/time has already passed.');
        } else {
            $result = change_reservation_status($pdo, $reservation_id, 'cancelled', $user_id);
            set_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Your reservation has been cancelled.' : $result['message']);
        }
    }

    redirect('/customer/my-reservations.php' . $redirect_query);
}

$sql = '
    SELECT r.id, r.reservation_date, r.party_size, r.notes, r.status, r.created_at,
           t.table_code, t.area, ts.start_time, ts.end_time
    FROM reservations r
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.user_id = :user_id
';
$params = [':user_id' => $user_id];
if ($status_filter !== '') {
    $sql .= ' AND r.status = :status';
    $params[':status'] = $status_filter;
}
// Vietnamese: "newest first" = ngay dat GAN NHAT len truoc (khop wireframe §4.4),
// khong phai theo thoi diem tao don - hai thu tu nay khac nhau ro trong thuc te.
$sql .= ' ORDER BY r.reservation_date DESC, ts.start_time DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$total_stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ?');
$total_stmt->execute([$user_id]);
$has_any_reservation = ((int) $total_stmt->fetchColumn()) > 0;

$now = new DateTimeImmutable('now');

$page_title = 'My Reservations';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">My Reservations</h1>

<form method="get" action="<?= BASE_URL ?>/customer/my-reservations.php" class="row g-2 align-items-end mb-4">
    <div class="col-auto">
        <label class="form-label" for="status">Filter status</label>
        <select class="form-select" id="status" name="status">
            <option value="">All</option>
            <?php foreach ($valid_statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $status_filter === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-gl-primary">Filter</button>
    </div>
</form>

<?php if (empty($reservations)): ?>
    <?php if (!$has_any_reservation): ?>
        <?php // Vietnamese: cau chu chinh xac lay tu docs/design-process.md §7 (empty state - chua co booking nao). ?>
        <div class="alert alert-warning">
            You have no reservations yet.
            <a href="<?= BASE_URL ?>/customer/book.php" class="alert-link">Book a Table</a> to get started.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">No reservations match this status filter.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Table</th>
                    <th>Area</th>
                    <th>Guests</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                    <?php
                    $slot_start = new DateTimeImmutable($r['reservation_date'] . ' ' . $r['start_time']);
                    $can_cancel = in_array($r['status'], ['pending', 'confirmed'], true) && $slot_start > $now;
                    ?>
                    <tr>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e($r['table_code']) ?></td>
                        <td><?= e(format_area_label($r['area'])) ?></td>
                        <td><?= e((string) $r['party_size']) ?></td>
                        <td><?= $r['notes'] !== null ? e($r['notes']) : '<span class="text-muted">&mdash;</span>' ?></td>
                        <td><?= status_badge_html($r['status']) ?></td>
                        <td>
                            <?php if ($can_cancel): ?>
                                <form method="post" action="<?= BASE_URL ?>/customer/my-reservations.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="cancel_reservation">
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="status_filter" value="<?= e($status_filter) ?>">
                                    <button
                                        type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Cancel this reservation? This cannot be undone.">
                                        Cancel
                                    </button>
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
