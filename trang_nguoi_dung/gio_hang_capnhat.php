<?php
// gio_hang_capnhat.php
session_start();

$id   = $_GET['id']   ?? null;
$type = $_GET['type'] ?? null;

if (!$id || !isset($_SESSION['cart'][$id])) {
    header('Location: gio_hang.php');
    exit;
}

/* ================== CHUẨN HÓA QTY ================== */
if (!isset($_SESSION['cart'][$id]['qty']) || (int)$_SESSION['cart'][$id]['qty'] <= 0) {
    $_SESSION['cart'][$id]['qty'] = 1;
}

if ($type === 'plus') {
    $_SESSION['cart'][$id]['qty']++;
} elseif ($type === 'minus') {
    $_SESSION['cart'][$id]['qty']--;
    if ((int)$_SESSION['cart'][$id]['qty'] <= 0) {
        unset($_SESSION['cart'][$id]);
    }
}

header('Location: gio_hang.php');
exit;
