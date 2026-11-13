# Website quản lý vé xem phim

Chạy bằng PHP 8.0+, MariaDB, Apache trong XAMPP. Database hiện có: `QuanLyRapPhim`, giữ nguyên 9 bảng.

## Chạy lần đầu

1. Bật Apache và MySQL trong XAMPP.
2. Mở `http://localhost/quanlyrapphim/`.
3. Chọn **Khởi tạo quản trị viên đầu tiên**, tự nhập email và mật khẩu. Không có mật khẩu mặc định. Trang khởi tạo chỉ dùng trên localhost và tự khóa khi đã có quản trị viên.
4. Đăng nhập tài khoản vừa tạo. Vào **Tài khoản** để thêm nhân viên. Khách hàng có thể tự đăng ký; đăng ký công khai luôn tạo vai trò KhachHang.
5. Tạo **Thể loại → Phim → Phòng → Ghế → Suất chiếu**. Chọn ngày trong tương lai, giờ kết thúc đủ thời lượng phim, không trùng lịch trong cùng phòng. Phòng phải hoạt động, ghế phải sử dụng, suất phải mở bán.
6. Từ trang chủ chọn giờ chiếu, chọn một ghế, tạo vé rồi thanh toán mô phỏng. Mỗi vé là một ghế; muốn thêm ghế, đặt thêm vé.

## Phân quyền

| Chức năng | Khách hàng | Nhân viên | Quản trị viên |
|---|---|---|---|
| Xem phim/lịch chiếu | Có | Có | Có |
| Đặt vé | Cho chính mình | Bán cho khách/vãng lai | Có |
| Xem vé/thanh toán/hủy vé chưa trả tiền | Vé của mình | Tất cả vé | Tất cả vé |
| Tiền mặt mô phỏng | Không | Có | Có |
| Chuyển khoản mô phỏng | Có | Có | Có |
| Soát vé | Không | Có | Có |
| Quản lý phim, thể loại, phòng, ghế, suất chiếu | Không | Không | Có |
| Quản lý tài khoản, thống kê | Không | Không | Có |
| Sửa hồ sơ, đổi mật khẩu | Của mình | Của mình | Của mình |

## Các quy tắc cần trình bày khi thi

- `app.php`: phiên đăng nhập, kiểm tra vai trò từ database mỗi request, CSRF, escape HTML và hàm chạy prepared statement.
- Mật khẩu lưu bằng `password_hash`, đối chiếu bằng `password_verify`.
- `catalog.php` + `admin_catalog.php`: các form CRUD dùng chung; tên bảng/cột lấy từ danh sách cố định trong code. Các file `phim.php`, `theloai.php`, `phong.php`, `ghe.php` chọn loại dữ liệu.
- `suatchieu.php`: khóa bản ghi phòng trong transaction trước khi kiểm tra khoảng thời gian giao nhau: bắt đầu cũ < kết thúc mới và kết thúc cũ > bắt đầu mới. Suất đã có vé chỉ được đổi Mở bán/Đóng bán.
- `ticket_service.php`: transaction, `SELECT ... FOR UPDATE`, kiểm tra ghế đúng phòng, trạng thái và thời gian. `UNIQUE(MaSuatChieu,MaGhe)` ngăn bán trùng ở database. Giá vé luôn lấy từ suất chiếu, không lấy giá từ biểu mẫu.
- Thanh toán thành công cập nhật cả bảng ThanhToan và Ve trong cùng transaction. Gửi lại yêu cầu không thu thêm lần nữa. Thanh toán thất bại có thể thử lại; bảng hiện tại lưu kết quả gần nhất của một vé, không phải lịch sử mọi lần thử.
- Khách chỉ xem và thao tác vé có MaKhachHang của mình. Nhân viên/quản trị viên được bán vé cho khách vãng lai qua MaNhanVien.
- Vé chưa thanh toán giữ ghế đến khi người dùng/nhân viên hủy, chưa có cơ chế tự hết hạn. Hủy xóa đặt chỗ chưa trả tiền và lần thanh toán thất bại liên quan để nhả ghế. Vé đã thanh toán không hủy/hoàn tiền trong phạm vi này.
- Soát vé chỉ cho vé đã thanh toán, từ 30 phút trước giờ chiếu đến giờ kết thúc, không soát hai lần.
- Khóa ngoại chặn xóa dữ liệu đã được dùng. Xóa phim/thể loại chưa bị chặn sẽ xóa liên kết Phim_TheLoai theo CASCADE có sẵn.
- `thongke.php`: JOIN, GROUP BY, COUNT, SUM; chỉ tính thanh toán thành công theo khoảng ngày.
- Không tự xóa database hoặc dữ liệu hiện có. Không tích hợp ngân hàng thật.

## Kiểm thử thủ công

1. Dùng các trình duyệt/phiên riêng cho ba vai trò. Khách/nhân viên nhập URL `taikhoan.php`, `phim.php`, `thongke.php` phải nhận lỗi 403.
2. Khách A đặt ghế A1. Khách B tải lại suất chiếu: A1 không chọn được. Hai người gửi cùng ghế cùng lúc chỉ một người thành công.
3. Khách B thử mở `chitietve.php?id=<vé của A>`: không xem được; gửi POST cũng không sửa được.
4. Thử thanh toán thất bại rồi thành công. Vé chuyển Chờ thanh toán → Đã thanh toán; thống kê chỉ cộng một lần.
5. Nhân viên bán vé vãng lai, thanh toán tiền mặt mô phỏng, soát vé đúng khoảng thời gian. Không soát vé chưa thanh toán/đã sử dụng.
6. Thử lịch chiếu trùng phòng, ghế trùng vị trí, tên phim/email trùng, xóa dữ liệu đã có vé: nhận thông báo lỗi.

## Cấu hình và Git

- `config.local.php` chứa cấu hình máy và mật khẩu, được `.gitignore` bỏ qua. Không sửa mật khẩu thật vào `config.example.php`.
- Khi chuyển sang máy khác, sao chép `config.example.php` thành `config.local.php`, điền kết nối và chuẩn bị database đúng cấu trúc.
- `.htaccess` chặn truy cập web tới file cấu hình, file nội bộ, thư mục `.git` và tài liệu. Chạy qua Apache/XAMPP.
- Lint: `Get-ChildItem *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }`.
- Đây là phạm vi đồ án chạy localhost; chưa bao gồm quên mật khẩu qua email, thanh toán thật, hoàn tiền và giữ ghế tự hết hạn.

## Mã vai trò số

Bảng TaiKhoan có thêm cột `MaVaiTro` tự sinh (GENERATED STORED): 0 = KhachHang, 1 = NhanVien, 2 = QuanTriVien. Khi đổi `VaiTro`, MariaDB tự cập nhật `MaVaiTro`. Không INSERT/UPDATE trực tiếp cột tự sinh. Trang Tài khoản hiển thị cả tên và mã. Logic phân quyền PHP tiếp tục dùng `VaiTro`.
