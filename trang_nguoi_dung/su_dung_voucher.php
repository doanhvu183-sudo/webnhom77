<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$ma = $_GET['ma'] ?? '';
if (!$ma) header("Location: trang_chu.php");

$stmt = $pdo->prepare("SELECT * FROM voucher WHERE ma_voucher=? AND trang_thai=1");
$stmt->execute([$ma]);
$vc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($vc) {
    $_SESSION['voucher'] = $vc;
}

header("Location: trang_chu.php");
exit;
