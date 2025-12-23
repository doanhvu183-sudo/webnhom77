<?php
require_once '_auth.php';
require_once '../cau_hinh/ket_noi.php';

/* ĐƠN TREO */
$donTreo = $pdo->query("
    SELECT COUNT(*) FROM donhang 
    WHERE trang_thai = 'CHO_XU_LY'
")->fetchColumn();

/* TỒN KHO THẤP */
$tonThap = $pdo->query("
    SELECT COUNT(*) FROM tonkho 
    WHERE so_luong < 5
")->fetchColumn();
?>

<h2>🔔 Thông báo hệ thống</h2>

<ul>
    <?php if ($donTreo > 0): ?>
        <li>⏳ Có <?= $donTreo ?> đơn hàng đang chờ xử lý</li>
    <?php endif; ?>

    <?php if ($tonThap > 0): ?>
        <li>⚠️ <?= $tonThap ?> sản phẩm sắp hết hàng</li>
    <?php endif; ?>
</ul>
