<?php
require 'app.php'; requireLogin(['QuanTriVien']);
$from=input('tu',$_GET)?:date('Y-m-01');$to=input('den',$_GET)?:date('Y-m-d');$error='';$rows=[];$total=['SoVe'=>0,'DoanhThu'=>0];
if(!validDate($from)||!validDate($to)||$from>$to){$error='Khoảng ngày không hợp lệ.';}
else{
    // GROUP BY theo phim, chỉ cộng giao dịch thành công; lọc ngày thanh toán.
    $rows=query("SELECT p.MaPhim,p.TenPhim,COUNT(*) AS SoVe,SUM(t.SoTien) AS DoanhThu FROM thanhtoan t JOIN ve v ON v.MaVe=t.MaVe JOIN suatchieu s ON s.MaSuatChieu=v.MaSuatChieu JOIN phim p ON p.MaPhim=s.MaPhim WHERE t.TrangThai='ThanhCong' AND DATE(t.ThoiGianThanhToan) BETWEEN ? AND ? GROUP BY p.MaPhim,p.TenPhim ORDER BY DoanhThu DESC",[$from,$to])->fetchAll();
    foreach($rows as $row){$total['SoVe']+=$row['SoVe'];$total['DoanhThu']+=$row['DoanhThu'];}
}
$pending=query("SELECT COUNT(*) FROM ve WHERE TrangThai='ChoThanhToan'")->fetchColumn();
pageHeader('Thống kê'); ?><h1>Thống kê doanh thu</h1><p class="muted">Doanh thu mô phỏng theo ngày thanh toán thành công. Không tính giao dịch thất bại.</p><?php showError($error); ?>
<section><form method="get" class="toolbar"><?php field('tu','Từ ngày',$from,'date');field('den','Đến ngày',$to,'date'); ?><button>Xem thống kê</button></form></section>
<div class="cards"><section><div class="muted">Doanh thu trong khoảng chọn</div><div class="stat"><?= money($total['DoanhThu']) ?></div></section><section><div class="muted">Vé thanh toán thành công</div><div class="stat"><?= h($total['SoVe']) ?></div></section><section><div class="muted">Vé chờ thanh toán (toàn hệ thống)</div><div class="stat"><?= h($pending) ?></div></section></div>
<section><h2>Theo phim</h2><div class="table-wrap"><table><tr><th>Phim</th><th>Số vé đã thanh toán</th><th>Doanh thu</th></tr><?php foreach($rows as $row): ?><tr><td><?= h($row['TenPhim']) ?></td><td><?= h($row['SoVe']) ?></td><td><?= money($row['DoanhThu']) ?></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="3">Chưa có doanh thu trong khoảng ngày này.</td></tr><?php endif; ?></table></div></section><?php pageFooter(); ?>
