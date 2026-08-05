<?php
/**
 * index.php
 * Trang chủ (homepage) của Golden Lotus Restaurant. Đây là điểm vào đầu tiên khi
 * người dùng truy cập ứng dụng: giới thiệu nhà hàng và dẫn tới đăng nhập/đăng ký.
 *
 * "Polish pass": hero dùng gradient nhiều lớp + hoạ tiết hoa sen trang trí +
 * hiệu ứng vào trang (fade/slide-up thuần CSS, không cần JS); khối "Our
 * Areas" trước đây chỉ là một dòng chữ, nay là 4 thẻ khu vực có glyph SVG
 * riêng + hiệu ứng cuộn-hiện-dần (scroll-reveal, IntersectionObserver, xem
 * public/js/main.js) — mặc định HIỆN SẴN nếu JS tắt (không phụ thuộc JS để
 * đọc được nội dung).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

// Vietnamese: xac dinh dich cua nut CTA chinh "Book a Table" theo trang thai
// dang nhap — chua dang nhap thi qua trang login kem ?redirect= de quay lai
// thang trang dat ban sau khi dang nhap thanh cong; da dang nhap la customer
// thi vao thang trang dat ban; la admin thi CTA nay khong phu hop (admin
// khong dat ban) nen dua ve dashboard cua admin.
if (!is_logged_in()) {
    $book_cta_href = BASE_URL . '/auth/login.php?redirect=' . urlencode('/customer/book.php');
} elseif (current_user()['role'] === 'admin') {
    $book_cta_href = BASE_URL . '/admin/dashboard.php';
} else {
    $book_cta_href = BASE_URL . '/customer/book.php';
}

$areas = [
    ['label' => 'Indoor Main', 'desc' => '8 tables &middot; seats 2-4', 'icon' => svg_icon_chair()],
    ['label' => 'Terrace',     'desc' => '5 tables &middot; seats 4-6', 'icon' => svg_icon_umbrella()],
    ['label' => 'Garden',      'desc' => '4 tables &middot; seats 6-8', 'icon' => svg_icon_leaf()],
    ['label' => 'VIP Room',    'desc' => '3 rooms &middot; seats 8-12', 'icon' => svg_icon_crown()],
];

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<?php
/*
 * Vietnamese: KIEM CHUNG TUONG PHAN cho chu trang tren --gl-grad-hero
 * (docs/design-process.md muc "Polish pass" co bang day du) - lop linear
 * cua gradient chay tu #0e7048 (nhat nhat) den #073c26 (dam nhat); trang mau
 * trang tren diem NHAT NHAT cua dai do (#0e7048) da dat 6.12:1, vuot nguong
 * AA 4.5:1 - nen bat ky vi tri nao chu roi vao doc theo dai linear deu an
 * toan. Lop radial "hao quang" vang chi dat o goc TREN-PHAI (88% 8%) va noi
 * dung chu (.gl-hero-content) nam ben TRAI, khong giao voi vung do, nen
 * khong lam giam ty le tuong phan da tinh o tren.
 */
?>
<div class="gl-hero mb-5">
    <?= svg_lotus_motif('gl-hero-motif') ?>
    <div class="gl-hero-content">
        <h1 class="gl-hero-enter mb-4">Reserve your table for authentic Vietnamese dining in seconds.</h1>

        <?php // Vietnamese: mot CTA chinh duy nhat (§1 "one primary action per screen") ?>
        <a class="btn btn-light btn-lg px-5 gl-hero-enter gl-hero-enter-delay-1" href="<?= e($book_cta_href) ?>">Book a Table</a>

        <?php if (!is_logged_in()): ?>
            <?php // Vietnamese: Login/Register van hien nhung o dang phu (link nho hon, khong phai nut lon) ?>
            <div class="mt-3 gl-hero-enter gl-hero-enter-delay-2">
                <a class="link-light small me-3" href="<?= BASE_URL ?>/auth/login.php">Login</a>
                <a class="link-light small" href="<?= BASE_URL ?>/auth/register.php">Register</a>
            </div>
        <?php else: ?>
            <p class="mt-3 mb-0 gl-hero-enter gl-hero-enter-delay-2">Welcome back, <?= e(current_user()['full_name']) ?>!</p>
        <?php endif; ?>
    </div>
</div>

<div class="gl-info-band">
    <div class="text-center mb-5 gl-reveal">
        <h3 class="mb-1">Opening Hours</h3>
        <p class="mb-0 text-muted">Daily, 11:00&ndash;22:00</p>
    </div>

    <h2 class="h3 text-center mb-4 gl-reveal">Our Areas</h2>
    <div class="row g-4">
        <?php foreach ($areas as $i => $area): ?>
            <div class="col-sm-6 col-lg-3 gl-reveal" style="transition-delay: <?= e((string) ($i * 80)) ?>ms;">
                <div class="gl-area-card text-center">
                    <div class="gl-area-card-icon mx-auto">
                        <?= $area['icon'] ?>
                    </div>
                    <h3 class="h5 mb-1"><?= e($area['label']) ?></h3>
                    <p class="text-muted small mb-0"><?= $area['desc'] ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
require __DIR__ . '/includes/footer.php';
