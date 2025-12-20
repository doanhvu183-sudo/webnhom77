<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== LOGIN ================== */
if (!isset($_SESSION['nguoi_dung'])) {
    header('Location: dang_nhap.php');
    exit;
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id'];

/* ================== LẤY ĐƠN ================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM donhang
    WHERE id_nguoi_dung = ?
    ORDER BY ngay_dat DESC
");
$stmt->execute([$id_nguoi_dung]);
$donhangs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mapTrangThai = [
    'CHO_XU_LY' => 'Chờ xử lý',
    'DANG_GIAO' => 'Đang giao',
    'HOAN_TAT'  => 'Hoàn tất'
];
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1200px] mx-auto px-4 py-10">

<h1 class="text-3xl font-black mb-8">Đơn hàng của tôi</h1>

<?php if (empty($donhangs)): ?>
    <div class="border rounded-xl p-8 text-center text-gray-500">
        Bạn chưa có đơn hàng nào.
    </div>
<?php endif; ?>

<div class="space-y-6">

<?php foreach ($donhangs as $dh): ?>

<?php
    // Lấy 1 ảnh đại diện cho đơn
    $stmtImg = $pdo->prepare("
        SELECT sp.hinh_anh
        FROM chitiet_donhang ct
        JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
        WHERE ct.id_don_hang = ?
        LIMIT 1
    ");
    $stmtImg->execute([$dh['id_don_hang']]);
    $img = $stmtImg->fetchColumn();
?>

<div class="border rounded-xl p-6 bg-white flex flex-col md:flex-row gap-6 items-center">

    <!-- IMAGE -->
    <img src="../assets/img/<?= htmlspecialchars($img ?: 'no-image.png') ?>"
         class="w-24 h-24 object-contain border rounded">

    <!-- INFO -->
    <div class="flex-1">
        <div class="font-bold text-lg mb-1">
            Mã đơn #<?= $dh['id_don_hang'] ?>
        </div>
        <div class="text-sm text-gray-500 mb-2">
            Ngày đặt: <?= date('d/m/Y H:i', strtotime($dh['ngay_dat'])) ?>
        </div>
        <div class="text-sm">
            Trạng thái:
            <span class="font-bold text-primary">
                <?= $mapTrangThai[$dh['trang_thai']] ?? 'Chờ xử lý' ?>
            </span>
        </div>
    </div>

    <!-- TOTAL (CHUẨN: SAU VOUCHER) -->
    <div class="text-right">
        <div class="font-black text-xl text-primary mb-2">
            <?= number_format($dh['tong_thanh_toan']) ?>₫
        </div>
        <a href="hoan_tat.php?id=<?= $dh['id_don_hang'] ?>"
           class="inline-block px-4 py-2 border rounded-full text-sm font-bold hover:bg-gray-100">
            Xem chi tiết
        </a>
    </div>

</div>

<?php endforeach; ?>

</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
