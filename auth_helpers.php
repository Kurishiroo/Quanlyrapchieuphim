<?php
function validateAccount($name, $email, $phone, $password, $requirePassword = true) {
    if ($name === '' || mb_strlen($name) > 100) throw new RuntimeException('Họ tên phải có từ 1 đến 100 ký tự.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) throw new RuntimeException('Email không hợp lệ hoặc dài hơn 100 ký tự.');
    if ($phone !== '' && !preg_match('/^[0-9+(). -]{6,15}$/', $phone)) throw new RuntimeException('Số điện thoại phải có 6–15 ký tự hợp lệ.');
    if (($requirePassword || $password !== '') && (strlen($password) < 8 || strlen($password) > 72)) throw new RuntimeException('Mật khẩu phải có từ 8 đến 72 byte (ký tự có dấu có thể chiếm nhiều byte).');
}
