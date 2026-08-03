<?php
/**
 * customer/book.php
 * Trang đặt bàn: khách chọn ngày + khung giờ + số người -> hệ thống lọc ra các bàn
 * còn đủ chỗ trống và chưa bị trùng lịch trong khung giờ đó -> khách chọn bàn và xác nhận.
 * Đây là màn hình trung tâm của luồng nghiệp vụ (business workflow), phải chống trùng lịch
 * (double-booking) cả ở tầng PHP lẫn ràng buộc UNIQUE/constraint trong database.
 *
 * Sẽ triển khai ở Phase P6.
 */

// TODO (Phase P6): require_login(); xử lý tìm bàn trống + tạo booking (status = 'pending').
