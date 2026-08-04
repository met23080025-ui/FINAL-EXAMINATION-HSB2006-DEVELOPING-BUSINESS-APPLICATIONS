/**
 * public/js/main.js
 * JavaScript thuần (vanilla JS), dùng cho validate phía client (UX) và các hiệu ứng nhỏ.
 * KHÔNG thay thế cho validate phía server (bắt buộc theo yêu cầu bảo mật).
 */

document.addEventListener('DOMContentLoaded', function () {
    // Vietnamese: vo hieu hoa nut submit NGAY khi bam (truoc khi cho phan hoi
    // mang), tranh khach bam hai lan lien tiep tao ra hai request gan nhu
    // trung nhau (vd bam "Confirm Reservation" hai lan). Day CHI la bien phap
    // UX de tranh loi khong ro rang - phong ve THAT SU chong trung dat cho
    // van la rang buoc UNIQUE INDEX cua CSDL (uq_reservations_active_slot,
    // xem includes/reservation.php), rang buoc do van dung ngay ca khi JS bi
    // tat hoac mot request thu hai duoc gui bang cach khac (vd curl).
    document.querySelectorAll('form.js-disable-on-submit').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Submitting...';
            }
        });
    });

    // Vietnamese: bat buoc xac nhan truoc khi thuc hien mot hanh dong khong
    // the hoan tac (Cancel/Reject) - noi dung hoi lay tu thuoc tinh
    // data-confirm cua chinh phan tu duoc bam, theo quy uoc trong
    // docs/design-process.md §7.
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});
