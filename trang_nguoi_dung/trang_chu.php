<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

/**
 * Đồng nhất hiển thị với Admin:
 * - Chỉ hiển thị SP: sanpham.hien_thi=1 AND sanpham.trang_thai=1
 * - Chỉ hiển thị SP thuộc DM đang bật: danhmuc.hien_thi=1
 * - KHÔNG tạo SALE ảo (không update DB khi load trang)
 */

/* ================== DATA ================== */

// Hàng mới (lọc ẩn/hiện + danh mục bật)
$hang_moi = $pdo->query("
    SELECT s.*
    FROM sanpham s
    JOIN danhmuc dm ON dm.id_danh_muc = s.id_danh_muc
    WHERE s.hien_thi = 1
      AND s.trang_thai = 1
      AND dm.hien_thi = 1
    ORDER BY s.ngay_tao DESC, s.id_san_pham DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Sale (lọc ẩn/hiện + danh mục bật)
// - Base giá gốc ưu tiên gia_goc, nếu null/0 thì dùng gia
$hang_sale = $pdo->query("
    SELECT s.*,
           ROUND(
             (
               (COALESCE(NULLIF(s.gia_goc,0), s.gia) - s.gia_khuyen_mai)
               / NULLIF(COALESCE(NULLIF(s.gia_goc,0), s.gia),0)
             ) * 100
           ) AS phan_tram_sale
    FROM sanpham s
    JOIN danhmuc dm ON dm.id_danh_muc = s.id_danh_muc
    WHERE s.hien_thi = 1
      AND s.trang_thai = 1
      AND dm.hien_thi = 1
      AND s.gia_khuyen_mai IS NOT NULL
      AND s.gia_khuyen_mai > 0
      AND COALESCE(NULLIF(s.gia_goc,0), s.gia) > s.gia_khuyen_mai
    ORDER BY s.ngay_cap_nhat DESC, s.id_san_pham DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Bán chạy: ưu tiên theo chitiet_donhang (sum so_luong)
try {
    $best_sellers = $pdo->query("
        SELECT s.*,
               SUM(ct.so_luong) AS sold_qty
        FROM chitiet_donhang ct
        JOIN sanpham s ON s.id_san_pham = ct.id_san_pham
        JOIN danhmuc dm ON dm.id_danh_muc = s.id_danh_muc
        WHERE s.hien_thi = 1
          AND s.trang_thai = 1
          AND dm.hien_thi = 1
        GROUP BY s.id_san_pham
        ORDER BY sold_qty DESC, s.id_san_pham DESC
        LIMIT 18
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $best_sellers = [];
}

// Nếu chưa có dữ liệu bán chạy → fallback random (nhưng vẫn lọc ẩn/hiện)
if (empty($best_sellers)) {
    $best_sellers = $pdo->query("
        SELECT s.*
        FROM sanpham s
        JOIN danhmuc dm ON dm.id_danh_muc = s.id_danh_muc
        WHERE s.hien_thi = 1
          AND s.trang_thai = 1
          AND dm.hien_thi = 1
        ORDER BY RAND()
        LIMIT 18
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<main class="bg-white">

<!-- ================= HERO ================= -->
<section class="relative h-[780px] bg-cover bg-center"
         style="background-image:url('../assets/img/hero2.jpg')">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative h-full max-w-[1400px] mx-auto px-10 flex flex-col justify-center text-white">
        <h1 class="text-5xl md:text-6xl font-black uppercase mb-6">
            Holiday Collection
        </h1>
        <a href="danh_muc.php?loai=hangmoi"
           class="w-fit bg-white text-black px-8 py-3 rounded-full font-bold hover:bg-gray-200 transition">
            Mua Ngay
        </a>
    </div>
</section>

<!-- ================= QUICK GENDER ================= -->
<section class="max-w-[1200px] mx-auto py-10 px-6 grid grid-cols-2 md:grid-cols-4 gap-4">
    <a href="danh_muc.php?loai=nu" class="quick-btn">Nữ</a>
    <a href="danh_muc.php?loai=nam" class="quick-btn">Nam</a>
    <a href="danh_muc.php?loai=treem" class="quick-btn">Trẻ Em</a>
    <a href="danh_muc.php?loai=sale" class="quick-btn">SALE</a>
</section>
<!-- ================= CATEGORIES ================= -->
<section class="category-row">
    <div class="category-inner">
        <a href="danh_muc.php?loai=hangmoi" class="cat-card">
            <img src="../assets/img/dm_hang_moi.jpg">
            <p>Hàng Mới</p>
        </a>
        <a href="danh_muc.php?loai=banchay" class="cat-card">
            <img src="../assets/img/dm_ban_chay.jpg">
            <p>Bán Chạy</p>
        </a>
        <a href="danh_muc.php?loai=giaydecao" class="cat-card">
            <img src="../assets/img/dm_giay_de_cao.jpg">
            <p>Giày Đế Cao</p>
        </a>
        <a href="danh_muc.php?loai=xuhuong" class="cat-card">
            <img src="../assets/img/dm_xu_huong.jpg">
            <p>Xu Hướng</p>
        </a>
        <a href="danh_muc.php?loai=collab" class="cat-card">
            <img src="../assets/img/dm_collab.jpg">
            <p>Collab</p>
        </a>
        <a href="danh_muc.php?loai=classic" class="cat-card">
            <img src="../assets/img/dm_classic.jpg">
            <p>Classic</p>
        </a>
    </div>
</section>

<!-- ================= CATEGORY BANNER ================= -->
<section class="category-banner">
    <a href="danh_muc.php?loai=xbox">
        <img src="../assets/img/sau_danh_muc.jpg" alt="">
    </a>
</section>

<!-- ================= HÀNG MỚI ================= -->
<section class="max-w-[1400px] mx-auto px-6 py-14">
    <h2 class="section-title">HÀNG MỚI</h2>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <?php foreach ($hang_moi as $sp): ?>
        <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
           class="product-card">
            <img src="../assets/img/<?= $sp['hinh_anh'] ?>" alt="">
            <p class="product-name"><?= $sp['ten_san_pham'] ?></p>
            <p class="product-price"><?= number_format($sp['gia']) ?>₫</p>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================= SALE ================= -->
<section class="bg-gray-50 py-14">
<div class="max-w-[1400px] mx-auto px-6">
    <h2 class="section-title text-red-600">ĐANG SALE</h2>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
        <?php foreach ($hang_sale as $sp): ?>
        <?php
            $base_goc = (int)($sp['gia_goc'] ?? 0);
            if ($base_goc <= 0) $base_goc = (int)($sp['gia'] ?? 0);
        ?>
        <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
           class="product-card relative">

            <span class="sale-badge">
                -<?= round($sp['phan_tram_sale']) ?>%
            </span>

            <img src="../assets/img/<?= $sp['hinh_anh'] ?>" alt="">

            <p class="product-name"><?= $sp['ten_san_pham'] ?></p>

            <div class="flex items-center gap-2">
                <span class="text-red-600 font-bold">
                    <?= number_format($sp['gia_khuyen_mai']) ?>₫
                </span>
                <span class="line-through text-gray-400 text-sm">
                    <?= number_format($base_goc) ?>₫
                </span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
</section>

<!-- ================= BEST SELLER ================= -->
<section class="max-w-[1400px] mx-auto px-6 py-14">
    <h2 class="section-title">BÁN CHẠY</h2>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
        <?php foreach ($best_sellers as $sp): ?>
        <a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
           class="product-card">
            <img src="../assets/img/<?= $sp['hinh_anh'] ?>">
            <p class="product-name"><?= $sp['ten_san_pham'] ?></p>
            <p class="product-price"><?= number_format($sp['gia']) ?>₫</p>
        </a>
        <?php endforeach; ?>
    </div>
</section>

</main>

<style>
.quick-btn{
    border:1px solid #000;
    padding:14px;
    text-align:center;
    font-weight:700;
    text-transform:uppercase;
    transition:.2s;
}
.quick-btn:hover{
    background:#000;
    color:#fff;
}
.section-title{
    font-size:28px;
    font-weight:900;
    margin-bottom:32px;
    text-transform:uppercase;
}
.product-card{
    background:#fff;
    border-radius:12px;
    padding:14px;
    transition:.25s;
}
.product-card:hover{
    transform:translateY(-4px);
    box-shadow:0 10px 30px rgba(0,0,0,.08);
}
.product-card img{
    width:100%;
    aspect-ratio:1/1;
    object-fit:contain;
    margin-bottom:10px;
}
.product-name{
    font-weight:700;
    font-size:14px;
    margin-bottom:6px;
}
.product-price{
    font-weight:800;
}
.sale-badge{
    position:absolute;
    top:10px;
    left:10px;
    background:#da0b0b;
    color:#fff;
    font-size:12px;
    padding:4px 8px;
    border-radius:6px;
    font-weight:800;
}
.category-row{
    padding:50px 0;
}
.category-inner{
    max-width:1200px;
    margin:auto;
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:20px;
}
.cat-card{
    text-align:center;
    font-weight:700;
    transition:.25s;
}
.cat-card img{
    width:100%;
    aspect-ratio:1/1;
    object-fit:cover;
    border-radius:14px;
    margin-bottom:10px;
}
.cat-card:hover{
    transform:translateY(-4px);
}
.category-banner{
    max-width:1400px;
    margin:30px auto 0;
    padding:0 20px;
}
.category-banner img{
    width:100%;
    border-radius:18px;
}
</style>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
