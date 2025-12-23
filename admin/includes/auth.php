<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: dang_nhap.php");
    exit;
}

function chiAdmin() {
    if ($_SESSION['admin']['vai_tro'] !== 'ADMIN') {
        die('Bạn không có quyền truy cập');
    }
}
