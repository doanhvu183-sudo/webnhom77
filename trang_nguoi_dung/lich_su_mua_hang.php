<?php
require_once __DIR__ . '/../giao_dien/header.php';
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['user'])) {
    echo "<div class='warning-box'>
            <p>Bạn cần đăng nhập để xem lịch sử đơn hàng.</p>
            <a class='btn-login' href='../tai_khoan/dang_nhap.php'>Đăng nhập</a>
          </div>";
    require_once __DIR__ . '/../giao_dien/footer.php';
    exit;
}

$id_user = $_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT * FROM DONHANG WHERE id_nguoi_dung = :id ORDER BY id_don_hang DESC");
$stmt->execute([':id' => $id_user]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../assets/css/order.css">

<div class="order-container">

    <h2>Lịch sử đơn hàng</h2>

    <?php if (!$orders): ?>
        <div class="order-empty">
            <img src="../assets/img/empty.png" class="empty-img">
            <p>Bạn chưa có đơn hàng nào.</p>
        </div>
    <?php else: ?>

        <?php foreach ($orders as $dh): ?>
            <div class="order-item">
                <div class="order-left">
                    <p class="order-id">Mã đơn: <strong><?= $dh['ma_don_hang'] ?></strong></p>
                    <p class="order-date">Ngày đặt: <?= $dh['ngay_dat'] ?></p>
                    <p class="order-status <?= strtolower($dh['trang_thai']) ?>">
                        <?= $dh['trang_thai'] ?>
                    </p>
                </div>

                <div class="order-right">
                    <p class="order-total">
                        <?= number_format($dh['tong_tien'], 0, ',', '.') ?> đ
                    </p>
                    <a href="chi_tiet_don.php?id=<?= $dh['id_don_hang'] ?>" class="btn-view">Xem chi tiết</a>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
