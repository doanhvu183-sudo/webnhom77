<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ho_ten = trim($_POST['ho_ten']);
    $so_dien_thoai = trim($_POST['so_dien_thoai']);
    $dia_chi = trim($_POST['dia_chi']);

    $stmt = $pdo->prepare("
        UPDATE nguoidung
        SET ho_ten = ?, so_dien_thoai = ?, dia_chi = ?
        WHERE id_nguoi_dung = ?
    ");
    $stmt->execute([$ho_ten, $so_dien_thoai, $dia_chi, $user['id']]);

    $_SESSION['nguoi_dung']['ho_ten'] = $ho_ten;
    $_SESSION['nguoi_dung']['so_dien_thoai'] = $so_dien_thoai;
    $_SESSION['nguoi_dung']['dia_chi'] = $dia_chi;

    $success = 'Cập nhật thông tin thành công';
}
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[600px] mx-auto px-6 py-12">

<h1 class="text-2xl font-black mb-6">Cập nhật tài khoản</h1>

<form method="post" class="border rounded-xl p-6 space-y-4 bg-white">

    <?php if ($success): ?>
        <div class="bg-green-50 text-green-600 px-4 py-3 rounded font-bold">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <div>
        <label class="font-bold">Họ tên</label>
        <input name="ho_ten" required
               value="<?= htmlspecialchars($user['ho_ten']) ?>"
               class="w-full border rounded px-4 py-2 mt-1">
    </div>

    <div>
        <label class="font-bold">Email</label>
        <input value="<?= htmlspecialchars($user['email']) ?>"
               disabled
               class="w-full border rounded px-4 py-2 mt-1 bg-gray-100">
    </div>

    <div>
        <label class="font-bold">Số điện thoại</label>
        <input name="so_dien_thoai"
               value="<?= htmlspecialchars($user['so_dien_thoai'] ?? '') ?>"
               class="w-full border rounded px-4 py-2 mt-1">
    </div>

    <div>
        <label class="font-bold">Địa chỉ</label>
        <textarea name="dia_chi"
                  class="w-full border rounded px-4 py-2 mt-1"><?= htmlspecialchars($user['dia_chi'] ?? '') ?></textarea>
    </div>

    <button class="w-full bg-black text-white py-3 rounded-full font-black">
        Lưu thay đổi
    </button>

    <a href="tai_khoan.php"
       class="block text-center text-sm underline text-gray-500">
        ← Quay lại tài khoản
    </a>

</form>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
