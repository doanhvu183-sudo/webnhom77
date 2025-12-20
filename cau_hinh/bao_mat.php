<?php
// cau_hinh/bao_mat.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Bắt buộc phải đăng nhập
 */
function yeu_cau_dang_nhap() {
    if (!isset($_SESSION['user'])) {
        header("Location: ../tai_khoan/dang_nhap.php");
        exit;
    }
}

/**
 * Chỉ cho phép Admin (id_vai_tro = 1)
 */
function yeu_cau_admin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 1) {
        die("<h2>Bạn không có quyền truy cập khu vực này!</h2>");
    }
}

/**
 * Hàm kiểm tra quyền theo id_chuc_nang (nếu sau này muốn dùng)
 * $pdo: đối tượng PDO
 * $id_chuc_nang: id trong bảng CHUCNANG
 */
function kiem_tra_quyen($pdo, $id_chuc_nang) {
    if (!isset($_SESSION['user'])) {
        return false;
    }

    $id_vai_tro = $_SESSION['user']['role']; // set trong xu_ly_dang_nhap

    $sql = "SELECT 1 FROM VAITRO_CHUCNANG 
            WHERE id_vai_tro = :vt 
              AND id_chuc_nang = :cn 
              AND quyen_truy_cap = 'Có'
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':vt' => $id_vai_tro,
        ':cn' => $id_chuc_nang
    ]);

    return (bool)$stmt->fetchColumn();
}
