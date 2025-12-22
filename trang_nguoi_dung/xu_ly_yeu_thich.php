<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    echo json_encode(['status' => 'login']);
    exit;
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id'];
$id_san_pham   = (int)($_POST['id_san_pham'] ?? 0);

if ($id_san_pham <= 0) {
    echo json_encode(['status' => 'error']);
    exit;
}

/* kiểm tra đã yêu thích chưa */
$stmt = $pdo->prepare("
    SELECT 1 FROM yeu_thich
    WHERE id_nguoi_dung = ? AND id_san_pham = ?
");
$stmt->execute([$id_nguoi_dung, $id_san_pham]);

if ($stmt->fetch()) {
    // xoá yêu thích
    $del = $pdo->prepare("
        DELETE FROM yeu_thich
        WHERE id_nguoi_dung = ? AND id_san_pham = ?
    ");
    $del->execute([$id_nguoi_dung, $id_san_pham]);

    echo json_encode(['status' => 'removed']);
} else {
    // thêm yêu thích
    $add = $pdo->prepare("
        INSERT INTO yeu_thich (id_nguoi_dung, id_san_pham)
        VALUES (?, ?)
    ");
    $add->execute([$id_nguoi_dung, $id_san_pham]);

    echo json_encode(['status' => 'added']);
}
