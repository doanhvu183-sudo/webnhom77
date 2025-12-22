<?php
session_start();
$id = intval($_GET['id']);

unset($_SESSION['cart'][$id]);

header("Location: gio_hang.php");
exit;
