<?php
session_start();
$id = $_GET['id'] ?? null;

if ($id && isset($_SESSION['cart'][$id])) {
    unset($_SESSION['cart'][$id]);
}

header('Location: gio_hang.php');
exit;
