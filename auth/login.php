<?php
/**
 * auth/login.php
 * Trang đăng nhập. Kiểm tra email/mật khẩu bằng password_verify(), sau khi đăng nhập
 * thành công phải gọi session_regenerate_id(true) để chống session fixation.
 *
 * Hỗ trợ tham số ?redirect= (do index.php gắn vào nút "Book a Table" khi
 * khách chưa đăng nhập) để quay lại đúng trang khách định vào sau khi xác
 * thực xong — LUÔN đi qua safe_redirect_target() trước khi dùng, xem lý do
 * chi tiết (chống open-redirect) trong comment của hàm đó ở includes/helpers.php.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Vietnamese: doc tham so redirect tu GET (lan dau vao trang) hoac POST (form
// da nhung lai qua hidden field) roi kiem chung ngay - $safe_redirect tu day
// tro di la gia tri DA duoc xac minh an toan, hoac null neu khong co/khong hop le.
$redirect_param = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['redirect'] ?? null)
    : ($_GET['redirect'] ?? null);
$safe_redirect = safe_redirect_target($redirect_param);

// Vietnamese: da dang nhap roi thi khong can dang nhap lai - dua thang toi
// dich da yeu cau (neu hop le) hoac dashboard theo vai tro.
if (is_logged_in()) {
    $role_default = current_user()['role'] === 'admin' ? '/admin/dashboard.php' : '/customer/dashboard.php';
    redirect($safe_redirect ?? $role_default);
}

$errors = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/auth/login.php');
    }

    $old_email = trim((string) ($_POST['email'] ?? ''));
    $password  = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$old_email]);
    $user = $stmt->fetch();

    // Vietnamese: van goi password_verify() du khong tim thay user (voi mot hash gia dinh dang
    // bcrypt hop le), de thoi gian xu ly ca hai nhanh (email sai / mat khau sai) gan bang nhau -
    // tranh ke tan cong do email nao ton tai trong he thong qua chenh lech thoi gian phan hoi.
    $hash_to_check = $user !== false ? $user['password_hash'] : '$2y$10$invalidinvalidinvalidinvalidinvalidu';
    $password_ok   = password_verify($password, $hash_to_check);

    // Vietnamese: dung CHUNG MOT thong bao cho ca "email khong ton tai" va "sai mat khau" -
    // neu tach rieng, ke tan cong co the do tung email de biet email nao DA dang ky trong he
    // thong (user enumeration) du khong dang nhap duoc. Thong bao chung khien viec do vo nghia.
    if ($user === false || !$password_ok) {
        $errors['login'] = 'Invalid email or password.';
    } elseif ((int) $user['is_active'] === 0) {
        $errors['login'] = 'This account has been locked. Please contact the restaurant.';
    }

    if (empty($errors)) {
        // Vietnamese: sinh lai session ID ngay sau khi xac thuc thanh cong de chong
        // session fixation (ID cu - co the ke tan cong da biet truoc - khong con hieu luc).
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ];

        $role_default = $user['role'] === 'admin' ? '/admin/dashboard.php' : '/customer/dashboard.php';
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
        redirect($safe_redirect ?? $role_default);
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="gl-auth-split row g-0">
            <div class="col-12 col-md-6 gl-auth-form-pane d-flex flex-column justify-content-center">
                <h1 class="h3 mb-4">Login</h1>

                <?php if (isset($errors['login'])): ?>
                    <div class="alert alert-danger"><?= e($errors['login']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= BASE_URL ?>/auth/login.php" class="js-disable-on-submit" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($safe_redirect !== null): ?>
                        <input type="hidden" name="redirect" value="<?= e($safe_redirect) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            class="form-control<?= isset($errors['login']) ? ' is-invalid' : '' ?>"
                            id="email" name="email" required
                            value="<?= e($old_email) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($errors['login']) ? ' is-invalid' : '' ?>"
                            id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-gl-primary w-100">Log in</button>
                </form>
                <p class="text-center mt-3 mb-0"><small>No account? <a href="<?= BASE_URL ?>/auth/register.php">Register</a></small></p>
            </div>

            <?php // Vietnamese: cot trang tri chi hien tu breakpoint md tro len ("single column below md" theo yeu cau nang cap giao dien) - duoi md chi con form, danh toan bo chieu rong cho form tren man hinh nho. ?>
            <div class="col-md-6 d-none d-md-flex gl-auth-panel">
                <?= svg_lotus_motif('gl-auth-panel-motif') ?>
                <div>
                    <h2 class="mb-3">Welcome back</h2>
                    <p class="mb-0">Authentic Vietnamese dining, reserved in seconds &mdash; sign in to manage your table.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
