<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $st = $pdo->prepare("SELECT * FROM admin WHERE email=? AND trang_thai=1 LIMIT 1");
  $st->execute([$email]);
  $ad = $st->fetch(PDO::FETCH_ASSOC);

  if ($ad && password_verify($password, $ad['password'])) {
    $_SESSION['admin'] = [
      'id' => (int)($ad['id_admin'] ?? $ad['id'] ?? 0),
      'ho_ten' => $ad['ho_ten'] ?? 'Admin',
      'email' => $ad['email'] ?? '',
      'vai_tro' => $ad['vai_tro'] ?? 'admin',
      'avatar' => $ad['avatar'] ?? null
    ];
    header("Location: index.php"); exit;
  } else {
    $error = 'Sai email hoặc mật khẩu.';
  }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center font-['Manrope']">
  <form method="post" class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg border">
    <div class="text-center mb-6">
      <div class="mx-auto size-12 rounded-2xl bg-blue-600 text-white font-extrabold flex items-center justify-center text-xl">C</div>
      <h1 class="text-2xl font-extrabold mt-3">Crocs Admin</h1>
      <p class="text-sm text-slate-500">Đăng nhập để quản trị hệ thống</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl text-sm font-semibold mb-4">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <label class="block text-sm font-bold">Email</label>
    <input name="email" required class="mt-1 w-full px-4 py-2 rounded-xl bg-slate-100 border-0 focus:ring-2 focus:ring-blue-200">

    <label class="block text-sm font-bold mt-4">Mật khẩu</label>
    <input type="password" name="password" required class="mt-1 w-full px-4 py-2 rounded-xl bg-slate-100 border-0 focus:ring-2 focus:ring-blue-200">

    <button class="w-full mt-6 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-2xl font-extrabold">
      Đăng nhập
    </button>
  </form>
</body>
</html>
