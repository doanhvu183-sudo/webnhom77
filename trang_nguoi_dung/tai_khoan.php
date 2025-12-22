<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1400px] mx-auto px-6 py-10">

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    <!-- ========== SIDEBAR ========== -->
    <aside class="lg:col-span-3 border rounded-xl p-4 bg-white space-y-2">

        <div class="flex items-center gap-3 p-3 border-b">
            <img src="../assets/avatar/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>"
                 class="w-12 h-12 rounded-full object-cover border">
            <div>
                <p class="font-bold"><?= htmlspecialchars($user['ho_ten']) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
            </div>
        </div>

        <button data-tab="profile" class="tk-tab active">
            👤 Thông tin tài khoản
        </button>

        <button data-tab="orders" class="tk-tab">
            📦 Đơn hàng của tôi
        </button>

        <button data-tab="favorite" class="tk-tab">
            ❤️ Sản phẩm yêu thích
        </button>

        <button data-tab="notify" class="tk-tab">
            🔔 Thông báo
        </button>

        <button data-tab="voucher" class="tk-tab">
            🎁 Voucher cá nhân
        </button>

        <button data-tab="setting" class="tk-tab">
            ⚙️ gợi ý sản phẩm
        </button>

        <a href="dang_xuat.php"
           class="block px-4 py-3 text-red-600 font-bold hover:bg-gray-100 rounded">
            🚪 Đăng xuất
        </a>

    </aside>

    <!-- ========== CONTENT ========== -->
    <section class="lg:col-span-9 space-y-8">

        <!-- TAB: PROFILE -->
        <div id="tab-profile" class="tk-content">
            <div class="border rounded-xl p-6 bg-white">
    <h2 class="text-xl font-black mb-6">👤 Thông tin tài khoản</h2>

    <form method="post" action="cap_nhat_tai_khoan.php" enctype="multipart/form-data"
          class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div>
            <label class="block font-bold mb-1">Họ tên</label>
            <input name="ho_ten" value="<?= htmlspecialchars($user['ho_ten']) ?>"
                   class="w-full border rounded px-4 py-3">
        </div>

        <div>
            <label class="block font-bold mb-1">Email</label>
            <input name="email" value="<?= htmlspecialchars($user['email']) ?>"
                   class="w-full border rounded px-4 py-3">
        </div>

        <div>
            <label class="block font-bold mb-1">Số điện thoại</label>
            <input name="so_dien_thoai" value="<?= htmlspecialchars($user['so_dien_thoai'] ?? '') ?>"
                   class="w-full border rounded px-4 py-3">
        </div>

        <div>
            <label class="block font-bold mb-1">Địa chỉ</label>
            <input name="dia_chi" value="<?= htmlspecialchars($user['dia_chi'] ?? '') ?>"
                   class="w-full border rounded px-4 py-3">
        </div>

        <div class="md:col-span-2">
            <label class="block font-bold mb-1">Avatar</label>
            <input type="file" name="avatar" class="border rounded px-4 py-3">
        </div>

        <div class="md:col-span-2">
            <button class="bg-primary text-white px-8 py-3 rounded-full font-black">
                Cập nhật thông tin
            </button>
        </div>

    </form>
</div>

        </div>

        <!-- TAB: ORDERS -->
        <div id="tab-orders" class="tk-content hidden">
            <!-- PHẦN 3 -->
             <?php
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) tong,
        SUM(trang_thai='CHO_XU_LY') cho_xu_ly,
        SUM(trang_thai='DANG_GIAO') dang_giao,
        SUM(trang_thai='HOAN_TAT') hoan_tat
    FROM donhang
    WHERE id_nguoi_dung = ?
");
$stmt->execute([$user['id']]);
$dh = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="border rounded-xl p-6 bg-white">
    <h2 class="text-xl font-black mb-6">📦 Đơn hàng của tôi</h2>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="tk-box">Tổng đơn<br><b><?= $dh['tong'] ?></b></div>
        <div class="tk-box">Chờ xử lý<br><b><?= $dh['cho_xu_ly'] ?></b></div>
        <div class="tk-box">Đang giao<br><b><?= $dh['dang_giao'] ?></b></div>
        <div class="tk-box">Hoàn tất<br><b><?= $dh['hoan_tat'] ?></b></div>
    </div>

    <a href="don_hang.php"
       class="inline-block border px-6 py-3 rounded-full font-bold hover:bg-gray-100">
        Xem chi tiết đơn hàng →
    </a>
</div>

<style>
.tk-box{
    border:1px solid #eee;
    border-radius:14px;
    padding:16px;
    text-align:center;
    font-weight:800;
}
</style>

        </div>

        <!-- TAB: FAVORITE -->
        <div id="tab-favorite" class="tk-content hidden">
            <!-- PHẦN 4 -->
             <?php
$stmt = $pdo->prepare("
    SELECT sp.*
    FROM yeu_thich yt
    JOIN sanpham sp ON sp.id_san_pham = yt.id_san_pham
    WHERE yt.id_nguoi_dung = ?
    ORDER BY yt.ngay_tao DESC
    LIMIT 8
");
$stmt->execute([$user['id']]);
$yeu_thich = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="border rounded-xl p-6 bg-white">
    <h2 class="text-xl font-black mb-6">❤️ Sản phẩm yêu thích</h2>

    <?php if (!$yeu_thich): ?>
        <p class="text-gray-500">Bạn chưa có sản phẩm yêu thích.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($yeu_thich as $sp): ?>
                <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
                   class="border rounded-xl p-4 hover:shadow">
                    <img src="../assets/img/<?= $sp['hinh_anh'] ?>"
                         class="w-full aspect-square object-contain mb-2">
                    <p class="font-bold text-sm"><?= $sp['ten_san_pham'] ?></p>
                    <p class="text-primary font-black"><?= number_format($sp['gia']) ?>₫</p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

        </div>

        <!-- TAB: NOTIFY -->
        <div id="tab-notify" class="tk-content hidden">
            <!-- PHẦN 5 -->
             <div class="border rounded-xl p-6 bg-white">
    <h2 class="text-xl font-black mb-6">🔔 Thông báo</h2>

    <ul class="space-y-4 text-sm">
        <li class="border-b pb-3">
            📦 Đơn hàng <b>#10231</b> đang được giao
        </li>
        <li class="border-b pb-3">
            ❌ Đơn hàng <b>#10212</b> đã bị hủy
        </li>
        <li class="border-b pb-3">
            🎁 Bạn vừa nhận được voucher giảm 15%
        </li>
    </ul>

    <p class="text-xs text-gray-400 mt-4">
        (Phần này sẵn sàng gắn DB + badge chuông)
    </p>
</div>

        </div>

        <!-- TAB: VOUCHER -->
        <div id="tab-voucher" class="tk-content hidden">
            <!-- PHẦN 6 -->
             <?php
$stmt = $pdo->prepare("
    SELECT *
    FROM voucher_nguoidung vnd
    JOIN voucher v ON v.ma_voucher = vnd.ma_voucher
    WHERE vnd.id_nguoi_dung = ?
");
$stmt->execute([$user['id']]);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="border rounded-xl p-6 bg-white">
    <h2 class="text-xl font-black mb-6">🎁 Voucher cá nhân</h2>

    <?php if (!$vouchers): ?>
        <p class="text-gray-500">Bạn chưa có voucher.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($vouchers as $v): ?>
                <div class="border rounded-xl p-4 flex justify-between items-center">
                    <div>
                        <b><?= $v['ma_voucher'] ?></b><br>
                        <span class="text-sm text-gray-500">
                            Hết hạn: <?= $v['ngay_ket_thuc'] ?>
                        </span>
                    </div>
                    <a href="gio_hang.php"
                       class="bg-black text-white px-4 py-2 rounded-full text-sm font-bold">
                        Sử dụng
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

        </div>

        <!-- TAB: SETTING -->
        <div id="tab-setting" class="tk-content hidden">
            <!-- PHẦN 7 -->
             <?php
$stmt = $pdo->query("
    SELECT *
    FROM sanpham
    ORDER BY RAND()
    LIMIT 6
");
$goi_y = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="border rounded-xl p-6 bg-white mt-10">
    <h2 class="text-xl font-black mb-6">✨ Gợi ý dành cho bạn</h2>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
        <?php foreach ($goi_y as $sp): ?>
            <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
               class="border rounded-xl p-4 hover:shadow">
                <img src="../assets/img/<?= $sp['hinh_anh'] ?>"
                     class="w-full aspect-square object-contain mb-2">
                <p class="font-bold text-sm"><?= $sp['ten_san_pham'] ?></p>
                <p class="text-primary font-black"><?= number_format($sp['gia']) ?>₫</p>
            </a>
        <?php endforeach; ?>
    </div>
</div>

        </div>

    </section>

</div>
</main>

<!-- ========== SCRIPT TAB ========== -->
<script>
document.querySelectorAll('.tk-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tk-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tk-content').forEach(c => c.classList.add('hidden'));

        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
    });
});
</script>

<style>
.tk-tab{
    width:100%;
    text-align:left;
    padding:12px 14px;
    border-radius:10px;
    font-weight:700;
    font-size:14px;
}
.tk-tab:hover{
    background:#f3f4f6;
}
.tk-tab.active{
    background:#da0b0b;
    color:#fff;
}
</style>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
