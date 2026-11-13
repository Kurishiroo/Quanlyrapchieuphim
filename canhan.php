<?php
require 'app.php'; require 'auth_helpers.php';
$user = requireLogin(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $password = is_string($_POST['MatKhauMoi'] ?? null) ? $_POST['MatKhauMoi'] : '';
        validateAccount(input('HoTen'), input('Email'), input('SoDienThoai'), $password, false);
        $old = is_string($_POST['MatKhauCu'] ?? null) ? $_POST['MatKhauCu'] : '';
        if (!password_verify($old, $user['MatKhau'])) throw new RuntimeException('Mật khẩu hiện tại không đúng.');
        if ($password !== ($_POST['XacNhan'] ?? '')) throw new RuntimeException('Mật khẩu mới không khớp.');
        query('UPDATE taikhoan SET HoTen=?, Email=?, SoDienThoai=?, MatKhau=? WHERE MaTaiKhoan=?',
            [input('HoTen'),input('Email'),input('SoDienThoai') ?: null,$password !== '' ? password_hash($password,PASSWORD_DEFAULT) : $user['MatKhau'],$user['MaTaiKhoan']]);
        session_regenerate_id(true); flash('Đã cập nhật thông tin cá nhân.'); redirect('canhan.php');
    } catch (Throwable $e) { $error = errorMessage($e); }
}
pageHeader('Thông tin cá nhân'); ?><section class="auth"><h1>Thông tin cá nhân</h1><?php showError($error); ?><form method="post"><?php csrf();
foreach (['HoTen'=>'Họ tên','Email'=>'Email','SoDienThoai'=>'Số điện thoại'] as $key=>$title) field($key,$title,$_SERVER['REQUEST_METHOD']==='POST' ? input($key) : $user[$key],$key==='Email'?'email':'text',$key!=='SoDienThoai');
field('MatKhauCu','Mật khẩu hiện tại','','password'); field('MatKhauMoi','Mật khẩu mới (để trống nếu giữ nguyên)','','password',false); field('XacNhan','Nhập lại mật khẩu mới','','password',false); ?><button>Lưu thông tin</button></form></section><?php pageFooter(); ?>
