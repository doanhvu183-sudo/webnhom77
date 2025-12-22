<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$id   = (int)$_POST['id'];
$size = (int)$_POST['size'];
$qty  = (int)$_POST['qty'];

$stmt = $pdo->prepare("SELECT * FROM sanpham WHERE id_san_pham=?");
$stmt->execute([$id]);
$sp = $stmt->fetch();

if (!$sp) die('SP không tồn tại');

$orderId = 'OD'.time().rand(100,999);

$_SESSION['cart'][$orderId] = [
    'created_at' => date('Y-m-d H:i'),
    'items' => [[
        'id' => $sp['id_san_pham'],
        'ten' => $sp['ten_san_pham'],
        'gia' => $sp['gia'],
        'qty' => $qty,
        'size' => $size,
        'hinh_anh' => $sp['hinh_anh']
    ]]
];

$_SESSION['msg'] = 'Đã thêm vào giỏ hàng';

header("Location: chi_tiet_san_pham.php?id=$id");
