<?php
/**
 * customer/dashboard.php
 * Trang chính sau khi khách hàng đăng nhập: đặt sắp tới gần nhất (nếu có),
 * lối tắt đặt bàn mới, lịch sử gần đây, và số lượng đặt chỗ theo từng trạng
 * thái. Yêu cầu đăng nhập (require_login()).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_login();

$user_id = current_user()['id'];

// Vietnamese: "sap toi" = status con hieu luc (pending/confirmed) VA thoi
// diem dat (ngay + gio bat dau khung gio) con o TUONG LAI - dung cach so
// sanh chuoi "ngay gio bat dau" voi NOW() ngay trong SQL (an toan vi ca hai
// deu dinh dang chuan 'YYYY-MM-DD HH:MM:SS' nen so sanh chuoi = so sanh thoi
// gian) thay vi keo het du lieu ve PHP moi loc, gon hon.
$upcoming_stmt = $pdo->prepare("
    SELECT r.id, r.reservation_date, r.party_size, r.status,
           t.table_code, t.area, ts.start_time, ts.end_time
    FROM reservations r
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.user_id = :user_id
      AND r.status IN ('pending', 'confirmed')
      AND TIMESTAMP(r.reservation_date, ts.start_time) > NOW()
    ORDER BY r.reservation_date ASC, ts.start_time ASC
    LIMIT 1
");
$upcoming_stmt->execute([':user_id' => $user_id]);
$next_reservation = $upcoming_stmt->fetch();

$history_stmt = $pdo->prepare('
    SELECT r.id, r.reservation_date, r.party_size, r.status,
           t.table_code, t.area, ts.start_time, ts.end_time
    FROM reservations r
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.user_id = :user_id
    ORDER BY r.reservation_date DESC, ts.start_time DESC
    LIMIT 5
');
$history_stmt->execute([':user_id' => $user_id]);
$recent_history = $history_stmt->fetchAll();

$status_counts_stmt = $pdo->prepare('
    SELECT status, COUNT(*) AS cnt FROM reservations WHERE user_id = ? GROUP BY status
');
$status_counts_stmt->execute([$user_id]);
$status_counts = array_column($status_counts_stmt->fetchAll(), 'cnt', 'status');

$all_statuses = ['pending', 'confirmed', 'completed', 'no_show', 'cancelled', 'rejected'];

$page_title = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Welcome, <?= e(current_user()['full_name']) ?></h1>
    <a class="btn btn-gl-primary" href="<?= BASE_URL ?>/customer/book.php">+ Book a Table</a>
</div>

<div class="card gl-next-reservation-card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Your next reservation</h2>
        <?php if ($next_reservation === false): ?>
            <?php // Vietnamese: khong co san cau chu chinh xac trong §7 cho truong hop nay - dung cung tinh than voi cau da chuan hoa (thong bao + hanh dong ke tiep ro rang). ?>
            <p class="mb-2 text-muted">You have no upcoming reservations.</p>
            <a href="<?= BASE_URL ?>/customer/book.php" class="btn btn-outline-secondary btn-sm">Book a Table to get started</a>
        <?php else: ?>
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div>
                    <div class="text-muted small">Date &amp; time</div>
                    <div class="gl-next-value">
                        <?= e($next_reservation['reservation_date']) ?>
                        &middot;
                        <?= e(substr($next_reservation['start_time'], 0, 5) . '-' . substr($next_reservation['end_time'], 0, 5)) ?>
                    </div>
                </div>
                <div>
                    <div class="text-muted small">Table</div>
                    <div class="fs-5"><?= e($next_reservation['table_code']) ?> &mdash; <?= e(format_area_label($next_reservation['area'])) ?></div>
                </div>
                <div>
                    <div class="text-muted small">Guests</div>
                    <div class="fs-5"><?= e((string) $next_reservation['party_size']) ?></div>
                </div>
                <div>
                    <div class="text-muted small">Status</div>
                    <div><?= status_badge_html($next_reservation['status']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<h2 class="h6 mb-2">Your bookings by status</h2>
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($all_statuses as $s): ?>
        <a href="<?= BASE_URL ?>/customer/my-reservations.php?status=<?= e($s) ?>" class="text-decoration-none">
            <span class="badge text-bg-light border text-dark">
                <?= e(ucfirst(str_replace('_', ' ', $s))) ?>: <?= e((string) ($status_counts[$s] ?? 0)) ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 mb-0">Recent history</h2>
    <a href="<?= BASE_URL ?>/customer/my-reservations.php" class="btn btn-sm btn-outline-secondary">View all &rarr;</a>
</div>

<?php if (empty($recent_history)): ?>
    <?php // Vietnamese: cau chu chinh xac lay tu docs/design-process.md §7 ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon"><?= svg_lotus_motif() ?></div>
        <p class="mb-2">You have no reservations yet.</p>
        <a href="<?= BASE_URL ?>/customer/book.php" class="btn btn-outline-secondary btn-sm">Book a Table to get started</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Table</th>
                    <th>Guests</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_history as $r): ?>
                    <tr>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5) . '-' . substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e($r['table_code']) ?></td>
                        <td><?= e((string) $r['party_size']) ?></td>
                        <td><?= status_badge_html($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
