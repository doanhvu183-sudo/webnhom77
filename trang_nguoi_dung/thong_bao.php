<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$id_nguoi_dung = $_SESSION['nguoi_dung']['id'];

/* ================== LẤY THÔNG BÁO ================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM thong_bao
    WHERE id_nguoi_dung = ?
    ORDER BY ngay_tao DESC
");
$stmt->execute([$id_nguoi_dung]);
$thong_bao = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================== ĐÁNH DẤU ĐÃ ĐỌC ================== */
$pdo->prepare("
    UPDATE thong_bao
    SET da_doc = 1
    WHERE id_nguoi_dung = ?
")->execute([$id_nguoi_dung]);
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[900px] mx-auto px-6 py-10">

<h1 class="text-3xl font-black mb-8">Thông báo của bạn</h1>

<?php if (!$thong_bao): ?>
<div class="border rounded-xl p-10 text-center text-gray-500">
    Bạn chưa có thông báo nào
</div>
<?php else: ?>

<div class="space-y-4">
<?php foreach ($thong_bao as $tb): ?>
    <div class="border rounded-xl p-5
                <?= $tb['da_doc'] ? 'bg-white' : 'bg-gray-50 border-primary' ?>">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-bold"><?= htmlspecialchars($tb['tieu_de']) ?></h2>
            <span class="text-xs text-gray-500">
                <?= date('d/m/Y H:i', strtotime($tb['ngay_tao'])) ?>
            </span>
        </div>
        <p class="text-sm text-gray-700">
            <?= nl2br(htmlspecialchars($tb['noi_dung'])) ?>
        </p>

        <?php if (!empty($tb['link'])): ?>
        <a href="<?= htmlspecialchars($tb['link']) ?>"
           class="inline-block mt-3 text-primary font-bold text-sm">
            Xem chi tiết →
        </a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php endif; ?>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
