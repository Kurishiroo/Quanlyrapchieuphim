<?php
require 'app.php'; requireLogin(['QuanTriVien']); require 'catalog.php';
if (!isset($entity, $catalog[$entity])) { http_response_code(404); exit('Không tìm thấy trang.'); }
$cfg = $catalog[$entity];
$table = $cfg['table']; $pk = $cfg['pk']; $fields = $cfg['fields'];
$error = ''; $id = 0; $data = []; $selectedGenres = [];
$rooms = query('SELECT MaPhong,TenPhong FROM phongchieu ORDER BY TenPhong')->fetchAll(PDO::FETCH_KEY_PAIR);
$genres = query('SELECT MaTheLoai,TenTheLoai FROM theloai ORDER BY TenTheLoai')->fetchAll(PDO::FETCH_KEY_PAIR);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    try {
        $id = input('id') === '0' ? 0 : positiveInt(input('id'));
        $action = input('action');
        if (!in_array($action,['save','delete'],true)) throw new RuntimeException('Thao tác không hợp lệ.');
        $pdo->beginTransaction();
        $old = $id ? query("SELECT * FROM $table WHERE $pk=? FOR UPDATE",[$id])->fetch() : null;
        if ($id && !$old) throw new RuntimeException('Dữ liệu không còn tồn tại.');
        if ($action === 'delete') {
            if (!$id) throw new RuntimeException('Thiếu mã cần xóa.');
            query("DELETE FROM $table WHERE $pk=?",[$id]);
        } else {
            $data = validateCatalog($fields);
            if ($entity === 'phim') {
                if ($data['NgayKhoiChieu'] && $data['NgayKetThuc'] && $data['NgayKetThuc'] < $data['NgayKhoiChieu']) throw new RuntimeException('Ngày kết thúc phải từ ngày khởi chiếu trở đi.');
                if ($old && query('SELECT COUNT(*) FROM suatchieu WHERE MaPhim=?',[$id])->fetchColumn() &&
                    ($old['ThoiLuong'] != $data['ThoiLuong'] || $old['NgayKhoiChieu'] != $data['NgayKhoiChieu'] || $old['NgayKetThuc'] != $data['NgayKetThuc'])) {
                    throw new RuntimeException('Phim đã có suất chiếu: giữ nguyên thời lượng và khoảng ngày chiếu để bảo toàn lịch.');
                }
                $selectedGenres = $_POST['TheLoai'] ?? [];
                if (!is_array($selectedGenres)) throw new RuntimeException('Thể loại không hợp lệ.');
                foreach ($selectedGenres as $genre) {
                    if (!is_string($genre) || !isset($genres[$genre])) throw new RuntimeException('Thể loại không hợp lệ.');
                }
                $selectedGenres = array_unique($selectedGenres);
            }
            if ($entity === 'ghe') {
                if (!preg_match('/^[A-Z]$/',$data['HangGhe'])) throw new RuntimeException('Hàng ghế là một chữ in hoa A–Z.');
                if (!query('SELECT MaPhong FROM phongchieu WHERE MaPhong=? FOR UPDATE',[$data['MaPhong']])->fetch()) throw new RuntimeException('Phòng không tồn tại.');
                if ($old && query('SELECT COUNT(*) FROM ve WHERE MaGhe=?',[$id])->fetchColumn() &&
                    ($old['MaPhong'] != $data['MaPhong'] || $old['HangGhe'] != $data['HangGhe'] || $old['SoGhe'] != $data['SoGhe'])) throw new RuntimeException('Ghế đã có vé: không được thay đổi phòng hoặc vị trí.');
            }
            if ($id) {
                $sets = implode(',',array_map(function($key){return "$key=?";},array_keys($data)));
                query("UPDATE $table SET $sets WHERE $pk=?",array_merge(array_values($data),[$id]));
            } else {
                $columns = implode(',',array_keys($data));
                $marks = implode(',',array_fill(0,count($data),'?'));
                query("INSERT INTO $table ($columns) VALUES ($marks)",array_values($data));
                $id = (int) $pdo->lastInsertId();
            }
            if ($entity === 'phim') {
                query('DELETE FROM phim_theloai WHERE MaPhim=?',[$id]);
                foreach ($selectedGenres as $genre) query('INSERT INTO phim_theloai (MaPhim,MaTheLoai) VALUES (?,?)',[$id,$genre]);
            }
        }
        $pdo->commit(); flash($action === 'delete' ? 'Đã xóa dữ liệu.' : 'Đã lưu dữ liệu.'); redirect($entity . '.php');
    } catch (Throwable $e) {
        $error = errorMessage($e);
        $data = $_POST;
        if (!is_array($selectedGenres)) $selectedGenres = [];
    }
} elseif (input('sua',$_GET) !== '') {
    try {
        $id = positiveInt(input('sua',$_GET));
        $data = query("SELECT * FROM $table WHERE $pk=?",[$id])->fetch();
        if (!$data) { $id=0; $data=[]; throw new RuntimeException('Không tìm thấy dữ liệu.'); }
        if ($entity === 'phim') $selectedGenres = query('SELECT MaTheLoai FROM phim_theloai WHERE MaPhim=?',[$id])->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $error = errorMessage($e); }
}
$q = input('q',$_GET);
$pattern = '%' . strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']) . '%';
$rows = query("SELECT * FROM $table WHERE {$cfg['search']} LIKE ? ESCAPE '!' ORDER BY $pk DESC",[$pattern])->fetchAll();
pageHeader($cfg['title']); ?>
<h1><?= h($cfg['title']) ?></h1><?php showError($error); ?>
<section><h2><?= $id ? 'Sửa #' . h($id) : 'Thêm mới' ?></h2><form method="post"><?php csrf(); ?>
<input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="action" value="save"><div class="grid">
<?php foreach ($fields as $key=>$field): [$title,$type,$required] = $field; $value = isset($data[$key]) && is_scalar($data[$key]) ? $data[$key] : '';
    if ($type==='select') { $options=[]; foreach($field[3] as $option) $options[$option]=label($option); selectField($key,$title,$options,$value); }
    elseif ($type==='room') selectField($key,$title,$rooms,$value);
    elseif ($type==='textarea') { ?><label class="full"><?= h($title) ?><textarea name="<?= h($key) ?>"><?= h($value) ?></textarea></label><?php }
    else field($key,$title,$value,$type,$required);
endforeach; ?>
<?php if ($entity==='phim'): ?><div class="full"><strong>Thể loại</strong><p class="muted">Có thể chọn nhiều thể loại. Tạo thể loại ở menu Thể loại nếu danh sách trống.</p>
<?php foreach($genres as $key=>$name): ?><label class="inline"><input type="checkbox" name="TheLoai[]" value="<?= h($key) ?>" <?= in_array($key,$selectedGenres) ? 'checked' : '' ?>><?= h($name) ?></label> <?php endforeach; ?></div><?php endif; ?>
</div><button>Lưu</button><a href="<?= h($entity) ?>.php">Làm mới / hủy sửa</a></form></section>
<section><form method="get" class="toolbar"><?php field('q',$entity==='ghe'?'Tìm theo hàng ghế':'Tìm theo tên',$q,'text',false); ?><button>Tìm kiếm</button><a href="<?= h($entity) ?>.php">Tất cả</a></form>
<div class="table-wrap"><table><thead><tr><th>Mã</th><?php foreach($fields as $key=>$field): if(in_array($key,['MoTa','AnhPoster'],true)) continue; ?><th><?= h($field[0]) ?></th><?php endforeach; ?><th>Thao tác</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= h($row[$pk]) ?></td><?php foreach($fields as $key=>$field): if(in_array($key,['MoTa','AnhPoster'],true)) continue; ?><td><?= h($key==='MaPhong' ? ($rooms[$row[$key]] ?? $row[$key]) : label($row[$key] ?? '—')) ?></td><?php endforeach; ?>
<td class="actions"><a href="<?= h($entity) ?>.php?sua=<?= h($row[$pk]) ?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa dữ liệu này? Liên kết thể loại liên quan cũng sẽ bị xóa.');"><?php csrf(); ?><input type="hidden" name="id" value="<?= h($row[$pk]) ?>"><input type="hidden" name="action" value="delete"><button class="danger">Xóa</button></form></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="10">Chưa có dữ liệu phù hợp.</td></tr><?php endif; ?></tbody></table></div></section><?php pageFooter(); ?>
