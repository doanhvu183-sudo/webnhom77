<?php
session_start();
require_once "../includes/db.php";

// Giỏ hàng lưu trong SESSION
$cart = $_SESSION['cart'] ?? [];

// Nếu giỏ hàng trống → về trang chủ
if (empty($cart)) {
    header("Location: trang_chu.php");
    exit;
}

// Lấy user nếu đã đăng nhập
$user = $_SESSION['user'] ?? null;

function vnd($n) { return number_format($n, 0, ',', '.') . " đ"; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Thanh toán</title>

<link rel="stylesheet" href="../assets/css/checkout.css?v=<?= time() ?>">

</head>
<body>

<?php include "../giao_dien/header.php"; ?>

<div class="checkout-wrapper">

    <h2>Thanh toán</h2>

    <form method="post" action="xu_ly_dat_hang.php">

        <h3>Thông tin giao hàng</h3>

        <?php if ($user): ?>
            <label>Họ tên</label>
            <input type="text" name="ho_ten" value="<?= $user['ho_ten'] ?>" required>

            <label>Số điện thoại</label>
            <input type="text" name="sdt" value="<?= $user['so_dien_thoai'] ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= $user['email'] ?>" required>

            <label>Địa chỉ giao hàng</label>
            <input type="text" name="dia_chi" value="<?= $user['dia_chi'] ?>" required>

        <?php else: ?>

            <label>Họ tên</label>
            <input type="text" name="ho_ten" placeholder="Nhập họ tên" required>

            <label>Số điện thoại</label>
            <input type="text" name="sdt" placeholder="Nhập số điện thoại" required>

            <label>Email</label>
            <input type="email" name="email" placeholder="Nhập email" required>

            <label>Địa chỉ giao hàng</label>
            <input type="text" name="dia_chi" placeholder="Nhập địa chỉ" required>

        <?php endif; ?>


        <h3>Phương thức thanh toán</h3>
        <select name="payment" required>
            <option value="COD">Thanh toán khi nhận hàng (COD)</option>
            <option value="MOMO">Ví MOMO</option>
            <option value="VNPAY">Thanh toán VNPAY</option>
        </select>


        <h3>Tóm tắt đơn hàng</h3>
        <div class="order-box">

            <?php
            $tong = 0;
            foreach ($cart as $c):
                $line = $c['gia'] * $c['so_luong'];
                $tong += $line;
            ?>
                <div class="order-line">
                    <?= htmlspecialchars($c['ten']) ?> × <?= $c['so_luong'] ?>
                    — <b><?= vnd($line) ?></b>
                </div>
            <?php endforeach; ?>

            <div class="total-price">Tổng tiền: <?= vnd($tong) ?></div>
        </div>

        <input type="hidden" name="tong_tien" value="<?= $tong ?>">

        <button class="btn-order">ĐẶT HÀNG</button>
    </form>
</div>

<?php include "../giao_dien/footer.php"; ?>

</body>
</html>
