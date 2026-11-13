<?php
require 'app.php'; $user=requireLogin();
$q=input('q',$_GET); $pattern='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
$sql="SELECT v.*,p.TenPhim,r.TenPhong,g.HangGhe,g.SoGhe,s.ThoiGianBatDau,k.HoTen AS KhachHang FROM ve v JOIN suatchieu s ON s.MaSuatChieu=v.MaSuatChieu JOIN phim p ON p.MaPhim=s.MaPhim JOIN phongchieu r ON r.MaPhong=s.MaPhong JOIN ghe g ON g.MaGhe=v.MaGhe LEFT JOIN taikhoan k ON k.MaTaiKhoan=v.MaKhachHang WHERE (p.TenPhim LIKE ? ESCAPE '!' OR v.MaVe=?)";
$params=[$pattern,ctype_digit($q)?$q:0];
if(!isStaff()){$sql.=' AND v.MaKhachHang=?';$params[]=$user['MaTaiKhoan'];}
$sql.=' ORDER BY v.MaVe DESC';$rows=query($sql,$params)->fetchAll();
pageHeader(isStaff()?'Bán vé & tra cứu':'Vé của tôi'); ?><h1><?= isStaff()?'Bán vé & tra cứu':'Vé của tôi' ?></h1><p><a class="button" href="index.php">Chọn suất chiếu để <?= isStaff()?'bán vé':'đặt vé' ?></a></p><section><form method="get" class="toolbar"><?php field('q','Tìm tên phim hoặc mã vé',$q,'search',false); ?><button>Tìm</button><a href="ve.php">Tất cả vé</a></form><div class="table-wrap"><table><tr><th>Mã vé</th><th>Phim</th><th>Suất chiếu</th><th>Phòng / Ghế</th><?php if(isStaff()): ?><th>Khách hàng</th><?php endif; ?><th>Giá vé</th><th>Trạng thái</th><th>Chi tiết</th></tr>
<?php foreach($rows as $row): ?><tr><td>#<?= h($row['MaVe']) ?></td><td><?= h($row['TenPhim']) ?></td><td><?= h($row['ThoiGianBatDau']) ?></td><td><?= h($row['TenPhong'].' / '.$row['HangGhe'].$row['SoGhe']) ?></td><?php if(isStaff()): ?><td><?= h($row['KhachHang']?:'Khách vãng lai') ?></td><?php endif; ?><td><?= money($row['GiaVe']) ?></td><td><span class="badge"><?= h(label($row['TrangThai'])) ?></span></td><td><a href="chitietve.php?id=<?= h($row['MaVe']) ?>">Xem vé</a></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8">Chưa có vé phù hợp.</td></tr><?php endif; ?></table></div></section><?php pageFooter(); ?>
