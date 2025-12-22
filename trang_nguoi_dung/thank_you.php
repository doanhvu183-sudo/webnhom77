<?php
$id = $_GET["id"] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Đặt hàng thành công</title>
<link rel="stylesheet" href="../assets/css/checkout.css">
</head>
<body>

<div class="checkout-wrapper" style="text-align:center; padding:70px;">
    <h1>🎉 ĐẶT HÀNG THÀNH CÔNG!</h1>
    <p>Cảm ơn bạn đã mua hàng tại cửa hàng của chúng tôi.</p>
    <p>Mã đơn hàng của bạn là:</p>
    <h2 style="color:#28a745;">#<?= $id ?></h2>

    <a href="trang_chu.php" class="btn-order" style="display:inline-block;margin-top:20px;">
        Tiếp tục mua hàng
    </a>
</div>

</body>
</html>
