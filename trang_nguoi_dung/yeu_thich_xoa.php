<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header('Location: dang_nhap.php');
    exit;
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id'];
$id_san_pham   = (int)($_GET['id'] ?? 0);

if ($id_san_pham > 0) {
    $stmt = $pdo->prepare("
        DELETE FROM yeu_thich
        WHERE id_nguoi_dung = ?
          AND id_san_pham = ?
    ");
    $stmt->execute([$id_nguoi_dung, $id_san_pham]);
}

header('Location: yeu_thich.php');
exit;
