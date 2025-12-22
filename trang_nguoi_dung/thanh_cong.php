<?php
require_once __DIR__ . '/../giao_dien/header.php';

$ma = $_GET['ma'] ?? '';
?>

<div class="success-box">
    <h2>Đặt hàng thành công!</h2>
    <p>Mã đơn hàng của bạn là: <strong><?= $ma ?></strong></p>
    <a class="btn-primary" href="lich_su_don.php">Xem lịch sử đơn hàng</a>
    <a class="btn-secondary" href="trang_chu.php">Tiếp tục mua sắm</a>
</div>

<?php
require_once __DIR__ . '/../giao_dien/footer.php';
