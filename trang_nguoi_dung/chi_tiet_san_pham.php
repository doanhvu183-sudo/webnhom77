<?php
// chi_tiet_san_pham.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ========= ID ========= */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Sản phẩm không tồn tại');

/* ========= SẢN PHẨM ========= */
$stmt = $pdo->prepare("SELECT * FROM sanpham WHERE id_san_pham=? LIMIT 1");
$stmt->execute([$id]);
$sp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sp) die('Không tìm thấy sản phẩm');

/* ========= GIÁ HIỆU LỰC ========= */
$gia_hieu_luc = (int)(
    (!empty($sp['gia_khuyen_mai']) && (int)$sp['gia_khuyen_mai'] > 0)
        ? $sp['gia_khuyen_mai']
        : $sp['gia']
);

/* ========= ADD TO CART / BUY NOW ========= */
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
    $size = $_POST['size'] ?? '';
    $qty  = max(1, (int)($_POST['qty'] ?? 1));

    if (!$size) {
        echo "<script>alert('Vui lòng chọn size');</script>";
    } else {
        $key = $id . '_' . $size;

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$key] = [
                'id'      => $id,
                'ten'     => $sp['ten_san_pham'],
                'anh'     => $sp['hinh_anh'],
                'size'    => $size,
                'qty'     => $qty,

                // đồng bộ giá: dùng don_gia là chuẩn, giữ 'gia' để tương thích code cũ
                'don_gia' => $gia_hieu_luc,
                'gia'     => $gia_hieu_luc
            ];
        }

        if (isset($_POST['buy_now'])) {
            header("Location: gio_hang.php");
        } else {
            echo "<script>alert('Đã thêm vào giỏ hàng');</script>";
        }
        exit;
    }
}

/* ========= KIỂM TRA ĐÃ MUA ========= */
$da_mua = false;
$id_nguoi_dung = $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? null);

if ($id_nguoi_dung) {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM donhang d
        JOIN chitiet_donhang ct ON d.id_don_hang = ct.id_don_hang
        WHERE d.id_nguoi_dung = ?
          AND ct.id_san_pham = ?
          AND d.trang_thai = 'HOAN_TAT'
        LIMIT 1
    ");
    $stmt->execute([$id_nguoi_dung, $id]);
    $da_mua = (bool)$stmt->fetch();
}

/* ========= GỬI ĐÁNH GIÁ ========= */
if (isset($_POST['gui_danh_gia']) && $da_mua) {
    $so_sao = (int)$_POST['so_sao'];
    $noi_dung = trim($_POST['noi_dung']);

    if ($so_sao >= 1 && $so_sao <= 5 && $noi_dung !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO danh_gia
            (id_san_pham, id_nguoi_dung, so_sao, noi_dung, trang_thai)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$id, $id_nguoi_dung, $so_sao, $noi_dung]);
        echo "<script>alert('Đánh giá đã gửi và chờ duyệt');</script>";
    }
}

/* ========= ĐÁNH GIÁ ĐÃ DUYỆT ========= */
$stmt = $pdo->prepare("
    SELECT dg.so_sao, dg.noi_dung, nd.ho_ten
    FROM danh_gia dg
    JOIN nguoidung nd ON dg.id_nguoi_dung = nd.id_nguoi_dung
    WHERE dg.id_san_pham=? AND dg.trang_thai=1
    ORDER BY dg.ngay_tao DESC
");
$stmt->execute([$id]);
$danh_gia = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_review = count($danh_gia);
$avg_star = $total_review
    ? round(array_sum(array_column($danh_gia, 'so_sao')) / $total_review, 1)
    : 0;

/* ========= SẢN PHẨM LIÊN QUAN ========= */
$stmt = $pdo->prepare("
    SELECT * FROM sanpham
    WHERE id_danh_muc=? AND id_san_pham!=?
    ORDER BY RAND() LIMIT 6
");
$stmt->execute([$sp['id_danh_muc'], $id]);
$sp_lien_quan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1280px] mx-auto px-6 py-10">

<div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

<!-- IMAGE + DESC -->
<div class="lg:col-span-7">
    <div class="bg-gray-100 rounded-xl p-6 flex justify-center">
        <img src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>"
             class="w-[70%] object-contain">
    </div>

    <div class="mt-6 border rounded-xl p-6">
        <h2 class="text-xl font-black mb-3">Mô tả sản phẩm</h2>
        <div class="text-sm text-gray-700">
            <?= nl2br(htmlspecialchars($sp['mo_ta'])) ?>
        </div>
    </div>
</div>

<!-- INFO -->
<div class="lg:col-span-5 space-y-6">
    <h1 class="text-3xl font-black uppercase"><?= htmlspecialchars($sp['ten_san_pham']) ?></h1>

    <?php if ($total_review): ?>
        <div class="text-yellow-500 font-bold">
            <?= $avg_star ?> ★ (<?= $total_review ?> đánh giá)
        </div>
    <?php endif; ?>

    <p class="text-2xl font-black text-primary"><?= number_format($gia_hieu_luc) ?>₫</p>

    <form method="post" class="space-y-6">

        <!-- SIZE -->
        <div>
            <p class="font-bold mb-2">Chọn size</p>
            <div class="grid grid-cols-5 gap-2">
                <?php for ($i=36;$i<=45;$i++): ?>
                <label>
                    <input type="radio" name="size" value="<?= $i ?>" required class="hidden peer">
                    <div class="border rounded py-2 text-center font-bold cursor-pointer
                                peer-checked:bg-black peer-checked:text-white">
                        <?= $i ?>
                    </div>
                </label>
                <?php endfor; ?>
            </div>
        </div>

        <!-- QTY -->
        <div class="flex items-center gap-4">
            <span class="font-bold">Số lượng</span>
            <input type="number" name="qty" value="1" min="1"
                   class="w-20 border rounded text-center font-bold">
        </div>

        <!-- BUTTONS -->
        <div class="flex gap-4">
            <button name="add_to_cart"
                    class="flex-1 bg-black text-white py-4 rounded-full font-black">
                Thêm vào giỏ
            </button>
            <button name="buy_now"
                    class="flex-1 border-2 border-black py-4 rounded-full font-black">
                Mua ngay
            </button>
        </div>
    </form>
</div>
</div>
<a href="yeu_thich_them.php?id=<?= $sp['id_san_pham'] ?>"
   class="block text-center border-2 border-black py-3 rounded-full font-black">
    ❤️ Thêm vào yêu thích
</a>

<!-- ĐÁNH GIÁ -->
<section class="mt-20 border-t pt-10">
<h2 class="text-2xl font-black mb-6">Đánh giá sản phẩm</h2>

<?php if ($da_mua): ?>
<form method="post" class="border rounded-xl p-6 mb-6 bg-gray-50">
    <select name="so_sao" required class="border rounded px-3 py-2 mb-3">
        <option value="">Chọn số sao</option>
        <?php for ($i=5;$i>=1;$i--): ?>
        <option value="<?= $i ?>"><?= $i ?> sao</option>
        <?php endfor; ?>
    </select>
    <textarea name="noi_dung" rows="4" required
              class="w-full border rounded px-3 py-2 mb-3"
              placeholder="Nhận xét của bạn"></textarea>
    <button name="gui_danh_gia"
            class="bg-black text-white px-6 py-3 rounded font-bold">
        Gửi đánh giá
    </button>
</form>
<?php elseif (isset($_SESSION['nguoi_dung'])): ?>
<p class="italic text-gray-500">Bạn cần mua sản phẩm để đánh giá.</p>
<?php endif; ?>

<?php foreach ($danh_gia as $dg): ?>
<div class="border rounded-xl p-5 mb-4">
    <div class="flex justify-between">
        <p class="font-bold"><?= htmlspecialchars($dg['ho_ten']) ?></p>
        <p class="text-yellow-500"><?= str_repeat('★', (int)$dg['so_sao']) ?></p>
    </div>
    <p class="text-sm mt-2"><?= nl2br(htmlspecialchars($dg['noi_dung'])) ?></p>
</div>
<?php endforeach; ?>
</section>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
