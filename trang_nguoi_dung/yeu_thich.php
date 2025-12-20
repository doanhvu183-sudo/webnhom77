<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== BẮT BUỘC ĐĂNG NHẬP ================== */
if (!isset($_SESSION['nguoi_dung'])) {
    header('Location: dang_nhap.php');
    exit;
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id'];

/* ================== XÓA YÊU THÍCH ================== */
if (isset($_GET['xoa'])) {
    $id_sp = (int)$_GET['xoa'];

    $stmt = $pdo->prepare("
        DELETE FROM yeu_thich
        WHERE id_nguoi_dung = ?
          AND id_san_pham = ?
    ");
    $stmt->execute([$id_nguoi_dung, $id_sp]);

    header("Location: yeu_thich.php");
    exit;
}

/* ================== LẤY DS YÊU THÍCH ================== */
$stmt = $pdo->prepare("
    SELECT sp.id_san_pham, sp.ten_san_pham, sp.gia, sp.hinh_anh, yt.ngay_tao
    FROM yeu_thich yt
    JOIN sanpham sp ON sp.id_san_pham = yt.id_san_pham
    WHERE yt.id_nguoi_dung = ?
    ORDER BY yt.ngay_tao DESC
");
$stmt->execute([$id_nguoi_dung]);
$ds_yeu_thich = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1200px] mx-auto px-6 py-10">

<h1 class="text-3xl font-black uppercase mb-8">Sản phẩm yêu thích</h1>

<?php if (empty($ds_yeu_thich)): ?>
    <div class="border rounded-xl p-12 text-center text-gray-500">
        <p class="text-lg mb-4">Bạn chưa có sản phẩm yêu thích nào</p>
        <a href="trang_chu.php"
           class="bg-black text-white px-6 py-3 rounded-full font-bold">
            Tiếp tục mua sắm
        </a>
    </div>
<?php else: ?>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

<?php foreach ($ds_yeu_thich as $sp): ?>
<div class="border rounded-xl p-4 hover:shadow-lg transition">

    <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>">
        <img src="../assets/img/<?= htmlspecialchars($sp['hinh_anh'] ?? 'no-image.png') ?>"
             class="w-full aspect-square object-contain mb-3">
    </a>

    <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>">
        <p class="font-bold text-sm mb-1 line-clamp-2">
            <?= htmlspecialchars($sp['ten_san_pham']) ?>
        </p>
    </a>

    <p class="font-extrabold text-primary mb-3">
        <?= number_format($sp['gia']) ?>₫
    </p>

    <div class="flex gap-2">
        <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
           class="flex-1 text-center border rounded py-2 text-sm font-bold hover:bg-gray-100">
            Xem
        </a>

        <a href="yeu_thich.php?xoa=<?= $sp['id_san_pham'] ?>"
           onclick="return confirm('Xóa khỏi yêu thích?')"
           class="flex-1 text-center bg-red-500 text-white rounded py-2 text-sm font-bold">
            Xóa
        </a>
    </div>

</div>
<?php endforeach; ?>

</div>
<?php endif; ?>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
