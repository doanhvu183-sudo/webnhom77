<?php
require_once __DIR__ . '/../giao_dien/header.php';
$ma = $_GET['ma'] ?? '';
?>
<main style="max-width:900px;margin:40px auto;text-align:center;font-family:Arial">
    <h1>Đặt hàng thành công 🎉</h1>
    <p>Mã đơn hàng của bạn: <b><?= htmlspecialchars($ma) ?></b></p>
    <a href="trang_chu.php" style="display:inline-block;margin-top:16px;padding:12px 20px;background:#111;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Về trang chủ</a>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
