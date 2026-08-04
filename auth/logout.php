<?php
/**
 * auth/logout.php
 * Huỷ session hiện tại và chuyển hướng người dùng về trang chủ kèm flash message.
 */

require_once __DIR__ . '/../includes/auth.php';

// Vietnamese: xoa toan bo du lieu session (bao gom $_SESSION['user']) va huy
// cookie session phia trinh duyet, sau do session_destroy() xoa du lieu phia
// server. Khong dung redirect() ngay sau day vi ham do can mot session moi
// (con hieu luc) de ghi flash message hien "da dang xuat" cho trang ke tiep.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Vietnamese: mo mot session HOAN TOAN MOI (session_start lai roi regenerate ID)
// chi de mang duoc mot flash message sang trang chu - session nay khong con
// $_SESSION['user'] nao ca, nguoi dung thuc su da dang xuat.
session_start();
session_regenerate_id(true);

set_flash('success', 'You have been logged out.');
redirect('/index.php');
