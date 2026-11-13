<?php
// Những thao tác liên quan vé/thanh toán cùng nằm trong một transaction.
function lockShow($id) {
    $show = query('SELECT * FROM suatchieu WHERE MaSuatChieu=? FOR UPDATE',[$id])->fetch();
    if (!$show) throw new RuntimeException('Suất chiếu không tồn tại.');
    return $show;
}
function ensureBookable($show) {
    $room = query('SELECT * FROM phongchieu WHERE MaPhong=? FOR UPDATE',[$show['MaPhong']])->fetch();
    $movie = query('SELECT * FROM phim WHERE MaPhim=? FOR UPDATE',[$show['MaPhim']])->fetch();
    if ($show['TrangThai']!=='MoBan' || $show['ThoiGianBatDau']<=date('Y-m-d H:i:s') || $room['TrangThai']!=='HoatDong' || $movie['TrangThai']==='NgungChieu') throw new RuntimeException('Suất chiếu đã bắt đầu, đóng bán, hoặc phim/phòng tạm ngừng phục vụ.');
}
function bookTicket($user, $showId, $seatId, $customerId = '') {
    global $pdo;
    $pdo->beginTransaction();
    $show = lockShow($showId);
    ensureBookable($show);
    $seat = query('SELECT * FROM ghe WHERE MaGhe=? FOR UPDATE',[$seatId])->fetch();
    if (!$seat || $seat['MaPhong']!=$show['MaPhong'] || $seat['TrangThai']!=='SuDung') throw new RuntimeException('Ghế không thuộc phòng chiếu hoặc đang bảo trì.');
    if (query('SELECT MaVe FROM ve WHERE MaSuatChieu=? AND MaGhe=?',[$showId,$seatId])->fetch()) throw new RuntimeException('Ghế vừa được người khác đặt. Vui lòng chọn ghế khác.');
    $customer = $user['VaiTro']==='KhachHang' ? $user['MaTaiKhoan'] : null;
    $employee = $user['VaiTro']==='KhachHang' ? null : $user['MaTaiKhoan'];
    if ($employee && $customerId!=='') {
        $customer = positiveInt($customerId);
        if (!query("SELECT MaTaiKhoan FROM taikhoan WHERE MaTaiKhoan=? AND VaiTro='KhachHang' AND TrangThai='HoatDong'",[$customer])->fetch()) throw new RuntimeException('Khách hàng không hợp lệ hoặc đã bị khóa.');
    }
    // UNIQUE(MaSuatChieu,MaGhe) của database là lớp bảo vệ cuối cùng chống trùng ghế.
    query('INSERT INTO ve (MaSuatChieu,MaGhe,MaKhachHang,MaNhanVien,GiaVe) VALUES (?,?,?,?,?)',[$showId,$seatId,$customer,$employee,$show['GiaVe']]);
    $id = $pdo->lastInsertId(); $pdo->commit(); return $id;
}
function changeTicket($user, $ticketId, $action, $method = '', $result = '') {
    global $pdo;
    $pdo->beginTransaction();
    $initial = query('SELECT MaSuatChieu FROM ve WHERE MaVe=?',[$ticketId])->fetch();
    if (!$initial) throw new RuntimeException('Không tìm thấy vé.');
    $show = lockShow($initial['MaSuatChieu']);
    $ticket = query('SELECT * FROM ve WHERE MaVe=? FOR UPDATE',[$ticketId])->fetch();
    if (!$ticket || ($user['VaiTro']==='KhachHang' && $ticket['MaKhachHang']!=$user['MaTaiKhoan'])) throw new RuntimeException('Bạn không có quyền thao tác vé này.');
    if ($action==='pay') {
        if (!in_array($method,['TienMat','ChuyenKhoan'],true) || ($user['VaiTro']==='KhachHang' && $method!=='ChuyenKhoan')) throw new RuntimeException('Phương thức thanh toán không hợp lệ. Khách hàng thanh toán mô phỏng bằng chuyển khoản.');
        if (!in_array($result,['ThanhCong','ThatBai'],true)) throw new RuntimeException('Kết quả mô phỏng không hợp lệ.');
        if ($ticket['TrangThai']!=='ChoThanhToan') throw new RuntimeException('Vé đã được thanh toán; không thu tiền lần nữa.');
        ensureBookable($show);
        $seat = query('SELECT TrangThai FROM ghe WHERE MaGhe=? FOR UPDATE',[$ticket['MaGhe']])->fetch();
        if (!$seat || $seat['TrangThai']!=='SuDung') throw new RuntimeException('Ghế đang bảo trì. Vui lòng liên hệ nhân viên hoặc hủy vé chưa thanh toán.');
        query('INSERT INTO thanhtoan (MaVe,SoTien,PhuongThuc,TrangThai) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE SoTien=VALUES(SoTien),PhuongThuc=VALUES(PhuongThuc),TrangThai=VALUES(TrangThai),ThoiGianThanhToan=CURRENT_TIMESTAMP',[$ticketId,$ticket['GiaVe'],$method,$result]);
        if ($result==='ThanhCong') query("UPDATE ve SET TrangThai='DaThanhToan' WHERE MaVe=?",[$ticketId]);
    } elseif ($action==='cancel') {
        if ($ticket['TrangThai']!=='ChoThanhToan') throw new RuntimeException('Chỉ hủy vé chưa thanh toán. Vé đã thanh toán được giữ để đối soát.');
        if (query("SELECT MaThanhToan FROM thanhtoan WHERE MaVe=? AND TrangThai='ThanhCong'",[$ticketId])->fetch()) throw new RuntimeException('Vé có thanh toán thành công, không thể hủy.');
        // Schema không có trạng thái DaHuy cho vé: xóa đặt chỗ chưa trả tiền để nhả ghế.
        query('DELETE FROM thanhtoan WHERE MaVe=?',[$ticketId]);
        query('DELETE FROM ve WHERE MaVe=?',[$ticketId]);
    } elseif ($action==='checkin') {
        if ($user['VaiTro']==='KhachHang') throw new RuntimeException('Chỉ nhân viên hoặc quản trị viên được soát vé.');
        if ($ticket['TrangThai']!=='DaThanhToan') throw new RuntimeException('Chỉ soát vé đã thanh toán và chưa sử dụng.');
        if ($show['TrangThai']==='DaHuy' || time()<strtotime($show['ThoiGianBatDau'])-1800 || time()>strtotime($show['ThoiGianKetThuc'])) throw new RuntimeException('Soát vé từ 30 phút trước giờ chiếu đến khi suất chiếu kết thúc.');
        query("UPDATE ve SET TrangThai='DaSuDung' WHERE MaVe=?",[$ticketId]);
    } else throw new RuntimeException('Thao tác không hợp lệ.');
    $pdo->commit();
}
