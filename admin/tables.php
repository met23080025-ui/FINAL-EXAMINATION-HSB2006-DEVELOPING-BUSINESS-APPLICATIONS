<?php
/**
 * admin/tables.php
 * CRUD quản lý bàn ăn (FR-12): số bàn, sức chứa, khu vực, trạng thái hoạt
 * động. Thêm "Add table" bằng modal Bootstrap, "Edit" bằng modal dùng lại
 * (điền dữ liệu bằng JS nhỏ đọc data-* của nút bấm), Deactivate/Activate là
 * nút POST 1-click có xác nhận, Delete chỉ cho phép khi bàn CHƯA từng xuất
 * hiện trong bất kỳ đặt chỗ nào.
 *
 * TAI SAO KHONG CHO XOA CUNG (hard delete) BAN DA CO DAT CHO:
 * reservations.table_id la KHOA NGOAI voi ON DELETE RESTRICT (xem
 * database/schema.sql) - ban than MySQL da tu choi neu co dong reservations
 * nao con tro toi ban do (dam bao TOAN VEN THAM CHIEU / referential
 * integrity, khong de lai "dat cho mo côi" tro toi mot ban khong con ton
 * tai). Thay vi de nguoi dung nhan mot loi SQL kho hieu (constraint
 * violation), code o day CHU DONG kiem tra truoc va tra ve thong bao than
 * thien, huong dan dung Deactivate (is_active = 0) - vua giu nguyen lich su
 * dat cho cu, vua loai ban do khoi ket qua tim ban con trong
 * (get_available_tables() da loc is_active = 1).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';
require_once __DIR__ . '/../includes/listing.php';

require_admin();

$script_path = BASE_URL . '/admin/tables.php';
$area_labels = ['indoor_main' => 'Indoor Main', 'terrace' => 'Terrace', 'garden' => 'Garden', 'vip' => 'VIP Room'];
// Vietnamese: mot chu cai in hoa + 2 chu so, khop voi toan bo ma ban hien co
// (T01-T17, V01-V03) va van du tong quat cho cac khu vuc/ban them sau nay.
$table_code_pattern = '/^[A-Z][0-9]{2}$/';

/**
 * Kiem tra du lieu form table_code/capacity/area, tra ve mang loi (rong neu hop le).
 * @return array<string,string>
 */
function validate_table_input(string $table_code, int $capacity, string $area, array $area_labels, string $pattern): array
{
    $errors = [];
    if ($table_code === '' || preg_match($pattern, $table_code) !== 1) {
        $errors['table_code'] = 'Table code must be one uppercase letter followed by two digits (e.g. T05, V02).';
    }
    if ($capacity < 1 || $capacity > 20) {
        $errors['capacity'] = 'Capacity must be between 1 and 20.';
    }
    if (!array_key_exists($area, $area_labels)) {
        $errors['area'] = 'Please choose a valid area.';
    }
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/admin/tables.php');
    }

    $form_action = (string) ($_POST['form_action'] ?? '');
    $return_qs   = (string) ($_POST['return_qs'] ?? '');
    $back_to     = '/admin/tables.php' . ($return_qs !== '' ? '?' . $return_qs : '');

    if ($form_action === 'create' || $form_action === 'update') {
        $id          = (int) ($_POST['id'] ?? 0);
        $table_code  = strtoupper(trim((string) ($_POST['table_code'] ?? '')));
        $capacity    = (int) ($_POST['capacity'] ?? 0);
        $area        = (string) ($_POST['area'] ?? '');

        $errors = validate_table_input($table_code, $capacity, $area, $area_labels, $table_code_pattern);

        if (empty($errors)) {
            $dup_stmt = $pdo->prepare('SELECT id FROM `tables` WHERE table_code = ? AND id != ?');
            $dup_stmt->execute([$table_code, $id]);
            if ($dup_stmt->fetch() !== false) {
                $errors['table_code'] = 'This table code is already in use.';
            }
        }

        if (!empty($errors)) {
            set_flash('danger', 'Could not save table: ' . implode(' ', $errors));
            redirect($back_to);
        }

        if ($form_action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO `tables` (table_code, capacity, area, is_active) VALUES (?, ?, ?, 1)');
            $stmt->execute([$table_code, $capacity, $area]);
            set_flash('success', "Table {$table_code} created.");
        } else {
            $stmt = $pdo->prepare('UPDATE `tables` SET table_code = ?, capacity = ?, area = ? WHERE id = ?');
            $stmt->execute([$table_code, $capacity, $area, $id]);
            set_flash('success', "Table {$table_code} updated.");
        }
        redirect($back_to);
    }

    if ($form_action === 'toggle_active') {
        $id            = (int) ($_POST['id'] ?? 0);
        $target_active = (int) ($_POST['target_active'] ?? 0);
        $stmt = $pdo->prepare('UPDATE `tables` SET is_active = ? WHERE id = ?');
        $stmt->execute([$target_active, $id]);
        set_flash('success', $target_active ? 'Table activated.' : 'Table deactivated. It will no longer appear in availability search.');
        redirect($back_to);
    }

    if ($form_action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $usage_stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE table_id = ?');
        $usage_stmt->execute([$id]);
        $usage_count = (int) $usage_stmt->fetchColumn();

        if ($usage_count > 0) {
            set_flash('danger', "Cannot delete this table: it has {$usage_count} reservation(s) on record. Use Deactivate instead to keep booking history intact.");
        } else {
            $stmt = $pdo->prepare('DELETE FROM `tables` WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Table deleted.');
        }
        redirect($back_to);
    }

    set_flash('danger', 'Unknown action.');
    redirect($back_to);
}

// -----------------------------------------------------------------------
// GET: doc bo loc + sap xep + phan trang (dung chung voi admin/bookings.php).
// -----------------------------------------------------------------------
$filter_keyword = trim((string) ($_GET['q'] ?? ''));
$filter_area    = (string) ($_GET['area'] ?? '');
if (!array_key_exists($filter_area, $area_labels)) {
    $filter_area = '';
}
$filter_active = (string) ($_GET['active'] ?? '');
if (!in_array($filter_active, ['1', '0'], true)) {
    $filter_active = '';
}

$sort_whitelist = [
    'code'     => 'table_code',
    'capacity' => 'capacity',
    'area'     => 'area',
];
$sort = resolve_sort($sort_whitelist, 'code', 'asc');

$where  = [];
$params = [];
if ($filter_keyword !== '') {
    $where[]            = 'table_code LIKE :keyword';
    $params[':keyword'] = '%' . $filter_keyword . '%';
}
if ($filter_area !== '') {
    $where[]          = 'area = :area';
    $params[':area']  = $filter_area;
}
if ($filter_active !== '') {
    $where[]            = 'is_active = :active';
    $params[':active']  = $filter_active;
}
$where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM `tables` {$where_sql}");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, 10, get_current_page());

$list_sql = "
    SELECT t.id, t.table_code, t.capacity, t.area, t.is_active,
           (SELECT COUNT(*) FROM reservations r WHERE r.table_id = t.id) AS reservation_count
    FROM `tables` t
    {$where_sql}
    ORDER BY {$sort['column']} {$sort['dir']}
    LIMIT :limit OFFSET :offset
";
$list_stmt = $pdo->prepare($list_sql);
foreach ($params as $key => $value) {
    $list_stmt->bindValue($key, $value);
}
$list_stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$list_stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$list_stmt->execute();
$tables = $list_stmt->fetchAll();

$current_qs          = http_build_query($_GET);
$has_active_filters   = $filter_keyword !== '' || $filter_area !== '' || $filter_active !== '';

$page_title = 'Manage Tables';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Manage Tables</h1>
    <button type="button" class="btn btn-gl-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">+ Add Table</button>
</div>

<form method="get" action="<?= e($script_path) ?>" class="gl-filter-bar js-auto-submit row g-3 align-items-end">
    <div class="col-sm-4">
        <label class="form-label" for="q">Search code</label>
        <input type="text" class="form-control" id="q" name="q" placeholder="e.g. T05" value="<?= e($filter_keyword) ?>">
    </div>
    <div class="col-sm-4 col-md-3">
        <label class="form-label" for="area">Area</label>
        <select class="form-select" id="area" name="area">
            <option value="">All</option>
            <?php foreach ($area_labels as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $filter_area === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-md-3">
        <label class="form-label" for="active">Status</label>
        <select class="form-select" id="active" name="active">
            <option value="">All</option>
            <option value="1" <?= $filter_active === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $filter_active === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-sm-4 col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filter</button>
    </div>
    <?php if ($has_active_filters): ?>
        <div class="col-12">
            <a href="<?= e($script_path) ?>" class="link-secondary small">&times; Clear filters</a>
        </div>
    <?php endif; ?>
</form>

<div class="gl-results-meta">
    <span class="text-muted"><?= e(showing_range_text($pagination, 'tables')) ?></span>
</div>

<?php if (empty($tables)): ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon">🪑</div>
        <?php if ($has_active_filters): ?>
            <p class="mb-2">No tables match these filters. Try widening the search or clearing a filter.</p>
            <a href="<?= e($script_path) ?>" class="btn btn-outline-secondary btn-sm">Clear filters</a>
        <?php else: ?>
            <p class="mb-0">No tables have been added yet.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= sort_header_html($script_path, $sort, 'code', 'Code') ?></th>
                    <th><?= sort_header_html($script_path, $sort, 'capacity', 'Capacity') ?></th>
                    <th><?= sort_header_html($script_path, $sort, 'area', 'Area') ?></th>
                    <th>Status</th>
                    <th>Reservations</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $t): ?>
                    <tr>
                        <td class="fw-bold"><?= e($t['table_code']) ?></td>
                        <td>Seats <?= e((string) $t['capacity']) ?></td>
                        <td><?= e(format_area_label($t['area'])) ?></td>
                        <td><?= $t['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                        <td><?= e((string) $t['reservation_count']) ?></td>
                        <td class="text-nowrap">
                            <button
                                type="button" class="btn btn-sm btn-outline-secondary js-edit-table-btn"
                                data-bs-toggle="modal" data-bs-target="#editTableModal"
                                data-id="<?= e((string) $t['id']) ?>"
                                data-code="<?= e($t['table_code']) ?>"
                                data-capacity="<?= e((string) $t['capacity']) ?>"
                                data-area="<?= e($t['area']) ?>">
                                Edit
                            </button>

                            <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                                <input type="hidden" name="form_action" value="toggle_active">
                                <input type="hidden" name="target_active" value="<?= $t['is_active'] ? '0' : '1' ?>">
                                <?php if ($t['is_active']): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="Deactivate table <?= e($t['table_code']) ?>? It will no longer appear in availability search until reactivated.">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                <?php endif; ?>
                            </form>

                            <?php if ((int) $t['reservation_count'] === 0): ?>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                                    <input type="hidden" name="form_action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Permanently delete table <?= e($t['table_code']) ?>? This cannot be undone.">Delete</button>
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

<!-- Modal: Add Table -->
<div class="modal fade" id="addTableModal" tabindex="-1" aria-labelledby="addTableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= e($script_path) ?>" class="modal-content js-disable-on-submit">
            <?= csrf_field() ?>
            <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
            <input type="hidden" name="form_action" value="create">
            <div class="modal-header">
                <h2 class="modal-title h5" id="addTableModalLabel">Add Table</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="add_table_code">Table code</label>
                    <input type="text" class="form-control" id="add_table_code" name="table_code" maxlength="10" placeholder="e.g. T05" required>
                    <div class="form-text">One uppercase letter followed by two digits.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="add_capacity">Capacity</label>
                    <input type="number" class="form-control" id="add_capacity" name="capacity" min="1" max="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="add_area">Area</label>
                    <select class="form-select" id="add_area" name="area" required>
                        <?php foreach ($area_labels as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gl-primary">Create Table</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Table (mot modal dung chung, JS ben duoi dien du lieu tu nut Edit duoc bam) -->
<div class="modal fade" id="editTableModal" tabindex="-1" aria-labelledby="editTableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= e($script_path) ?>" class="modal-content js-disable-on-submit">
            <?= csrf_field() ?>
            <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
            <input type="hidden" name="form_action" value="update">
            <input type="hidden" name="id" id="edit_table_id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="editTableModalLabel">Edit Table</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="edit_table_code">Table code</label>
                    <input type="text" class="form-control" id="edit_table_code" name="table_code" maxlength="10" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_capacity">Capacity</label>
                    <input type="number" class="form-control" id="edit_capacity" name="capacity" min="1" max="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_area">Area</label>
                    <select class="form-select" id="edit_area" name="area" required>
                        <?php foreach ($area_labels as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gl-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Vietnamese: dien san du lieu cua dong duoc bam "Edit" vao modal dung chung,
// doc tu cac thuoc tinh data-* cua chinh nut bam (khong goi them request nao) -
// gan truc tiep o day (thay vi main.js) vi day la glue rieng cho cau truc
// modal cua trang nay, khong dung lai o trang khac.
document.querySelectorAll('.js-edit-table-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('edit_table_id').value = btn.dataset.id;
        document.getElementById('edit_table_code').value = btn.dataset.code;
        document.getElementById('edit_capacity').value = btn.dataset.capacity;
        document.getElementById('edit_area').value = btn.dataset.area;
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
