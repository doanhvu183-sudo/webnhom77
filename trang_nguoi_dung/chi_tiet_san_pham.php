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
$gia_goc = (int)($sp['gia'] ?? 0);
$gia_km  = (int)($sp['gia_khuyen_mai'] ?? 0);
$co_km   = ($gia_km > 0 && $gia_km < $gia_goc);

$gia_hieu_luc = (int)($co_km ? $gia_km : $gia_goc);

/* ========= FLASH MESSAGE (toast) ========= */
function _flash_set($type, $msg) {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function _flash_get() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ========= ADD TO CART / BUY NOW ========= */
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
  $size = trim($_POST['size'] ?? '');
  $qty  = max(1, (int)($_POST['qty'] ?? 1));

  // Validate size (đang dùng size 36-45)
  if ($size === '') {
    _flash_set('error', 'Vui lòng chọn size.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#mua");
    exit;
  }
  if (!preg_match('/^\d{2}$/', $size)) {
    _flash_set('error', 'Size không hợp lệ.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#mua");
    exit;
  }

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
      'don_gia' => $gia_hieu_luc,
      'gia'     => $gia_hieu_luc, // tương thích code cũ
    ];
  }

  if (isset($_POST['buy_now'])) {
    _flash_set('success', 'Đã thêm vào giỏ. Đang chuyển đến giỏ hàng...');
    header("Location: gio_hang.php");
    exit;
  }

  _flash_set('success', 'Đã thêm vào giỏ hàng.');
  header("Location: chi_tiet_san_pham.php?id=" . $id . "#mua");
  exit;
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
  $so_sao = (int)($_POST['so_sao'] ?? 0);
  $noi_dung = trim($_POST['noi_dung'] ?? '');

  if ($so_sao >= 1 && $so_sao <= 5 && $noi_dung !== '') {
    $stmt = $pdo->prepare("
      INSERT INTO danh_gia
      (id_san_pham, id_nguoi_dung, so_sao, noi_dung, trang_thai)
      VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->execute([$id, $id_nguoi_dung, $so_sao, $noi_dung]);
    _flash_set('success', 'Đánh giá đã gửi và chờ duyệt.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#danhgia");
    exit;
  } else {
    _flash_set('error', 'Vui lòng nhập đầy đủ nội dung và số sao hợp lệ.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#danhgia");
    exit;
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

$flash = _flash_get();
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">

  <!-- TOAST -->
  <?php if ($flash): ?>
    <div id="toast"
         class="fixed top-6 right-6 z-[9999] max-w-sm w-[92vw] sm:w-auto
                rounded-xl border px-4 py-3 shadow-lg bg-white">
      <div class="flex items-start gap-3">
        <div class="mt-1 text-sm font-black
          <?= $flash['type']==='success' ? 'text-green-600' : 'text-red-600' ?>">
          <?= $flash['type']==='success' ? 'OK' : 'LỖI' ?>
        </div>
        <div class="text-sm text-gray-800 font-semibold">
          <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <button type="button" class="ml-auto text-gray-500 hover:text-black font-black" onclick="document.getElementById('toast')?.remove()">×</button>
      </div>
    </div>
    <script>
      setTimeout(() => { const t = document.getElementById('toast'); if (t) t.remove(); }, 2500);
    </script>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">

    <!-- LEFT: IMAGE + DESC -->
    <div class="lg:col-span-7 space-y-6">
      <!-- IMAGE (Zoom) -->
      <div class="rounded-2xl border bg-white p-4">
        <div id="zoomWrap"
             class="relative overflow-hidden rounded-xl bg-gray-50
                    aspect-[4/3] w-full">
          <img id="zoomImg"
               src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>"
               alt="<?= htmlspecialchars($sp['ten_san_pham']) ?>"
               class="w-full h-full object-cover select-none
                      transition-transform duration-150 will-change-transform"
               draggable="false">
          <div class="pointer-events-none absolute inset-0 ring-1 ring-black/5 rounded-xl"></div>
        </div>

        <p class="mt-3 text-xs text-gray-500">
          Gợi ý: rê chuột trên ảnh để phóng to.
        </p>
      </div>

      <!-- DESC -->
      <div class="rounded-2xl border bg-white p-6">
        <h2 class="text-lg sm:text-xl font-black mb-3">Mô tả sản phẩm</h2>
        <div class="text-sm leading-6 text-gray-700">
          <?= nl2br(htmlspecialchars($sp['mo_ta'])) ?>
        </div>
      </div>
    </div>

    <!-- RIGHT: INFO -->
    <div class="lg:col-span-5">
      <div class="lg:sticky lg:top-24 space-y-5">

        <div class="rounded-2xl border bg-white p-6">
          <div class="flex items-start gap-3">
            <h1 class="text-2xl sm:text-3xl font-black uppercase leading-tight flex-1">
              <?= htmlspecialchars($sp['ten_san_pham']) ?>
            </h1>

            <a href="yeu_thich_them.php?id=<?= (int)$sp['id_san_pham'] ?>"
               class="shrink-0 inline-flex items-center justify-center
                      w-11 h-11 rounded-full border font-black hover:bg-black hover:text-white transition">
              ♥
            </a>
          </div>

          <?php if ($total_review): ?>
            <div class="mt-2 text-sm font-bold text-yellow-600">
              <?= $avg_star ?> ★ <span class="text-gray-500">(<?= $total_review ?> đánh giá)</span>
            </div>
          <?php else: ?>
            <div class="mt-2 text-sm text-gray-500 italic">Chưa có đánh giá</div>
          <?php endif; ?>

          <div class="mt-4 flex items-end gap-3">
            <div class="text-3xl font-black text-primary">
              <?= number_format($gia_hieu_luc) ?>₫
            </div>
            <?php if ($co_km): ?>
              <div class="text-sm text-gray-500 line-through font-bold mb-1">
                <?= number_format($gia_goc) ?>₫
              </div>
            <?php endif; ?>
          </div>

          <div id="mua"></div>

          <form method="post" class="mt-6 space-y-6">

            <!-- SIZE -->
            <div>
              <p class="font-black mb-2">Chọn size</p>
              <div class="grid grid-cols-5 gap-2">
                <?php for ($i=36;$i<=45;$i++): ?>
                  <label class="block">
                    <input type="radio" name="size" value="<?= $i ?>" class="hidden peer" required>
                    <div class="border rounded-lg py-2 text-center font-black cursor-pointer
                                hover:border-black
                                peer-checked:bg-black peer-checked:text-white peer-checked:border-black">
                      <?= $i ?>
                    </div>
                  </label>
                <?php endfor; ?>
              </div>
            </div>

            <!-- QTY -->
            <div class="flex items-center justify-between gap-4">
              <span class="font-black">Số lượng</span>
              <input type="number" name="qty" value="1" min="1"
                     class="w-24 border rounded-lg text-center font-black py-2">
            </div>

            <!-- BUTTONS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button type="submit" name="add_to_cart"
                      class="w-full bg-black text-white py-4 rounded-full font-black
                             hover:opacity-90 active:scale-[0.99] transition">
                Thêm vào giỏ
              </button>
              <button type="submit" name="buy_now"
                      class="w-full border-2 border-black py-4 rounded-full font-black
                             hover:bg-black hover:text-white active:scale-[0.99] transition">
                Mua ngay
              </button>
            </div>

            <p class="text-xs text-gray-500">
              Lưu ý: Nếu bạn bấm “Thêm vào giỏ”, hệ thống sẽ báo bằng thông báo góc phải và không bị màn hình trắng.
            </p>
          </form>
        </div>

      </div>
    </div>
  </div>

  <!-- ĐÁNH GIÁ -->
  <section id="danhgia" class="mt-14 border-t pt-10">
    <h2 class="text-2xl font-black mb-6">Đánh giá sản phẩm</h2>

    <?php if ($da_mua): ?>
      <form method="post" class="rounded-2xl border p-6 mb-6 bg-gray-50">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <select name="so_sao" required class="border rounded-lg px-3 py-2 font-semibold">
            <option value="">Chọn số sao</option>
            <?php for ($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>"><?= $i ?> sao</option>
            <?php endfor; ?>
          </select>
        </div>

        <textarea name="noi_dung" rows="4" required
                  class="w-full border rounded-lg px-3 py-2 mb-3"
                  placeholder="Nhận xét của bạn"></textarea>

        <button type="submit" name="gui_danh_gia"
                class="bg-black text-white px-6 py-3 rounded-lg font-black hover:opacity-90 transition">
          Gửi đánh giá
        </button>
      </form>
    <?php elseif (isset($_SESSION['nguoi_dung'])): ?>
      <p class="italic text-gray-500">Bạn cần mua sản phẩm để đánh giá.</p>
    <?php else: ?>
      <p class="italic text-gray-500">Vui lòng đăng nhập để xem/đánh giá.</p>
    <?php endif; ?>

    <?php foreach ($danh_gia as $dg): ?>
      <div class="rounded-2xl border bg-white p-5 mb-4">
        <div class="flex items-start justify-between gap-3">
          <p class="font-black"><?= htmlspecialchars($dg['ho_ten']) ?></p>
          <p class="text-yellow-600 font-black text-sm">
            <?= str_repeat('★', (int)$dg['so_sao']) ?>
          </p>
        </div>
        <p class="text-sm mt-2 text-gray-700 leading-6">
          <?= nl2br(htmlspecialchars($dg['noi_dung'])) ?>
        </p>
      </div>
    <?php endforeach; ?>
  </section>

</main>

<script>
  // Hover zoom theo vị trí con trỏ
  (function () {
    const wrap = document.getElementById('zoomWrap');
    const img  = document.getElementById('zoomImg');
    if (!wrap || !img) return;

    const ZOOM = 1.8;

    function setOrigin(e) {
      const r = wrap.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width) * 100;
      const y = ((e.clientY - r.top)  / r.height) * 100;
      img.style.transformOrigin = `${x}% ${y}%`;
    }

    wrap.addEventListener('mouseenter', () => {
      img.style.transform = `scale(${ZOOM})`;
    });

    wrap.addEventListener('mousemove', (e) => {
      setOrigin(e);
    });

    wrap.addEventListener('mouseleave', () => {
      img.style.transformOrigin = `50% 50%`;
      img.style.transform = 'scale(1)';
    });

    // Mobile: không zoom để tránh giật
    wrap.addEventListener('touchstart', () => {
      img.style.transform = 'scale(1)';
      img.style.transformOrigin = '50% 50%';
    }, {passive:true});
  })();
</script>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
