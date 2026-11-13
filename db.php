<?php
// Sao chép config.example.php thành config.local.php và điền cấu hình máy bạn.
$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    exit('Thiếu config.local.php. Hãy sao chép config.example.php và điền thông tin kết nối.');
}
$config = require $configFile;
$host = $config['host'];
$dbname = $config['dbname'];
$username = $config['username'];
$password = $config['password'];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit('Không thể kết nối database. Hãy kiểm tra MySQL và thông tin kết nối.');
}