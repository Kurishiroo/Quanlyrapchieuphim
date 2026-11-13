<?php
require 'app.php';
$q=input('q',$_GET); $pattern='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
$movies=query("SELECT p.*, (SELECT GROUP_CONCAT(t.TenTheLoai SEPARATOR ', ') FROM phim_theloai pt JOIN theloai t ON t.MaTheLoai=pt.MaTheLoai WHERE pt.MaPhim=p.MaPhim) AS TheLoai FROM phim p WHERE p.TrangThai<>'NgungChieu' AND p.TenPhim LIKE ? ESCAPE '!' ORDER BY p.NgayKhoiChieu DESC,p.MaPhim DESC",[$pattern])->fetchAll();
$shows=query("SELECT s.*,r.TenPhong FROM suatchieu s JOIN phongchieu r ON r.MaPhong=s.MaPhong WHERE s.TrangThai='MoBan' AND s.ThoiGianBatDau>NOW() AND r.TrangThai='HoatDong' ORDER BY s.ThoiGianBatDau")->fetchAll();
$byMovie=[]; foreach($shows as $s) $byMovie[$s['MaPhim']][]=$s;
pageHeader('Phim & lịch chiếu'); ?>
<section class="hero"><div class="eyebrow">Một bộ phim · Một trải nghiệm</div><h1>Hẹn bạn tại rạp.</h1><p>Chọn bộ phim yêu thích, tìm suất chiếu phù hợp và đặt ghế của bạn. Mỗi vé dành cho một ghế trong một suất chiếu.</p><a class="button" href="#phim">Khám phá lịch chiếu</a></section>
<section><form method="get" class="toolbar"><?php field('q','Bạn muốn xem phim gì?',$q,'search',false); ?><button>Tìm phim</button><a href="index.php">Tất cả phim</a></form></section>
<h2 id="phim">Phim & lịch chiếu</h2><div class="cards">
<?php foreach($movies as $movie): ?><article class="card movie-card">
<?php if (!empty($movie['AnhPoster'])): ?><img class="movie-poster" src="<?= h($movie['AnhPoster']) ?>" alt="Poster <?= h($movie['TenPhim']) ?>" loading="lazy" width="400" height="600" onerror="this.style.display='none'">
<?php endif; ?><span class="badge"><?= h(label($movie['TrangThai'])) ?></span><h2><?= h($movie['TenPhim']) ?></h2><p class="muted"><?= h($movie['ThoiLuong']) ?> phút · <?= h($movie['TheLoai']?:'Chưa phân loại') ?></p><p class="prewrap"><?= h($movie['MoTa']) ?></p>
<p class="muted">Khởi chiếu: <?= h($movie['NgayKhoiChieu']?:'Đang cập nhật') ?></p>
<?php foreach($byMovie[$movie['MaPhim']]??[] as $s): ?><a class="button quiet" href="datve.php?suat=<?= h($s['MaSuatChieu']) ?>"><?= h(date('d/m H:i',strtotime($s['ThoiGianBatDau']))) ?> · <?= h($s['TenPhong']) ?> · <?= money($s['GiaVe']) ?></a><?php endforeach; ?>
<?php if(empty($byMovie[$movie['MaPhim']])): ?><p class="muted">Chưa có suất chiếu mở bán.</p><?php endif; ?></article><?php endforeach; ?></div>
<?php if(!$movies): ?><section>Chưa có phim phù hợp. Vui lòng quay lại sau.</section><?php endif; ?>
<?php if(!query("SELECT COUNT(*) FROM taikhoan WHERE VaiTro='QuanTriVien'")->fetchColumn()): ?><section><h2>Bắt đầu sử dụng hệ thống</h2><a href="khoitao.php">Khởi tạo quản trị viên đầu tiên trên localhost</a></section><?php endif; ?>
<?php pageFooter(); ?>
