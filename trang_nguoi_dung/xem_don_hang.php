<?php
require_once __DIR__ . '/../giao_dien/header.php';
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$id_don = $_GET['id'] ?? 0;
$id_user = $_SESSION['user']['id'] ?? 0;

// Lấy thông tin đơn
$don = $pdo->prepare("SELECT * FROM DONHANG WHERE id_don_hang = :id AND id_nguoi_dung = :user");
$don->execute(['id' => $id_don, 'user' => $id_user]);
$don = $don->fetch();

if (!$don) {
    echo "<p>Đơn hàng không tồn tại.</p>";
    require_once __DIR__ . '/../giao_dien/footer.php';
    exit;
}

// Lấy chi tiết đơn
$ct = $pdo->prepare("
    SELECT C.*, S.ten_san_pham, S.hinh_anh
    FROM CHITIET_DONHANG C
    JOIN SANPHAM S ON C.id_san_pham = S.id_san_pham
    WHERE id_don_hang = :id
");
$ct->execute(['id' => $id_don]);
$chi_tiet = $ct->fetchAll();
?>

<link rel="stylesheet" href="../assets/css/order.css">

<div class="order-detail-container">

    <h2>Chi tiết đơn hàng: <?= $don['ma_don_hang'] ?></h2>

    <p><strong>Trạng thái:</strong> <?= $don['trang_thai'] ?></p>
    <p><strong>Tổng tiền:</strong> <?= number_format($don['tong_tien'], 0, ',', '.') ?> đ</p>

    <h3>Sản phẩm trong đơn</h3>

    <table class="orders-table">
        <thead>
            <tr>
                <th>Hình</th>
                <th>Tên sản phẩm</th>
                <th>Số lượng</th>
                <th>Giá bán</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($chi_tiet as $sp): ?>
                <tr>
                    <td><img class="thumb" src="../assets/img/<?= $sp['hinh_anh'] ?>" alt=""></td>
                    <td><?= $sp['ten_san_pham'] ?></td>
                    <td><?= $sp['so_luong'] ?></td>
                    <td><?= number_format($sp['gia_ban'], 0, ',', '.') ?> đ</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="lich_su_don.php" class="btn-outline">← Quay lại</a>

</div>

<?php
require_once __DIR__ . '/../giao_dien/footer.php';
?>
