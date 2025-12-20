<?php
// hoan_tat.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== KIỂM TRA ĐĂNG NHẬP ================== */
if (!isset($_SESSION['nguoi_dung'])) {
    header('Location: dang_nhap.php');
    exit;
}

$id_don_hang = (int)($_GET['id'] ?? 0);
if ($id_don_hang <= 0) {
    die('Đơn hàng không hợp lệ');
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? 0);

/* ================== LẤY ĐƠN HÀNG ================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM donhang
    WHERE id_don_hang = ?
      AND id_nguoi_dung = ?
    LIMIT 1
");
$stmt->execute([$id_don_hang, $id_nguoi_dung]);
$donhang = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$donhang) {
    die('Không tìm thấy đơn hàng');
}

/* ================== LẤY CHI TIẾT ĐƠN ================== */
$stmt = $pdo->prepare("
    SELECT ct.*, sp.hinh_anh
    FROM chitiet_donhang ct
    LEFT JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
    WHERE ct.id_don_hang = ?
      AND ct.id_san_pham > 0
      AND ct.so_luong > 0
      AND ct.don_gia > 0
      AND (ct.thanh_tien IS NULL OR ct.thanh_tien > 0)
      AND ct.ten_san_pham <> ''
");
$stmt->execute([$id_don_hang]);
$chi_tiet = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ================== MAP TRẠNG THÁI ================== */
$mapTrangThai = [
    'CHO_XU_LY' => 'Chờ xử lý',
    'DANG_GIAO' => 'Đang giao',
    'HOAN_TAT'  => 'Hoàn tất'
];
$trang_thai_text = $mapTrangThai[$donhang['trang_thai'] ?? ''] ?? 'Chờ xử lý';

/* ================== TỔNG TIỀN CHUẨN ================== */
$tong_thanh_toan = 0;

if (isset($donhang['tong_thanh_toan'])) {
    $tong_thanh_toan = (int)$donhang['tong_thanh_toan'];
} elseif (isset($donhang['tong_tien'])) {
    $tong_thanh_toan = (int)$donhang['tong_tien'];
} else {
    foreach ($chi_tiet as $sp) {
        $tong_thanh_toan += (int)($sp['thanh_tien'] ?? ((int)($sp['don_gia'] ?? 0) * (int)($sp['so_luong'] ?? 0)));
    }
}
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1100px] mx-auto px-4 py-12">

<!-- SUCCESS -->
<div class="bg-green-50 border border-green-200 rounded-xl p-8 text-center mb-10">
    <div class="text-4xl mb-3">🎉</div>
    <h1 class="text-3xl font-black mb-2">Đặt hàng thành công</h1>
    <p class="text-gray-600">
        Cảm ơn bạn đã mua sắm tại cửa hàng
    </p>
</div>

<!-- ORDER INFO -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">

    <div class="border rounded-xl p-6 bg-white">
        <h2 class="font-black uppercase mb-4">Thông tin đơn hàng</h2>
        <div class="space-y-2 text-sm">
            <div><strong>Mã đơn:</strong> #<?= (int)$donhang['id_don_hang'] ?></div>
            <div><strong>Ngày đặt:</strong>
                <?= !empty($donhang['ngay_dat']) ? date('d/m/Y H:i', strtotime($donhang['ngay_dat'])) : '-' ?>
            </div>
            <div><strong>Trạng thái:</strong>
                <span class="text-primary font-bold">
                    <?= $trang_thai_text ?>
                </span>
            </div>
            <div><strong>Thanh toán:</strong>
                <?= htmlspecialchars($donhang['phuong_thuc_thanh_toan'] ?? ($donhang['phuong_thuc'] ?? 'COD')) ?>
            </div>
            <?php if (!empty($donhang['ma_voucher'])): ?>
            <div><strong>Voucher:</strong> <?= htmlspecialchars($donhang['ma_voucher']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="border rounded-xl p-6 bg-white">
        <h2 class="font-black uppercase mb-4">Thông tin nhận hàng</h2>
        <div class="space-y-2 text-sm">
            <div><strong>Họ tên:</strong>
                <?= htmlspecialchars($donhang['ho_ten_nhan'] ?? ($_SESSION['nguoi_dung']['ho_ten'] ?? '')) ?>
            </div>
            <div><strong>Email:</strong>
                <?= htmlspecialchars($_SESSION['nguoi_dung']['email'] ?? '') ?>
            </div>
            <div><strong>Địa chỉ:</strong>
                <?= htmlspecialchars($donhang['dia_chi_nhan'] ?? '') ?>
            </div>
        </div>
    </div>

</div>

<!-- PRODUCT LIST -->
<div class="border rounded-xl bg-white mb-10">
    <div class="p-6 border-b font-black uppercase">
        Sản phẩm đã đặt
    </div>

    <?php foreach ($chi_tiet as $sp): ?>
    <div class="flex gap-4 p-6 border-b last:border-0 items-center">
        <img src="../assets/img/<?= htmlspecialchars($sp['hinh_anh'] ?? 'no-image.png') ?>"
             class="w-20 h-20 object-contain border rounded">

        <div class="flex-1">
            <div class="font-bold">
                <?= htmlspecialchars($sp['ten_san_pham'] ?? '') ?>
            </div>
            <div class="text-sm text-gray-500">
                <?php if (!empty($sp['size'])): ?>Size <?= htmlspecialchars($sp['size']) ?> | <?php endif; ?>
                Số lượng: <?= (int)($sp['so_luong'] ?? 0) ?>
            </div>
        </div>

        <div class="font-bold">
            <?= number_format((int)($sp['thanh_tien'] ?? ((int)($sp['don_gia'] ?? 0) * (int)($sp['so_luong'] ?? 0)))) ?>₫
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TOTAL -->
<div class="border rounded-xl p-6 bg-gray-50 flex justify-between items-center">
    <span class="text-xl font-black uppercase">Tổng thanh toán</span>
    <span class="text-2xl font-black text-primary">
        <?= number_format($tong_thanh_toan) ?>₫
    </span>
</div>

<!-- ACTION -->
<div class="mt-10 flex gap-4">
    <a href="trang_chu.php"
       class="px-6 py-3 border rounded-full font-bold hover:bg-gray-100">
        Về trang chủ
    </a>

    <a href="don_hang.php"
       class="px-6 py-3 bg-black text-white rounded-full font-bold">
        Xem đơn hàng
    </a>
</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
