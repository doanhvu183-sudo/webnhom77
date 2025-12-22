<?php
session_start();

$id = intval($_POST['id']);
$action = $_POST['action'];

if (!isset($_SESSION['cart'][$id])) {
    header("Location: gio_hang.php");
    exit;
}

if ($action === "plus") {
    $_SESSION['cart'][$id]['so_luong']++;
}

if ($action === "minus" && $_SESSION['cart'][$id]['so_luong'] > 1) {
    $_SESSION['cart'][$id]['so_luong']--;
}

header("Location: gio_hang.php");
exit;
