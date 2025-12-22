<?php
session_start();
require_once "../includes/db.php";

$id = intval($_POST['id_san_pham']);
$qty = intval($_POST['so_luong']);

if ($qty < 1) $qty = 1;

// Lấy sản phẩm từ DB
$stmt = $pdo->prepare("SELECT * FROM sanpham WHERE id_san_pham = ?");
$stmt->execute([$id]);
$sp = $stmt->fetch();

if (!$sp) {
    die("Sản phẩm không tồn tại");
}

// Nếu giỏ chưa tồn tại → tạo
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Nếu sản phẩm đã có trong giỏ
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['so_luong'] += $qty;
} else {
    $_SESSION['cart'][$id] = [
        'id' => $sp['id_san_pham'],
        'ten' => $sp['ten_san_pham'],
        'gia' => $sp['gia'],
        'anh' => $sp['hinh_anh'],
        'so_luong' => $qty
    ];
}

header("Location: gio_hang.php");
exit;
