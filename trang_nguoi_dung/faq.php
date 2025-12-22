<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$faqs = $pdo->query("
    SELECT * FROM faq
    WHERE trang_thai = 1
    ORDER BY thu_tu ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-[900px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black uppercase text-center mb-10">
    Câu hỏi thường gặp
</h1>

<div class="space-y-4">

<?php foreach ($faqs as $faq): ?>
<div class="border rounded-xl p-5 bg-white">
    <button onclick="toggleFAQ(this)"
            class="w-full flex justify-between items-center font-bold text-left">
        <span><?= htmlspecialchars($faq['cau_hoi']) ?></span>
        <span class="material-symbols-outlined">expand_more</span>
    </button>

    <div class="mt-3 text-sm text-gray-600 hidden">
        <?= nl2br(htmlspecialchars($faq['tra_loi'])) ?>
    </div>
</div>
<?php endforeach; ?>

</div>

</main>

<script>
function toggleFAQ(btn) {
    const content = btn.nextElementSibling;
    content.classList.toggle('hidden');
}
</script>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
