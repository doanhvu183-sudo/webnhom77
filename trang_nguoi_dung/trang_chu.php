<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

// Tự sinh SALE ảo 1 lần duy nhất
$sql_products = "SELECT id_san_pham, gia, gia_goc FROM sanpham";
$all_products = $pdo->query($sql_products)->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_products as $p) {
    if ($p['gia_goc'] == NULL) {
        $percent = rand(10, 30);
        $gia_goc = $p['gia'];
        $gia_km  = $gia_goc - ($gia_goc * $percent / 100);

        $update = $pdo->prepare(
            "UPDATE sanpham SET gia_goc = :goc, gia_khuyen_mai = :km WHERE id_san_pham = :id"
        );
        $update->execute([
            ':goc' => $gia_goc,
            ':km'  => $gia_km,
            ':id'  => $p['id_san_pham']
        ]);
    }
}

// HÀNG MỚI
$sql_new = "SELECT * FROM SANPHAM ORDER BY id_san_pham DESC LIMIT 20";
$hang_moi = $pdo->query($sql_new)->fetchAll(PDO::FETCH_ASSOC);

// SALE
/* LẤY SẢN PHẨM SALE THẬT */
$sql_sale = "
    SELECT *,
    (gia_goc - gia_khuyen_mai) / gia_goc * 100 AS phan_tram_sale
    FROM sanpham
    WHERE gia_khuyen_mai IS NOT NULL 
    AND gia_khuyen_mai > 0
    AND gia_goc > gia_khuyen_mai
    ORDER BY id_san_pham DESC 
    LIMIT 20";

$hang_sale = $pdo->query($sql_sale)->fetchAll(PDO::FETCH_ASSOC);


// BÁN CHẠY
$sql_best = "SELECT * FROM SANPHAM ORDER BY RAND() LIMIT 18";
$best_sellers = $pdo->query($sql_best)->fetchAll(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="../assets/css/trang_chu.css?v=<?=time()?>">

<main class="home-page">

<!-- ================= HERO SLIDER ================= -->
<section class="hero-slider">

    <div class="hero-item active" style="background-image: url('../assets/img/hero1.jpg');"></div>
    <div class="hero-item" style="background-image: url('../assets/img/hero2.jpg');"></div>
    <div class="hero-item" style="background-image: url('../assets/img/hero3.jpg');"></div>

    <button class="hero-btn hero-prev">&#10094;</button>
    <button class="hero-btn hero-next">&#10095;</button>

    <div class="hero-dots">
        <span class="hero-dot active"></span>
        <span class="hero-dot"></span>
        <span class="hero-dot"></span>
    </div>
</section>


<script src="../assets/js/hero.js"></script>



    <!-- ================= GENDER ================= -->
    <section class="gender-tabs">
        <div class="tabs-inner">
            <a href="danh_muc.php?loai=nu" class="tab-item">Nữ</a>
            <a href="danh_muc.php?loai=nam" class="tab-item">Nam</a>
            <a href="danh_muc.php?loai=treem" class="tab-item">Trẻ Em</a>
            <a href="danh_muc.php?loai=sale" class="tab-item">SALE</a>
        </div>
    </section>

    <!-- ================= CATEGORIES ================= -->
    <section class="category-row">
        <div class="category-inner">

            <a href="danh_muc.php?loai=hangmoi" class="cat-card">
                <img src="../assets/img/dm_hang_moi.jpg"><p>Hàng Mới</p>
            </a>

            <a href="danh_muc.php?loai=banchay" class="cat-card">
                <img src="../assets/img/dm_ban_chay.jpg"><p>Bán Chạy</p>
            </a>

            <a href="danh_muc.php?loai=giaydecao" class="cat-card">
                <img src="../assets/img/dm_giay_de_cao.jpg"><p>Giày Đế Cao</p>
            </a>

            <a href="danh_muc.php?loai=xuhuong" class="cat-card">
                <img src="../assets/img/dm_xu_huong.jpg"><p>Xu Hướng</p>
            </a>

            <a href="danh_muc.php?loai=collab" class="cat-card">
                <img src="../assets/img/dm_collab.jpg"><p>Collab</p>
            </a>

            <a href="danh_muc.php?loai=classic" class="cat-card">
                <img src="../assets/img/dm_classic.jpg"><p>Classic</p>
            </a>

        </div>
    </section>

    <!-- ================= CATEGORY BANNER ================= -->
    <section class="category-banner">
        <a href="danh_muc.php?loai=xbox">
            <img src="../assets/img/sau_danh_muc.jpg">
        </a>
    </section>

    <!-- ================= HÀNG MỚI ================= -->
    <section class="product-slider-section">
        <h2>HÀNG MỚI</h2>

<div class="product-slider">

    <button class="slider-arrow prev">&#10094;</button>

    <div class="slider-wrapper">
        <div class="slider-track">
            <?php foreach ($hang_moi as $sp): ?>
                <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>" class="slider-item"> 
                    <img src="../assets/img/<?= $sp['hinh_anh'] ?>" alt="">
                    <p><?= $sp['ten_san_pham'] ?></p>
                    <strong><?= number_format($sp['gia']) ?>đ</strong>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <button class="slider-arrow next">&#10095;</button>
</div>



    <!-- ================= SALE ================= -->
<section class="sale-section">
    <h2>ĐANG SALE</h2>

<div class="product-slider">

    <button class="slider-arrow prev">&#10094;</button>

    <div class="slider-wrapper">
        <div class="slider-track">

            <?php foreach ($hang_sale as $sp): ?>
                <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>" class="slider-item"> 

                    <div class="product-img">
                        <img src="../assets/img/<?= $sp['hinh_anh'] ?>" alt="">

                        <?php if ($sp['phan_tram_sale'] > 0): ?>
                            <span class="badge-sale">-<?= round($sp['phan_tram_sale']) ?>%</span>
                        <?php endif; ?>
                    </div>

                    <p class="product-name"><?= $sp['ten_san_pham'] ?></p>

                    <div class="product-price">
                        <span class="price-sale"><?= number_format($sp['gia_khuyen_mai']) ?>đ</span>
                        <span class="price-old"><?= number_format($sp['gia_goc']) ?>đ</span>
                    </div>

                    
                </a>
            <?php endforeach; ?>

        </div>
    </div>

    <button class="slider-arrow next">&#10095;</button>
</div>

</section>


    <!-- ================= BEST SELLER ================= -->
    <section class="best-section">
        <h2>BÁN CHẠY</h2>

        <div class="best-grid">
            <?php foreach ($best_sellers as $sp): ?>
            <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>" class="slider-item"> 
                <img src="../assets/img/<?= $sp['hinh_anh'] ?>">
                <p class="best-name"><?= $sp['ten_san_pham'] ?></p>
                <p class="best-price"><?= number_format($sp['gia']) ?>₫</p>
            </a>
            <?php endforeach; ?>
        </div>

    </section>

</main>
<script src="../assets/js/slider_loop.js"></script>

<script src="../assets/js/product_slider.js" defer></script>

<?php require_once __DIR__.'/../giao_dien/footer.php'; ?>
