<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$vouchers = $pdo->query("
    SELECT *
    FROM voucher
    WHERE trang_thai = 1
    ORDER BY ngay_tao DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-[1200px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black uppercase text-center mb-10">
    Ưu đãi & Voucher
</h1>

<?php if (!$vouchers): ?>
<p class="text-center text-gray-500">
    Hiện chưa có ưu đãi.
</p>
<?php else: ?>
<div class="grid md:grid-cols-2 gap-6">
<?php foreach ($vouchers as $v): ?>
<div class="border rounded-xl p-6 flex justify-between items-center">
    <div>
        <h3 class="font-black"><?= $v['ma_voucher'] ?></h3>
        <p class="text-sm text-gray-600">
            <?= $v['loai']=='PHAN_TRAM'
                ? 'Giảm '.$v['gia_tri'].'%'
                : 'Giảm '.number_format($v['gia_tri']).'₫' ?>
        </p>
        <p class="text-xs text-gray-400">
            Hết hạn: <?= $v['ngay_ket_thuc'] ?>
        </p>
    </div>
    <a href="gio_hang.php"
       class="bg-primary text-white px-5 py-2 rounded-full font-bold">
        Sử dụng
    </a>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
