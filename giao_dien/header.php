<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== BADGE COUNT ================== */
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['qty'] ?? $item['so_luong_mua'] ?? 1);
    }
}

$so_yt = 0;
$so_tb = 0;
$uid = null;

if (isset($_SESSION['nguoi_dung'])) {
    $uid = $_SESSION['nguoi_dung']['id'];

    // Yêu thích
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM yeu_thich WHERE id_nguoi_dung=?");
    $stmt->execute([$uid]);
    $so_yt = (int)$stmt->fetchColumn();

    // Thông báo chưa đọc
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM thong_bao
        WHERE id_nguoi_dung=? AND da_doc=0
    ");
    $stmt->execute([$uid]);
    $so_tb = (int)$stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Crocs Vietnam</title>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200..800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>

<script>
tailwind.config = {
    theme: {
        extend: {
            colors: { primary: "#da0b0b" },
            fontFamily: { display: ["Plus Jakarta Sans", "sans-serif"] }
        }
    }
}
</script>
</head>

<body class="font-display bg-white text-[#181111] overflow-x-hidden">

<!-- ANNOUNCEMENT -->
<div class="bg-black text-white text-[11px] font-bold py-2 px-4 flex justify-between">
    <span>🚚 Miễn phí giao hàng toàn quốc</span>
    <span class="hidden md:block">Dép chính hãng – Xu hướng 2025</span>
</div>

<!-- HEADER -->
<header class="sticky top-0 z-50 bg-white border-b">
<div class="max-w-[1440px] mx-auto px-6 py-4 flex justify-between items-center">

<!-- LOGO -->
<a href="../trang_nguoi_dung/trang_chu.php"
   class="text-3xl font-black italic uppercase tracking-wide">
    Crocs™
</a>

<!-- MENU -->
<nav class="hidden lg:flex gap-8 text-[13px] font-extrabold uppercase">

    <a href="../trang_nguoi_dung/trang_chu.php" class="hover:text-primary">Trang chủ</a>

    <!-- SẢN PHẨM -->
    <div class="relative group">
        <div class="cursor-pointer hover:text-primary">Sản phẩm</div>

        <div class="absolute left-0 top-full pt-3 hidden group-hover:block
                    bg-white border shadow-lg w-48 z-50">
            <a href="../trang_nguoi_dung/danh_muc.php?gioi_tinh=nu"
               class="block px-5 py-3 hover:bg-gray-100">Dép nữ</a>
            <a href="../trang_nguoi_dung/danh_muc.php?gioi_tinh=nam"
               class="block px-5 py-3 hover:bg-gray-100">Dép nam</a>
            <a href="../trang_nguoi_dung/danh_muc.php?gioi_tinh=tre_em"
               class="block px-5 py-3 hover:bg-gray-100">Dép trẻ em</a>
            <a href="../trang_nguoi_dung/danh_muc.php?loai=phu_kien"
               class="block px-5 py-3 hover:bg-gray-100">Phụ kiện</a>
        </div>
    </div>

    <a href="../trang_nguoi_dung/uu_dai.php" class="text-primary">Ưu đãi</a>
    <a href="../trang_nguoi_dung/blog.php">Blog</a>
    <a href="../trang_nguoi_dung/faq.php">FAQ</a>
    <a href="../trang_nguoi_dung/gioi_thieu.php">Giới thiệu</a>
    <a href="../trang_nguoi_dung/lien_he.php">Liên hệ</a>
</nav>

<!-- ICONS -->
<div class="flex items-center gap-6">

    <!-- SEARCH -->
<form action="../trang_nguoi_dung/tim_kiem.php"
      method="get"
      class="hidden md:block relative">

    <input
        name="q"
        required
        placeholder="Tìm kiếm sản phẩm..."
        class="border rounded-full pl-4 pr-10 py-2 text-sm w-56"/>

    <button type="submit"
            class="absolute right-3 top-2 text-gray-400 hover:text-primary">
        <span class="material-symbols-outlined text-[20px]">
            search
        </span>
    </button>
</form>


    <!-- FAVORITE -->
    <div class="relative flex items-center justify-center size-9">
        <a href="../trang_nguoi_dung/yeu_thich.php"
           class="material-symbols-outlined leading-none hover:text-primary">
            favorite
        </a>
        <?php if ($so_yt > 0): ?>
        <span class="absolute -top-1 -right-1 bg-primary text-white text-[10px]
                     size-4 rounded-full flex items-center justify-center">
            <?= $so_yt ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- CART -->
    <div class="relative flex items-center justify-center size-9">
        <a href="../trang_nguoi_dung/gio_hang.php"
           class="material-symbols-outlined leading-none hover:text-primary">
            shopping_cart
        </a>
        <?php if ($cartCount > 0): ?>
        <span class="absolute -top-1 -right-1 bg-primary text-white text-[10px]
                     size-4 rounded-full flex items-center justify-center">
            <?= $cartCount ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- ACCOUNT -->
<div class="relative flex items-center justify-center size-9 group">

    <span class="material-symbols-outlined leading-none cursor-pointer hover:text-primary">
        account_circle
    </span>

    <!-- HOVER BRIDGE -->
    <div class="absolute top-full left-0 w-full h-3"></div>

    <!-- DROPDOWN -->
    <div class="absolute right-0 top-[calc(100%+6px)]
                invisible opacity-0 group-hover:visible group-hover:opacity-100
                transition-all duration-150
                bg-white border rounded shadow w-44 text-sm z-50">

        <a href="../trang_nguoi_dung/tai_khoan.php"
           class="block px-4 py-2 hover:bg-gray-100">
            Tài khoản
        </a>

        <a href="../trang_nguoi_dung/don_hang.php"
           class="block px-4 py-2 hover:bg-gray-100">
            Đơn hàng
        </a>

        <a href="../trang_nguoi_dung/dang_xuat.php"
           class="block px-4 py-2 text-red-600 hover:bg-gray-100">
            Đăng xuất
        </a>
    </div>
</div>
        

        <!-- NOTIFICATION -->
<div class="relative flex items-center justify-center size-9 group">

    <span class="material-symbols-outlined leading-none cursor-pointer hover:text-primary">
        notifications
    </span>

    <?php if ($so_tb > 0): ?>
    <span class="absolute -top-1 -right-1 bg-primary text-white text-[10px]
                 size-4 rounded-full flex items-center justify-center">
        <?= $so_tb ?>
    </span>
    <?php endif; ?>

    <!-- HOVER BRIDGE -->
    <div class="absolute top-full left-0 w-full h-3"></div>

    <!-- DROPDOWN -->
    <div class="absolute right-0 top-[calc(100%+6px)]
                invisible opacity-0 group-hover:visible group-hover:opacity-100
                transition-all duration-150
                bg-white border rounded-xl shadow-lg w-80 z-50 text-sm">

        <div class="font-bold px-4 py-3 border-b">
            Thông báo
        </div>

        <div class="max-h-64 overflow-y-auto">
            <?php if (empty($ds_tb)): ?>
                <p class="px-4 py-4 text-gray-500 text-center">
                    Không có thông báo
                </p>
            <?php else: ?>
                <?php foreach ($ds_tb as $tb): ?>
                <a href="<?= htmlspecialchars($tb['link'] ?? '#') ?>"
                   class="block px-4 py-3 hover:bg-gray-50 border-b last:border-0">
                    <p class="font-semibold"><?= htmlspecialchars($tb['tieu_de']) ?></p>
                    <p class="text-xs text-gray-500">
                        <?= date('d/m/Y H:i', strtotime($tb['ngay_tao'])) ?>
                    </p>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="../trang_nguoi_dung/thong_bao.php"
           class="block px-4 py-3 text-center font-bold hover:bg-gray-100">
            Xem tất cả
        </a>
    </div>
</div>
</div>



</div>

<div class="bg-[#a71818] text-white text-[11px] font-bold py-2 text-center">
    PHÍ SHIP 0Đ | Ưu đãi hấp dẫn mỗi ngày
</div>
</header>
