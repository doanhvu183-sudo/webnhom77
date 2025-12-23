<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$doanhThu = $pdo->query("
    SELECT IFNULL(SUM(tong_tien),0)
    FROM donhang
    WHERE DATE(ngay_dat) = CURDATE()
")->fetchColumn();

$donMoi = $pdo->query("
    SELECT COUNT(*) FROM donhang
    WHERE trang_thai = 'CHO_XU_LY'
")->fetchColumn();

$tonKho = $pdo->query("
    SELECT IFNULL(SUM(so_luong),0) FROM ton_kho
")->fetchColumn();
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<div class="content">
    <h2>Bảng điều khiển</h2>

    <div class="box">Doanh thu hôm nay: <b><?= number_format($doanhThu) ?> đ</b></div>
    <div class="box">Đơn chờ xử lý: <b><?= $donMoi ?></b></div>
    <div class="box">Tổng tồn kho: <b><?= $tonKho ?></b></div>
</div>

<?php include 'footer.php'; ?>
