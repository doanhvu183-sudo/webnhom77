<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mat_khau_cu = $_POST['mat_khau_cu'] ?? '';
    $mat_khau_moi = $_POST['mat_khau_moi'] ?? '';
    $xac_nhan = $_POST['xac_nhan'] ?? '';

    if ($mat_khau_moi !== $xac_nhan) {
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        $stmt = $pdo->prepare("SELECT mat_khau FROM nguoidung WHERE id_nguoi_dung = ?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($mat_khau_cu, $hash)) {
            $error = 'Mật khẩu hiện tại không đúng';
        } else {
            $newHash = password_hash($mat_khau_moi, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("
                UPDATE nguoidung SET mat_khau = ? WHERE id_nguoi_dung = ?
            ");
            $upd->execute([$newHash, $user['id']]);

            $success = 'Đổi mật khẩu thành công';
        }
    }
}
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[600px] mx-auto px-6 py-12">

<h1 class="text-2xl font-black mb-6">Đổi mật khẩu</h1>

<form method="post" class="border rounded-xl p-6 space-y-4 bg-white">

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 px-4 py-3 rounded font-bold">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-50 text-green-600 px-4 py-3 rounded font-bold">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <div>
        <label class="font-bold">Mật khẩu hiện tại</label>
        <input type="password" name="mat_khau_cu" required
               class="w-full border rounded px-4 py-2 mt-1">
    </div>

    <div>
        <label class="font-bold">Mật khẩu mới</label>
        <input type="password" name="mat_khau_moi" required
               class="w-full border rounded px-4 py-2 mt-1">
    </div>

    <div>
        <label class="font-bold">Xác nhận mật khẩu mới</label>
        <input type="password" name="xac_nhan" required
               class="w-full border rounded px-4 py-2 mt-1">
    </div>

    <button class="w-full bg-black text-white py-3 rounded-full font-black">
        Cập nhật mật khẩu
    </button>

    <a href="tai_khoan.php"
       class="block text-center text-sm underline text-gray-500">
        ← Quay lại tài khoản
    </a>

</form>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
