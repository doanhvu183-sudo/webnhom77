<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$ho_ten = trim($_POST['ho'].' '.$_POST['ten']);
$ten_dang_nhap = trim($_POST['ten_dang_nhap']);
$email = trim($_POST['email']);
$so_dien_thoai = $_POST['so_dien_thoai'] ?? null;
$dia_chi = $_POST['dia_chi'] ?? null;
$gioi_tinh = $_POST['gioi_tinh'] ?? null;
$ngay_sinh = $_POST['ngay_sinh'] ?? null;

$mat_khau = $_POST['mat_khau'];
$xac_nhan = $_POST['xac_nhan_mat_khau'];

if ($mat_khau !== $xac_nhan) {
    die("<script>alert('Mật khẩu không khớp');history.back();</script>");
}

$hash = password_hash($mat_khau, PASSWORD_DEFAULT);

$check = $pdo->prepare("
    SELECT 1 FROM nguoidung 
    WHERE email=? OR ten_dang_nhap=?
");
$check->execute([$email, $ten_dang_nhap]);

if ($check->fetch()) {
    die("<script>alert('Email hoặc tên đăng nhập đã tồn tại');history.back();</script>");
}

$sql = "
INSERT INTO nguoidung
(ten_dang_nhap, mat_khau, ho_ten, email, so_dien_thoai, dia_chi, gioi_tinh, ngay_sinh, vai_tro)
VALUES (?,?,?,?,?,?,?,?, 'khach')
";

$pdo->prepare($sql)->execute([
    $ten_dang_nhap,
    $hash,
    $ho_ten,
    $email,
    $so_dien_thoai,
    $dia_chi,
    $gioi_tinh,
    $ngay_sinh
]);

echo "<script>alert('Đăng ký thành công');location.href='dang_nhap.php';</script>";
