<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../giao_dien/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Bài viết không tồn tại');
$pdo->prepare("
    UPDATE blog
    SET luot_xem = luot_xem + 1
    WHERE id_blog = ?
")->execute([$id]);


$stmt = $pdo->prepare("SELECT * FROM blog WHERE id_blog = ?");
$stmt->execute([$id]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) die('Không tìm thấy bài viết');
?>

<main class="max-w-[900px] mx-auto px-6 py-14">

<h1 class="text-4xl font-black mb-4">
    <?= htmlspecialchars($blog['tieu_de']) ?>
</h1>

<p class="text-sm text-gray-400 mb-6">
    <?= date('d/m/Y H:i', strtotime($blog['ngay_tao'])) ?>
</p>

<?php if ($blog['hinh_anh']): ?>
<img src="../assets/img/<?= $blog['hinh_anh'] ?>"
     class="w-full rounded-xl mb-8">
<?php endif; ?>

<div class="prose max-w-none">
    <?= nl2br($blog['noi_dung']) ?>
</div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
