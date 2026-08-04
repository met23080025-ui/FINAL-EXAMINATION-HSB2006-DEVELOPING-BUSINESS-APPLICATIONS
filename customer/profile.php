<?php
/**
 * customer/profile.php
 * Chỉnh sửa thông tin cá nhân (họ tên/điện thoại/email) và đổi mật khẩu (yêu
 * cầu nhập lại mật khẩu cũ, băm mật khẩu mới bằng password_hash() trước khi lưu).
 *
 * Hai form độc lập trên cùng một trang, phân biệt qua trường ẩn "form_action"
 * (update_profile | change_password) — mỗi form có lỗi/thành công riêng, form
 * còn lại không bị ảnh hưởng khi một form kia được submit.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_id = current_user()['id'];

$profile_errors  = [];
$password_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Phien lam viec da het han, vui long thu lai.');
        redirect('/customer/profile.php');
    }

    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'update_profile') {
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $phone     = trim((string) ($_POST['phone'] ?? ''));

        if ($full_name === '') {
            $profile_errors['full_name'] = 'Please enter your full name.';
        } elseif (mb_strlen($full_name) > 100) {
            $profile_errors['full_name'] = 'Full name must be at most 100 characters.';
        }

        if ($email === '') {
            $profile_errors['email'] = 'Please enter your email.';
        } elseif (!is_valid_email($email)) {
            $profile_errors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 150) {
            $profile_errors['email'] = 'Email must be at most 150 characters.';
        }

        if ($phone === '') {
            $profile_errors['phone'] = 'Please enter your phone number.';
        } elseif (!is_valid_phone($phone)) {
            $profile_errors['phone'] = 'Please enter a valid phone number (8-15 digits, optional leading +).';
        }

        // Vietnamese: kiem tra trung email VOI NGUOI KHAC (loai tru chinh minh qua id != ?) -
        // email hien tai cua chinh minh khong tinh la trung.
        if (!isset($profile_errors['email'])) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch() !== false) {
                $profile_errors['email'] = 'This email is already used by another account.';
            }
        }

        if (empty($profile_errors)) {
            $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->execute([$full_name, $email, $phone, $user_id]);

            // Vietnamese: cap nhat lai session ngay de navbar hien ten/email moi,
            // khong phai doi den lan dang nhap sau moi thay doi.
            $_SESSION['user']['full_name'] = $full_name;
            $_SESSION['user']['email']     = $email;

            set_flash('success', 'Profile updated successfully.');
            redirect('/customer/profile.php');
        }
    } elseif ($form_action === 'change_password') {
        $current_password     = (string) ($_POST['current_password'] ?? '');
        $new_password         = (string) ($_POST['new_password'] ?? '');
        $confirm_new_password = (string) ($_POST['confirm_new_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $current_hash = $stmt->fetchColumn();

        if (!password_verify($current_password, $current_hash)) {
            $password_errors['current_password'] = 'Current password is incorrect.';
        }

        if (!is_strong_password($new_password)) {
            $password_errors['new_password'] = 'Password must be at least 8 characters and include at least one letter and one number.';
        } elseif ($new_password !== $confirm_new_password) {
            $password_errors['confirm_new_password'] = 'Passwords do not match.';
        }

        if (empty($password_errors)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$new_hash, $user_id]);

            set_flash('success', 'Password changed successfully.');
            redirect('/customer/profile.php');
        }
    }
}

// Vietnamese: luon lay du lieu moi nhat tu CSDL de hien thi - session chi luu
// full_name/email/role (xem includes/auth.php), khong co phone.
$stmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user_row = $stmt->fetch();

$profile_old = [
    'full_name' => $_POST['full_name'] ?? $user_row['full_name'],
    'email'     => $_POST['email'] ?? $user_row['email'],
    'phone'     => $_POST['phone'] ?? $user_row['phone'],
];

$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h2 class="h4 mb-4">Edit Profile</h2>
                <form method="post" action="<?= BASE_URL ?>/customer/profile.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full name</label>
                        <input
                            type="text"
                            class="form-control<?= isset($profile_errors['full_name']) ? ' is-invalid' : '' ?>"
                            id="full_name" name="full_name" maxlength="100" required
                            value="<?= e($profile_old['full_name']) ?>">
                        <?php if (isset($profile_errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= e($profile_errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input
                            type="email"
                            class="form-control<?= isset($profile_errors['email']) ? ' is-invalid' : '' ?>"
                            id="email" name="email" maxlength="150" required
                            value="<?= e($profile_old['email']) ?>">
                        <?php if (isset($profile_errors['email'])): ?>
                            <div class="invalid-feedback"><?= e($profile_errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="phone">Phone</label>
                        <input
                            type="text"
                            class="form-control<?= isset($profile_errors['phone']) ? ' is-invalid' : '' ?>"
                            id="phone" name="phone" maxlength="20" required
                            value="<?= e($profile_old['phone']) ?>">
                        <?php if (isset($profile_errors['phone'])): ?>
                            <div class="invalid-feedback"><?= e($profile_errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-gl-primary w-100">Save changes</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h2 class="h4 mb-4">Change Password</h2>
                <form method="post" action="<?= BASE_URL ?>/customer/profile.php" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="change_password">

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($password_errors['current_password']) ? ' is-invalid' : '' ?>"
                            id="current_password" name="current_password" required>
                        <?php if (isset($password_errors['current_password'])): ?>
                            <div class="invalid-feedback"><?= e($password_errors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="new_password">New password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($password_errors['new_password']) ? ' is-invalid' : '' ?>"
                            id="new_password" name="new_password" minlength="8" required>
                        <div class="form-text">At least 8 characters, with at least one letter and one number.</div>
                        <?php if (isset($password_errors['new_password'])): ?>
                            <div class="invalid-feedback"><?= e($password_errors['new_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm_new_password">Confirm new password</label>
                        <input
                            type="password"
                            class="form-control<?= isset($password_errors['confirm_new_password']) ? ' is-invalid' : '' ?>"
                            id="confirm_new_password" name="confirm_new_password" minlength="8" required>
                        <?php if (isset($password_errors['confirm_new_password'])): ?>
                            <div class="invalid-feedback"><?= e($password_errors['confirm_new_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-gl-primary w-100">Change password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
