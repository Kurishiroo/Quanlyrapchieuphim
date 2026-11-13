<?php
// Danh sách cố định do lập trình viên khai báo, không lấy tên bảng/cột từ người dùng.
// Cấu trúc trường: nhãn, kiểu, bắt buộc, giới hạn hoặc các lựa chọn.
$catalog = [
    'phim' => ['title'=>'Quản lý phim','table'=>'phim','pk'=>'MaPhim','search'=>'TenPhim','fields'=>[
        'TenPhim'=>['Tên phim','text',true,100], 'MoTa'=>['Mô tả','textarea',false,65535],
        'AnhPoster'=>['Đường dẫn poster (images/posters/ten-anh.jpg hoặc https://...)','text',false,500],
        'ThoiLuong'=>['Thời lượng (phút)','number',true,2147483647],
        'NgayKhoiChieu'=>['Ngày khởi chiếu','date',false], 'NgayKetThuc'=>['Ngày kết thúc','date',false],
        'TrangThai'=>['Trạng thái','select',true,['SapChieu','DangChieu','NgungChieu']]]],
    'theloai' => ['title'=>'Quản lý thể loại','table'=>'theloai','pk'=>'MaTheLoai','search'=>'TenTheLoai','fields'=>[
        'TenTheLoai'=>['Tên thể loại','text',true,100], 'MoTa'=>['Mô tả','textarea',false,65535]]],
    'phong' => ['title'=>'Quản lý phòng chiếu','table'=>'phongchieu','pk'=>'MaPhong','search'=>'TenPhong','fields'=>[
        'TenPhong'=>['Tên phòng','text',true,50], 'LoaiPhong'=>['Loại phòng','text',true,30],
        'TrangThai'=>['Trạng thái','select',true,['HoatDong','BaoTri','NgungHoatDong']]]],
    'ghe' => ['title'=>'Quản lý ghế','table'=>'ghe','pk'=>'MaGhe','search'=>'HangGhe','fields'=>[
        'MaPhong'=>['Phòng','room',true], 'HangGhe'=>['Hàng ghế (A–Z)','text',true,1],
        'SoGhe'=>['Số ghế','number',true,2147483647], 'LoaiGhe'=>['Loại ghế','select',true,['Thuong','VIP']],
        'TrangThai'=>['Trạng thái','select',true,['SuDung','BaoTri']]]],
];

function validateCatalog($fields) {
    $data = [];
    foreach ($fields as $key=>$field) {
        [$title,$type,$required] = $field;
        $value = input($key);
        if ($required && $value === '') throw new RuntimeException('Vui lòng nhập ' . $title . '.');
        if ($value !== '') {
            if (in_array($type,['text','textarea'],true) && mb_strlen($value) > $field[3]) throw new RuntimeException($title . ' quá dài.');
            if ($type === 'textarea' && strlen($value) > 65535) throw new RuntimeException('Mô tả vượt quá 65535 byte.');
            if (in_array($type,['number','room'],true)) positiveInt($value);
            if ($type === 'date' && !validDate($value)) throw new RuntimeException($title . ' không hợp lệ.');
            if ($type === 'select' && !in_array($value,$field[3],true)) throw new RuntimeException($title . ' không hợp lệ.');
        }
        if ($key === 'AnhPoster' && $value !== '' && !preg_match('~^(https://[^\s]+|images/posters/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp))$~i', $value)) throw new RuntimeException('Poster phải là URL HTTPS hoặc đường dẫn ảnh trong images/posters/.');
        $data[$key] = $value === '' ? null : $value;
    }
    return $data;
}
