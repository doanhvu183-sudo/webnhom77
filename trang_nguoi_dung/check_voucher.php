<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$ma = strtoupper(trim($_POST['ma_voucher'] ?? ''));

$stmt = $pdo->prepare("
    SELECT * FROM voucher
    WHERE ma_voucher = ?
      AND trang_thai = 1
      AND so_luot > 0
      AND CURDATE() BETWEEN ngay_bat_dau AND ngay_ket_thuc
");
$stmt->execute([$ma]);
$vc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vc) {
    die('Voucher không hợp lệ');
}

$_SESSION['voucher'] = $vc;
header('Location: gio_hang.php');
