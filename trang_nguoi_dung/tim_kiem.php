<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo "<div class='max-w-[1200px] mx-auto px-6 py-10 text-center text-gray-500'>
            Vui lòng nhập từ khóa tìm kiếm
          </div>";
    require_once __DIR__ . '/../giao_dien/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT *
    FROM sanpham
    WHERE ten_san_pham LIKE ?
       OR mo_ta LIKE ?
    ORDER BY ngay_tao DESC
");
$like = "%$q%";
$stmt->execute([$like, $like]);
$ds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-[1200px] mx-auto px-6 py-10">
    <h1 class="text-2xl font-black mb-6">
        Kết quả tìm kiếm cho: “<?= htmlspecialchars($q) ?>”
    </h1>

    <?php if (empty($ds)): ?>
        <p class="text-gray-500">Không tìm thấy sản phẩm phù hợp.</p>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
            <?php foreach ($ds as $sp): ?>
            <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
               class="product-card">
                <img src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>">
                <p class="product-name"><?= htmlspecialchars($sp['ten_san_pham']) ?></p>
                <p class="product-price"><?= number_format($sp['gia']) ?>₫</p>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
