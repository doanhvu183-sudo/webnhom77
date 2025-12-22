<?php
session_start();
$cart = $_SESSION['cart'] ?? [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Giỏ hàng</title>
<link rel="stylesheet" href="../assets/css/cart.css">
</head>
<body>

<?php include "../giao_dien/header.php"; ?>

<div class="cart-container">

    <h2>Giỏ hàng của bạn</h2>

    <?php if (empty($cart)): ?>
        <p class="empty">Giỏ hàng trống</p>
    <?php else: ?>

    <table class="cart-table">
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th>Giá</th>
                <th>Số lượng</th>
                <th>Tạm tính</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php
            $tong = 0;
            foreach ($cart as $item):
                $tam_tinh = $item['gia'] * $item['so_luong'];
                $tong += $tam_tinh;
            ?>
            <tr>
                <td class="cart-product">
                    <img src="../assets/img/<?= $item['anh'] ?>" alt="">
                    <span><?= $item['ten'] ?></span>
                </td>

                <td><?= number_format($item['gia'], 0, ',', '.') ?>đ</td>

                <td>
                    <form action="update_qty.php" method="post" class="qty-form">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">

                        <button name="action" value="minus">-</button>
                        <input type="text" name="so_luong" value="<?= $item['so_luong'] ?>">
                        <button name="action" value="plus">+</button>
                    </form>
                </td>

                <td><?= number_format($tam_tinh, 0, ',', '.') ?>đ</td>

                <td>
                    <a class="remove" href="xoa_item.php?id=<?= $item['id'] ?>">✖</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cart-total">
        <h3>Tổng tiền: <?= number_format($tong, 0, ',', '.') ?>đ</h3>
        <a href="checkout.php" class="checkout-btn">Thanh toán</a>
    </div>

    <?php endif; ?>

</div>

<?php include "../giao_dien/footer.php"; ?>

</body>
</html>
