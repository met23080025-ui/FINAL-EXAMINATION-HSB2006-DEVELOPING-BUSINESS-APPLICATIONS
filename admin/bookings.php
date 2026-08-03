<?php
/**
 * admin/bookings.php
 * Danh sách toàn bộ đặt bàn: tìm kiếm + lọc (ngày, trạng thái, tên khách) + sắp xếp
 * (giờ đặt, ngày tạo) + phân trang. Admin duyệt (confirmed) / từ chối (rejected) đặt bàn
 * đang pending, và đánh dấu completed / no_show sau giờ đặt.
 *
 * Sẽ triển khai ở Phase P6/P7.
 */

// TODO (Phase P6/P7): require_admin(); SELECT có WHERE/ORDER BY/LIMIT động + UPDATE trạng thái.
