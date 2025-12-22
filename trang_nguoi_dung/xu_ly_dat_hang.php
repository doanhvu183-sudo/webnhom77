<?php
session_start();
require_once "../includes/db.php";

// Lấy giỏ hàng
$cart = $_SESSION["cart"] ?? [];

if (empty($cart)) {
    die("Giỏ hàng trống!");
}

// Lấy dữ liệu form
$ho_ten = $_POST["ho_ten"];
$sdt    = $_POST["sdt"];
$email  = $_POST["email"]; // Email không lưu vào donhang, chỉ dùng gửi thông báo
$dia_chi = $_POST["dia_chi"];
$payment = $_POST["payment"];
$tong_tien = $_POST["tong_tien"];

// ID user nếu đăng nhập
$id_user = $_SESSION["user"]["id"] ?? null;

// Tạo mã đơn hàng
$ma_don = "DH" . time();

// INSERT ĐƠN HÀNG (theo đúng cấu trúc bảng của bạn)
$ins = $pdo->prepare("
    INSERT INTO donhang 
    (id_nguoi_dung, ma_don_hang, tong_tien, trang_thai, phuong_thuc, ngay_dat, ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan)
    VALUES (?,?,?,?,?,NOW(),?,?,?)
");

$ins->execute([
    $id_user,
    $ma_don,
    $tong_tien,
    "Chờ xác nhận",
    $payment,
    $ho_ten,
    $sdt,
    $dia_chi
]);

// Lấy id đơn hàng vừa tạo
$id_don = $pdo->lastInsertId();

// Lưu chi tiết đơn hàng
$ins_ct = $pdo->prepare("
    INSERT INTO chitiet_donhang (id_don_hang, id_san_pham, so_luong, don_gia)
    VALUES (?,?,?,?)
");

foreach ($cart as $item) {
    $ins_ct->execute([
        $id_don,
        $item["id"],
        $item["so_luong"],
        $item["gia"]
    ]);
}


// Xóa giỏ hàng sau đặt
unset($_SESSION["cart"]);

// Chuyển sang trang cảm ơn
header("Location: thank_you.php?id=" . $id_don);
exit;
