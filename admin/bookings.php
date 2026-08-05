<?php
/**
 * admin/bookings.php
 * Danh sách toàn bộ đặt bàn — hàng đợi duyệt (approval queue) của admin, nay
 * có thêm tìm kiếm theo tên/email khách, lọc theo trạng thái/khoảng ngày/khu
 * vực, sắp xếp theo cột, và phân trang 15 dòng/trang (Phase P7, FR-09).
 *
 * Pending vẫn luôn nổi lên đầu (tie-break `(status='pending') DESC` đứng
 * trước cột sắp xếp do người dùng chọn) - đúng luồng "Admin — clear the
 * pending queue" trong docs/design-process.md §3, dù admin đổi cách sắp xếp
 * theo cột nào thì các đơn đang chờ duyệt vẫn ưu tiên hiển thị trước.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';
require_once __DIR__ . '/../includes/listing.php';

require_admin();

$admin_id    = current_user()['id'];
$script_path = BASE_URL . '/admin/bookings.php';

$valid_statuses = ['pending', 'confirmed', 'completed', 'no_show', 'cancelled', 'rejected'];
$area_labels     = ['indoor_main' => 'Indoor Main', 'terrace' => 'Terrace', 'garden' => 'Garden', 'vip' => 'VIP Room'];

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

    // Vietnamese: gio lai nguyen bo loc/sap xep/trang dang xem (duoc luu san
    // trong truong an "return_qs" luc ve HTML, xem ben duoi) - de admin xu ly
    // xong 1 dong trong danh sach da loc van o LAI dung trang/bo loc do, khong
    // bi nhay ve trang 1 khong loc gi.
    $return_qs = (string) ($_POST['return_qs'] ?? '');
    $back_to   = '/admin/bookings.php' . ($return_qs !== '' ? '?' . $return_qs : '');

    if (!isset($action_to_status[$action])) {
        set_flash('danger', 'Unknown action.');
        redirect($back_to);
    }

    $result = change_reservation_status($pdo, $reservation_id, $action_to_status[$action], $admin_id);
    set_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect($back_to);
}

// -----------------------------------------------------------------------
// Doc va kiem tra bo loc tu query string (GET) - moi gia tri khong hop le se
// bi bo qua (coi nhu khong loc theo tieu chi do) thay vi lam sap trang.
// -----------------------------------------------------------------------
$filter_keyword   = trim((string) ($_GET['q'] ?? ''));
$filter_status    = (string) ($_GET['status'] ?? '');
if (!in_array($filter_status, $valid_statuses, true)) {
    $filter_status = '';
}
$filter_area = (string) ($_GET['area'] ?? '');
if (!array_key_exists($filter_area, $area_labels)) {
    $filter_area = '';
}
$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
$filter_date_from = (string) ($_GET['date_from'] ?? '');
if (preg_match($date_pattern, $filter_date_from) !== 1) {
    $filter_date_from = '';
}
$filter_date_to = (string) ($_GET['date_to'] ?? '');
if (preg_match($date_pattern, $filter_date_to) !== 1) {
    $filter_date_to = '';
}

// Vietnamese: DANH SACH TRANG (whitelist) cot duoc phep sap xep - xem giai
// thich day du trong includes/listing.php vi sao khong the bind ten cot qua
// dau "?" nhu bind gia tri.
$sort_whitelist = [
    'date'       => 'r.reservation_date',
    'created'    => 'r.created_at',
    'party_size' => 'r.party_size',
];
$sort = resolve_sort($sort_whitelist, 'date', 'asc');

// -----------------------------------------------------------------------
// Xay dung menh de WHERE tu bo loc - MOI gia tri deu bind qua tham so (?),
// khong bao gio noi truc tiep gia tri nguoi dung nhap vao chuoi SQL.
// -----------------------------------------------------------------------
$where  = [];
$params = [];

if ($filter_keyword !== '') {
    // Vietnamese: PDO khi EMULATE_PREPARES=false KHONG cho phep dung LAI cung
    // mot ten placeholder (vd :keyword) hai lan trong mot cau lenh - phai
    // dung hai ten khac nhau du gan cung mot gia tri, neu khong se nhan loi
    // "SQLSTATE[HY093]: Invalid parameter number" luc execute().
    $where[]               = '(u.full_name LIKE :keyword1 OR u.email LIKE :keyword2)';
    $params[':keyword1']   = '%' . $filter_keyword . '%';
    $params[':keyword2']   = '%' . $filter_keyword . '%';
}
if ($filter_status !== '') {
    $where[]            = 'r.status = :status';
    $params[':status']  = $filter_status;
}
if ($filter_area !== '') {
    $where[]          = 't.area = :area';
    $params[':area']  = $filter_area;
}
if ($filter_date_from !== '') {
    $where[]               = 'r.reservation_date >= :date_from';
    $params[':date_from']  = $filter_date_from;
}
if ($filter_date_to !== '') {
    $where[]             = 'r.reservation_date <= :date_to';
    $params[':date_to']  = $filter_date_to;
}

$where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$from_sql = '
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    JOIN `tables` t ON t.id = r.table_id
    JOIN time_slots ts ON ts.id = r.time_slot_id
    ' . $where_sql . '
';

$count_stmt = $pdo->prepare('SELECT COUNT(*) ' . $from_sql);
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, 15, get_current_page());

// Vietnamese: (r.status = 'pending') DESC dung TRUOC cot sap xep do nguoi
// dung chon - dam bao don pending luon noi len dau bat ke dang sap xep theo
// cot nao (xem ly do trong doc-comment o dau file).
$list_sql = '
    SELECT r.id, r.reservation_date, r.party_size, r.notes, r.status, r.created_at,
           t.table_code, t.area, ts.start_time, ts.end_time,
           u.full_name AS customer_name, u.email AS customer_email
    ' . $from_sql . "
    ORDER BY (r.status = 'pending') DESC, {$sort['column']} {$sort['dir']}
    LIMIT :limit OFFSET :offset
";
$list_stmt = $pdo->prepare($list_sql);
foreach ($params as $key => $value) {
    $list_stmt->bindValue($key, $value);
}
// Vietnamese: LIMIT/OFFSET phai bind kieu PDO::PARAM_INT tuong minh - neu de
// PDO tu doan kieu (bindValue mac dinh coi la chuoi/PARAM_STR) thi voi
// EMULATE_PREPARES=false, MySQL native prepared statement se tu choi dung
// gia tri KIEU CHUOI cho LIMIT/OFFSET (vd ném loi cu phap).
$list_stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$list_stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$list_stmt->execute();
$reservations = $list_stmt->fetchAll();

$now = new DateTimeImmutable('now');

// Vietnamese: query string HIEN TAI (bo loc + sap xep + trang) - nhung vao
// truong an cua moi form hanh dong (Approve/Reject/...) de POST xong quay lai
// DUNG cho nay (xem xu ly POST o tren).
$current_qs = http_build_query($_GET);

$has_active_filters = $filter_keyword !== '' || $filter_status !== '' || $filter_area !== '' || $filter_date_from !== '' || $filter_date_to !== '';

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Manage Bookings</h1>
</div>

<form method="get" action="<?= e($script_path) ?>" class="gl-filter-bar js-auto-submit row g-3 align-items-end">
    <div class="col-md-4 col-lg-3">
        <label class="form-label" for="q">Search customer</label>
        <input type="text" class="form-control" id="q" name="q" placeholder="Name or email" value="<?= e($filter_keyword) ?>">
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status">
            <option value="">All</option>
            <?php foreach ($valid_statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="area">Area</label>
        <select class="form-select" id="area" name="area">
            <option value="">All</option>
            <?php foreach ($area_labels as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filter_area === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="date_from">From date</label>
        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($filter_date_from) ?>">
    </div>
    <div class="col-6 col-md-3 col-lg-2">
        <label class="form-label" for="date_to">To date</label>
        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($filter_date_to) ?>">
    </div>
    <div class="col-md-4 col-lg-1 d-flex gap-2">
        <button type="submit" class="btn btn-gl-primary flex-fill">Filter</button>
    </div>
    <?php if ($has_active_filters): ?>
        <div class="col-12">
            <a href="<?= e($script_path) ?>" class="link-secondary small">&times; Clear filters</a>
        </div>
    <?php endif; ?>
</form>

<div class="gl-results-meta">
    <span class="text-muted"><?= e(showing_range_text($pagination, 'bookings')) ?></span>
</div>

<?php if (empty($reservations)): ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon">📋</div>
        <?php if ($has_active_filters): ?>
            <?php // Vietnamese: cau chu chinh xac lay tu docs/design-process.md §7 ?>
            <p class="mb-2">No bookings match these filters. Try widening the date range or clearing a filter.</p>
            <a href="<?= e($script_path) ?>" class="btn btn-outline-secondary btn-sm">Clear filters</a>
        <?php else: ?>
            <p class="mb-0">No bookings in the system yet.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Table</th>
                    <th>Area</th>
                    <th><?= sort_header_html($script_path, $sort, 'date', 'Date') ?></th>
                    <th>Slot</th>
                    <th><?= sort_header_html($script_path, $sort, 'party_size', 'Guests') ?></th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th><?= sort_header_html($script_path, $sort, 'created', 'Created') ?></th>
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
                        <td><?= e($r['customer_name']) ?><div class="text-muted small"><?= e($r['customer_email']) ?></div></td>
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
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button
                                        type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Reject this booking? The customer will be notified and cannot be re-approved afterwards.">
                                        Reject
                                    </button>
                                </form>
                            <?php elseif ($r['status'] === 'confirmed' && $has_passed): ?>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="reservation_id" value="<?= e((string) $r['id']) ?>">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <button type="submit" class="btn btn-sm btn-info">Mark Completed</button>
                                </form>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
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

    <?= pagination_nav_html($script_path, $pagination) ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
