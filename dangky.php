<?php
require 'app.php'; require 'auth_helpers.php';
if (currentUser()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $password = is_string($_POST['MatKhau'] ?? null) ? $_POST['MatKhau'] : '';
        validateAccount(input('HoTen'), input('Email'), input('SoDienThoai'), $password);
        if ($password !== ($_POST['XacNhan'] ?? null)) throw new RuntimeException('Hai mật khẩu không khớp.');
        // Không đọc VaiTro từ POST: người đăng ký luôn là KhachHang.
        query('INSERT INTO taikhoan (HoTen,Email,MatKhau,SoDienThoai,VaiTro) VALUES (?,?,?,?,?)',
            [input('HoTen'), input('Email'), password_hash($password, PASSWORD_DEFAULT), input('SoDienThoai') ?: null, 'KhachHang']);
        flash('Đăng ký thành công. Bạn có thể đăng nhập.'); redirect('dangnhap.php');
    } catch (Throwable $e) { $error = errorMessage($e); }
}
pageHeader('Đăng ký'); ?>
<section class="auth"><h1>Tạo tài khoản</h1><?php showError($error); ?><form method="post"><?php csrf();
field('HoTen','Họ tên',input('HoTen')); field('Email','Email',input('Email'),'email'); field('SoDienThoai','Số điện thoại',input('SoDienThoai'),'tel',false);
field('MatKhau','Mật khẩu (ít nhất 8 ký tự)','','password'); field('XacNhan','Nhập lại mật khẩu','','password'); ?><button>Đăng ký</button></form><a href="dangnhap.php">Đã có tài khoản? Đăng nhập</a></section><?php pageFooter(); ?>
