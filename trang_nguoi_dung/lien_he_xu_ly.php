<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$ho_ten   = trim($_POST['ho_ten'] ?? '');
$email    = trim($_POST['email'] ?? '');
$tieu_de  = trim($_POST['tieu_de'] ?? '');
$noi_dung = trim($_POST['noi_dung'] ?? '');

if (!$ho_ten || !$email || !$tieu_de || !$noi_dung) {
    $_SESSION['lien_he_err'] = 'Vui lòng điền đầy đủ thông tin';
    header('Location: lien_he.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO lienhe
        (ho_ten, email, tieu_de, noi_dung)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$ho_ten, $email, $tieu_de, $noi_dung]);

    $_SESSION['lien_he_ok'] = 'Gửi liên hệ thành công! Chúng tôi sẽ phản hồi sớm.';
} catch (Exception $e) {
    $_SESSION['lien_he_err'] = 'Có lỗi xảy ra, vui lòng thử lại.';
}

header('Location: lien_he.php');
exit;
