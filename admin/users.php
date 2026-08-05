<?php
/**
 * admin/users.php
 * Quản lý người dùng (FR-14): xem danh sách, tìm kiếm theo tên/email, lọc
 * theo vai trò, khoá/mở khoá tài khoản (is_active), đổi vai trò
 * (customer <-> admin).
 *
 * TU BAO VE (self-protection): mot admin KHONG duoc tu khoa hoac tu ha cap
 * chinh tai khoan dang dang nhap cua minh. Ly do: neu cho phep, mot admin co
 * the vo tinh (hoac bi lua qua CSRF neu ho quen dang xuat may dung chung) tu
 * khoa minh khoi he thong ma KHONG CON AI khac co quyen admin de mo lai -
 * trong pham vi do an nay khong co "super-admin" rieng hay thao tac CSDL thu
 * cong duoc tinh la luong khoi phuc hop le. Kiem tra nay phai o TANG SERVER
 * (khong chi an nut tren giao dien) vi giao dien co the bi bo qua bang cach
 * gui thang mot POST request.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/listing.php';

require_admin();

$script_path   = BASE_URL . '/admin/users.php';
$current_admin = current_user();
$current_id    = (int) $current_admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/admin/users.php');
    }

    $form_action = (string) ($_POST['form_action'] ?? '');
    $target_id   = (int) ($_POST['id'] ?? 0);
    $return_qs   = (string) ($_POST['return_qs'] ?? '');
    $back_to     = '/admin/users.php' . ($return_qs !== '' ? '?' . $return_qs : '');

    // Vietnamese: chan CA HAI hanh dong nhay cam tren CHINH tai khoan dang
    // dang nhap - kiem tra truoc tien, truoc khi dong den logic rieng cua
    // tung hanh dong, de khong the lach qua bang cach nao khac.
    if ($target_id === $current_id) {
        set_flash('danger', 'You cannot change your own role or active status.');
        redirect($back_to);
    }

    if ($form_action === 'toggle_active') {
        $target_active = (int) ($_POST['target_active'] ?? 0);
        $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$target_active, $target_id]);
        set_flash('success', $target_active ? 'Account activated.' : 'Account deactivated.');
        redirect($back_to);
    }

    if ($form_action === 'change_role') {
        $new_role = (string) ($_POST['role'] ?? '');
        if (!in_array($new_role, ['customer', 'admin'], true)) {
            set_flash('danger', 'Invalid role.');
            redirect($back_to);
        }
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$new_role, $target_id]);
        set_flash('success', 'Role updated.');
        redirect($back_to);
    }

    set_flash('danger', 'Unknown action.');
    redirect($back_to);
}

// -----------------------------------------------------------------------
// GET: tim kiem + loc + sap xep + phan trang.
// -----------------------------------------------------------------------
$filter_keyword = trim((string) ($_GET['q'] ?? ''));
$filter_role    = (string) ($_GET['role'] ?? '');
if (!in_array($filter_role, ['customer', 'admin'], true)) {
    $filter_role = '';
}
$filter_active = (string) ($_GET['active'] ?? '');
if (!in_array($filter_active, ['1', '0'], true)) {
    $filter_active = '';
}

$sort_whitelist = [
    'name'    => 'full_name',
    'email'   => 'email',
    'created' => 'created_at',
];
$sort = resolve_sort($sort_whitelist, 'name', 'asc');

$where  = [];
$params = [];
if ($filter_keyword !== '') {
    // Vietnamese: xem giai thich chi tiet ve gioi han placeholder trung ten
    // cua PDO (EMULATE_PREPARES=false) trong admin/bookings.php - ap dung
    // dung nguyen ly do o day.
    $where[]              = '(full_name LIKE :keyword1 OR email LIKE :keyword2)';
    $params[':keyword1']  = '%' . $filter_keyword . '%';
    $params[':keyword2']  = '%' . $filter_keyword . '%';
}
if ($filter_role !== '') {
    $where[]          = 'role = :role';
    $params[':role']  = $filter_role;
}
if ($filter_active !== '') {
    $where[]            = 'is_active = :active';
    $params[':active']  = $filter_active;
}
$where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users {$where_sql}");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, 10, get_current_page());

$list_sql = "
    SELECT id, full_name, email, phone, role, is_active, created_at
    FROM users
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
$users = $list_stmt->fetchAll();

$current_qs        = http_build_query($_GET);
$has_active_filters = $filter_keyword !== '' || $filter_role !== '' || $filter_active !== '';

$page_title = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-4">Manage Users</h1>

<form method="get" action="<?= e($script_path) ?>" class="gl-filter-bar js-auto-submit row g-3 align-items-end">
    <div class="col-sm-5 col-md-4">
        <label class="form-label" for="q">Search</label>
        <input type="text" class="form-control" id="q" name="q" placeholder="Name or email" value="<?= e($filter_keyword) ?>">
    </div>
    <div class="col-sm-3 col-md-3">
        <label class="form-label" for="role">Role</label>
        <select class="form-select" id="role" name="role">
            <option value="">All</option>
            <option value="customer" <?= $filter_role === 'customer' ? 'selected' : '' ?>>Customer</option>
            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
    </div>
    <div class="col-sm-3 col-md-3">
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
    <span class="text-muted"><?= e(showing_range_text($pagination, 'users')) ?></span>
</div>

<?php if (empty($users)): ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon"><?= svg_lotus_motif() ?></div>
        <?php if ($has_active_filters): ?>
            <p class="mb-2">No users match these filters. Try widening the search or clearing a filter.</p>
            <a href="<?= e($script_path) ?>" class="btn btn-outline-secondary btn-sm">Clear filters</a>
        <?php else: ?>
            <p class="mb-0">No users found.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= sort_header_html($script_path, $sort, 'name', 'Name') ?></th>
                    <th><?= sort_header_html($script_path, $sort, 'email', 'Email') ?></th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th><?= sort_header_html($script_path, $sort, 'created', 'Joined') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $is_self = (int) $u['id'] === $current_id; ?>
                    <tr>
                        <td><?= e($u['full_name']) ?><?php if ($is_self): ?> <span class="badge text-bg-light border">You</span><?php endif; ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= $u['phone'] !== null ? e($u['phone']) : '<span class="text-muted">&mdash;</span>' ?></td>
                        <td><span class="badge <?= $u['role'] === 'admin' ? 'text-bg-dark' : 'text-bg-light border' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                        <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                        <td><?= e(substr($u['created_at'], 0, 10)) ?></td>
                        <td class="text-nowrap">
                            <?php if ($is_self): ?>
                                <span class="text-muted small">&mdash;</span>
                            <?php else: ?>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline-flex align-items-center gap-1 js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $u['id']) ?>">
                                    <input type="hidden" name="form_action" value="change_role">
                                    <select class="form-select form-select-sm" name="role" style="width:auto;">
                                        <option value="customer" <?= $u['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm="Change <?= e($u['full_name']) ?>'s role?">Save</button>
                                </form>

                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $u['id']) ?>">
                                    <input type="hidden" name="form_action" value="toggle_active">
                                    <input type="hidden" name="target_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
                                    <?php if ($u['is_active']): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="Deactivate <?= e($u['full_name']) ?>'s account? They will not be able to log in until reactivated.">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                    <?php endif; ?>
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
