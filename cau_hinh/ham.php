<?php
// Định dạng giá tiền
function dinh_dang_gia($so)
{
    return number_format($so, 0, ',', '.') . ' đ';
}

// KHỞI TẠO GIỎ HÀNG
function init_gio_hang()
{
    if (!isset($_SESSION['gio_hang'])) {
        $_SESSION['gio_hang'] = [];
    }
}

// TÍNH TỔNG GIỎ HÀNG
function tinh_tong_gio()
{
    init_gio_hang();
    $tong = 0;
    foreach ($_SESSION['gio_hang'] as $sp) {
        $tong += $sp['gia'] * $sp['so_luong'];
    }
    return $tong;
}
