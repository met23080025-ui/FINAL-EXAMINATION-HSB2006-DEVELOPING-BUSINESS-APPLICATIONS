/**
 * public/js/main.js
 * JavaScript thuần (vanilla JS), dùng cho validate phía client (UX) và các hiệu ứng nhỏ.
 * KHÔNG thay thế cho validate phía server (bắt buộc theo yêu cầu bảo mật).
 */

// Vietnamese: gan class nay NGAY (dong lenh DAU TIEN cua toan bo file, truoc
// ca DOMContentLoaded) - day la "co so" cho MOI mau an-roi-hien-dan trong
// public/css/style.css (.gl-reveal, .gl-table-card, .gl-bar-row,
// .invalid-feedback, .gl-hero-enter...): CSS chi an truoc mot noi dung khi
// <html> co class "js-ready". Neu JS bi tat, script loi tai (404, chan boi
// trinh duyet...), hoac bat ky loi nao xay ra TRUOC dong lenh nay, class do
// khong bao gio xuat hien -> CSS khong an gi ca -> noi dung hien SAN. Day la
// bai hoc rut ra tu su co "the ban tren book.php vo hinh" (2026-08-05, xem
// docs/development-log.md): nguyen nhan that su hom do la mot loi CU PHAP
// CSS (hai gia tri easing trong cung mot khai bao animation) khien opacity:0
// khong bao gio duoc dua ve 1 - khong lien quan JS - nhung quy tac "an mac
// dinh, JS moi duoc THEM hoat hinh" nay van duoc ap dung o day de bat ky loi
// TUONG TU nao trong tuong lai (CSS hay JS) chi lam mat hoat hinh, khong bao
// gio lam mat noi dung.
document.documentElement.classList.add('js-ready');

document.addEventListener('DOMContentLoaded', function () {
    // Vietnamese: doc mot lan, dung lai o nhieu hieu ung ben duoi thay vi goi
    // matchMedia() nhieu lan - true khi nguoi dung da bat "Reduce motion" o
    // he dieu hanh/trinh duyet (ly do tien dinh/vestibular, xem
    // docs/design-process.md muc "Polish pass").
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

    // Vietnamese: form loc (filter) mang class "js-auto-submit" se tu dong
    // gui lai khi mot truong <select>/date/checkbox ben trong no doi gia tri,
    // khong can bam nut "Filter" - tien loi cho admin loc nhanh qua nhieu lua
    // chon. CHI gan vao su kien "change" (kich hoat luc rieng focus / chon
    // xong), KHONG gan vao "input"/"keyup" - neu gan vao "input" thi o
    // <input type="text"> se tu gui sau MOI phim go, "nhot" nguoi dung go
    // ban phim (mat focus giua chung, con tro nhay ve dau) - dung "change"
    // tren the text/date cung chi kich hoat luc blur (roi khoi o), khong
    // phai tung phim go.
    document.querySelectorAll('form.js-auto-submit').forEach(function (form) {
        form.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach(function (field) {
            field.addEventListener('change', function () {
                form.submit();
            });
        });
    });

    // =========================================================================
    // Toast (thong bao flash) - tu dong tat sau 4s kem thanh tien trinh cho
    // success/info/warning; loi (danger) o lai cho den khi nguoi dung tu dong
    // (bam nut dong). Ban than viec luu/lay flash van la session PHP nhu cu
    // (includes/helpers.php) - day CHI la lop trinh bay moi.
    // =========================================================================
    document.querySelectorAll('.gl-toast').forEach(function (toast) {
        var isError = toast.classList.contains('gl-toast-danger');
        var closeBtn = toast.querySelector('.gl-toast-close');
        var progress = toast.querySelector('.gl-toast-progress');
        var dismissTimer = null;

        function dismiss() {
            if (dismissTimer) {
                clearTimeout(dismissTimer);
            }
            if (prefersReducedMotion) {
                toast.remove();
                return;
            }
            toast.classList.add('is-leaving');
            toast.addEventListener('animationend', function () {
                toast.remove();
            }, { once: true });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', dismiss);
        }

        if (!isError) {
            if (progress) {
                progress.classList.add('is-active');
            }
            // Vietnamese: dung setTimeout doc lap voi hoat hinh CSS cua thanh
            // tien trinh - du prefers-reduced-motion lam hoat hinh CSS chay
            // gan-tuc-thi, toast van thuc su bi go sau ~4s dung nhu thiet ke,
            // khong phu thuoc animationend co ban hay khong.
            dismissTimer = setTimeout(dismiss, 4000);
        }
    });

    // =========================================================================
    // Highlight mot lan cho dong dat cho vua tao (tu book.php) hoac vua huy
    // (tu my-reservations.php) sau khi redirect PRG - server gan
    // ?highlight=<id> vao URL dich, JS o day tim dung <tr data-reservation-id>
    // roi xoa tham so khoi URL (history.replaceState) de F5 khong highlight
    // lai. Duoi reduced-motion, CSS da ep animation gan nhu tuc thi (0.01ms)
    // nen hieu ung tu nhien "tat" ma khong can nhanh rieng o day.
    // =========================================================================
    (function highlightRowFromQueryString() {
        var params = new URLSearchParams(window.location.search);
        var highlightId = params.get('highlight');
        if (!highlightId) {
            return;
        }
        var row = document.querySelector('tr[data-reservation-id="' + CSS.escape(highlightId) + '"]');
        if (row) {
            row.classList.add('gl-row-highlight');
        }
        params.delete('highlight');
        var newSearch = params.toString();
        var newUrl = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
        window.history.replaceState(null, '', newUrl);
    })();

    // =========================================================================
    // The chon ban tren book.php - dong bo class .is-selected voi radio dang
    // duoc chon. CSS :has() da tu xu ly viec nay o trinh duyet ho tro, nhung
    // van gan them class bang JS de nhat quan (vd cho cac thao tac JS khac co
    // the doc trang thai qua class thay vi phai tu :has() lai) va lam phuong
    // an du phong ro rang.
    // =========================================================================
    document.querySelectorAll('.gl-table-card').forEach(function (card) {
        var input = card.querySelector('input[type="radio"]');
        if (!input) {
            return;
        }
        input.addEventListener('change', function () {
            document.querySelectorAll('.gl-table-card').forEach(function (c) {
                c.classList.remove('is-selected');
            });
            if (input.checked) {
                card.classList.add('is-selected');
            }
        });
        if (input.checked) {
            card.classList.add('is-selected');
        }
    });

    // =========================================================================
    // Dem so tang dan (count-up) cho 4 o tile tren admin/dashboard.php - CHI
    // trang tri, gia tri cuoi cung LUON la gia tri PHP da render san trong
    // text content (neu JS tat hoac prefers-reduced-motion, so hien nguyen,
    // dung, khong can hoat hinh moi doc duoc).
    // =========================================================================
    if (!prefersReducedMotion) {
        document.querySelectorAll('[data-count-target]').forEach(function (el) {
            var target = parseFloat(el.dataset.countTarget);
            if (isNaN(target)) {
                return;
            }
            var suffix = el.dataset.countSuffix || '';
            var duration = 650;
            var startTime = null;

            function step(timestamp) {
                if (startTime === null) {
                    startTime = timestamp;
                }
                var progress = Math.min((timestamp - startTime) / duration, 1);
                // easeOutCubic - bat dau nhanh, ket thuc muot, khong giat.
                var eased = 1 - Math.pow(1 - progress, 3);
                var current = Math.round(target * eased);
                el.textContent = current + suffix;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    el.textContent = target + suffix;
                }
            }
            window.requestAnimationFrame(step);
        });
    }

    // =========================================================================
    // Bieu do cot (reports.php) - cac thanh duoc PHP render san voi width:0%
    // va data-target-width; sau khi trang ve xong, doi sang gia tri that de
    // transition CSS (public/css/style.css .gl-bar-fill) tao hieu ung "lon
    // dan tu 0". Dung requestAnimationFrame long nhau (double-rAF) de dam bao
    // trinh duyet da ve xong khung hinh dau (width:0%) truoc khi doi sang gia
    // tri moi - neu doi ngay trong cung 1 frame, trinh duyet co the gop hai
    // thay doi lam mot va transition khong chay.
    // =========================================================================
    var bars = document.querySelectorAll('.gl-bar-fill[data-target-width]');
    if (bars.length > 0) {
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                bars.forEach(function (bar) {
                    bar.style.width = bar.dataset.targetWidth + '%';
                });
            });
        });
    }

    // =========================================================================
    // Scroll-reveal cho noi dung duoi man hinh dau (index.php) - IntersectionObserver
    // chi THEM class hien (.is-visible), khong bao gio an noi dung neu trinh
    // duyet khong ho tro API nay (kiem tra 'IntersectionObserver' in window).
    // CSS chi an noi dung khi <html> co class "js-ready" (luon co vi dong
    // dau file gan class do ngay lap tuc) VA phan tu chua nhan ".is-visible" -
    // neu ca doan script nay khong chay duoc vi ly do gi, noi dung se ket
    // thuc o trang thai "an" - vi vay bat buoc kiem tra ho tro truoc khi dung.
    // =========================================================================
    var revealTargets = document.querySelectorAll('.gl-reveal');
    if (revealTargets.length > 0) {
        if ('IntersectionObserver' in window) {
            var revealObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

            revealTargets.forEach(function (el) {
                revealObserver.observe(el);
            });
        } else {
            // Vietnamese: trinh duyet qua cu khong co IntersectionObserver -
            // hien het ngay, khong de noi dung bi ket o trang thai an vinh vien.
            revealTargets.forEach(function (el) {
                el.classList.add('is-visible');
            });
        }
    }
});
