<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$blogs = $pdo->query("
    SELECT * FROM blog
    WHERE trang_thai = 1
    ORDER BY ngay_tao DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-[1200px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black uppercase text-center mb-12">
    Blog & Tin tức
</h1>

<div class="grid md:grid-cols-3 gap-8">

<?php foreach ($blogs as $b): ?>
<a href="blog_chi_tiet.php?id=<?= $b['id_blog'] ?>"
   class="border rounded-xl overflow-hidden hover:shadow-lg transition bg-white">

    <img src="../assets/img/<?= $b['hinh_anh'] ?? 'no-image.png' ?>"
         class="w-full h-52 object-cover">

    <div class="p-5">
        <h3 class="font-black text-lg mb-2">
            <?= htmlspecialchars($b['tieu_de']) ?>
        </h3>

        <p class="text-sm text-gray-600 mb-3">
            <?= htmlspecialchars($b['mo_ta_ngan']) ?>
        </p>

        <span class="text-xs text-gray-400">
            <?= date('d/m/Y', strtotime($b['ngay_tao'])) ?>
        </span>
    </div>
</a>
<?php endforeach; ?>

</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
