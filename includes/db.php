<?php
/**
 * includes/db.php
 * Tạo kết nối PDO tới MySQL, dùng chung cho toàn bộ ứng dụng.
 * Được include ở đầu mỗi file PHP cần truy vấn database.
 *
 * Sẽ triển khai ở Phase P4b: tạo đối tượng PDO với PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION
 * và bật chế độ prepared statement thật (không emulate) để đảm bảo an toàn.
 */

require_once __DIR__ . '/../config.php';

// TODO (Phase P4b): tạo $pdo = new PDO(...) tại đây và return để các file khác include sử dụng.
