<?php
session_start();
require_once "../includes/db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION["user"])) {
    header("Location: dang_nhap.php?redirect=don_hang.php");
    exit;
}

$id_don = $_GET["id"] ?? 0;

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT * FROM donhang WHERE id_don_hang = ? LIMIT 1");
$stmt->execute([$id_don]);
$order = $stmt->fetch();

if (!$order) {
    die("Đơn hàng không tồn tại!");
}

// Lấy chi tiết đơn hàng + sản phẩm
$stmt = $pdo->prepare("
    SELECT ct.*, sp.ten_san_pham, sp.hinh_anh 
    FROM chitiet_donhang ct
    JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
    WHERE ct.id_don_hang = ?
");
$stmt->execute([$id_don]);
$items = $stmt->fetchAll();

function vnd($n) { return number_format($n, 0, ',', '.') . " đ"; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
}

.detail-wrapper {
    max-width: 900px;
    margin: 40px auto;
    background: white;
    padding: 25px 30px;
    border-radius: 10px;
    box-shadow: 0 0 12px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    padding: 15px 20px;
    background: #fafafa;
    border: 1px solid #ddd;
    border-radius: 10px;
    margin-bottom: 25px;
}

.order-header div {
    font-size: 15px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    margin-top: 25px;
    margin-bottom: 15px;
}

.product-list {
    margin-top: 15px;
}

.item-row {
    display: flex;
    align-items: center;
    background: #ffffff;
    padding: 12px 10px;
    border-radius: 8px;
    border: 1px solid #eee;
    margin-bottom: 12px;
}

.item-row img {
    width: 90px;
    height: 90px;
    border-radius: 8px;
    object-fit: cover;
    margin-right: 15px;
}

.item-info {
    flex: 1;
}

.item-info .name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 4px;
}

.item-info .qty {
    color: #444;
    margin-top: 4px;
}

.item-info .price {
    margin-top: 6px;
    color: #d0011b;
    font-weight: bold;
    font-size: 16px;
}

.total-box {
    margin-top: 25px;
    padding: 15px;
    background: #fafafa;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: 18px;
    font-weight: bold;
    color: #d0011b;
}

.ship-box {
    margin-top: 15px;
    padding: 15px;
    background: #fafafa;
    border-radius: 10px;
    border: 1px solid #ddd;
}

.ship-box p {
    margin: 4px 0;
}
</style>

</head>

<body>
<?php include "../giao_dien/header.php"; ?>

<div class="detail-wrapper">

    <div class="order-header">
        <div><b>Mã đơn:</b> #<?= $order["ma_don_hang"] ?></div>
        <div><b>Trạng thái:</b> <?= $order["trang_thai"] ?></div>
        <div><b>Ngày đặt:</b> <?= date("d/m/Y H:i", strtotime($order["ngay_dat"])) ?></div>
        <div><b>Thanh toán:</b> <?= $order["phuong_thuc"] ?></div>
    </div>

    <div class="section-title">Sản phẩm</div>

    <div class="product-list">
        <?php foreach ($items as $sp): ?>
            <div class="item-row">
                <img src="../assets/img/<?= $sp["hinh_anh"] ?>" alt="">
                <div class="item-info">
                    <div class="name"><?= $sp["ten_san_pham"] ?></div>
                    <div class="qty">Số lượng: <?= $sp["so_luong"] ?></div>
                    <div class="price"><?= vnd($sp["don_gia"]) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">Tổng tiền</div>
    <div class="total-box"><?= vnd($order["tong_tien"]) ?></div>

    <div class="section-title">Thông tin người nhận</div>
    <div class="ship-box">
        <p><b>Họ tên:</b> <?= $order["ho_ten_nhan"] ?></p>
        <p><b>SĐT:</b> <?= $order["so_dien_thoai_nhan"] ?></p>
        <p><b>Địa chỉ:</b> <?= $order["dia_chi_nhan"] ?></p>
    </div>

</div>

<?php include "../giao_dien/footer.php"; ?>

</body>
</html>
