<?php
// admin/dang_nhap.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$error = '';

// Nếu đã đăng nhập rồi thì vào thẳng dashboard
if (!empty($_SESSION['admin']) && !empty($_SESSION['admin']['id_admin'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ Email và Mật khẩu';
    } else {
        // Cho phép đăng nhập bằng email (đúng yêu cầu hiện tại)
        $stmt = $pdo->prepare("
            SELECT id_admin, email, username, password, ho_ten, avatar, trang_thai, vai_tro
            FROM admin
            WHERE email = ? AND trang_thai = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Chuẩn hoá vai_tro để so quyền ổn định
            $vaiTro = strtoupper(trim((string)($admin['vai_tro'] ?? 'ADMIN')));

            session_regenerate_id(true);

            // Lưu đúng key để các file khác dùng thống nhất
            $_SESSION['admin'] = [
                'id_admin' => (int)$admin['id_admin'],
                'ho_ten'   => $admin['ho_ten'] ?? '',
                'email'    => $admin['email'] ?? '',
                'username' => $admin['username'] ?? '',
                'vai_tro'  => $vaiTro,
                'avatar'   => $admin['avatar'] ?? null,
            ];

            header("Location: index.php");
            exit;
        } else {
            $error = 'Sai email hoặc mật khẩu';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center font-sans">

<form method="post" class="bg-white w-full max-w-md p-8 rounded-2xl shadow-lg">
    <h1 class="text-2xl font-extrabold text-center mb-2">Crocs Admin</h1>
    <p class="text-center text-sm text-slate-500 mb-6">Đăng nhập để quản trị hệ thống</p>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 p-3 rounded mb-4 text-sm">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="mb-4">
        <label class="text-sm font-semibold">Email</label>
        <input name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               class="w-full mt-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
    </div>

    <div class="mb-6">
        <label class="text-sm font-semibold">Mật khẩu</label>
        <input type="password" name="password" required
               class="w-full mt-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200">
    </div>

    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold">
        Đăng nhập
    </button>

    <div class="text-xs text-slate-400 mt-4 text-center">
        Tip: admin/nhân viên được phân quyền theo <b>vai_tro</b>.
    </div>
</form>

</body>
</html>
