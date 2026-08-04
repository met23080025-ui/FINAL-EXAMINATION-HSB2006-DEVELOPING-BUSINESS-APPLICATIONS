<?php
/**
 * includes/auth.php
 * Chứa các hàm kiểm tra đăng nhập và phân quyền (customer / admin) — "role
 * middleware" của Phase P4b. Được include ở đầu các trang cần bảo vệ (VD:
 * /admin/*.php phải gọi require_admin() trước khi in bất kỳ HTML nào).
 *
 * Luồng đăng nhập thật (kiểm tra password_hash, ghi $_SESSION['user']) sẽ do
 * auth/login.php triển khai ở Phase P5 — các hàm ở đây chỉ ĐỌC session, nên
 * có thể xây dựng và test song song, không phụ thuộc P5 phải xong trước.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Tra ve thong tin nguoi dung dang dang nhap (id, full_name, email, role),
 * hoac null neu chua dang nhap. Du lieu nay duoc auth/login.php ghi vao
 * $_SESSION['user'] ngay sau khi password_verify() thanh cong.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Chan trang neu chua dang nhap: luu flash message va chuyen huong ve trang dang nhap.
 * Goi ham nay o dong dau tien (truoc bat ky output nao) cua moi trang can dang nhap.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Vui long dang nhap de tiep tuc.');
        redirect('/auth/login.php');
    }
}

/**
 * Chan trang neu khong phai admin: bat buoc da dang nhap (require_login()) va
 * co role = 'admin', neu khong se chuyen huong ve trang chu voi thong bao loi.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Ban khong co quyen truy cap trang nay.');
        redirect('/index.php');
    }
}
