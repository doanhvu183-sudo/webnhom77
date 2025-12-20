<?php
// gio_hang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== GIỎ HÀNG ================== */
$cart = $_SESSION['cart'] ?? [];
$tong_tien = 0;
$tong_so_luong = 0;

foreach ($cart as $sp) {
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));

    $tong_so_luong += $qty;
    $tong_tien += $don_gia * $qty;
}

/* ================== ÁP DỤNG VOUCHER ================== */
if (isset($_POST['ap_dung_voucher'])) {
    $ma = trim($_POST['voucher']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM voucher
        WHERE ma_voucher = ?
          AND trang_thai = 1
          AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$ma]);
    $vc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vc) {
        $_SESSION['voucher_error'] = 'Voucher không tồn tại hoặc đã hết hạn';
        header("Location: gio_hang.php");
        exit;
    }

    // check đã dùng voucher
    if (isset($_SESSION['nguoi_dung'])) {
        $uid = $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? null);
        if ($uid) {
            $check = $pdo->prepare("
                SELECT 1
                FROM voucher_nguoidung
                WHERE id_nguoi_dung = ? AND ma_voucher = ?
                LIMIT 1
            ");
            $check->execute([$uid, $ma]);
            if ($check->fetch()) {
                $_SESSION['voucher_error'] = 'Bạn đã sử dụng voucher này';
                header("Location: gio_hang.php");
                exit;
            }
        }
    }

    $_SESSION['voucher'] = $vc;
    header("Location: gio_hang.php");
    exit;
}

/* ================== TÍNH GIẢM GIÁ ================== */
$tien_giam = 0;

if (!empty($_SESSION['voucher'])) {
    $vc = $_SESSION['voucher'];

    if (($vc['loai'] ?? '') === 'TIEN') {
        $tien_giam = (int)($vc['gia_tri'] ?? 0);
    } elseif (($vc['loai'] ?? '') === 'PHAN_TRAM') {
        $tien_giam = (int)floor($tong_tien * ((int)($vc['gia_tri'] ?? 0)) / 100);
        if (!empty($vc['toi_da'])) {
            $tien_giam = min($tien_giam, (int)$vc['toi_da']);
        }
    }
}

$tong_thanh_toan = max(0, $tong_tien - $tien_giam);
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1200px] mx-auto px-6 py-10">

<h1 class="text-3xl font-black uppercase mb-8">Giỏ hàng</h1>

<?php if (empty($cart)): ?>
<div class="border rounded-xl p-12 text-center text-gray-500">
    <p class="text-lg mb-4">Giỏ hàng của bạn đang trống</p>
    <a href="trang_chu.php"
       class="bg-black text-white px-6 py-3 rounded-full font-bold">
        Tiếp tục mua sắm
    </a>
</div>

<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

<!-- ================= DANH SÁCH SẢN PHẨM ================= -->
<div class="lg:col-span-8">
<div class="overflow-x-auto border rounded-xl">
<table class="w-full text-sm">
<thead class="bg-gray-100 uppercase text-xs font-bold">
<tr>
    <th class="p-4 text-left">Sản phẩm</th>
    <th class="p-4 text-center">Size</th>
    <th class="p-4 text-right">Giá</th>
    <th class="p-4 text-center">SL</th>
    <th class="p-4 text-right">Thành tiền</th>
    <th></th>
</tr>
</thead>
<tbody>

<?php foreach ($cart as $key => $sp):
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
    $thanh_tien = $don_gia * $qty;
?>
<tr class="border-t">
<td class="p-4 flex gap-4 items-center">
    <img src="../assets/img/<?= htmlspecialchars($sp['anh'] ?? 'no-image.png') ?>"
         class="w-16 h-16 object-contain border rounded">
    <div>
        <p class="font-bold uppercase"><?= htmlspecialchars($sp['ten'] ?? '') ?></p>
    </div>
</td>

<td class="p-4 text-center"><?= htmlspecialchars($sp['size'] ?? '-') ?></td>

<td class="p-4 text-right font-bold"><?= number_format($don_gia) ?>₫</td>

<td class="p-4 text-center">
    <div class="inline-flex items-center border rounded">
        <a href="gio_hang_capnhat.php?id=<?= urlencode($key) ?>&type=minus" class="px-3">−</a>
        <span class="px-3 font-bold"><?= $qty ?></span>
        <a href="gio_hang_capnhat.php?id=<?= urlencode($key) ?>&type=plus" class="px-3">+</a>
    </div>
</td>

<td class="p-4 text-right font-extrabold text-primary">
    <?= number_format($thanh_tien) ?>₫
</td>

<td class="p-4 text-center">
    <a href="gio_hang_xoa.php?id=<?= urlencode($key) ?>"
       class="text-red-600 text-xs underline">Xóa</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</div>

<!-- ================= TÓM TẮT ĐƠN HÀNG ================= -->
<div class="lg:col-span-4">
<div class="border rounded-xl p-6 sticky top-24 bg-white">

<h2 class="text-xl font-black mb-4">Tóm tắt đơn hàng</h2>

<div class="flex justify-between mb-2">
    <span>Tổng sản phẩm</span>
    <span class="font-bold"><?= $tong_so_luong ?></span>
</div>

<div class="flex justify-between mb-2">
    <span>Tạm tính</span>
    <span class="font-bold"><?= number_format($tong_tien) ?>₫</span>
</div>

<?php if ($tien_giam > 0): ?>
<div class="flex justify-between mb-2 text-green-600">
    <span>Giảm giá</span>
    <span>-<?= number_format($tien_giam) ?>₫</span>
</div>
<?php endif; ?>

<!-- ================= ÁP DỤNG VOUCHER ================= -->
<div class="border-t pt-4 mt-4">
<form method="post" class="space-y-2">
    <label class="block text-sm font-bold">Mã giảm giá</label>
    <div class="flex gap-2">
        <input name="voucher"
               placeholder="Nhập mã voucher"
               class="flex-1 border rounded px-3 py-2 text-sm"
               required>
        <button name="ap_dung_voucher"
                class="bg-black text-white px-4 rounded font-bold text-sm">
            Áp dụng
        </button>
    </div>
</form>

<?php if (!empty($_SESSION['voucher_error'])): ?>
<p class="text-red-600 text-sm mt-2">
    <?= $_SESSION['voucher_error']; unset($_SESSION['voucher_error']); ?>
</p>
<?php endif; ?>

<?php if (!empty($_SESSION['voucher'])): ?>
<p class="text-green-600 text-sm mt-2 font-bold">
    ✔ Đã áp dụng <?= htmlspecialchars($_SESSION['voucher']['ma_voucher'] ?? '') ?>
</p>
<?php endif; ?>
</div>

<div class="border-t pt-4 mt-4 flex justify-between text-lg font-black">
    <span>Tổng cộng</span>
    <span class="text-primary"><?= number_format($tong_thanh_toan) ?>₫</span>
</div>

<a href="thanh_toan.php"
   class="mt-6 block text-center bg-primary text-white py-4 rounded-full font-black uppercase">
    Tiến hành thanh toán
</a>

</div>
</div>

</div>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
