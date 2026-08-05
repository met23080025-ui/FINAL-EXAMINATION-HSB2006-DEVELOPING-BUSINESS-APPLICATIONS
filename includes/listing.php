<?php
/**
 * includes/listing.php
 * Các hàm dùng chung cho MỌI trang danh sách có tìm kiếm/lọc/sắp xếp/phân
 * trang của admin (bookings.php, tables.php, timeslots.php, users.php) —
 * xây một lần ở đây thay vì mỗi trang tự viết lại logic phân trang/sắp xếp
 * riêng (Phase P7, tiêu chí 4 + NFR-05 khả năng bảo trì).
 *
 * QUY TẮC BẢO MẬT QUAN TRỌNG NHẤT của file này: prepared statement (PDO) chỉ
 * bind được GIÁ TRỊ (value) vào chỗ của dấu "?", không thể bind TÊN CỘT
 * (identifier) như "ORDER BY :column" — PDO sẽ coi :column là một chuỗi giá
 * trị rồi đưa vào dấu nháy, gây lỗi SQL chứ không sắp xếp được. Vì vậy tên
 * cột trong ORDER BY bắt buộc phải được nối thẳng vào chuỗi SQL, và điều đó
 * CHỈ an toàn khi giá trị được nối vào đến từ một DANH SÁCH TRẮNG (whitelist)
 * do chính code định nghĩa — không bao giờ lấy thẳng $_GET['sort'] rồi nối
 * vào SQL, vì khi đó người dùng có thể tự ý chèn bất kỳ chuỗi nào (SQL
 * injection qua tên cột thay vì qua giá trị).
 */

require_once __DIR__ . '/helpers.php';

/**
 * Lay so trang hien tai tu query string (?page=), mac dinh la 1, khong bao gio < 1.
 */
function get_current_page(): int
{
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

/**
 * Doi chieu "khoa sap xep" tu URL (vd ?sort=date&dir=desc) sang ten COT SQL
 * THAT su, bang cach tra qua $whitelist (mang ['khoa_url' => 'bieu_thuc_sql']).
 * Neu khoa tren URL khong co trong whitelist (bi sua tay hoac khong hop le),
 * am tham quay ve $default_key thay vi bao loi - trang van hien duoc, chi la
 * khong sap xep theo y do lach cua nguoi dung.
 *
 * @param array<string,string> $whitelist
 * @return array{key:string, column:string, dir:string}
 */
function resolve_sort(array $whitelist, string $default_key, string $default_dir = 'asc'): array
{
    $key = (string) ($_GET['sort'] ?? $default_key);
    if (!array_key_exists($key, $whitelist)) {
        $key = $default_key;
    }

    $dir = strtolower((string) ($_GET['dir'] ?? $default_dir));
    if ($dir !== 'asc' && $dir !== 'desc') {
        $dir = $default_dir;
    }

    return ['key' => $key, 'column' => $whitelist[$key], 'dir' => $dir];
}

/**
 * Tinh cac thong so phan trang (trang hien tai, offset, tong so trang...) tu
 * tong so dong ket qua. $requested_page duoc "ep" ve trong khoang hop le
 * [1, total_pages] - vd ?page=999 tren mot danh sach chi co 3 trang se tu
 * dong hien trang 3 thay vi tra ve mot trang rong hoac loi.
 *
 * @return array{page:int, per_page:int, total_rows:int, total_pages:int, offset:int, from:int, to:int}
 */
function paginate(int $total_rows, int $per_page, int $requested_page): array
{
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page        = min(max(1, $requested_page), $total_pages);
    $offset      = ($page - 1) * $per_page;

    return [
        'page'        => $page,
        'per_page'    => $per_page,
        'total_rows'  => $total_rows,
        'total_pages' => $total_pages,
        'offset'      => $offset,
        'from'        => $total_rows === 0 ? 0 : $offset + 1,
        'to'          => min($offset + $per_page, $total_rows),
    ];
}

/**
 * Ghep query string hien tai ($_GET) voi cac gia tri ghi de trong $overrides,
 * dung de sinh link giu nguyen bo loc dang ap dung (vd link doi trang, link
 * doi cot sap xep) - day la ly do bo loc "song sot" qua phan trang va co the
 * bookmark/chia se duoc (yeu cau cua STEP 1). Gia tri null hoac chuoi rong
 * trong $overrides se XOA tham so do khoi query string thay vi giu chuoi rong.
 *
 * @param array<string, scalar|null> $overrides
 */
function listing_query_string(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

/**
 * Sinh HTML cho MOT tieu de cot co the bam de sap xep, kem mui ten chi
 * chieu dang ap dung (▲/▼) neu cot nay dang la cot sap xep hien tai. Bam lai
 * vao cung cot se DAO CHIEU (asc <-> desc); bam vao cot khac se sap xep theo
 * cot do voi chieu asc truoc. Luon dua ve page=1 vi doi cach sap xep ma van o
 * nguyen trang cu (vd trang 5) de dan den ket qua gay nham lan.
 */
function sort_header_html(string $script_path, array $current_sort, string $key, string $label): string
{
    $next_dir  = ($current_sort['key'] === $key && $current_sort['dir'] === 'asc') ? 'desc' : 'asc';
    $qs        = listing_query_string(['sort' => $key, 'dir' => $next_dir, 'page' => null]);
    $indicator = '';
    if ($current_sort['key'] === $key) {
        $indicator = $current_sort['dir'] === 'asc' ? ' <span aria-hidden="true">&#9650;</span>' : ' <span aria-hidden="true">&#9660;</span>';
    }
    $sr = $current_sort['key'] === $key ? ' <span class="visually-hidden">(sorted ' . ($current_sort['dir'] === 'asc' ? 'ascending' : 'descending') . ')</span>' : '';

    return '<a href="' . e($script_path . '?' . $qs) . '" class="gl-sort-link">' . e($label) . $indicator . $sr . '</a>';
}

/**
 * Sinh HTML thanh phan trang (Prev / danh sach so trang / Next), giu nguyen
 * toan bo bo loc + cach sap xep dang ap dung qua listing_query_string().
 * Khong hien gi neu chi co 1 trang - trang danh sach ngan gon khong can thanh
 * phan trang lam roi giao dien.
 */
function pagination_nav_html(string $script_path, array $pagination): string
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav aria-label="Pagination"><ul class="pagination">';

    $prev_disabled = $pagination['page'] <= 1 ? ' disabled' : '';
    $prev_qs = listing_query_string(['page' => max(1, $pagination['page'] - 1)]);
    $html .= '<li class="page-item' . $prev_disabled . '"><a class="page-link" href="' . e($script_path . '?' . $prev_qs) . '">&laquo; Prev</a></li>';

    for ($p = 1; $p <= $pagination['total_pages']; $p++) {
        $active = $p === $pagination['page'] ? ' active' : '';
        $qs     = listing_query_string(['page' => $p]);
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($script_path . '?' . $qs) . '">' . e((string) $p) . '</a></li>';
    }

    $next_disabled = $pagination['page'] >= $pagination['total_pages'] ? ' disabled' : '';
    $next_qs = listing_query_string(['page' => min($pagination['total_pages'], $pagination['page'] + 1)]);
    $html .= '<li class="page-item' . $next_disabled . '"><a class="page-link" href="' . e($script_path . '?' . $next_qs) . '">Next &raquo;</a></li>';

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Cau chu "Showing X-Y of Z results" (STEP 1) - dung chung cho moi trang danh sach.
 */
function showing_range_text(array $pagination, string $noun = 'results'): string
{
    if ($pagination['total_rows'] === 0) {
        return "Showing 0 {$noun}";
    }
    return "Showing {$pagination['from']}-{$pagination['to']} of {$pagination['total_rows']} {$noun}";
}
