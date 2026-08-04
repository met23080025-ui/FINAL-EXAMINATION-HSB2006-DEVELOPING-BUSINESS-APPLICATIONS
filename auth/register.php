<?php
/**
 * auth/register.php
 * Trang đăng ký tài khoản khách hàng mới.
 * Kiểm tra định dạng email và độ mạnh mật khẩu, băm mật khẩu bằng password_hash()
 * trước khi lưu vào bảng users (role = 'customer').
 *
 * Luồng dữ liệu: GET hiển thị form trống -> POST validate toàn bộ ở server
 * (không bao giờ chỉ tin client) -> nếu có lỗi, render lại form kèm lỗi từng
 * trường + giữ nguyên giá trị đã nhập (trừ mật khẩu) -> nếu hợp lệ, INSERT
 * bằng prepared statement rồi chuyển tới auth/login.php.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Vietnamese: da dang nhap roi thi khong can vao lai trang dang ky.
if (is_logged_in()) {
    redirect(current_user()['role'] === 'admin' ? '/admin/dashboard.php' : '/customer/dashboard.php');
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vietnamese: kiem tra CSRF truoc tien - form gia mao tu trang khac se bi chan ngay o day.
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/auth/register.php');
    }

    // Vietnamese: giu lai gia tri da nhap (tru mat khau) de render lai form neu co loi,
    // nguoi dung khong phai go lai tu dau.
    $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $old['email']     = trim((string) ($_POST['email'] ?? ''));
    $old['phone']     = trim((string) ($_POST['phone'] ?? ''));
    $password         = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if ($old['full_name'] === '') {
        $errors['full_name'] = 'Please enter your full name.';
    } elseif (mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Full name must be at most 100 characters.';
    }

    if ($old['email'] === '') {
        $errors['email'] = 'Please enter your email.';
    } elseif (!is_valid_email($old['email'])) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (mb_strlen($old['email']) > 150) {
        $errors['email'] = 'Email must be at most 150 characters.';
    }

    if ($old['phone'] === '') {
        $errors['phone'] = 'Please enter your phone number.';
    } elseif (!is_valid_phone($old['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number (8-15 digits, optional leading +).';
    }

    if (!is_strong_password($password)) {
        $errors['password'] = 'Password must be at least 8 characters and include at least one letter and one number.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Vietnamese: chi truy van kiem tra trung email khi dinh dang email da hop le,
    // tranh mot truy van CSDL khong can thiet khi form ro rang con loi khac.
    if (!isset($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch() !== false) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        // Vietnamese: password_hash() voi PASSWORD_DEFAULT (hien la bcrypt) - khong
        // bao gio luu mat khau plaintext, khong bao gio log/echo bien $password.
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, phone, role, is_active) VALUES (?, ?, ?, ?, 'customer', 1)"
        );
        $stmt->execute([$old['full_name'], $old['email'], $password_hash, $old['phone']]);

        set_flash('success', 'Account created successfully! Please log in.');
        redirect('/auth/login.php');
    }

    set_flash('danger', 'Please fix the errors below.');
}

$page_title = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center">Register</h1>
                <form method="post" action="<?= BASE_URL ?>/auth/register.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full name</label>
                        <input
                            type="text"
                            class="form-control<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>"
                            id="full_name" name="full_name" maxlength="100" required
                            value="<?= e($old['full_name']) ?>">
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                            id="email" name="email" maxlength="150" required
                            value="<?= e($old['email']) ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input
                            type="text"
                            class="form-control<?= isset($errors['phone']) ? ' is-invalid' : '' ?>"
                            id="phone" name="phone" maxlength="20" required
                            value="<?= e($old['phone']) ?>">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                            id="password" name="password" minlength="8" required>
                        <div class="form-text">At least 8 characters, with at least one letter and one number.</div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($errors['confirm_password']) ? ' is-invalid' : '' ?>"
                            id="confirm_password" name="confirm_password" minlength="8" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-gl-primary w-100">Create account</button>
                </form>
                <p class="text-center mt-3 mb-0"><small>Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Log in</a></small></p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
