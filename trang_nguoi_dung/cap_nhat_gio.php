<?php
session_start();

if (!isset($_SESSION['gio_hang'])) {
    $_SESSION['gio_hang'] = [];
}

$action = $_POST['action'] ?? '';
$id = isset($_POST['id_san_pham']) ? (int)$_POST['id_san_pham'] : 0;

if ($id > 0 && isset($_SESSION['gio_hang'][$id])) {
    switch ($action) {
        case 'tang':
            $_SESSION['gio_hang'][$id]['so_luong']++;
            if ($_SESSION['gio_hang'][$id]['so_luong'] > $_SESSION['gio_hang'][$id]['ton_kho']) {
                $_SESSION['gio_hang'][$id]['so_luong'] = $_SESSION['gio_hang'][$id]['ton_kho'];
            }
            break;

        case 'giam':
            $_SESSION['gio_hang'][$id]['so_luong']--;
            if ($_SESSION['gio_hang'][$id]['so_luong'] < 1) {
                $_SESSION['gio_hang'][$id]['so_luong'] = 1;
            }
            break;

        case 'xoa':
            unset($_SESSION['gio_hang'][$id]);
            break;

        case 'set':
            $qty = max(1, (int)($_POST['so_luong'] ?? 1));
            if ($qty > $_SESSION['gio_hang'][$id]['ton_kho']) {
                $qty = $_SESSION['gio_hang'][$id]['ton_kho'];
            }
            $_SESSION['gio_hang'][$id]['so_luong'] = $qty;
            break;
    }
}

header("Location: gio_hang.php");
exit;
