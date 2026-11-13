<?php
require 'app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Vui lòng dùng nút Đăng xuất.'); }
checkCsrf();
$_SESSION = [];
session_regenerate_id(true);
redirect('dangnhap.php');
