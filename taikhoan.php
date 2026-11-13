<?php
require 'app.php'; require 'auth_helpers.php';
$me = requireLogin(['QuanTriVien']); $error=''; $id=0; $data=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    checkCsrf(); $locked=false;
    try {
        $id = input('id') === '0' ? 0 : positiveInt(input('id'));
        $locked = (bool) query("SELECT GET_LOCK('quanlyrapphim_accounts',5)")->fetchColumn();
        if (!$locked) throw new RuntimeException('Tài khoản đang được cập nhật. Vui lòng thử lại.');
        $pdo->beginTransaction();
        $old = $id ? query('SELECT * FROM taikhoan WHERE MaTaiKhoan=? FOR UPDATE',[$id])->fetch() : null;
        if ($id && !$old) throw new RuntimeException('Tài khoản không tồn tại.');
        $action = input('action');
        if (!in_array($action,['save','delete'],true)) throw new RuntimeException('Thao tác không hợp lệ.');
        if ($id == $me['MaTaiKhoan'] && ($action==='delete' || input('VaiTro')!=='QuanTriVien' || input('TrangThai')!=='HoatDong')) throw new RuntimeException('Không được xóa, khóa hoặc hạ quyền chính bạn.');
        if ($old && $old['VaiTro']==='QuanTriVien' && $old['TrangThai']==='HoatDong' &&
            ($action==='delete' || input('VaiTro')!=='QuanTriVien' || input('TrangThai')!=='HoatDong') &&
            query("SELECT COUNT(*) FROM taikhoan WHERE VaiTro='QuanTriVien' AND TrangThai='HoatDong'")->fetchColumn() <= 1) throw new RuntimeException('Phải giữ ít nhất một quản trị viên hoạt động.');
        if ($action==='delete') {
            if (!$id) throw new RuntimeException('Thiếu mã tài khoản.');
            query('DELETE FROM taikhoan WHERE MaTaiKhoan=?',[$id]);
        } else {
            $password = is_string($_POST['MatKhau'] ?? null) ? $_POST['MatKhau'] : '';
            validateAccount(input('HoTen'),input('Email'),input('SoDienThoai'),$password,!$id);
            if (!in_array(input('VaiTro'),['KhachHang','NhanVien','QuanTriVien'],true) || !in_array(input('TrangThai'),['HoatDong','BiKhoa'],true)) throw new RuntimeException('Vai trò hoặc trạng thái không hợp lệ.');
            $params=[input('HoTen'),input('Email'),$password!=='' ? password_hash($password,PASSWORD_DEFAULT) : $old['MatKhau'],input('SoDienThoai') ?: null,input('VaiTro'),input('TrangThai')];
            if ($id) { $params[]=$id; query('UPDATE taikhoan SET HoTen=?,Email=?,MatKhau=?,SoDienThoai=?,VaiTro=?,TrangThai=? WHERE MaTaiKhoan=?',$params); }
            else query('INSERT INTO taikhoan (HoTen,Email,MatKhau,SoDienThoai,VaiTro,TrangThai) VALUES (?,?,?,?,?,?)',$params);
        }
        $pdo->commit(); flash('Đã cập nhật tài khoản.');
    } catch(Throwable $e) { $error=errorMessage($e); $data=$_POST; }
    finally { if($locked) query("SELECT RELEASE_LOCK('quanlyrapphim_accounts')"); }
    if($error==='') redirect('taikhoan.php');
} elseif (input('sua',$_GET)!=='') {
    $data=query('SELECT MaTaiKhoan,HoTen,Email,SoDienThoai,VaiTro,TrangThai FROM taikhoan WHERE MaTaiKhoan=?',[input('sua',$_GET)])->fetch();
    if($data) $id=$data['MaTaiKhoan']; else { $data=[]; $error='Không tìm thấy tài khoản.'; }
}
$q=input('q',$_GET); $pattern='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
$rows=query("SELECT MaTaiKhoan,HoTen,Email,SoDienThoai,VaiTro,MaVaiTro,TrangThai FROM taikhoan WHERE HoTen LIKE ? ESCAPE '!' OR Email LIKE ? ESCAPE '!' ORDER BY MaTaiKhoan DESC",[$pattern,$pattern])->fetchAll();
pageHeader('Tài khoản'); ?><h1>Quản lý tài khoản</h1><?php showError($error); ?><section><h2><?= $id?'Sửa tài khoản #'.h($id):'Thêm tài khoản' ?></h2><form method="post"><?php csrf(); ?><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="action" value="save"><div class="grid">
<?php foreach(['HoTen'=>'Họ tên','Email'=>'Email','SoDienThoai'=>'Số điện thoại'] as $key=>$title) field($key,$title,is_scalar($data[$key]??'')?($data[$key]??''):'',$key==='Email'?'email':'text',$key!=='SoDienThoai');
field('MatKhau',$id?'Mật khẩu mới (để trống để giữ nguyên)':'Mật khẩu','','password',!$id);
selectField('VaiTro','Vai trò',['KhachHang'=>'0 — Khách hàng','NhanVien'=>'1 — Nhân viên','QuanTriVien'=>'2 — Quản trị viên'],input('VaiTro',$data)?:'KhachHang');
selectField('TrangThai','Trạng thái',['HoatDong'=>'Hoạt động','BiKhoa'=>'Bị khóa'],input('TrangThai',$data)?:'HoatDong'); ?></div><button>Lưu tài khoản</button><a href="taikhoan.php">Hủy / thêm mới</a></form></section>
<section><form method="get" class="toolbar"><?php field('q','Tìm tên hoặc email',$q,'text',false); ?><button>Tìm</button></form><div class="table-wrap"><table><tr><th>Mã</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Vai trò</th><th>Mã vai trò</th><th>Trạng thái</th><th>Thao tác</th></tr>
<?php foreach($rows as $row): ?><tr><?php foreach($row as $value): ?><td><?= h(label($value??'—')) ?></td><?php endforeach; ?><td><a href="taikhoan.php?sua=<?= h($row['MaTaiKhoan']) ?>">Sửa</a><?php if($row['MaTaiKhoan']!=$me['MaTaiKhoan']): ?><form method="post" onsubmit="return confirm('Xóa tài khoản này? Tài khoản đã có vé chỉ có thể khóa.');"><?php csrf(); ?><input type="hidden" name="id" value="<?= h($row['MaTaiKhoan']) ?>"><input type="hidden" name="action" value="delete"><button class="danger">Xóa</button></form><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8">Không có tài khoản phù hợp.</td></tr><?php endif; ?></table></div></section><?php pageFooter(); ?>
