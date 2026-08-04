<?php
/**
 * includes/helpers.php
 * Các hàm dùng chung: validate dữ liệu, hiển thị flash message, sinh/kiểm tra CSRF token,
 * và hàm rút gọn để escape dữ liệu ra HTML (chống XSS).
 */

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    // Vietnamese: khởi động session ở đây vì flash message và CSRF token đều
    // lưu trong $_SESSION — guard bằng session_status() để include nhiều lần
    // (từ các file khác nhau) không bị lỗi "session already started".
    session_start();
}

/**
 * Escape du lieu truoc khi in ra HTML de chong XSS.
 * Luon dung ham nay quanh MOI du lieu dong (tu CSDL hoac tu nguoi dung) khi echo ra trang.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Chuyen huong toi mot duong dan tuong doi trong ung dung (tu dong ghep BASE_URL) roi dung script.
 */
function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Luu mot thong bao flash (hien mot lan roi mat) vao session.
 * $type dung theo mau Bootstrap alert: success | danger | warning | info.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Lay toan bo thong bao flash dang cho va xoa khoi session (chi hien mot lan).
 * @return array<int, array{type: string, message: string}>
 */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Sinh (hoac lay lai) CSRF token cho session hien tai.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Tra ve san mot the <input type="hidden"> chua CSRF token, dan truc tiep vao trong <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Kiem tra CSRF token gui len (thuong tu $_POST['csrf_token']) co khop voi token cua session khong.
 * Dung hash_equals() thay vi === de tranh timing attack.
 */
function csrf_verify(?string $submittedToken): bool
{
    if (empty($_SESSION['csrf_token']) || $submittedToken === null) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Kiem tra dinh dang email hop le (dung cho form dang ky/dang nhap).
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Kiem tra do manh mat khau: toi thieu 8 ky tu, co it nhat 1 chu cai va 1 chu so.
 * Dung cho form dang ky (FR yeu cau "validate email + password strength").
 */
function is_strong_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

/**
 * Kiem tra dinh dang so dien thoai: cho phep dau "+" o dau, con lai la chu
 * so, dau cach hoac dau gach ngang, tong 8-15 chu so sau khi bo ky tu trang
 * tri (phu hop ca so Viet Nam 10 so lan so quoc te co ma vung).
 */
function is_valid_phone(string $phone): bool
{
    $digits_only = preg_replace('/[\s\-]/', '', $phone);
    return preg_match('/^\+?[0-9]{8,15}$/', $digits_only) === 1;
}

/**
 * Kiem tra mot duong dan "redirect" nguoi dung gui len (vd tu
 * auth/login.php?redirect=...) co AN TOAN de dieu huong toi hay khong, tra
 * ve duong dan da kiem chung (van con nguyen, chua ghep BASE_URL) neu hop
 * le, hoac null neu khong hop le/vang mat — noi goi phai tu chon fallback
 * (thuong la trang dashboard theo vai tro) khi ham nay tra ve null.
 *
 * LY DO (giai thich cho vien/viva): day la phong ve chong "open redirect".
 * Neu ung dung dieu huong thang toi bat ky gia tri nao nguoi dung dua vao
 * tham so ?redirect=, ke tan cong co the gui link dang
 * "auth/login.php?redirect=https://evil.example" cho nan nhan; nan nhan thay
 * domain that (goldenlotus) trong thanh dia chi luc dang nhap, dang nhap
 * that, roi bi ung dung TU DONG chuyen sang trang gia mao ngay sau do — nan
 * nhan de mat canh giac hon nhieu so voi bi gui thang link la tu dau vi ho
 * vua tin tuong xong trang that. Day khong phai loi XSS/CSRF ma la loi "tin
 * tuong nham" du lieu do nguoi dung kiem soat (redirect param) de quyet dinh
 * dieu huong sau xac thuc.
 *
 * Chi chap nhan khi CA BON dieu kien sau deu dung (that bai bat ky dieu nao
 * -> coi la khong hop le):
 *   1) Bat dau bang DUY NHAT MOT dau "/" (khong phai "//..." - "//evil.com"
 *      la URL "protocol-relative": trinh duyet tu dien lai bang scheme hien
 *      tai va coi phan sau la MOT HOST KHAC, khong phai duong dan noi bo).
 *   2) Khong chua "://" (loai truong hop nhu "/x://evil.com" co gang lach
 *      qua kiem tra dau tien) va khong bat dau bang mot "scheme:" bat ky
 *      (vd "/javascript:alert(1)").
 *   3) Khong chua ky tu backslash "\" (mot so trinh duyet/thu vien coi "\"
 *      tuong duong "/", nen "/\evil.com" co the bi hieu la "//evil.com" o
 *      tang khac) va khong chua ky tu dieu khien (CR/LF...) de tranh header
 *      injection khi ghep vao "Location:".
 *   4) Sau khi qua 3 buoc tren, duong dan chi con la mot duong dan TUONG DOI
 *      bat dau bang "/", va noi goi (redirect()) luon ghep no SAU BASE_URL
 *      truoc khi dua vao header Location — nen ket qua cuoi cung chac chan
 *      nam trong pham vi ung dung cua chinh minh, khong the "thoat ra" host
 *      hay ung dung khac.
 */
function safe_redirect_target(?string $candidate): ?string
{
    if ($candidate === null || $candidate === '') {
        return null;
    }

    if ($candidate[0] !== '/' || ($candidate[1] ?? '') === '/') {
        return null;
    }

    if (str_contains($candidate, '\\') || str_contains($candidate, '://')) {
        return null;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $candidate) === 1) {
        return null;
    }

    if (preg_match('#^/[a-zA-Z][a-zA-Z0-9+.\-]*:#', $candidate) === 1) {
        return null;
    }

    return $candidate;
}
