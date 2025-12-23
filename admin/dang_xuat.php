<?php
session_start();
unset($_SESSION['admin']);
header("Location: dang_nhap.php");
exit;
