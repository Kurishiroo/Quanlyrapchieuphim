<?php
require 'app.php'; require 'ticket_service.php'; $user=requireLogin(); $error='';
$showId=input('suat',$_GET);
if($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf(); $showId=input('MaSuatChieu');
    try {
        $id=bookTicket($user,positiveInt($showId),positiveInt(input('MaGhe')),input('MaKhachHang'));
        flash('Đã giữ ghế. Vui lòng thanh toán hoặc hủy đặt chỗ nếu không sử dụng.');redirect('chitietve.php?id='.$id);
    }catch(Throwable $e){$error=errorMessage($e);}
}
$show=query('SELECT s.*,p.TenPhim,p.TrangThai AS TrangThaiPhim,r.TenPhong,r.TrangThai AS TrangThaiPhong FROM suatchieu s JOIN phim p ON p.MaPhim=s.MaPhim JOIN phongchieu r ON r.MaPhong=s.MaPhong WHERE s.MaSuatChieu=?',[$showId])->fetch();
if(!$show){http_response_code(404);pageHeader('Không tìm thấy suất chiếu');echo '<section>Không tìm thấy suất chiếu. <a href="index.php">Về lịch chiếu</a></section>';pageFooter();exit;}
$seats=query('SELECT g.*,v.MaVe FROM ghe g LEFT JOIN ve v ON v.MaGhe=g.MaGhe AND v.MaSuatChieu=? WHERE g.MaPhong=? ORDER BY g.HangGhe,g.SoGhe',[$showId,$show['MaPhong']])->fetchAll();
$open=$show['TrangThai']==='MoBan' && $show['ThoiGianBatDau']>date('Y-m-d H:i:s') && $show['TrangThaiPhong']==='HoatDong' && $show['TrangThaiPhim']!=='NgungChieu';
pageHeader('Chọn ghế'); ?><section><span class="eyebrow"><?= isStaff()?'Bán vé tại quầy':'Đặt vé xem phim' ?></span><h1><?= h($show['TenPhim']) ?></h1><p><?= h($show['TenPhong']) ?> · <?= h(date('d/m/Y H:i',strtotime($show['ThoiGianBatDau']))) ?> · <strong><?= money($show['GiaVe']) ?> / vé</strong></p><?php showError($error); ?>
<?php if(!$open): ?><div class="notice error">Suất chiếu hiện không nhận đặt vé.</div><?php endif; ?>
<div class="screen">MÀN HÌNH</div><form method="post"><?php csrf(); ?><input type="hidden" name="MaSuatChieu" value="<?= h($showId) ?>">
<div class="seats"><?php foreach($seats as $seat): $disabled=!$open || $seat['MaVe'] || $seat['TrangThai']!=='SuDung'; ?><label class="seat"><input type="radio" name="MaGhe" value="<?= h($seat['MaGhe']) ?>" required <?= $disabled?'disabled':'' ?>><span><?= h($seat['HangGhe'].$seat['SoGhe']) ?><br><small><?= h($disabled?'Không trống':label($seat['LoaiGhe'])) ?></small></span></label><?php endforeach; ?></div>
<?php if(!$seats): ?><p>Phòng chưa có ghế. Quản trị viên cần thêm ghế trước khi bán vé.</p><?php endif; ?>
<?php if(isStaff()): $customers=query("SELECT MaTaiKhoan,CONCAT(HoTen,' · ',Email) AS Ten FROM taikhoan WHERE VaiTro='KhachHang' AND TrangThai='HoatDong' ORDER BY HoTen")->fetchAll(PDO::FETCH_KEY_PAIR); ?><label>Khách hàng (để trống nếu khách vãng lai)<select name="MaKhachHang"><option value="">Khách vãng lai</option><?php foreach($customers as $key=>$name): ?><option value="<?= h($key) ?>"><?= h($name) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<p class="muted">Ghế xám đã có người đặt hoặc đang bảo trì. Giá vé áp dụng chung cho mọi loại ghế trong suất chiếu này.</p><button <?= !$open || !$seats?'disabled':'' ?>><?= isStaff()?'Tạo vé':'Đặt ghế này' ?></button><a href="index.php">Quay lại lịch chiếu</a></form></section><?php pageFooter(); ?>
