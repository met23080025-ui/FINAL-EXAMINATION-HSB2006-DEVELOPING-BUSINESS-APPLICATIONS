<?php
/**
 * customer/my-bookings.php
 * Xem lịch sử đặt bàn của chính khách hàng, có thể lọc theo trạng thái
 * (pending/confirmed/completed/no_show/cancelled/rejected) và huỷ đặt bàn
 * (chỉ khi trạng thái còn là pending hoặc confirmed).
 *
 * Sẽ triển khai ở Phase P6.
 */

// TODO (Phase P6): require_login(); SELECT bookings theo user_id + xử lý huỷ (cancel).
