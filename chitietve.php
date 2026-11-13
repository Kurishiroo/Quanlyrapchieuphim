<?php
require 'app.php'; require 'ticket_service.php'; $user=requireLogin(); $error=''; $id=input('id',$_GET);
if($_SERVER['REQUEST_METHOD']==='POST'){
    checkCsrf();
    try{
        changeTicket($user,positiveInt($id),input('action'),input('PhuongThuc'),input('KetQua'));
        if(input('action')==='cancel'){flash('Đã hủy vé chưa thanh toán và trả ghế về trạng thái trống.');redirect('ve.php');}
        flash(input('action')==='checkin'?'Đã soát vé.':(input('KetQua')==='ThanhCong'?'Thanh toán mô phỏng thành công.':'Đã mô phỏng thanh toán thất bại. Bạn có thể thử lại hoặc hủy vé.'));
        redirect('chitietve.php?id='.(int)$id);
    }catch(Throwable $e){$error=errorMessage($e);}
}
$sql='SELECT v.*,p.TenPhim,r.TenPhong,g.HangGhe,g.SoGhe,s.ThoiGianBatDau,s.ThoiGianKetThuc,k.HoTen AS KhachHang,n.HoTen AS NhanVien FROM ve v JOIN suatchieu s ON s.MaSuatChieu=v.MaSuatChieu JOIN phim p ON p.MaPhim=s.MaPhim JOIN phongchieu r ON r.MaPhong=s.MaPhong JOIN ghe g ON g.MaGhe=v.MaGhe LEFT JOIN taikhoan k ON k.MaTaiKhoan=v.MaKhachHang LEFT JOIN taikhoan n ON n.MaTaiKhoan=v.MaNhanVien WHERE v.MaVe=?';
$params=[$id];if(!isStaff()){$sql.=' AND v.MaKhachHang=?';$params[]=$user['MaTaiKhoan'];}
$ticket=query($sql,$params)->fetch();
if(!$ticket){http_response_code(404);pageHeader('Không tìm thấy vé');echo '<section>Không tìm thấy vé hoặc bạn không có quyền xem. <a href="ve.php">Về danh sách vé</a></section>';pageFooter();exit;}
$payment=query('SELECT * FROM thanhtoan WHERE MaVe=?',[$id])->fetch();
pageHeader('Vé #'.$ticket['MaVe']);showError($error); ?>
<section><span class="eyebrow">Rạp Việt · Vé xem phim</span><h1>#<?= h($ticket['MaVe']) ?> — <?= h($ticket['TenPhim']) ?></h1><div class="grid"><div><p><strong>Phòng:</strong> <?= h($ticket['TenPhong']) ?></p><p><strong>Ghế:</strong> <?= h($ticket['HangGhe'].$ticket['SoGhe']) ?></p><p><strong>Bắt đầu:</strong> <?= h($ticket['ThoiGianBatDau']) ?></p><p><strong>Kết thúc:</strong> <?= h($ticket['ThoiGianKetThuc']) ?></p></div><div><p><strong>Khách hàng:</strong> <?= h($ticket['KhachHang']?:'Khách vãng lai') ?></p><p><strong>Nhân viên bán:</strong> <?= h($ticket['NhanVien']?:'Khách tự đặt') ?></p><p><strong>Giá vé:</strong> <?= money($ticket['GiaVe']) ?></p><p><span class="badge"><?= h(label($ticket['TrangThai'])) ?></span></p></div></div>
<?php if($payment): ?><p>Thanh toán: <?= h(label($payment['TrangThai'])) ?> · <?= h(label($payment['PhuongThuc'])) ?> · <?= h($payment['ThoiGianThanhToan']) ?></p><?php endif; ?>
<p class="muted">Đây là hệ thống học tập, mọi giao dịch đều là mô phỏng và không chuyển tiền thật.</p><button class="quiet" onclick="window.print()">In vé</button><a class="no-print" href="ve.php">Về danh sách vé</a></section>
<?php if($ticket['TrangThai']==='ChoThanhToan'): ?><section class="no-print"><h2>Thanh toán mô phỏng</h2><form method="post"><?php csrf(); ?><input type="hidden" name="action" value="pay"><div class="grid"><?php
$methods=['ChuyenKhoan'=>'Chuyển khoản mô phỏng'];if(isStaff())$methods=['TienMat'=>'Tiền mặt (mô phỏng)']+$methods;
selectField('PhuongThuc','Phương thức',$methods,array_key_first($methods));selectField('KetQua','Kết quả mô phỏng',['ThanhCong'=>'Thành công','ThatBai'=>'Thất bại'],'ThanhCong'); ?></div><button>Xác nhận thanh toán <?= money($ticket['GiaVe']) ?></button></form>
<form method="post" onsubmit="return confirm('Hủy đặt chỗ và trả lại ghế?');"><?php csrf(); ?><input type="hidden" name="action" value="cancel"><button class="danger">Hủy vé chưa thanh toán</button></form></section><?php endif; ?>
<?php if(isStaff() && $ticket['TrangThai']==='DaThanhToan'): ?><section class="no-print"><h2>Soát vé tại rạp</h2><p>Thực hiện từ 30 phút trước giờ bắt đầu đến khi suất chiếu kết thúc.</p><form method="post"><?php csrf(); ?><input type="hidden" name="action" value="checkin"><button>Xác nhận khách đã vào rạp</button></form></section><?php endif; ?>
<?php pageFooter(); ?>
