<?php
/**
 * includes/reservation.php
 * Các hàm lõi của nghiệp vụ đặt bàn (business workflow) — phần quan trọng
 * nhất của đồ án (tiêu chí 4, 45 điểm). Mọi trang (customer/book.php,
 * customer/my-reservations.php, admin/bookings.php) đều đi qua các hàm ở đây
 * thay vì tự viết SQL riêng, để logic nghiệp vụ chỉ tồn tại ở MỘT nơi.
 *
 * $pdo được truyền vào làm tham số đầu tiên của mỗi hàm (thay vì dùng
 * "global $pdo") để lời gọi hàm luôn rõ ràng nó cần một kết nối CSDL.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Doi gia tri ENUM `area` trong CSDL (vd 'indoor_main') sang nhan hien thi
 * cho nguoi dung (vd "Indoor Main") - tach rieng khoi du lieu CSDL de dang
 * doi cach hien thi ma khong dung den gia tri luu tru.
 */
function format_area_label(string $area): string
{
    $labels = [
        'indoor_main' => 'Indoor Main',
        'terrace'     => 'Terrace',
        'garden'      => 'Garden',
        'vip'         => 'VIP Room',
    ];

    return $labels[$area] ?? $area;
}

/**
 * Tra ve HTML cua mot badge Bootstrap cho trang thai dat cho, dung DUNG mot
 * bang mau -> trang thai o MOT noi (xem docs/design-process.md §5.1) de moi
 * man hinh hien thi trang thai (my-reservations.php, admin/bookings.php) deu
 * dong nhat, khong tu chon mau rieng.
 *
 * no_show dung class rieng .badge-status-no-show (vien do, nen trang, dinh
 * nghia trong public/css/style.css) thay vi text-bg-danger nhu 'rejected' -
 * ca hai deu la "xau" ve mat nghiep vu nhung phai phan biet duoc bang mat
 * thuong tren cung mot cot trang thai (§5.1 giai thich ly do).
 */
function status_badge_html(string $status): string
{
    $bootstrap_class_by_status = [
        'pending'   => 'text-bg-warning',
        'confirmed' => 'text-bg-success',
        'completed' => 'text-bg-info',
        'cancelled' => 'text-bg-secondary',
        'rejected'  => 'text-bg-danger',
    ];

    $label = ucfirst(str_replace('_', ' ', $status));

    if ($status === 'no_show') {
        return '<span class="badge badge-status-no-show">' . e($label) . '</span>';
    }

    $class = $bootstrap_class_by_status[$status] ?? 'text-bg-secondary';
    return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
}

/**
 * Tim cac ban con trong cho mot (ngay, khung gio, so khach): con active,
 * du suc chua, va KHONG co dat cho nao dang "con hieu luc" (pending/confirmed)
 * trung dung (table_id, date, slot). Sap xep capacity TANG DAN de uu tien
 * ban vua du cho khach truoc - giu lai cac ban lon (VIP 8-12 cho) cho nhung
 * nhom dong hon thay vi de mot khach le/nhom nho chiem mat mot ban lon, dung
 * dung yeu cau nghiep vu da khoa trong CLAUDE.md ("prefer the smallest
 * sufficient table so large tables stay free for large parties").
 *
 * @return array<int, array{id:int, table_code:string, capacity:int, area:string}>
 */
function get_available_tables(PDO $pdo, string $date, int $time_slot_id, int $party_size): array
{
    // Vietnamese: dung NOT EXISTS tuong minh (thay vi dua vao cot sinh
    // active_slot_key) vi active_slot_key chi phuc vu rang buoc UNIQUE chong
    // trung o tang CSDL (xem database/schema.sql) - o day can mot dieu kien
    // ro rang, de doc, khong phu thuoc vao cach cot do duoc sinh ra.
    $sql = '
        SELECT t.id, t.table_code, t.capacity, t.area
        FROM `tables` t
        WHERE t.is_active = 1
          AND t.capacity >= :party_size
          AND NOT EXISTS (
              SELECT 1 FROM reservations r
              WHERE r.table_id = t.id
                AND r.reservation_date = :res_date
                AND r.time_slot_id = :time_slot_id
                AND r.status IN (\'pending\', \'confirmed\')
          )
        ORDER BY t.capacity ASC, t.table_code ASC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':party_size'    => $party_size,
        ':res_date'      => $date,
        ':time_slot_id'  => $time_slot_id,
    ]);
    return $stmt->fetchAll();
}

/**
 * Kiem tra mot (ngay, khung gio) co con dat duoc khong: khong duoc trong qua
 * khu, khong duoc qua 30 ngay toi, va neu la HOM NAY thi khung gio phai chua
 * bat dau. LUON dung gio server (new DateTimeImmutable('now')) - khong bao
 * gio tin ngay/gio ma client gui len, vi client co the sua dong ho may hoac
 * gui thang gia tri sai qua form.
 *
 * @return array{ok: bool, message: ?string}
 */
function is_slot_bookable(PDO $pdo, string $date, int $time_slot_id): array
{
    $today = new DateTimeImmutable('today');

    $target_date = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($target_date === false || $target_date->format('Y-m-d') !== $date) {
        return ['ok' => false, 'message' => 'Invalid date.'];
    }

    if ($target_date < $today) {
        return ['ok' => false, 'message' => 'You cannot book a date in the past.'];
    }

    if ($target_date > $today->modify('+30 days')) {
        return ['ok' => false, 'message' => 'Bookings are only allowed up to 30 days in advance.'];
    }

    $stmt = $pdo->prepare('SELECT start_time FROM time_slots WHERE id = ? AND is_active = 1');
    $stmt->execute([$time_slot_id]);
    $slot = $stmt->fetch();
    if ($slot === false) {
        return ['ok' => false, 'message' => 'This time slot is not available.'];
    }

    // Vietnamese: chi can kiem tra "khung gio da bat dau chua" khi ngay dat la
    // HOM NAY - cac ngay trong tuong lai thi khung gio nao cung con hop le.
    if ($target_date->format('Y-m-d') === $today->format('Y-m-d')) {
        $slot_start = new DateTimeImmutable($today->format('Y-m-d') . ' ' . $slot['start_time']);
        if ($slot_start <= new DateTimeImmutable('now')) {
            return ['ok' => false, 'message' => 'This time slot has already started or passed today. Please choose a later slot or another date.'];
        }
    }

    return ['ok' => true, 'message' => null];
}

/**
 * Tao mot dat cho moi (status = 'pending'), boc trong transaction.
 *
 * Day la noi xu ly RACE CONDITION kinh dien cua he thong dat cho: hai khach
 * cung goi get_available_tables() gan nhu cung luc, ca hai deu thay CUNG MOT
 * ban con trong (vi luc do chua ai INSERT ca), roi ca hai cung bam "Confirm"
 * cach nhau vai chuc mili-giay. Kiem tra "con trong khong" o tang PHP luc
 * nay da LOI THOI - khong the sua duoc bang cach kiem tra lai truoc khi
 * INSERT (van co the co mot request khac chen vao giua "kiem tra" va "ghi").
 * Chi UNIQUE INDEX cua CSDL (uq_reservations_active_slot, xem
 * database/schema.sql) moi "trong tai" duoc: CA HAI INSERT cung gui xuong,
 * MySQL dam bao chi mot cai thanh cong - cai con lai nhan loi SQLSTATE 23000
 * / ma loi 1062 (duplicate entry). Ham nay bat loi do va tra ve mot thong
 * bao than thien thay vi de loi 500 lo ra ngoai.
 *
 * @return array{ok: bool, message: string, reservation_id: ?int}
 */
function create_reservation(
    PDO $pdo,
    int $user_id,
    int $table_id,
    int $time_slot_id,
    string $date,
    int $party_size,
    ?string $notes
): array {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO reservations (user_id, table_id, time_slot_id, reservation_date, party_size, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$user_id, $table_id, $time_slot_id, $date, $party_size, $notes]);
        $reservation_id = (int) $pdo->lastInsertId();
        $pdo->commit();

        return [
            'ok'             => true,
            'message'        => 'Your reservation has been submitted and is pending approval.',
            'reservation_id' => $reservation_id,
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();

        // Vietnamese: SQLSTATE 23000 = "Integrity constraint violation" (bao gom
        // ca UNIQUE lan FOREIGN KEY) - kiem them ma loi MySQL cu the 1062
        // (duplicate entry) de chac chan day la do trung uq_reservations_active_slot,
        // khong phai mot loi rang buoc khac bi nham lan.
        if ($e->getCode() === '23000' && str_contains($e->getMessage(), '1062')) {
            return [
                'ok'             => false,
                'message'        => 'Sorry, that table was just taken for this date and time. Please choose another table.',
                'reservation_id' => null,
            ];
        }

        throw $e;
    }
}

/**
 * BANG DUY NHAT dinh nghia vong doi trang thai dat cho (status lifecycle,
 * da khoa trong CLAUDE.md). Moi noi trong ung dung muon doi trang thai PHAI
 * goi qua change_reservation_status() (va vi vay di qua ham nay) thay vi tu
 * UPDATE status truc tiep - tranh tinh trang hai cho trong code dinh nghia
 * hai luat chuyen trang thai khac nhau roi lech nhau dan.
 *
 * pending -> confirmed | rejected | cancelled
 * confirmed -> completed | no_show | cancelled
 * completed | no_show | cancelled | rejected -> (khong con chuyen di dau duoc nua - trang thai cuoi)
 */
function can_transition(string $from_status, string $to_status): bool
{
    $allowed = [
        'pending'   => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['completed', 'no_show', 'cancelled'],
        'completed' => [],
        'no_show'   => [],
        'cancelled' => [],
        'rejected'  => [],
    ];

    return in_array($to_status, $allowed[$from_status] ?? [], true);
}

/**
 * Doi trang thai mot dat cho, co kiem tra qua can_transition(), ghi lai ai
 * thao tac (actioned_by) va luc nao (actioned_at), boc trong transaction.
 *
 * Dung "SELECT ... FOR UPDATE" de khoa dong ngay hang can doi trong luc
 * transaction dang mo - tranh truong hop hai thao tac cung doi trang thai
 * MOT reservation gan nhu cung luc (vd admin bam Approve dung luc customer
 * bam Cancel) deu doc thay status CU roi cung ghi de dua tren du lieu da
 * loi thoi; FOR UPDATE bat request thu hai phai doi den khi request dau
 * COMMIT/ROLLBACK xong moi duoc doc, nen luon thay du lieu moi nhat.
 *
 * @return array{ok: bool, message: string}
 */
function change_reservation_status(PDO $pdo, int $reservation_id, string $to_status, int $actioned_by_user_id): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status FROM reservations WHERE id = ? FOR UPDATE');
        $stmt->execute([$reservation_id]);
        $current_status = $stmt->fetchColumn();

        if ($current_status === false) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Reservation not found.'];
        }

        if (!can_transition($current_status, $to_status)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => "Cannot change status from '{$current_status}' to '{$to_status}'."];
        }

        $update = $pdo->prepare(
            'UPDATE reservations SET status = ?, actioned_by = ?, actioned_at = NOW() WHERE id = ?'
        );
        $update->execute([$to_status, $actioned_by_user_id, $reservation_id]);
        $pdo->commit();

        return ['ok' => true, 'message' => 'Reservation status updated.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
