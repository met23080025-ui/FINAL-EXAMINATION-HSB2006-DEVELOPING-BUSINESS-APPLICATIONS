<?php
/**
 * admin/reports.php
 * Thống kê đặt bàn theo khoảng ngày (FR-15): số đơn/ngày (biểu đồ cột thuần
 * HTML/CSS), số đơn theo trạng thái, theo khu vực, số khách trung bình,
 * khung giờ đông khách xếp hạng — và xuất CSV của đúng tập kết quả đang lọc.
 *
 * QUAN TRONG: nhanh xuat CSV (export=csv) phai duoc xu ly TRUOC bat ky HTML
 * nao duoc in ra (truoc khi require includes/header.php) - giong het quy tac
 * "redirect() phai chay truoc output" ap dung cho cac trang khac, vi header()
 * (dat Content-Type/Content-Disposition) that bai neu output da bat dau.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';

require_admin();

$area_labels = ['indoor_main' => 'Indoor Main', 'terrace' => 'Terrace', 'garden' => 'Garden', 'vip' => 'VIP Room'];

/**
 * Kiem tra chuoi co dung dinh dang ngay Y-m-d va la mot ngay hop le hay khong.
 */
function is_valid_date_string(string $value): bool
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

// Vietnamese: khoang ngay mac dinh trung voi pham vi seed du lieu demo trong
// CLAUDE.md (14 ngay qua den 7 ngay toi) - de bao cao co du lieu y nghia
// ngay khi mo trang lan dau, khong hien trang trong.
$default_from = (new DateTimeImmutable('today'))->modify('-14 days')->format('Y-m-d');
$default_to   = (new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');

$date_from = (string) ($_GET['from'] ?? $default_from);
$date_to   = (string) ($_GET['to'] ?? $default_to);

if (!is_valid_date_string($date_from)) {
    $date_from = $default_from;
}
if (!is_valid_date_string($date_to)) {
    $date_to = $default_to;
}
// Vietnamese: neu nguoi dung nhap dao nguoc (from > to), tu doi cho lai thay
// vi bao loi - than thien hon va khong co cach hieu nao khac hop ly hon.
if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

$params_range = [':from' => $date_from, ':to' => $date_to];

// -----------------------------------------------------------------------
// Xuat CSV - PHAI chay va exit() TRUOC khi co bat ky HTML nao duoc in ra.
// -----------------------------------------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $pdo->prepare('
        SELECT r.id, r.reservation_date, ts.start_time, ts.end_time, t.table_code, t.area,
               u.full_name AS customer_name, u.email AS customer_email, r.party_size, r.status, r.created_at
        FROM reservations r
        JOIN users u ON u.id = r.user_id
        JOIN `tables` t ON t.id = r.table_id
        JOIN time_slots ts ON ts.id = r.time_slot_id
        WHERE r.reservation_date BETWEEN :from AND :to
        ORDER BY r.reservation_date ASC, ts.start_time ASC
    ');
    $stmt->execute($params_range);
    $export_rows = $stmt->fetchAll();

    $filename = "bookings_report_{$date_from}_to_{$date_to}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // Vietnamese: fputcsv() tu dong lo dau nhay/dau phay/xuong dong trong du
    // lieu (vd ghi chu khach co dau phay) - khong tu ghep chuoi CSV bang tay.
    fputcsv($out, ['Reservation ID', 'Date', 'Start Time', 'End Time', 'Table', 'Area', 'Customer Name', 'Customer Email', 'Party Size', 'Status', 'Created At']);
    foreach ($export_rows as $row) {
        fputcsv($out, [
            $row['id'],
            $row['reservation_date'],
            substr($row['start_time'], 0, 5),
            substr($row['end_time'], 0, 5),
            $row['table_code'],
            format_area_label($row['area']),
            $row['customer_name'],
            $row['customer_email'],
            $row['party_size'],
            $row['status'],
            $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// -----------------------------------------------------------------------
// So don / ngay - dien du 0 cho cac ngay khong co don nao, de bieu do khong
// bo trong ngay giua khoang (LEFT JOIN tu danh sach ngay sinh o PHP, vi MySQL
// khong co san ham sinh chuoi ngay lien tuc don gian tren moi ban MySQL/MariaDB).
// -----------------------------------------------------------------------
$per_day_stmt = $pdo->prepare('
    SELECT reservation_date, COUNT(*) AS cnt
    FROM reservations
    WHERE reservation_date BETWEEN :from AND :to
    GROUP BY reservation_date
');
$per_day_stmt->execute($params_range);
$per_day_counts = array_column($per_day_stmt->fetchAll(), 'cnt', 'reservation_date');

$per_day = [];
$cursor  = new DateTimeImmutable($date_from);
$end     = new DateTimeImmutable($date_to);
while ($cursor <= $end) {
    $d = $cursor->format('Y-m-d');
    $per_day[] = ['label' => $d, 'count' => (int) ($per_day_counts[$d] ?? 0)];
    $cursor = $cursor->modify('+1 day');
}

// By status
$by_status_stmt = $pdo->prepare('
    SELECT status, COUNT(*) AS cnt FROM reservations
    WHERE reservation_date BETWEEN :from AND :to
    GROUP BY status ORDER BY cnt DESC
');
$by_status_stmt->execute($params_range);
$by_status = $by_status_stmt->fetchAll();

// By area
$by_area_stmt = $pdo->prepare('
    SELECT t.area, COUNT(*) AS cnt
    FROM reservations r JOIN `tables` t ON t.id = r.table_id
    WHERE r.reservation_date BETWEEN :from AND :to
    GROUP BY t.area ORDER BY cnt DESC
');
$by_area_stmt->execute($params_range);
$by_area = $by_area_stmt->fetchAll();

// Average party size
$avg_stmt = $pdo->prepare('SELECT AVG(party_size) FROM reservations WHERE reservation_date BETWEEN :from AND :to');
$avg_stmt->execute($params_range);
$avg_party_size_raw = $avg_stmt->fetchColumn();
$avg_party_size = $avg_party_size_raw !== null ? round((float) $avg_party_size_raw, 1) : 0.0;

// Busiest slots ranked
$slots_stmt = $pdo->prepare('
    SELECT ts.start_time, ts.end_time, COUNT(*) AS cnt
    FROM reservations r JOIN time_slots ts ON ts.id = r.time_slot_id
    WHERE r.reservation_date BETWEEN :from AND :to
    GROUP BY r.time_slot_id, ts.start_time, ts.end_time
    ORDER BY cnt DESC
');
$slots_stmt->execute($params_range);
$busiest_slots = $slots_stmt->fetchAll();

$total_in_range_stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_date BETWEEN :from AND :to');
$total_in_range_stmt->execute($params_range);
$total_in_range = (int) $total_in_range_stmt->fetchColumn();

/**
 * Sinh HTML cho MOT hang trong bieu do cot ngang (thanh dai ti le voi $count/$max).
 *
 * Vietnamese: thanh cot render san voi width:0% + data-target-width="<pct>" -
 * public/js/main.js doi sang gia tri that ngay sau khi trang ve xong de
 * transition CSS (.gl-bar-fill trong style.css) tao hieu ung "lon dan tu 0".
 * $index dung de so le (stagger) hieu ung mo dan (fade-in) cua CA HANG qua
 * bien CSS --gl-row-i (xem .gl-bar-row trong style.css).
 */
function bar_row_html(string $label, int $count, int $max, int $index = 0): string
{
    $pct = $max > 0 ? round(($count / $max) * 100) : 0;
    return '<div class="gl-bar-row" style="--gl-row-i: ' . $index . ';">'
        . '<div class="text-truncate">' . e($label) . '</div>'
        . '<div class="gl-bar-track"><div class="gl-bar-fill" style="width:0%" data-target-width="' . $pct . '"></div></div>'
        . '<div class="gl-bar-value">' . e((string) $count) . '</div>'
        . '</div>';
}

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Reports</h1>

<form method="get" action="<?= BASE_URL ?>/admin/reports.php" class="gl-filter-bar js-disable-on-submit row g-3 align-items-end">
    <div class="col-sm-4 col-md-3">
        <label class="form-label" for="from">From date</label>
        <input type="date" class="form-control" id="from" name="from" value="<?= e($date_from) ?>">
    </div>
    <div class="col-sm-4 col-md-3">
        <label class="form-label" for="to">To date</label>
        <input type="date" class="form-control" id="to" name="to" value="<?= e($date_to) ?>">
    </div>
    <div class="col-sm-4 col-md-2">
        <button type="submit" class="btn btn-gl-primary w-100">Apply</button>
    </div>
    <div class="col-md-3 ms-md-auto">
        <a class="btn btn-outline-secondary w-100"
           href="<?= BASE_URL ?>/admin/reports.php?<?= e(http_build_query(['from' => $date_from, 'to' => $date_to, 'export' => 'csv'])) ?>">
            &darr; Export CSV
        </a>
    </div>
</form>

<p class="text-muted"><?= e((string) $total_in_range) ?> booking(s) between <?= e($date_from) ?> and <?= e($date_to) ?>.</p>

<?php if ($total_in_range === 0): ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon"><?= svg_lotus_motif() ?></div>
        <p class="mb-0">No bookings in this date range. Try a wider range.</p>
    </div>
<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Bookings per day</h2>
                    <div class="gl-bar-chart">
                        <?php $max_day = max(array_column($per_day, 'count')) ?: 1; ?>
                        <?php foreach ($per_day as $i => $row): ?>
                            <?= bar_row_html($row['label'], $row['count'], $max_day, $i) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Bookings by status</h2>
                    <div class="gl-bar-chart">
                        <?php $max_status = max(array_column($by_status, 'cnt')) ?: 1; ?>
                        <?php foreach ($by_status as $i => $row): ?>
                            <?= bar_row_html(ucfirst(str_replace('_', ' ', $row['status'])), (int) $row['cnt'], $max_status, $i) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Bookings by area</h2>
                    <div class="gl-bar-chart">
                        <?php $max_area = max(array_column($by_area, 'cnt')) ?: 1; ?>
                        <?php foreach ($by_area as $i => $row): ?>
                            <?= bar_row_html(format_area_label($row['area']), (int) $row['cnt'], $max_area, $i) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Busiest slots (ranked)</h2>
                    <div class="gl-bar-chart">
                        <?php $max_slot = max(array_column($busiest_slots, 'cnt')) ?: 1; ?>
                        <?php foreach ($busiest_slots as $i => $row): ?>
                            <?= bar_row_html(substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5), (int) $row['cnt'], $max_slot, $i) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="gl-tile gl-tile-today h-100">
                <div class="gl-tile-icon"><?= svg_icon_people() ?></div>
                <div class="gl-tile-value"><?= e((string) $avg_party_size) ?></div>
                <div class="gl-tile-label">Average Party Size</div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
