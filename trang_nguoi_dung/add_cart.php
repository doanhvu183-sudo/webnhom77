<?php
session_start();

$id = $_POST['id'] ?? 0;
$ten = $_POST['ten'] ?? '';
$gia = $_POST['gia'] ?? 0;
$hinh = $_POST['hinh'] ?? '';
$sl  = $_POST['so_luong'] ?? 1;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Nếu sản phẩm đã có trong giỏ → tăng số lượng
if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['so_luong'] += $sl;
} else {
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'ten' => $ten,
        'gia' => $gia,
        'hinh' => $hinh,
        'so_luong' => $sl
    ];
}

echo "OK";
