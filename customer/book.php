<?php
/**
 * customer/book.php
 * Trang đặt bàn: khách chọn ngày + khung giờ + số người -> hệ thống lọc ra các bàn
 * còn đủ chỗ trống và chưa bị trùng lịch trong khung giờ đó -> khách chọn bàn và xác nhận.
 * Đây là màn hình trung tâm của luồng nghiệp vụ (business workflow), phải chống trùng lịch
 * (double-booking) cả ở tầng PHP lẫn ràng buộc UNIQUE/constraint trong database.
 *
 * Theo docs/design-process.md §4.3: ngay + khung gio + so khach duoc chon
 * CUNG LUC trong thanh tim kiem TRUOC KHI tim - ban chi duoc chon SAU KHI
 * co ket qua (progressive disclosure). Tim kiem la GET (khong doi trang
 * thai, co the bookmark/refresh an toan); xac nhan dat cho la POST theo
 * mau Post/Redirect/Get (docs/design-process.md §7).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_login();

$user_id = current_user()['id'];

$time_slots = $pdo->query('SELECT id, start_time, end_time FROM time_slots WHERE is_active = 1 ORDER BY start_time')->fetchAll();

// Vietnamese: xu ly XAC NHAN DAT CHO (POST) truoc - neu co loi, quay lai
// trang nay kem lai y nguyen 3 tham so tim kiem qua query string, de ket
// qua tim kiem hien lai dung nhu luc khach dang xem, khong phai tim lai tu dau.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'confirm_reservation') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/customer/book.php');
    }

    $date          = trim((string) ($_POST['date'] ?? ''));
    $party_size    = (int) ($_POST['party_size'] ?? 0);
    $time_slot_id  = (int) ($_POST['time_slot_id'] ?? 0);
    $table_id      = (int) ($_POST['table_id'] ?? 0);
    $notes_raw     = trim((string) ($_POST['notes'] ?? ''));
    $notes         = $notes_raw === '' ? null : $notes_raw;

    $back_query = http_build_query(['date' => $date, 'party_size' => $party_size, 'time_slot_id' => $time_slot_id]);

    if ($party_size < 1) {
        set_flash('danger', 'Please enter a valid party size.');
        redirect('/customer/book.php?' . $back_query);
    }

    // Vietnamese: kiem tra lai ngay/khung gio server-side - khong bao gio tin
    // gia tri form gui len, du no da qua kiem tra luc tim kiem (khach co the
    // sua truc tiep HTML/gui request thu cong voi ngay/khung gio khac).
    $bookable = is_slot_bookable($pdo, $date, $time_slot_id);
    if (!$bookable['ok']) {
        set_flash('danger', $bookable['message']);
        redirect('/customer/book.php?' . $back_query);
    }

    // Vietnamese: khong tin table_id ma form gui len la MOT LUA CHON HOP LE -
    // phai kiem tra lai no thuc su con nam trong danh sach ban con trong TAI
    // THOI DIEM SUBMIT (khong phai tai thoi diem tim kiem truoc do), vi tinh
    // trang co the da doi giua hai lan (nguoi khac vua dat mat ban do, hoac
    // khach tu sua table_id trong HTML de thu mot ban khong du suc chua).
    $available_now = get_available_tables($pdo, $date, $time_slot_id, $party_size);
    $available_ids = array_column($available_now, 'id');

    if (!in_array($table_id, $available_ids, true)) {
        set_flash('danger', 'Sorry, that table is no longer available for this date, time, and party size. Please choose another table.');
        redirect('/customer/book.php?' . $back_query);
    }

    $result = create_reservation($pdo, $user_id, $table_id, $time_slot_id, $date, $party_size, $notes);

    if ($result['ok']) {
        set_flash('success', $result['message']);
        redirect('/customer/my-reservations.php');
    }

    // Vietnamese: that bai do dung trung UNIQUE INDEX (race condition, xem
    // create_reservation()) - quay lai trang tim kiem de khach thay danh sach
    // ban con trong MOI NHAT (ban vua bi nguoi khac lay se khong con trong do).
    set_flash('danger', $result['message']);
    redirect('/customer/book.php?' . $back_query);
}

// Vietnamese: xu ly TIM KIEM (GET) - chi chay khi ca 3 tham so deu co mat tren query string.
$search = [
    'date'         => trim((string) ($_GET['date'] ?? '')),
    'party_size'   => trim((string) ($_GET['party_size'] ?? '')),
    'time_slot_id' => trim((string) ($_GET['time_slot_id'] ?? '')),
];

$has_searched      = false;
$search_errors     = [];
$available_tables  = [];

if ($search['date'] !== '' && $search['party_size'] !== '' && $search['time_slot_id'] !== '') {
    $has_searched  = true;
    $party_size_int   = (int) $search['party_size'];
    $time_slot_id_int = (int) $search['time_slot_id'];

    if ($party_size_int < 1) {
        $search_errors[] = 'Please enter a valid party size (at least 1).';
    }

    $bookable = is_slot_bookable($pdo, $search['date'], $time_slot_id_int);
    if (!$bookable['ok']) {
        $search_errors[] = $bookable['message'];
    }

    if (empty($search_errors)) {
        $available_tables = get_available_tables($pdo, $search['date'], $time_slot_id_int, $party_size_int);
    }
}

$page_title = 'Book a Table';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Book a Table</h1>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= BASE_URL ?>/customer/book.php" class="row g-3 align-items-end" novalidate>
            <div class="col-sm-4 col-md-3">
                <label class="form-label" for="date">Date</label>
                <input
                    type="date" class="form-control" id="date" name="date"
                    min="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"
                    value="<?= e($search['date']) ?>" required>
            </div>
            <div class="col-sm-3 col-md-2">
                <label class="form-label" for="party_size">Party size</label>
                <input
                    type="number" class="form-control" id="party_size" name="party_size"
                    min="1" max="12" value="<?= e($search['party_size']) ?>" required>
            </div>
            <div class="col-sm-5 col-md-4">
                <label class="form-label" for="time_slot_id">Time slot</label>
                <select class="form-select" id="time_slot_id" name="time_slot_id" required>
                    <option value="">Choose a slot&hellip;</option>
                    <?php foreach ($time_slots as $slot): ?>
                        <option value="<?= e((string) $slot['id']) ?>" <?= $search['time_slot_id'] === (string) $slot['id'] ? 'selected' : '' ?>>
                            <?= e(substr($slot['start_time'], 0, 5) . '-' . substr($slot['end_time'], 0, 5)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-gl-primary w-100">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($has_searched): ?>
    <?php if (!empty($search_errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($search_errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php elseif (empty($available_tables)): ?>
        <?php // Vietnamese: cau chu chinh xac lay tu docs/design-process.md §7 (empty state cua tim kiem) ?>
        <div class="alert alert-warning">
            No tables are available for this date, time slot, and party size. Try a different date, time, or slot.
        </div>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/customer/book.php" class="js-disable-on-submit" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="confirm_reservation">
            <input type="hidden" name="date" value="<?= e($search['date']) ?>">
            <input type="hidden" name="party_size" value="<?= e($search['party_size']) ?>">
            <input type="hidden" name="time_slot_id" value="<?= e($search['time_slot_id']) ?>">

            <h2 class="h5 mb-3">Available tables</h2>
            <?php // Vietnamese: sap xep san theo capacity tang dan tu get_available_tables() - ban vua du cho hien truoc tien. ?>
            <div class="row g-3 mb-4">
                <?php foreach ($available_tables as $i => $table): ?>
                    <div class="col-sm-6 col-lg-4">
                        <label class="card h-100 p-3" style="cursor: pointer;">
                            <div class="form-check">
                                <input
                                    class="form-check-input" type="radio" name="table_id"
                                    value="<?= e((string) $table['id']) ?>" required <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="form-check-label fw-bold">
                                    <?= e($table['table_code']) ?> &mdash; <?= e(format_area_label($table['area'])) ?>
                                </span>
                            </div>
                            <div class="text-muted small">Seats <?= e((string) $table['capacity']) ?></div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mb-4">
                <label class="form-label" for="notes">Notes (optional)</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="255"></textarea>
            </div>

            <button type="submit" class="btn btn-gl-primary btn-lg">Confirm Reservation</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
