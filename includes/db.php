<?php
/**
 * includes/db.php
 * Tạo kết nối PDO tới MySQL, dùng chung cho toàn bộ ứng dụng.
 * Được include ở đầu mỗi file PHP cần truy vấn database (biến $pdo sẽ có sẵn
 * sau khi require_once file này).
 */

require_once __DIR__ . '/../config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

try {
    // Vietnamese: ERRMODE_EXCEPTION để lỗi query ném exception thay vì âm thầm
    // trả về false; EMULATE_PREPARES=false để MySQL thực thi prepared statement
    // thật (không giả lập ở tầng PHP) — bắt buộc theo quy tắc bảo mật của dự án.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $ex) {
    // Vietnamese: không bao giờ để lộ chi tiết kết nối (host/user/pass) ra
    // ngoài khi APP_DEBUG tắt — chỉ hiện chi tiết lúc đang phát triển.
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die('Loi ket noi CSDL: ' . $ex->getMessage());
    }
    die('He thong dang gap su co, vui long thu lai sau.');
}
