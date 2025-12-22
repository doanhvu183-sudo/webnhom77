<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$q = trim($_GET['q']);

$sql = $pdo->prepare("SELECT * FROM sanpham WHERE ten_san_pham LIKE :q");
$sql->execute([':q' => "%$q%"]);
$results = $sql->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../assets/css/tim_kiem.css?v=<?=time()?>">

<main class="search-page">
    <h2>Kết quả tìm kiếm cho: "<?= htmlspecialchars($q) ?>"</h2>

    <div class="search-grid">
        <?php foreach ($results as $sp): ?>
            <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>" class="search-item">
                <img src="../assets/img/<?= $sp['hinh_anh'] ?>">
                <p><?= $sp['ten_san_pham'] ?></p>
                <strong><?= number_format($sp['gia']) ?>đ</strong>
            </a>
        <?php endforeach ?>
    </div>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
