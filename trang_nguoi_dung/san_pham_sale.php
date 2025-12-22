<?php
require_once __DIR__ . '/../giao_dien/header.php';
require_once __DIR__ . '/../xu_ly/lay_san_pham_sale.php';
?>

<link rel="stylesheet" href="../assets/css/sale.css?v=<?=time()?>">

<main class="sale-page">

<h2 class="title">SẢN PHẨM ĐANG SALE</h2>

<div class="sale-grid">
<?php foreach ($hang_sale as $sp): ?>
    <div class="sale-item">

        <div class="img-box">
            <img src="../assets/img/<?= $sp['hinh_anh'] ?>" alt="">
            <span class="badge-sale">-<?= round($sp['phan_tram_sale']) ?>%</span>
        </div>

        <p class="name"><?= $sp['ten_san_pham'] ?></p>

        <div class="price-box">
            <span class="gia-sale"><?= number_format($sp['gia_khuyen_mai']) ?>đ</span>
            <span class="gia-goc"><?= number_format($sp['gia_goc']) ?>đ</span>
        </div>

    </div>
<?php endforeach; ?>
</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
