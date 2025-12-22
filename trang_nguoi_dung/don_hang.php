<?php
session_start();
require_once "../includes/db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION["user"])) {
    header("Location: dang_nhap.php?redirect=don_hang.php");
    exit;
}

$id_user = $_SESSION["user"]["id"];

// Lấy danh sách đơn hàng + ảnh đại diện
$stmt = $pdo->prepare("
    SELECT 
        dh.*, 
        (SELECT sp.hinh_anh 
         FROM chitiet_donhang ct 
         JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
         WHERE ct.id_don_hang = dh.id_don_hang
         LIMIT 1
        ) AS anh_dai_dien
    FROM donhang dh
    WHERE dh.id_nguoi_dung = ?
    ORDER BY dh.id_don_hang DESC
");
$stmt->execute([$id_user]);
$orders = $stmt->fetchAll();

function vnd($n){ return number_format($n,0,',','.')." đ"; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đơn hàng của tôi</title>
<link rel="stylesheet" href="../assets/css/orders.css?v=<?= time() ?>">

<style>
.btn-cancel {
    padding: 6px 14px;
    background: #ff3b3b;
    color: white !important;
    border-radius: 6px;
    font-size: 14px;
    display: inline-block;
    text-decoration: none;
    font-weight: 600;
}
.btn-cancel:hover {
    background: #e60000;
}

.btn-detail {
    padding: 6px 14px;
    background: #0080ff;
    color: white !important;
    border-radius: 6px;
    font-size: 14px;
    display: inline-block;
    text-decoration: none;
    font-weight: 600;
}
.btn-detail:hover {
    background: #005fcc;
}
</style>

</head>

<body>
<?php include "../giao_dien/header.php"; ?>

<div class="order-wrapper">
    <h2>Đơn hàng của tôi</h2>

    <?php if (empty($orders)): ?>
        <p class="no-order">Bạn chưa có đơn hàng nào.</p>
    <?php else: ?>

        <table class="order-table">
            <thead>
                <tr>
                    <th>Ảnh</th>
                    <th>Mã đơn</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thanh toán</th>
                    <th>Ngày đặt</th>
                    <th>Hủy đơn</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>

                    <!-- ẢNH -->
                    <td>
                        <?php if ($o["anh_dai_dien"]): ?>
                            <img src="../assets/img/<?= $o['anh_dai_dien'] ?>"
                                 style="width:60px;height:60px;border-radius:8px;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:60px;height:60px;background:#eee;border-radius:8px;"></div>
                        <?php endif; ?>
                    </td>

                    <td>#<?= $o["ma_don_hang"] ?></td>
                    <td><?= vnd($o["tong_tien"]) ?></td>
                    <td><?= $o["trang_thai"] ?></td>
                    <td><?= $o["phuong_thuc"] ?></td>
                    <td><?= date("d/m/Y H:i", strtotime($o["ngay_dat"])) ?></td>

                    <!-- CỘT HỦY ĐƠN -->
                    <td>
    <?php if ($o["trang_thai"] === "Chờ duyệt"): ?>
        <a class="btn-cancel"
           href="../xu_ly/huy_don.php?id=<?= $o["id_don_hang"] ?>"
           onclick="return confirm('Bạn có chắc chắn muốn hủy đơn này?')">
            Hủy đơn
        </a>
    <?php else: ?>
        <span style="color:#999;">Không thể hủy</span>
    <?php endif; ?>
</td>


                    <!-- CỘT CHI TIẾT -->
                    <td>
                        <a class="btn-detail"
                           href="don_hang_chi_tiet.php?id=<?= $o["id_don_hang"] ?>">
                            Xem
                        </a>
                    </td>

                </tr>
            <?php endforeach; ?>
            </tbody>

        </table>

    <?php endif; ?>

</div>

<?php include "../giao_dien/footer.php"; ?>
</body>
</html>
