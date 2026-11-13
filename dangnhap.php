<?php
require 'app.php';
if (currentUser()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    // Tên admin là bí danh của tài khoản quản trị được tạo trên máy này.
    $login = input('Email');
    $email = strtolower($login) === 'admin' ? 'admin@localhost.test' : $login;
    $user = query('SELECT * FROM taikhoan WHERE Email=?', [$email])->fetch();
    $password = isset($_POST['MatKhau']) && is_string($_POST['MatKhau']) ? $_POST['MatKhau'] : '';
    if ($user && $user['TrangThai'] === 'HoatDong' && password_verify($password, $user['MatKhau'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $user['MaTaiKhoan'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('Đăng nhập thành công.'); redirect('index.php');
    }
    $error = 'Tên đăng nhập/email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.';
}
pageHeader('Đăng nhập'); ?>
<section class="auth"><h1>Chào mừng trở lại</h1><p class="muted">Đăng nhập để đặt vé hoặc làm việc tại rạp.</p><?php showError($error); ?>
<form method="post"><?php csrf(); field('Email','Email hoặc tên đăng nhập',input('Email'),'text'); field('MatKhau','Mật khẩu','','password'); ?><button>Đăng nhập</button></form>
<p>Chưa có tài khoản? <a href="dangky.php">Đăng ký khách hàng</a></p>
<?php if (!query("SELECT COUNT(*) FROM taikhoan WHERE VaiTro='QuanTriVien'")->fetchColumn()): ?><p><a href="khoitao.php">Khởi tạo quản trị viên đầu tiên trên máy chủ</a></p><?php endif; ?></section>
<?php pageFooter(); ?>
