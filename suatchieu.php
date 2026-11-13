<?php
require 'app.php'; requireLogin(['QuanTriVien']);
$error=''; $id=0; $data=[];
$movies=query('SELECT MaPhim,TenPhim FROM phim ORDER BY TenPhim')->fetchAll(PDO::FETCH_KEY_PAIR);
$rooms=query('SELECT MaPhong,TenPhong FROM phongchieu ORDER BY TenPhong')->fetchAll(PDO::FETCH_KEY_PAIR);
if($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf();
    try {
        $id=input('id')==='0'?0:positiveInt(input('id'));
        $action=input('action');
        if(!in_array($action,['save','delete'],true)) throw new RuntimeException('Thao tác không hợp lệ.');
        $pdo->beginTransaction();
        // Khóa phòng: hai yêu cầu tạo lịch đồng thời không thể tạo lịch trùng.
        $roomId=positiveInt(input('MaPhong'));
        $room=query('SELECT * FROM phongchieu WHERE MaPhong=? FOR UPDATE',[$roomId])->fetch();
        if(!$room) throw new RuntimeException('Phòng không tồn tại.');
        $old=$id?query('SELECT * FROM suatchieu WHERE MaSuatChieu=? FOR UPDATE',[$id])->fetch():null;
        if($id && !$old) throw new RuntimeException('Suất chiếu không tồn tại.');
        if($action==='delete') {
            if(!$id) throw new RuntimeException('Thiếu mã suất chiếu.');
            query('DELETE FROM suatchieu WHERE MaSuatChieu=?',[$id]);
        } else {
            $movieId=positiveInt(input('MaPhim'));
            $movie=query('SELECT * FROM phim WHERE MaPhim=? FOR UPDATE',[$movieId])->fetch();
            if(!$movie) throw new RuntimeException('Phim không tồn tại.');
            $start=input('ThoiGianBatDau'); $end=input('ThoiGianKetThuc'); $price=input('GiaVe'); $status=input('TrangThai');
            if(!validDate($start,'Y-m-d\TH:i') || !validDate($end,'Y-m-d\TH:i') || $end<=$start) throw new RuntimeException('Thời gian chiếu không hợp lệ; kết thúc phải sau bắt đầu.');
            if(!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$price)) throw new RuntimeException('Giá vé từ 0 đến 99999999.99.');
            if(!in_array($status,['MoBan','DongBan','DaHuy'],true)) throw new RuntimeException('Trạng thái không hợp lệ.');
            $startSql=str_replace('T',' ',$start).':00'; $endSql=str_replace('T',' ',$end).':00';
            $hasTickets=$id && query('SELECT COUNT(*) FROM ve WHERE MaSuatChieu=?',[$id])->fetchColumn()>0;
            if($hasTickets && ($old['MaPhim']!=$movieId || $old['MaPhong']!=$roomId || $old['ThoiGianBatDau']!==$startSql || $old['ThoiGianKetThuc']!==$endSql || (float)$old['GiaVe']!==(float)$price || $status==='DaHuy')) throw new RuntimeException('Suất đã có vé: chỉ được chuyển Mở bán / Đóng bán, không đổi lịch, giá hoặc hủy.');
            if(!$hasTickets && $startSql<=date('Y-m-d H:i:s')) throw new RuntimeException('Thời gian bắt đầu phải ở tương lai.');
            if(strtotime($endSql)-strtotime($startSql)<(int)$movie['ThoiLuong']*60) throw new RuntimeException('Khoảng thời gian chiếu ngắn hơn thời lượng phim.');
            if(($movie['NgayKhoiChieu'] && substr($start,0,10)<$movie['NgayKhoiChieu']) || ($movie['NgayKetThuc'] && substr($start,0,10)>$movie['NgayKetThuc'])) throw new RuntimeException('Suất chiếu nằm ngoài khoảng ngày chiếu của phim.');
            if($status==='MoBan' && ($room['TrangThai']!=='HoatDong' || $movie['TrangThai']==='NgungChieu')) throw new RuntimeException('Chỉ mở bán khi phòng hoạt động và phim chưa ngừng chiếu.');
            if($status!=='DaHuy' && query("SELECT MaSuatChieu FROM suatchieu WHERE MaPhong=? AND MaSuatChieu<>? AND TrangThai<>'DaHuy' AND ThoiGianBatDau<? AND ThoiGianKetThuc>? LIMIT 1",[$roomId,$id,$endSql,$startSql])->fetch()) throw new RuntimeException('Lịch chiếu bị trùng với suất khác trong cùng phòng.');
            $params=[$movieId,$roomId,$startSql,$endSql,$price,$status];
            if($id){$params[]=$id;query('UPDATE suatchieu SET MaPhim=?,MaPhong=?,ThoiGianBatDau=?,ThoiGianKetThuc=?,GiaVe=?,TrangThai=? WHERE MaSuatChieu=?',$params);}
            else query('INSERT INTO suatchieu (MaPhim,MaPhong,ThoiGianBatDau,ThoiGianKetThuc,GiaVe,TrangThai) VALUES (?,?,?,?,?,?)',$params);
        }
        $pdo->commit();flash('Đã cập nhật suất chiếu.');redirect('suatchieu.php');
    }catch(Throwable $e){$error=errorMessage($e);$data=$_POST;}
}elseif(input('sua',$_GET)!==''){
    $data=query('SELECT * FROM suatchieu WHERE MaSuatChieu=?',[input('sua',$_GET)])->fetch();
    if($data){$id=$data['MaSuatChieu'];foreach(['ThoiGianBatDau','ThoiGianKetThuc'] as $key)$data[$key]=str_replace(' ','T',substr($data[$key],0,16));}
    else {$data=[];$error='Không tìm thấy suất chiếu.';}
}
$q=input('q',$_GET);$pattern='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
$rows=query("SELECT s.*,p.TenPhim,r.TenPhong FROM suatchieu s JOIN phim p ON p.MaPhim=s.MaPhim JOIN phongchieu r ON r.MaPhong=s.MaPhong WHERE p.TenPhim LIKE ? ESCAPE '!' ORDER BY s.ThoiGianBatDau DESC",[$pattern])->fetchAll();
pageHeader('Suất chiếu'); ?><h1>Quản lý suất chiếu</h1><?php showError($error); ?><section><h2><?= $id?'Sửa suất #'.h($id):'Thêm suất chiếu' ?></h2><form method="post"><?php csrf(); ?><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="action" value="save"><div class="grid"><?php
selectField('MaPhim','Phim',$movies,input('MaPhim',$data));selectField('MaPhong','Phòng',$rooms,input('MaPhong',$data));
field('ThoiGianBatDau','Bắt đầu',input('ThoiGianBatDau',$data),'datetime-local');field('ThoiGianKetThuc','Kết thúc',input('ThoiGianKetThuc',$data),'datetime-local');
field('GiaVe','Giá vé (đồng)',input('GiaVe',$data));selectField('TrangThai','Trạng thái',['MoBan'=>'Mở bán','DongBan'=>'Đóng bán','DaHuy'=>'Đã hủy'],input('TrangThai',$data)?:'MoBan'); ?></div><button>Lưu suất chiếu</button><a href="suatchieu.php">Hủy / thêm mới</a></form></section>
<section><form method="get" class="toolbar"><?php field('q','Tìm theo tên phim',$q,'text',false); ?><button>Tìm</button></form><div class="table-wrap"><table><tr><th>Mã</th><th>Phim / Phòng</th><th>Bắt đầu</th><th>Kết thúc</th><th>Giá</th><th>Trạng thái</th><th>Thao tác</th></tr>
<?php foreach($rows as $row): ?><tr><td><?= h($row['MaSuatChieu']) ?></td><td><?= h($row['TenPhim']) ?><br><small><?= h($row['TenPhong']) ?></small></td><td><?= h($row['ThoiGianBatDau']) ?></td><td><?= h($row['ThoiGianKetThuc']) ?></td><td><?= money($row['GiaVe']) ?></td><td><?= h(label($row['TrangThai'])) ?></td><td><a href="suatchieu.php?sua=<?= h($row['MaSuatChieu']) ?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa suất chiếu chưa có vé này?');"><?php csrf(); ?><input type="hidden" name="id" value="<?= h($row['MaSuatChieu']) ?>"><input type="hidden" name="MaPhong" value="<?= h($row['MaPhong']) ?>"><input type="hidden" name="action" value="delete"><button class="danger">Xóa</button></form></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="7">Chưa có suất chiếu phù hợp.</td></tr><?php endif; ?></table></div></section><?php pageFooter(); ?>
