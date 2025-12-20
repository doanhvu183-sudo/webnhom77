<?php
// includes/functions_cart.php

function require_login() {
    if (!isset($_SESSION['nguoi_dung'])) {
        header("Location: dang_nhap.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function get_user_id() {
    return $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? null;
}

function get_or_create_cart_id(PDO $pdo, $id_nguoi_dung) {
    $stmt = $pdo->prepare("SELECT id_gio_hang FROM giohang WHERE id_nguoi_dung = ?");
    $stmt->execute([$id_nguoi_dung]);
    $cart = $stmt->fetch();
    if ($cart) return $cart['id_gio_hang'];

    $stmt = $pdo->prepare("INSERT INTO giohang(id_nguoi_dung, tong_tien) VALUES(?, 0)");
    $stmt->execute([$id_nguoi_dung]);
    return $pdo->lastInsertId();
}

function add_to_cart(PDO $pdo, $id_nguoi_dung, $id_san_pham, $so_luong) {
    $id_gio_hang = get_or_create_cart_id($pdo, $id_nguoi_dung);

    // lấy giá hiện tại
    $sp = $pdo->prepare("SELECT gia, so_luong FROM sanpham WHERE id_san_pham = ?");
    $sp->execute([$id_san_pham]);
    $sanpham = $sp->fetch();
    if (!$sanpham) return "Sản phẩm không tồn tại";

    if ($so_luong <= 0) $so_luong = 1;
    if ($so_luong > (int)$sanpham['so_luong']) {
        return "Số lượng vượt tồn kho";
    }

    // đã có trong giỏ chưa?
    $ct = $pdo->prepare("SELECT id_chi_tiet_gio_hang, so_luong FROM chitiet_giohang WHERE id_gio_hang=? AND id_san_pham=?");
    $ct->execute([$id_gio_hang, $id_san_pham]);
    $row = $ct->fetch();

    if ($row) {
        $newQty = (int)$row['so_luong'] + $so_luong;
        if ($newQty > (int)$sanpham['so_luong']) {
            return "Số lượng vượt tồn kho";
        }
        $upd = $pdo->prepare("UPDATE chitiet_giohang SET so_luong=? WHERE id_chi_tiet_gio_hang=?");
        $upd->execute([$newQty, $row['id_chi_tiet_gio_hang']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO chitiet_giohang(id_gio_hang, id_san_pham, so_luong, gia) VALUES(?,?,?,?)");
        $ins->execute([$id_gio_hang, $id_san_pham, $so_luong, $sanpham['gia']]);
    }

    recalc_cart_total($pdo, $id_gio_hang);
    return null;
}

function update_cart_qty(PDO $pdo, $id_nguoi_dung, $id_san_pham, $so_luong) {
    $id_gio_hang = get_or_create_cart_id($pdo, $id_nguoi_dung);

    $sp = $pdo->prepare("SELECT so_luong FROM sanpham WHERE id_san_pham=?");
    $sp->execute([$id_san_pham]);
    $sanpham = $sp->fetch();
    if (!$sanpham) return;

    $max = (int)$sanpham['so_luong'];
    if ($so_luong < 1) $so_luong = 1;
    if ($so_luong > $max) $so_luong = $max;

    $upd = $pdo->prepare("UPDATE chitiet_giohang SET so_luong=? WHERE id_gio_hang=? AND id_san_pham=?");
    $upd->execute([$so_luong, $id_gio_hang, $id_san_pham]);

    recalc_cart_total($pdo, $id_gio_hang);
}

function remove_item(PDO $pdo, $id_nguoi_dung, $id_san_pham) {
    $id_gio_hang = get_or_create_cart_id($pdo, $id_nguoi_dung);
    $del = $pdo->prepare("DELETE FROM chitiet_giohang WHERE id_gio_hang=? AND id_san_pham=?");
    $del->execute([$id_gio_hang, $id_san_pham]);
    recalc_cart_total($pdo, $id_gio_hang);
}

function recalc_cart_total(PDO $pdo, $id_gio_hang) {
    $stmt = $pdo->prepare("SELECT SUM(so_luong * gia) AS tong FROM chitiet_giohang WHERE id_gio_hang=?");
    $stmt->execute([$id_gio_hang]);
    $tong = $stmt->fetchColumn() ?: 0;

    $upd = $pdo->prepare("UPDATE giohang SET tong_tien=? WHERE id_gio_hang=?");
    $upd->execute([$tong, $id_gio_hang]);
}

function get_cart_items(PDO $pdo, $id_nguoi_dung) {
    $stmt = $pdo->prepare("
        SELECT gh.id_gio_hang, ct.id_san_pham, ct.so_luong, ct.gia,
               sp.ten_san_pham, sp.hinh_anh, sp.so_luong AS ton_kho
        FROM giohang gh
        JOIN chitiet_giohang ct ON gh.id_gio_hang = ct.id_gio_hang
        JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
        WHERE gh.id_nguoi_dung = ?
        ORDER BY ct.id_chi_tiet_gio_hang DESC
    ");
    $stmt->execute([$id_nguoi_dung]);
    return $stmt->fetchAll();
}

function format_vnd($n) {
    return number_format((float)$n, 0, ',', '.') . " đ";
}
