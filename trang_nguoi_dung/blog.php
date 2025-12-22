<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

/* ===== BLOG CHÍNH ===== */
$blogs = $pdo->query("
    SELECT * FROM blog
    WHERE trang_thai = 1
    ORDER BY ngay_tao DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ===== SIDEBAR ===== */
$blog_moi = $pdo->query("
    SELECT id_blog, tieu_de
    FROM blog
    WHERE trang_thai = 1
    ORDER BY ngay_tao DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$blog_xem_nhieu = $pdo->query("
    SELECT id_blog, tieu_de, luot_xem
    FROM blog
    WHERE trang_thai = 1
    ORDER BY luot_xem DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-[1400px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black uppercase text-center mb-12">
    Blog & Tin tức
</h1>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

<!-- ================= BLOG LIST ================= -->
<div class="lg:col-span-8 space-y-8">

<?php foreach ($blogs as $b): ?>
<div class="border rounded-2xl overflow-hidden bg-white hover:shadow-lg transition">

    <img src="../assets/img/<?= $b['hinh_anh'] ?? 'no-image.png' ?>"
         class="w-full h-64 object-cover">

    <div class="p-6">

        <!-- BADGE -->
        <span class="inline-block bg-black text-white text-xs px-3 py-1 rounded-full mb-3">
            <?= htmlspecialchars($b['loai']) ?>
        </span>

        <h2 class="text-2xl font-black mb-2">
            <?= htmlspecialchars($b['tieu_de']) ?>
        </h2>

        <div class="flex flex-wrap gap-4 text-xs text-gray-500 mb-3">
            <span>👤 <?= htmlspecialchars($b['tac_gia']) ?></span>
            <span>📅 <?= date('d/m/Y', strtotime($b['ngay_tao'])) ?></span>
            <span>👁 <?= (int)$b['luot_xem'] ?> lượt xem</span>
        </div>

        <p class="text-gray-600 mb-4">
            <?= htmlspecialchars($b['mo_ta_ngan']) ?>
        </p>

        <a href="blog_chi_tiet.php?id=<?= $b['id_blog'] ?>"
           class="inline-block font-bold text-primary hover:underline">
            Xem chi tiết →
        </a>

    </div>
</div>
<?php endforeach; ?>

</div>

<!-- ================= SIDEBAR ================= -->
<aside class="lg:col-span-4 space-y-8">

<!-- BLOG MỚI -->
<div class="border rounded-xl p-5">
    <h3 class="font-black mb-4 uppercase">Bài mới nhất</h3>
    <ul class="space-y-3 text-sm">
        <?php foreach ($blog_moi as $m): ?>
        <li>
            <a href="blog_chi_tiet.php?id=<?= $m['id_blog'] ?>"
               class="hover:text-primary">
                <?= htmlspecialchars($m['tieu_de']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- BLOG XEM NHIỀU -->
<div class="border rounded-xl p-5">
    <h3 class="font-black mb-4 uppercase">Xem nhiều</h3>
    <ul class="space-y-3 text-sm">
        <?php foreach ($blog_xem_nhieu as $x): ?>
        <li class="flex justify-between">
            <a href="blog_chi_tiet.php?id=<?= $x['id_blog'] ?>"
               class="hover:text-primary">
                <?= htmlspecialchars($x['tieu_de']) ?>
            </a>
            <span class="text-gray-400">
                <?= $x['luot_xem'] ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

</aside>

</div>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
