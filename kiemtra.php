<?php
require 'app.php';
requireLogin(['QuanTriVien']);
pageHeader('Kiểm tra kết nối');
echo '<section><h1>Kết nối database thành công</h1><p>PDO và MariaDB đang hoạt động.</p></section>';
pageFooter();
