<?php
session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Đăng nhập - Crocs Vietnam</title>

<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200..800&display=swap" rel="stylesheet">

<style>
body { font-family: 'Plus Jakarta Sans', sans-serif; }
</style>
</head>

<body class="bg-white">

<?php include __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[420px] mx-auto px-4 py-20">

<h1 class="text-3xl font-black uppercase text-center mb-10">
    Đăng nhập
</h1>

<form method="post" action="xu_ly_dang_nhap.php" class="space-y-5">

<input name="login" placeholder="Email hoặc tên đăng nhập" required class="w-full border px-4 py-3 rounded">

<input name="mat_khau" type="password" placeholder="Mật khẩu" required class="w-full border px-4 py-3 rounded">

<button class="w-full bg-black text-white py-4 rounded-full font-bold uppercase">
    Đăng nhập
</button>

<p class="text-center text-sm mt-4">
    Chưa có tài khoản?
    <a href="dang_ky.php" class="font-bold underline">Đăng ký</a>
</p>

</form>
</main>

<?php include __DIR__ . '/../giao_dien/footer.php'; ?>
</body>
</html>
