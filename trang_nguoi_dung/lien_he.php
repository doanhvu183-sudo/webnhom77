<?php
require_once __DIR__ . '/../giao_dien/header.php';
?>

<main class="max-w-[1200px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black uppercase text-center mb-12">
    Liên hệ với chúng tôi
</h1>

<div class="grid md:grid-cols-2 gap-12">

<!-- ================= THÔNG TIN ================= -->
<div>
    <h2 class="text-2xl font-black mb-4">Crocs Vietnam</h2>

    <ul class="space-y-4 text-sm text-gray-700">
        <li class="flex gap-3">
            <span class="material-symbols-outlined">location_on</span>
            <span>383 Hồ Tùng Mậu, Cấu Giấy, Hà Nội</span>
        </li>

        <li class="flex gap-3">
            <span class="material-symbols-outlined">call</span>
            <span>Hotline: <strong>0974775265</strong></span>
        </li>

        <li class="flex gap-3">
            <span class="material-symbols-outlined">mail</span>
            <span>Email: doanhvu183@gmail.com</span>
        </li>

        <li class="flex gap-3">
            <span class="material-symbols-outlined">schedule</span>
            <span>Thứ 2 – CN | 08:00 – 21:00</span>
        </li>
    </ul>

    <p class="mt-6 text-gray-600 text-sm">
        Mọi thắc mắc về đơn hàng, sản phẩm, đổi trả hoặc hợp tác,
        vui lòng gửi thông tin cho chúng tôi qua form bên cạnh.
    </p>
</div>

<!-- ================= FORM ================= -->
<div class="border rounded-xl p-8 bg-white shadow-sm">

<form method="post" class="space-y-5">

    <div>
        <label class="block text-sm font-bold mb-1">Họ và tên</label>
        <input type="text" required
               class="w-full border rounded px-4 py-3"
               placeholder="Nguyễn Văn A">
    </div>

    <div>
        <label class="block text-sm font-bold mb-1">Email</label>
        <input type="email" required
               class="w-full border rounded px-4 py-3"
               placeholder="email@example.com">
    </div>

    <div>
        <label class="block text-sm font-bold mb-1">Số điện thoại</label>
        <input type="text"
               class="w-full border rounded px-4 py-3"
               placeholder="09xxxxxxxx">
    </div>

    <div>
        <label class="block text-sm font-bold mb-1">Nội dung</label>
        <textarea rows="5" required
                  class="w-full border rounded px-4 py-3"
                  placeholder="Nhập nội dung liên hệ..."></textarea>
    </div>

    <button type="submit"
            class="w-full bg-primary text-white py-4 rounded-full font-black uppercase">
        Gửi liên hệ
    </button>

</form>

<p class="text-xs text-gray-400 mt-4 text-center">
    Chúng tôi sẽ phản hồi trong vòng 24h làm việc.
</p>

</div>

</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
