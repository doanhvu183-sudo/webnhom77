<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung']['id_nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$gio_hang = $_SESSION['gio_hang'] ?? [];
if (empty($gio_hang)) {
    header("Location: gio_hang.php");
    exit;
}

$id_nguoi_dung = (int)$_SESSION['nguoi_dung']['id_nguoi_dung'];
$ho_ten_nhan = trim($_POST['ho_ten_nhan'] ?? '');
$so_dien_thoai_nhan = trim($_POST['so_dien_thoai_nhan'] ?? '');
$dia_chi_nhan = trim($_POST['dia_chi_nhan'] ?? '');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');
$phuong_thuc = $_POST['phuong_thuc'] ?? 'COD';

if ($ho_ten_nhan=='' || $so_dien_thoai_nhan=='' || $dia_chi_nhan=='') {
    header("Location: thanh_toan.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // tính tổng tiền
    $tong_tien = 0;
    foreach ($gio_hang as $sp){
        $tong_tien += $sp['gia']*$sp['so_luong'];
    }

    $ma_don_hang = 'DH' . date('YmdHis') . rand(100,999);
    $trang_thai = 'Chờ xác nhận';

    // insert donhang
    $stmtDH = $pdo->prepare("
        INSERT INTO donhang (id_nguoi_dung, ma_don_hang, tong_tien, trang_thai, phuong_thuc, ngay_dat, ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan, ghi_chu)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    $stmtDH->execute([
        $id_nguoi_dung, $ma_don_hang, $tong_tien, 
        $trang_thai, $phuong_thuc,
        $ho_ten_nhan, $so_dien_thoai_nhan, $dia_chi_nhan, $ghi_chu
    ]);

    $id_don_hang = $pdo->lastInsertId();

    // insert chitiet_donhang + trừ tồn
    $stmtCT = $pdo->prepare("
        INSERT INTO chitiet_donhang (id_don_hang, id_san_pham, so_luong, don_gia, thanh_tien)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmtTru = $pdo->prepare("
        UPDATE sanpham SET so_luong = so_luong - ? 
        WHERE id_san_pham = ? AND so_luong >= ?
    ");

    foreach ($gio_hang as $sp){
        $thanh_tien = $sp['gia']*$sp['so_luong'];

        $stmtCT->execute([
            $id_don_hang, $sp['id_san_pham'], $sp['so_luong'], $sp['gia'], $thanh_tien
        ]);

        $stmtTru->execute([
            $sp['so_luong'], $sp['id_san_pham'], $sp['so_luong']
        ]);

        if ($stmtTru->rowCount() == 0) {
            throw new Exception("Sản phẩm {$sp['ten_san_pham']} không đủ tồn kho.");
        }
    }

    $pdo->commit();

    // clear cart
    unset($_SESSION['gio_hang']);

    header("Location: thong_bao_dat_hang.php?ma=$ma_don_hang");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Lỗi đặt hàng: " . $e->getMessage();
}
