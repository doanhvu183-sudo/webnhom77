<?php
// thanh_toan.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

/* ================== BẮT BUỘC ĐĂNG NHẬP ================== */
if (!isset($_SESSION['nguoi_dung'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    echo "<div class='max-w-[1200px] mx-auto px-6 py-10 text-center text-gray-500'>
            Giỏ hàng trống.
          </div>";
    require_once __DIR__ . '/../giao_dien/footer.php';
    exit;
}

/* ================== TÍNH TỔNG TIỀN (ĐỒNG BỘ) ================== */
$tong_tien = 0;

foreach ($cart as $sp) {
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $tong_tien += $don_gia * $qty;
}

/* ================== ÁP DỤNG VOUCHER (SESSION) ================== */
$tien_giam = 0;
$ma_voucher = null;

if (!empty($_SESSION['voucher'])) {
    $vc = $_SESSION['voucher'];
    $ma_voucher = $vc['ma_voucher'] ?? null;

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

<main class="max-w-[1200px] mx-auto px-6 py-10">

<h1 class="text-3xl font-black uppercase mb-8">Thanh toán</h1>

<form action="dat_hang_xuly.php" method="post"
      class="grid grid-cols-1 lg:grid-cols-12 gap-10">

<!-- gửi key sản phẩm -->
<?php foreach ($cart as $key => $sp): ?>
    <input type="hidden" name="cart_keys[]" value="<?= htmlspecialchars($key) ?>">
<?php endforeach; ?>

<!-- ================= LEFT ================= -->
<div class="lg:col-span-7 space-y-8">

<section class="border rounded-xl p-6">
<h2 class="text-xl font-black mb-4">Thông tin nhận hàng</h2>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <input name="ho_ten" required
           value="<?= htmlspecialchars($user['ho_ten'] ?? '') ?>"
           placeholder="Họ và tên"
           class="border rounded-lg px-4 py-3">

    <input name="email" required
           value="<?= htmlspecialchars($user['email'] ?? '') ?>"
           placeholder="Email"
           class="border rounded-lg px-4 py-3">

    <input name="so_dien_thoai" required
           value="<?= htmlspecialchars($user['so_dien_thoai'] ?? '') ?>"
           placeholder="Số điện thoại"
           class="border rounded-lg px-4 py-3">

    <input name="dia_chi" required
           value="<?= htmlspecialchars($user['dia_chi'] ?? '') ?>"
           placeholder="Địa chỉ nhận hàng"
           class="border rounded-lg px-4 py-3 md:col-span-2">
</div>
</section>

<section class="border rounded-xl p-6">
<h2 class="text-xl font-black mb-4">Phương thức thanh toán</h2>

<label class="flex items-center gap-3 mb-3 font-medium">
    <input type="radio" name="phuong_thuc" value="COD" checked>
    Thanh toán khi nhận hàng (COD)
</label>

<label class="flex items-center gap-3 font-medium">
    <input type="radio" name="phuong_thuc" value="ChuyenKhoan">
    Chuyển khoản ngân hàng
</label>
</section>

</div>

<!-- ================= RIGHT ================= -->
<div class="lg:col-span-5">
<div class="sticky top-24 border rounded-xl p-6 bg-white">

<h2 class="text-xl font-black mb-4">Đơn hàng của bạn</h2>

<div class="overflow-x-auto border rounded-lg mb-4">
<table class="w-full text-sm">
<thead class="bg-gray-100 text-xs uppercase font-bold">
<tr>
    <th class="p-3 text-left">Sản phẩm</th>
    <th class="p-3 text-center">SL</th>
    <th class="p-3 text-right">Thành tiền</th>
</tr>
</thead>
<tbody>

<?php foreach ($cart as $sp):
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $thanh_tien = $don_gia * $qty;
?>
<tr class="border-t">
<td class="p-3">
    <div class="flex gap-3 items-center">
        <img src="../assets/img/<?= htmlspecialchars($sp['anh'] ?? 'no-image.png') ?>"
             class="w-14 h-14 object-contain border rounded">
        <div>
            <p class="font-bold"><?= htmlspecialchars($sp['ten'] ?? '') ?></p>
            <p class="text-xs text-gray-500">
                <?php if (!empty($sp['size'])): ?>Size <?= htmlspecialchars($sp['size']) ?><?php endif; ?>
            </p>
        </div>
    </div>
</td>

<td class="p-3 text-center font-bold"><?= $qty ?></td>

<td class="p-3 text-right font-bold text-primary">
    <?= number_format($thanh_tien) ?>₫
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<div class="space-y-2 text-sm">
    <div class="flex justify-between">
        <span>Tạm tính</span>
        <span class="font-bold"><?= number_format($tong_tien) ?>₫</span>
    </div>

    <?php if ($tien_giam > 0): ?>
    <div class="flex justify-between text-green-600">
        <span>Giảm giá (<?= htmlspecialchars($ma_voucher ?? '') ?>)</span>
        <span>-<?= number_format($tien_giam) ?>₫</span>
    </div>
    <?php endif; ?>
</div>

<div class="border-t pt-4 mt-4 flex justify-between text-lg font-black">
    <span>Tổng thanh toán</span>
    <span class="text-primary"><?= number_format($tong_thanh_toan) ?>₫</span>
</div>

<button type="submit"
        class="mt-6 w-full bg-primary text-white py-4 rounded-xl font-black uppercase">
    Hoàn tất đơn hàng
</button>

</div>
</div>

</form>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
