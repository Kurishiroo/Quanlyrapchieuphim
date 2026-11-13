<?php
// Các hàm dùng chung: PDO, phiên đăng nhập, phân quyền, biểu mẫu.
date_default_timezone_set('Asia/Ho_Chi_Minh');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
require_once __DIR__ . '/db.php';
$pdo->exec("SET time_zone = '+07:00'");
$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(32));

function h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function input($key, $source = null) {
    $source = $source ?? $_POST;
    return isset($source[$key]) && (is_string($source[$key]) || is_int($source[$key])) ? trim((string) $source[$key]) : '';
}
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function redirect($url) { header('Location: ' . $url); exit; }
function flash($message) { $_SESSION['flash'] = $message; }
function csrf() { echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">'; }
function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'], input('csrf'))) {
        http_response_code(403);
        exit('Biểu mẫu hết hạn hoặc không hợp lệ. Vui lòng tải lại trang.');
    }
}
function currentUser() {
    // Đọc lại vai trò/trạng thái mỗi request: khóa tài khoản có hiệu lực ngay.
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true;
        if (isset($_SESSION['uid'])) {
            $user = query('SELECT * FROM taikhoan WHERE MaTaiKhoan=? AND TrangThai=?', [$_SESSION['uid'], 'HoatDong'])->fetch() ?: null;
            if (!$user) unset($_SESSION['uid']);
        }
    }
    return $user;
}
function requireLogin($roles = []) {
    $user = currentUser();
    if (!$user) redirect('dangnhap.php');
    if ($roles && !in_array($user['VaiTro'], $roles, true)) {
        http_response_code(403);
        pageHeader('Không có quyền truy cập');
        echo '<section><h1>Không có quyền truy cập</h1><p>Tài khoản của bạn không được sử dụng chức năng này.</p><a href="index.php">Về trang chủ</a></section>';
        pageFooter(); exit;
    }
    return $user;
}
function isStaff() { $u = currentUser(); return $u && in_array($u['VaiTro'], ['NhanVien', 'QuanTriVien'], true); }
function positiveInt($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    if ($id === false) throw new RuntimeException('Mã hoặc số lượng không hợp lệ.');
    return $id;
}
function validDate($value, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat('!' . $format, $value);
    return $d && $d->format($format) === $value && substr($value, 0, 4) >= '1000';
}
function money($value) {
    $amount = (float) $value;
    return number_format($amount, $amount == floor($amount) ? 0 : 2, ',', '.') . ' đ';
}
function label($value) {
    $labels = ['KhachHang'=>'Khách hàng','NhanVien'=>'Nhân viên','QuanTriVien'=>'Quản trị viên',
        'HoatDong'=>'Hoạt động','BiKhoa'=>'Bị khóa','SapChieu'=>'Sắp chiếu','DangChieu'=>'Đang chiếu',
        'NgungChieu'=>'Ngừng chiếu','BaoTri'=>'Bảo trì','NgungHoatDong'=>'Ngừng hoạt động','SuDung'=>'Sử dụng',
        'Thuong'=>'Thường','VIP'=>'VIP','MoBan'=>'Mở bán','DongBan'=>'Đóng bán','DaHuy'=>'Đã hủy',
        'ChoThanhToan'=>'Chờ thanh toán','DaThanhToan'=>'Đã thanh toán','DaSuDung'=>'Đã sử dụng',
        'ThanhCong'=>'Thành công','ThatBai'=>'Thất bại','TienMat'=>'Tiền mặt','ChuyenKhoan'=>'Chuyển khoản mô phỏng'];
    return $labels[$value] ?? $value;
}
function errorMessage($e) {
    global $pdo;
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1062) return 'Dữ liệu bị trùng (tên, email, vị trí ghế hoặc ghế đã được đặt).';
        if ($code === 1451) return 'Không thể xóa vì dữ liệu đã được sử dụng. Hãy đổi trạng thái nếu cần.';
        if ($code === 1452) return 'Dữ liệu liên quan không còn tồn tại. Vui lòng tải lại trang.';
        error_log($e->getMessage());
        return 'Không thể hoàn tất thao tác. Vui lòng tải lại trang và thử lại.';
    }
    return $e->getMessage();
}
function pageHeader($title) {
    $u = currentUser();
    ?><!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h($title) ?> · Rạp Việt</title><link rel="stylesheet" href="style.css"></head><body>
    <header><a class="brand" href="index.php">RẠP VIỆT <small>CINEMA</small></a><nav><a href="index.php">Phim & lịch chiếu</a>
    <?php if ($u): ?><a href="ve.php"><?= isStaff() ? 'Bán vé & tra cứu' : 'Vé của tôi' ?></a>
    <?php if ($u['VaiTro'] === 'QuanTriVien'): ?>
        <a href="phim.php">Phim</a><a href="theloai.php">Thể loại</a><a href="phong.php">Phòng</a><a href="ghe.php">Ghế</a><a href="suatchieu.php">Suất chiếu</a><a href="taikhoan.php">Tài khoản</a><a href="thongke.php">Thống kê</a>
    <?php endif; ?><a href="canhan.php"><?= h($u['HoTen']) ?> · <?= h(label($u['VaiTro'])) ?></a><form method="post" action="dangxuat.php" class="inline"><?php csrf(); ?><button class="quiet">Đăng xuất</button></form>
    <?php else: ?><a href="dangnhap.php">Đăng nhập</a><a href="dangky.php">Đăng ký</a><?php endif; ?></nav></header><main>
    <?php if (isset($_SESSION['flash'])): ?><div class="notice success" role="status"><?= h($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif;
}
function pageFooter() { echo '</main><footer>Rạp Việt · Đồ án Kỹ thuật phần mềm ứng dụng · Thanh toán mô phỏng</footer></body></html>'; }
function showError($error) { if ($error !== '') echo '<div class="notice error" role="alert">' . h($error) . '</div>'; }
function field($name, $title, $value = '', $type = 'text', $required = true) {
    echo '<label>' . h($title) . '<input name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '"' . ($required ? ' required' : '') . '></label>';
}
function selectField($name, $title, $options, $value = '') {
    echo '<label>' . h($title) . '<select name="' . h($name) . '" required><option value="">-- Chọn --</option>';
    foreach ($options as $key => $text) echo '<option value="' . h($key) . '"' . ((string) $key === (string) $value ? ' selected' : '') . '>' . h($text) . '</option>';
    echo '</select></label>';
}
