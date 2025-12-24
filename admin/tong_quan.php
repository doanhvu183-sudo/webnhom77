<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ====== BẢO VỆ ADMIN ====== */
if (!isset($_SESSION['admin'])) {
    header('Location: dang_nhap.php');
    exit;
}

/* ====== TRUY VẤN DỮ LIỆU THẬT ====== */

// Doanh thu hôm nay
$doanhThuHomNay = $pdo->query("
    SELECT IFNULL(SUM(tong_thanh_toan),0)
    FROM donhang
    WHERE DATE(ngay_dat) = CURDATE()
")->fetchColumn();

// Doanh thu hôm qua
$doanhThuHomQua = $pdo->query("
    SELECT IFNULL(SUM(tong_thanh_toan),0)
    FROM donhang
    WHERE DATE(ngay_dat) = CURDATE() - INTERVAL 1 DAY
")->fetchColumn();

// Tổng đơn
$tongDon = $pdo->query("SELECT COUNT(*) FROM donhang")->fetchColumn();

// Đơn chờ xử lý
$donChoXuLy = $pdo->query("
    SELECT COUNT(*) FROM donhang WHERE trang_thai = 'CHO_XU_LY'
")->fetchColumn();

// Tổng tồn kho
$tongTonKho = $pdo->query("
    SELECT IFNULL(SUM(so_luong),0) FROM sanpham
")->fetchColumn();

// SP sắp hết (<=5)
$spSapHet = $pdo->query("
    SELECT COUNT(*) FROM sanpham WHERE so_luong <= 5
")->fetchColumn();


?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin – Tổng quan</title>

<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>

<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: { display: ['Manrope', 'sans-serif'] },
      colors: {
        primary: '#137fec',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444'
      }
    }
  }
}
</script>
</head>

<body class="font-display bg-slate-100 text-slate-800 h-screen flex">

<!-- SIDEBAR -->
<aside class="w-64 bg-white border-r p-5 hidden md:flex flex-col">
    <div class="flex items-center gap-3 mb-8">
        <div class="w-9 h-9 bg-primary text-white flex items-center justify-center rounded font-extrabold">C</div>
        <span class="font-extrabold text-lg">Crocs Admin</span>
    </div>

    <nav class="space-y-2 text-sm font-semibold">
        <a class="block px-4 py-3 rounded-xl bg-primary text-white" href="#">Tổng quan</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-100" href="san_pham.php">Sản phẩm</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-100" href="don_hang.php">Đơn hàng</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-100" href="khach_hang.php">Khách hàng</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-100" href="voucher.php">Voucher</a>
        <a class="block px-4 py-3 rounded-xl hover:bg-slate-100" href="dang_xuat.php">Đăng xuất</a>
    </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 overflow-y-auto">

<!-- TOPBAR -->
<header class="bg-white border-b h-16 flex items-center justify-between px-6 sticky top-0">
    <h1 class="text-xl font-extrabold">Bảng điều khiển</h1>
    <div class="flex items-center gap-4">
        <span class="material-symbols-outlined">notifications</span>
        <span class="material-symbols-outlined">account_circle</span>
    </div>
</header>

<div class="p-8 space-y-8">

<!-- GREETING -->
<div>
    <h2 class="text-2xl font-extrabold">Chào buổi sáng, Admin 👋</h2>
    <p class="text-slate-500 text-sm">Tổng quan tình hình kinh doanh hôm nay</p>
</div>

<!-- CARDS -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
    <div class="bg-white p-6 rounded-2xl shadow">
        <p class="text-sm text-slate-500">Doanh thu hôm nay</p>
        <p class="text-2xl font-extrabold text-primary">
            <?= number_format($doanhThuHomNay) ?> ₫
        </p>
        <p class="text-xs text-slate-400 mt-1">Hôm qua <?= number_format($doanhThuHomQua) ?> ₫</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
        <p class="text-sm text-slate-500">Tổng đơn hàng</p>
        <p class="text-2xl font-extrabold"><?= $tongDon ?></p>
        <p class="text-xs text-slate-400 mt-1">Chờ xử lý <?= $donChoXuLy ?></p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
        <p class="text-sm text-slate-500">Tổng tồn kho</p>
        <p class="text-2xl font-extrabold"><?= number_format($tongTonKho) ?></p>
        <p class="text-xs text-danger mt-1">Sắp hết <?= $spSapHet ?> SP</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
        <p class="text-sm text-slate-500">Biên lợi nhuận</p>
        <p class="text-2xl font-extrabold">~30%</p>
    </div>
</div>

<!-- BEST SELL -->
<div class="bg-white p-6 rounded-2xl shadow">
    <h3 class="font-extrabold mb-4">Bán chạy nhất</h3>
    <div class="space-y-3">
        
    </div>
</div>

</div>
</main>
</body>
</html>
