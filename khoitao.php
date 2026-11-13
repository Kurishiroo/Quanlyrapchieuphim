<?php
require 'app.php'; require 'auth_helpers.php';
// Chỉ cho thiết lập trên chính máy XAMPP, và chỉ khi chưa có quản trị viên.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit('Chỉ khởi tạo trên máy chủ localhost.'); }
$error = '';
if (query("SELECT COUNT(*) FROM taikhoan WHERE VaiTro='QuanTriVien'")->fetchColumn()) { http_response_code(403); exit('Đã có quản trị viên. Hãy đăng nhập để quản lý tài khoản.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $locked = false;
    try {
        $locked = (bool) query("SELECT GET_LOCK('quanlyrapphim_init_admin',5)")->fetchColumn();
        if (!$locked) throw new RuntimeException('Đang có yêu cầu khởi tạo khác, vui lòng thử lại.');
        if (query("SELECT COUNT(*) FROM taikhoan WHERE VaiTro='QuanTriVien'")->fetchColumn()) throw new RuntimeException('Đã có quản trị viên.');
        $password = is_string($_POST['MatKhau'] ?? null) ? $_POST['MatKhau'] : '';
        validateAccount(input('HoTen'), input('Email'), '', $password);
        if ($password !== ($_POST['XacNhan'] ?? null)) throw new RuntimeException('Hai mật khẩu không khớp.');
        query('INSERT INTO taikhoan (HoTen,Email,MatKhau,VaiTro) VALUES (?,?,?,?)', [input('HoTen'), input('Email'), password_hash($password, PASSWORD_DEFAULT), 'QuanTriVien']);
        flash('Đã tạo quản trị viên. Hãy đăng nhập.');
    } catch (Throwable $e) { $error = errorMessage($e); }
    finally { if ($locked) query("SELECT RELEASE_LOCK('quanlyrapphim_init_admin')"); }
    if ($error === '') redirect('dangnhap.php');
}
pageHeader('Khởi tạo'); ?><section class="auth"><h1>Quản trị viên đầu tiên</h1><p>Tự chọn tài khoản quản trị. Trang này tự khóa sau khi khởi tạo thành công.</p><?php showError($error); ?><form method="post"><?php csrf(); field('HoTen','Họ tên',input('HoTen')); field('Email','Email',input('Email'),'email'); field('MatKhau','Mật khẩu','','password'); field('XacNhan','Nhập lại mật khẩu','','password'); ?><button>Tạo quản trị viên</button></form></section><?php pageFooter(); ?>
