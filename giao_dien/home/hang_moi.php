<?php
$sql = "
    SELECT * FROM sanpham
    ORDER BY ngay_tao DESC
    LIMIT 4
";
$rs = mysqli_query($conn, $sql);
?>

<section class="py-10 px-4 md:px-10 flex justify-center bg-white">
<div class="w-full max-w-[1280px]">

<div class="flex items-center justify-center mb-10">
<h2 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight">
HÀNG MỚI
</h2>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">

<?php while ($sp = mysqli_fetch_assoc($rs)) { ?>

<div class="group cursor-pointer">
<div class="relative aspect-square mb-3 bg-[#f7f7f7] rounded-lg overflow-hidden">

<div class="absolute top-2 left-2 z-10">
<span class="text-[10px] font-bold text-blue-600 bg-white px-1.5 py-0.5 border border-blue-100 rounded">
MỚI
</span>
</div>

<div
class="w-full h-full bg-contain bg-center bg-no-repeat p-4 transition-transform duration-300 group-hover:scale-105"
style="background-image:url('../assets/img/<?= $sp['hinh_anh'] ?>')">
</div>

<a href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>"
class="absolute bottom-3 right-3 p-2 bg-white rounded-full shadow hover:bg-black hover:text-white transition-colors opacity-0 group-hover:opacity-100">
<span class="material-symbols-outlined text-sm">add_shopping_cart</span>
</a>

</div>

<h3 class="font-bold text-sm text-center mb-1 text-gray-800">
<?= htmlspecialchars($sp['ten_san_pham']) ?>
</h3>

<?php if ($sp['gia_khuyen_mai'] > 0) { ?>
<p class="text-center font-bold text-sm text-red-600">
<?= number_format($sp['gia_khuyen_mai']) ?>₫
<span class="text-gray-400 text-xs line-through ml-1">
<?= number_format($sp['gia_goc']) ?>₫
</span>
</p>
<?php } else { ?>
<p class="text-center font-bold text-sm">
<?= number_format($sp['gia']) ?>₫
</p>
<?php } ?>

</div>

<?php } ?>

</div>
</div>
</section>
