<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
$id = $user['id'];
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1300px] mx-auto px-6 py-12">
<div class="grid grid-cols-12 gap-8">

<!-- ================= SIDEBAR ================= -->
<aside class="col-span-3">
<div class="border rounded-xl p-5 bg-white sticky top-28">

<div class="flex items-center gap-4 mb-6">
    <img src="../assets/img/avatar/<?= $user['avatar'] ?? 'default.png' ?>"
         class="w-14 h-14 rounded-full object-cover border">
    <div>
        <p class="font-black"><?= htmlspecialchars($user['ho_ten']) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
    </div>
</div>

<nav class="space-y-2 text-sm font-bold">
    <button onclick="showTab('profile')" class="menu-item">👤 Thông tin cá nhân</button>
    <button onclick="showTab('orders')" class="menu-item">📦 Đơn hàng</button>
    <button onclick="showTab('favorite')" class="menu-item">❤️ Yêu thích</button>
    <button onclick="showTab('notify')" class="menu-item">🔔 Thông báo</button>
    <button onclick="showTab('voucher')" class="menu-item">🎁 Voucher cá nhân</button>
    <button onclick="showTab('suggest')" class="menu-item">✨ Gợi ý cho bạn</button>
    <a href="dang_xuat.php" class="block text-red-600 px-3 py-2 rounded hover:bg-red-50">
        🚪 Đăng xuất
    </a>
</nav>

</div>
</aside>

<!-- ================= CONTENT ================= -->
<section class="col-span-9 space-y-10">

<!-- ========== PROFILE ========== -->
<div id="tab-profile" class="tab">
<h2 class="text-2xl font-black mb-6">Thông tin tài khoản</h2>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 border rounded-xl p-6 bg-white">

<form method="post" enctype="multipart/form-data" class="md:col-span-2 flex items-center gap-6">
    <img src="../assets/img/avatar/<?= $user['avatar'] ?? 'default.png' ?>"
         class="w-24 h-24 rounded-full object-cover border">
    <div>
        <input type="file" name="avatar" class="text-sm mb-2">
        <button name="upload_avatar" class="font-bold underline">Đổi avatar</button>
    </div>
</form>

<div>
    <label class="text-sm font-bold">Họ tên</label>
    <input value="<?= htmlspecialchars($user['ho_ten']) ?>"
           class="border rounded px-4 py-2 w-full">
</div>

<div>
    <label class="text-sm font-bold">Email</label>
    <input value="<?= htmlspecialchars($user['email']) ?>"
           class="border rounded px-4 py-2 w-full" disabled>
</div>

<div>
    <label class="text-sm font-bold">Số điện thoại</label>
    <input value="<?= htmlspecialchars($user['so_dien_thoai'] ?? '') ?>"
           class="border rounded px-4 py-2 w-full">
</div>

<div>
    <label class="text-sm font-bold">Địa chỉ</label>
    <input value="<?= htmlspecialchars($user['dia_chi'] ?? '') ?>"
           class="border rounded px-4 py-2 w-full">
</div>

</div>
</div>

<!-- ========== ORDERS ========== -->
<div id="tab-orders" class="tab hidden">
<h2 class="text-2xl font-black mb-6">Đơn hàng của tôi</h2>

<?php
$orders = $pdo->prepare("
    SELECT * FROM donhang
    WHERE id_nguoi_dung=?
    ORDER BY ngay_dat DESC
");
$orders->execute([$id]);
?>

<div class="space-y-4">
<?php foreach ($orders as $o): ?>
<div class="border rounded-xl p-5 bg-white flex justify-between items-center">
    <div>
        <p class="font-bold">Đơn #<?= $o['id_don_hang'] ?></p>
        <p class="text-sm text-gray-500"><?= $o['trang_thai'] ?></p>
    </div>
    <div class="font-black"><?= number_format($o['tong_tien']) ?>₫</div>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- ========== FAVORITE ========== -->
<div id="tab-favorite" class="tab hidden">
<h2 class="text-2xl font-black mb-6">Sản phẩm yêu thích</h2>

<?php
$fav = $pdo->prepare("
    SELECT sp.*
    FROM yeu_thich yt
    JOIN sanpham sp ON sp.id_san_pham = yt.id_san_pham
    WHERE yt.id_nguoi_dung=?
");
$fav->execute([$id]);
?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
<?php foreach ($fav as $sp): ?>
<a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
   class="border rounded-xl p-3 bg-white hover:shadow">
    <img src="../assets/img/<?= $sp['hinh_anh'] ?>" class="aspect-square object-contain mb-2">
    <p class="font-bold text-sm"><?= $sp['ten_san_pham'] ?></p>
</a>
<?php endforeach; ?>
</div>
</div>

<!-- ========== NOTIFY ========== -->
<div id="tab-notify" class="tab hidden">
<h2 class="text-2xl font-black mb-6">Thông báo</h2>

<?php
$tb = $pdo->prepare("
    SELECT * FROM thong_bao
    WHERE id_nguoi_dung=?
    ORDER BY ngay_tao DESC
");
$tb->execute([$id]);
?>

<div class="space-y-4">
<?php foreach ($tb as $n): ?>
<div class="border rounded-xl p-4 bg-white">
    <p class="font-bold"><?= $n['tieu_de'] ?></p>
    <p class="text-sm text-gray-600"><?= $n['noi_dung'] ?></p>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- ========== VOUCHER ========== -->
<div id="tab-voucher" class="tab hidden">
<h2 class="text-2xl font-black mb-6">Voucher cá nhân</h2>

<?php
$vc = $pdo->prepare("
    SELECT * FROM voucher
    WHERE trang_thai=1
");
$vc->execute();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
<?php foreach ($vc as $v): ?>
<div class="border rounded-xl p-4 bg-green-50">
    <p class="font-black"><?= $v['ma_voucher'] ?></p>
    <p class="text-sm">
        <?= $v['loai']==='PHAN_TRAM' ? 'Giảm '.$v['gia_tri'].'%' : 'Giảm '.number_format($v['gia_tri']).'₫' ?>
    </p>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- ========== SUGGEST ========== -->
<div id="tab-suggest" class="tab hidden">
<h2 class="text-2xl font-black mb-6">Gợi ý cho bạn</h2>

<?php
$goiy = $pdo->query("
    SELECT * FROM sanpham ORDER BY RAND() LIMIT 6
");
?>

<div class="grid grid-cols-2 md:grid-cols-3 gap-4">
<?php foreach ($goiy as $sp): ?>
<a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
   class="border rounded-xl p-3 bg-white hover:shadow">
    <img src="../assets/img/<?= $sp['hinh_anh'] ?>" class="aspect-square object-contain mb-2">
    <p class="font-bold"><?= $sp['ten_san_pham'] ?></p>
</a>
<?php endforeach; ?>
</div>
</div>

</section>
</div>
</main>

<script>
function showTab(tab){
    document.querySelectorAll('.tab').forEach(t => t.classList.add('hidden'));
    document.getElementById('tab-'+tab).classList.remove('hidden');
}
</script>

<style>
.menu-item{
    width:100%;
    text-align:left;
    padding:10px 12px;
    border-radius:8px;
}
.menu-item:hover{
    background:#f3f4f6;
}
</style>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
