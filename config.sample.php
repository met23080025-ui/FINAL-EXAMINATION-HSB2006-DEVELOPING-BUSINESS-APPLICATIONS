<?php
/**
 * config.sample.php
 *
 * Đây là file MẪU cho cấu hình kết nối database.
 * Cách dùng: copy file này thành "config.php" (cùng thư mục), rồi điền
 * thông tin thật của database XAMPP trên máy bạn vào "config.php".
 *
 * File "config.php" đã được thêm vào .gitignore -> sẽ KHÔNG được commit lên GitHub.
 * Chỉ file "config.sample.php" (không chứa mật khẩu thật) mới được đưa lên repo.
 */

// Thông tin kết nối MySQL/MariaDB (mặc định XAMPP: host=localhost, user=root, password rỗng)
define('DB_HOST', 'localhost');
define('DB_NAME', 'golden_lotus');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Bật/tắt chế độ debug: khi true sẽ hiển thị lỗi chi tiết (chỉ dùng khi phát triển, KHÔNG dùng khi nộp bài chạy thật)
define('APP_DEBUG', true);

// Đường dẫn gốc của ứng dụng, dùng cho các thẻ <a href="..."> và redirect (sửa lại nếu tên thư mục XAMPP khác)
define('BASE_URL', '/Cuoiki');
