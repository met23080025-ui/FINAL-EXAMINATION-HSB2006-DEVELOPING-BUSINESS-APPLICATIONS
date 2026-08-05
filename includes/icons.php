<?php
/**
 * includes/icons.php
 * Thư viện icon SVG nội tuyến (inline), tự vẽ tay bằng <path>/<circle> — KHÔNG
 * dùng bất kỳ thư viện icon hay font-icon ngoài nào (đúng yêu cầu "no icon
 * libraries" của đợt nâng cấp UI). Mỗi hàm trả về một chuỗi HTML SVG "tin cậy"
 * (hard-code sẵn trong file này, không bao giờ nhận dữ liệu người dùng), nên
 * được echo thẳng ra trang mà KHÔNG cần bọc qua e() — khác với dữ liệu động
 * (tên khách, ghi chú...) vốn luôn phải qua e() như mọi nơi khác trong dự án.
 *
 * Toàn bộ icon dùng style "stroke line-art" (fill="none", stroke="currentColor")
 * để tự động ăn theo màu chữ/màu token CSS của phần tử cha (vd icon trong nút
 * màu trắng, icon trong thẻ tile màu --gl-primary) mà không cần khai báo màu
 * riêng ở từng noi goi. aria-hidden="true" vi day la hinh trang tri, khong
 * mang thong tin - vien nen doc noi dung that (label, so lieu) tu text/aria-label
 * dat ben canh icon, khong phai tu SVG.
 */

/**
 * Hoa tiet hoa sen (lotus motif) — bieu tuong nhan dien rieng cua du an, dung
 * lam diem nhan trang tri (goc hero, panel dang nhap, empty state, footer).
 * 5 canh hoa doi xung (dung <path> giong het nhau, xoay bang transform="rotate"
 * quanh cung mot tam) + hai duong gon song o day, tat ca la duong net (stroke),
 * khong to mau (fill="none") de hop voi tinh than "line-art" toi gian.
 */
function svg_lotus_motif(string $extra_class = '', string $width = '100%'): string
{
    $class = trim('gl-lotus-motif ' . $extra_class);
    return <<<SVG
    <svg class="{$class}" width="{$width}" viewBox="0 0 100 90" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <g>
            <path d="M50,75 C35,60 35,35 50,18 C65,35 65,60 50,75 Z" transform="rotate(-60 50 75)"/>
            <path d="M50,75 C35,60 35,35 50,18 C65,35 65,60 50,75 Z" transform="rotate(-30 50 75)"/>
            <path d="M50,75 C35,60 35,35 50,18 C65,35 65,60 50,75 Z"/>
            <path d="M50,75 C35,60 35,35 50,18 C65,35 65,60 50,75 Z" transform="rotate(30 50 75)"/>
            <path d="M50,75 C35,60 35,35 50,18 C65,35 65,60 50,75 Z" transform="rotate(60 50 75)"/>
        </g>
        <path d="M18,80 Q50,90 82,80"/>
        <path d="M27,85 Q50,92 73,85" opacity="0.55"/>
    </svg>
    SVG;
}

/**
 * Icon khu vuc "Indoor Main" — ghe an don gian.
 */
function svg_icon_chair(): string
{
    return '<svg class="gl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M6.5 10V6a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v4"/>'
        . '<path d="M4.5 10h15v4.5h-15z"/>'
        . '<path d="M6.5 14.5V21M17.5 14.5V21"/>'
        . '</svg>';
}

/**
 * Icon khu vuc "Terrace" — o dua ngoai troi.
 */
function svg_icon_umbrella(): string
{
    return '<svg class="gl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M3.5 11C3.5 6.5 7.5 3.5 12 3.5S20.5 6.5 20.5 11z"/>'
        . '<path d="M12 11v8a2 2 0 0 1-2 2"/>'
        . '<path d="M12 3.5V2"/>'
        . '</svg>';
}

/**
 * Icon khu vuc "Garden" — chiec la.
 */
function svg_icon_leaf(): string
{
    return '<svg class="gl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M4.5 19.5C4.5 10 12 4.5 19.5 4.5c0 7.5-5.5 15-15 15z"/>'
        . '<path d="M4.5 19.5 15 9"/>'
        . '</svg>';
}

/**
 * Icon khu vuc "VIP Room" — vuong mien.
 */
function svg_icon_crown(): string
{
    return '<svg class="gl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M4 18.5h16l-1.2-9-4.3 4.3L12 7l-2.5 6.8-4.3-4.3z"/>'
        . '<path d="M4 18.5h16v2.2H4z"/>'
        . '</svg>';
}

/**
 * Dau check nho, dung cho trang thai "da chon" (vd the ban trong book.php).
 */
function svg_icon_check(): string
{
    return '<svg class="gl-icon gl-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M5 12.5l4.5 4.5L19 7"/>'
        . '</svg>';
}

/**
 * Icon lich — tile "Today's Bookings" tren admin dashboard.
 */
function svg_icon_calendar(): string
{
    return '<svg class="gl-icon gl-tile-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<rect x="3.5" y="5.5" width="17" height="15" rx="2"/>'
        . '<path d="M3.5 9.5h17M8 3.5v4M16 3.5v4"/>'
        . '</svg>';
}

/**
 * Icon dong ho cat - tile "Pending Approval".
 */
function svg_icon_hourglass(): string
{
    return '<svg class="gl-icon gl-tile-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M6.5 3.5h11M6.5 20.5h11"/>'
        . '<path d="M7.5 3.5c0 5 4.5 5.5 4.5 8.5s-4.5 3.5-4.5 8.5M16.5 3.5c0 5-4.5 5.5-4.5 8.5s4.5 3.5 4.5 8.5"/>'
        . '</svg>';
}

/**
 * Icon vong tron gach cheo - tile "Cancellation Rate".
 */
function svg_icon_cancel_circle(): string
{
    return '<svg class="gl-icon gl-tile-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<circle cx="12" cy="12" r="8.5"/>'
        . '<path d="M8.8 8.8l6.4 6.4M15.2 8.8l-6.4 6.4"/>'
        . '</svg>';
}

/**
 * Icon ngon lua - tile "Busiest Slot".
 */
function svg_icon_flame(): string
{
    return '<svg class="gl-icon gl-tile-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M12 3c1.8 2.6-1.6 3.6-1.6 6.2a3.6 3.6 0 1 0 7.2 0c0-1.7-1.6-2.6-1.6-4.3 1.8.9 3.6 3.4 3.6 6.1a5.6 5.6 0 1 1-11.2 0C8.4 7.4 10 5 12 3z"/>'
        . '</svg>';
}

/**
 * Icon ban an don gian (mat ban + 2 chan) - dung canh nhan "Table" o cac the
 * tom tat dat cho (vd the "next reservation" tren customer/dashboard.php).
 */
function svg_icon_table(): string
{
    return '<svg class="gl-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M3.5 8.5l2.5-5h12l2.5 5"/>'
        . '<path d="M3.5 8.5h17"/>'
        . '<path d="M6 8.5v11.5M18 8.5v11.5"/>'
        . '</svg>';
}

/**
 * Icon nhom nguoi - tile/o "Average Party Size" tren reports.php.
 */
function svg_icon_people(): string
{
    return '<svg class="gl-icon gl-tile-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<circle cx="9" cy="8" r="3"/>'
        . '<path d="M2.5 20c0-4 3-6.5 6.5-6.5S15.5 16 15.5 20"/>'
        . '<circle cx="17" cy="9" r="2.3"/>'
        . '<path d="M21.5 20c0-3-1.8-5.2-4.3-5.7"/>'
        . '</svg>';
}
