<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Đăng Ký Tài Khoản - Crocs Vietnam</title>

<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200..800&display=swap" rel="stylesheet">

<style>
body { font-family: 'Plus Jakarta Sans', sans-serif; }
input[type="radio"]:checked + label {
    background:#000;color:#fff;border-color:#000
}
</style>
</head>

<body class="bg-white">

<?php include __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[520px] mx-auto px-4 py-16">

<h1 class="text-3xl font-black uppercase text-center mb-10">
    Đăng ký tài khoản
</h1>

<form method="post" action="xu_ly_dang_ky.php" class="space-y-4">

<div class="flex gap-4">
    <input name="ho" placeholder="Họ" required class="w-1/2 border px-4 py-3 rounded">
    <input name="ten" placeholder="Tên" required class="w-1/2 border px-4 py-3 rounded">
</div>

<input name="ten_dang_nhap" placeholder="Tên đăng nhập" required class="w-full border px-4 py-3 rounded">

<input name="email" type="email" placeholder="Email" required class="w-full border px-4 py-3 rounded">

<input name="so_dien_thoai" placeholder="Số điện thoại" class="w-full border px-4 py-3 rounded">

<input name="dia_chi" placeholder="Địa chỉ" class="w-full border px-4 py-3 rounded">

<div class="flex gap-4">
    <input hidden id="gt_nam" type="radio" name="gioi_tinh" value="Nam">
    <label for="gt_nam" class="border px-4 py-2 rounded cursor-pointer">Nam</label>

    <input hidden id="gt_nu" type="radio" name="gioi_tinh" value="Nữ">
    <label for="gt_nu" class="border px-4 py-2 rounded cursor-pointer">Nữ</label>
</div>

<input name="ngay_sinh" type="date" class="w-full border px-4 py-3 rounded">

<input name="mat_khau" type="password" placeholder="Mật khẩu" required class="w-full border px-4 py-3 rounded">

<input name="xac_nhan_mat_khau" type="password" placeholder="Xác nhận mật khẩu" required class="w-full border px-4 py-3 rounded">

<button class="w-full bg-black text-white py-4 rounded-full font-bold uppercase">
    Đăng ký
</button>

<p class="text-center text-sm mt-4">
    Đã có tài khoản?
    <a href="dang_nhap.php" class="font-bold underline">Đăng nhập</a>
</p>

</form>
</main>

<?php include __DIR__ . '/../giao_dien/footer.php'; ?>
</body>
</html>
