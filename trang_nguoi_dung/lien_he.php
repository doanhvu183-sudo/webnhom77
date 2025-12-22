<?php
session_start();
require_once __DIR__ . '/../giao_dien/header.php';
?>

<main class="max-w-[1000px] mx-auto px-6 py-12">

<h1 class="text-3xl font-black uppercase mb-8">Liên hệ với chúng tôi</h1>

<div class="grid grid-cols-1 md:grid-cols-2 gap-10">

<!-- THÔNG TIN LIÊN HỆ -->
<div class="space-y-5">
    <h2 class="text-xl font-black">Crocs Vietnam</h2>

    <p>
        <strong>📍 Địa chỉ:</strong><br>
        123 Nguyễn Trãi, Quận 1, TP.HCM
    </p>

    <p>
        <strong>📞 Hotline:</strong><br>
        0909 999 999
    </p>

    <p>
        <strong>📧 Email:</strong><br>
        support@crocs-vietnam.vn
    </p>

    <p class="text-gray-500 text-sm">
        Thời gian làm việc: Thứ 2 – Thứ 7 (08:00 – 18:00)
    </p>
</div>

<!-- FORM -->
<div class="border rounded-xl p-6 bg-white">
<form action="lien_he_xu_ly.php" method="post" class="space-y-4">

    <input name="ho_ten" required
           placeholder="Họ và tên"
           class="w-full border rounded px-4 py-3">

    <input name="email" type="email" required
           placeholder="Email"
           class="w-full border rounded px-4 py-3">

    <input name="tieu_de" required
           placeholder="Tiêu đề"
           class="w-full border rounded px-4 py-3">

    <textarea name="noi_dung" rows="5" required
              placeholder="Nội dung liên hệ"
              class="w-full border rounded px-4 py-3"></textarea>

    <button class="w-full bg-black text-white py-3 rounded-full font-black">
        Gửi liên hệ
    </button>

</form>

<?php if (!empty($_SESSION['lien_he_ok'])): ?>
<p class="text-green-600 font-bold mt-4">
    <?= $_SESSION['lien_he_ok']; unset($_SESSION['lien_he_ok']); ?>
</p>
<?php endif; ?>

<?php if (!empty($_SESSION['lien_he_err'])): ?>
<p class="text-red-600 font-bold mt-4">
    <?= $_SESSION['lien_he_err']; unset($_SESSION['lien_he_err']); ?>
</p>
<?php endif; ?>

</div>

</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
