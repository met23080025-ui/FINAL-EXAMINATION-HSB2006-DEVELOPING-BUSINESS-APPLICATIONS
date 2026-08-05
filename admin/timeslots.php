<?php
/**
 * admin/timeslots.php
 * CRUD quản lý khung giờ phục vụ (FR-13): giờ bắt đầu, giờ kết thúc, bật/tắt.
 * Cùng quy tắc xoá-hay-vô-hiệu-hoá như admin/tables.php (xem doc-comment ở
 * đó) - áp dụng lại vì lý do giống hệt: reservations.time_slot_id cũng là
 * khoá ngoại ON DELETE RESTRICT.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reservation.php';
require_once __DIR__ . '/../includes/listing.php';

require_admin();

$script_path = BASE_URL . '/admin/timeslots.php';

/**
 * Kiem tra start_time/end_time hop le va KHONG trung (overlap) voi bat ky
 * khung gio ACTIVE nao khac trong he thong. Overlap xay ra khi khoang
 * [start, end) cua khung gio moi giao voi khoang cua mot khung gio active co
 * san: dieu kien kinh dien "moi.start < cu.end VA moi.end > cu.start".
 *
 * @return array<string,string>
 */
function validate_slot_input(PDO $pdo, string $start, string $end, int $self_id): array
{
    $errors = [];
    $time_pattern = '/^\d{2}:\d{2}$/';

    if (preg_match($time_pattern, $start) !== 1) {
        $errors['start_time'] = 'Please enter a valid start time.';
    }
    if (preg_match($time_pattern, $end) !== 1) {
        $errors['end_time'] = 'Please enter a valid end time.';
    }
    if (!empty($errors)) {
        return $errors;
    }

    if ($end <= $start) {
        $errors['end_time'] = 'End time must be after start time.';
        return $errors;
    }

    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM time_slots
        WHERE is_active = 1 AND id != :self_id
          AND start_time < :new_end AND end_time > :new_start
    ');
    $stmt->execute([':self_id' => $self_id, ':new_end' => $end, ':new_start' => $start]);
    if ((int) $stmt->fetchColumn() > 0) {
        $errors['start_time'] = 'This time range overlaps with an existing active time slot.';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/admin/timeslots.php');
    }

    $form_action = (string) ($_POST['form_action'] ?? '');
    $return_qs   = (string) ($_POST['return_qs'] ?? '');
    $back_to     = '/admin/timeslots.php' . ($return_qs !== '' ? '?' . $return_qs : '');

    if ($form_action === 'create' || $form_action === 'update') {
        $id    = (int) ($_POST['id'] ?? 0);
        $start = trim((string) ($_POST['start_time'] ?? ''));
        $end   = trim((string) ($_POST['end_time'] ?? ''));

        $errors = validate_slot_input($pdo, $start, $end, $id);

        if (!empty($errors)) {
            set_flash('danger', 'Could not save time slot: ' . implode(' ', $errors));
            redirect($back_to);
        }

        if ($form_action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO time_slots (start_time, end_time, is_active) VALUES (?, ?, 1)');
            $stmt->execute([$start, $end]);
            set_flash('success', 'Time slot created.');
        } else {
            $stmt = $pdo->prepare('UPDATE time_slots SET start_time = ?, end_time = ? WHERE id = ?');
            $stmt->execute([$start, $end, $id]);
            set_flash('success', 'Time slot updated.');
        }
        redirect($back_to);
    }

    if ($form_action === 'toggle_active') {
        $id            = (int) ($_POST['id'] ?? 0);
        $target_active = (int) ($_POST['target_active'] ?? 0);
        $stmt = $pdo->prepare('UPDATE time_slots SET is_active = ? WHERE id = ?');
        $stmt->execute([$target_active, $id]);
        set_flash('success', $target_active ? 'Time slot activated.' : 'Time slot deactivated. It will no longer appear in availability search.');
        redirect($back_to);
    }

    if ($form_action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $usage_stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE time_slot_id = ?');
        $usage_stmt->execute([$id]);
        $usage_count = (int) $usage_stmt->fetchColumn();

        if ($usage_count > 0) {
            set_flash('danger', "Cannot delete this time slot: it has {$usage_count} reservation(s) on record. Use Deactivate instead to keep booking history intact.");
        } else {
            $stmt = $pdo->prepare('DELETE FROM time_slots WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'Time slot deleted.');
        }
        redirect($back_to);
    }

    set_flash('danger', 'Unknown action.');
    redirect($back_to);
}

// -----------------------------------------------------------------------
// GET: loc trang thai + sap xep + phan trang.
// -----------------------------------------------------------------------
$filter_active = (string) ($_GET['active'] ?? '');
if (!in_array($filter_active, ['1', '0'], true)) {
    $filter_active = '';
}

$sort_whitelist = ['start' => 'start_time', 'end' => 'end_time'];
$sort = resolve_sort($sort_whitelist, 'start', 'asc');

$where  = [];
$params = [];
if ($filter_active !== '') {
    $where[]            = 'is_active = :active';
    $params[':active']  = $filter_active;
}
$where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM time_slots {$where_sql}");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$pagination = paginate($total_rows, 10, get_current_page());

$list_sql = "
    SELECT ts.id, ts.start_time, ts.end_time, ts.is_active,
           (SELECT COUNT(*) FROM reservations r WHERE r.time_slot_id = ts.id) AS reservation_count
    FROM time_slots ts
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
$slots = $list_stmt->fetchAll();

$current_qs        = http_build_query($_GET);
$has_active_filters = $filter_active !== '';

$page_title = 'Manage Time Slots';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Manage Time Slots</h1>
    <button type="button" class="btn btn-gl-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal">+ Add Time Slot</button>
</div>

<form method="get" action="<?= e($script_path) ?>" class="gl-filter-bar js-auto-submit row g-3 align-items-end">
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
    <span class="text-muted"><?= e(showing_range_text($pagination, 'time slots')) ?></span>
</div>

<?php if (empty($slots)): ?>
    <div class="gl-empty-state">
        <div class="gl-empty-icon">🕒</div>
        <?php if ($has_active_filters): ?>
            <p class="mb-2">No time slots match this filter.</p>
            <a href="<?= e($script_path) ?>" class="btn btn-outline-secondary btn-sm">Clear filters</a>
        <?php else: ?>
            <p class="mb-0">No time slots have been added yet.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th><?= sort_header_html($script_path, $sort, 'start', 'Start') ?></th>
                    <th><?= sort_header_html($script_path, $sort, 'end', 'End') ?></th>
                    <th>Status</th>
                    <th>Reservations</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $s): ?>
                    <tr>
                        <td class="fw-bold"><?= e(substr($s['start_time'], 0, 5)) ?></td>
                        <td><?= e(substr($s['end_time'], 0, 5)) ?></td>
                        <td><?= $s['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                        <td><?= e((string) $s['reservation_count']) ?></td>
                        <td class="text-nowrap">
                            <button
                                type="button" class="btn btn-sm btn-outline-secondary js-edit-slot-btn"
                                data-bs-toggle="modal" data-bs-target="#editSlotModal"
                                data-id="<?= e((string) $s['id']) ?>"
                                data-start="<?= e(substr($s['start_time'], 0, 5)) ?>"
                                data-end="<?= e(substr($s['end_time'], 0, 5)) ?>">
                                Edit
                            </button>

                            <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                <input type="hidden" name="form_action" value="toggle_active">
                                <input type="hidden" name="target_active" value="<?= $s['is_active'] ? '0' : '1' ?>">
                                <?php if ($s['is_active']): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="Deactivate the <?= e(substr($s['start_time'], 0, 5)) ?>-<?= e(substr($s['end_time'], 0, 5)) ?> slot? It will no longer appear in availability search until reactivated.">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                <?php endif; ?>
                            </form>

                            <?php if ((int) $s['reservation_count'] === 0): ?>
                                <form method="post" action="<?= e($script_path) ?>" class="d-inline js-disable-on-submit">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
                                    <input type="hidden" name="id" value="<?= e((string) $s['id']) ?>">
                                    <input type="hidden" name="form_action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Permanently delete this time slot? This cannot be undone.">Delete</button>
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

<!-- Modal: Add Time Slot -->
<div class="modal fade" id="addSlotModal" tabindex="-1" aria-labelledby="addSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= e($script_path) ?>" class="modal-content js-disable-on-submit">
            <?= csrf_field() ?>
            <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
            <input type="hidden" name="form_action" value="create">
            <div class="modal-header">
                <h2 class="modal-title h5" id="addSlotModalLabel">Add Time Slot</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="add_start_time">Start time</label>
                    <input type="time" class="form-control" id="add_start_time" name="start_time" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="add_end_time">End time</label>
                    <input type="time" class="form-control" id="add_end_time" name="end_time" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-gl-primary">Create Slot</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Time Slot -->
<div class="modal fade" id="editSlotModal" tabindex="-1" aria-labelledby="editSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= e($script_path) ?>" class="modal-content js-disable-on-submit">
            <?= csrf_field() ?>
            <input type="hidden" name="return_qs" value="<?= e($current_qs) ?>">
            <input type="hidden" name="form_action" value="update">
            <input type="hidden" name="id" id="edit_slot_id">
            <div class="modal-header">
                <h2 class="modal-title h5" id="editSlotModalLabel">Edit Time Slot</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="edit_start_time">Start time</label>
                    <input type="time" class="form-control" id="edit_start_time" name="start_time" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="edit_end_time">End time</label>
                    <input type="time" class="form-control" id="edit_end_time" name="end_time" required>
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
document.querySelectorAll('.js-edit-slot-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('edit_slot_id').value = btn.dataset.id;
        document.getElementById('edit_start_time').value = btn.dataset.start;
        document.getElementById('edit_end_time').value = btn.dataset.end;
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
