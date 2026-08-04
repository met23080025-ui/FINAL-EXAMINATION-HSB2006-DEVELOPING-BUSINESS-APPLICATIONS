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
