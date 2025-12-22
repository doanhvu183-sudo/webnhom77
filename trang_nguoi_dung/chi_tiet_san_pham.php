<?php
// chi_tiet_san_pham.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================= FLASH (toast) ================= */
function _flash_set($type, $msg) {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function _flash_get() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ================= ID ================= */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Sản phẩm không tồn tại');

/* ================= SẢN PHẨM ================= */
$stmt = $pdo->prepare("SELECT * FROM sanpham WHERE id_san_pham=? LIMIT 1");
$stmt->execute([$id]);
$sp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sp) die('Không tìm thấy sản phẩm');

/* ================= GIÁ HIỆU LỰC ================= */
$gia_goc = (int)($sp['gia'] ?? 0);
$gia_km  = (int)($sp['gia_khuyen_mai'] ?? 0);
$co_km   = ($gia_km > 0 && $gia_km < $gia_goc);
$gia_hieu_luc = (int)($co_km ? $gia_km : $gia_goc);

/* ================= ADD TO CART / BUY NOW ================= */
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
  $size = trim($_POST['size'] ?? '');
  $qty  = max(1, (int)($_POST['qty'] ?? 1));

  if ($size === '') {
    _flash_set('error', 'Vui lòng chọn size trước khi mua hoặc thêm vào giỏ.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#mua");
    exit;
  }

  // size đang 36-45 (2 chữ số)
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

/* ================= KIỂM TRA ĐÃ MUA ================= */
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

/* ================= GỬI ĐÁNH GIÁ ================= */
if (isset($_POST['gui_danh_gia']) && $da_mua) {
  $so_sao  = (int)($_POST['so_sao'] ?? 0);
  $noi_dung = trim($_POST['noi_dung'] ?? '');

  if ($so_sao >= 1 && $so_sao <= 5 && $noi_dung !== '') {
    $stmt = $pdo->prepare("
      INSERT INTO danh_gia (id_san_pham, id_nguoi_dung, so_sao, noi_dung, trang_thai)
      VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->execute([$id, $id_nguoi_dung, $so_sao, $noi_dung]);
    _flash_set('success', 'Đánh giá đã gửi và chờ duyệt.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#danhgia");
    exit;
  } else {
    _flash_set('error', 'Vui lòng nhập đủ nội dung và chọn số sao hợp lệ.');
    header("Location: chi_tiet_san_pham.php?id=" . $id . "#danhgia");
    exit;
  }
}

/* ================= ĐÁNH GIÁ ĐÃ DUYỆT =================
   Bảng danh_gia của bạn KHÔNG có ngay_tao, nên order theo id_danh_gia DESC
*/
$stmt = $pdo->prepare("
  SELECT dg.so_sao, dg.noi_dung, nd.ho_ten
  FROM danh_gia dg
  JOIN nguoidung nd ON dg.id_nguoi_dung = nd.id_nguoi_dung
  WHERE dg.id_san_pham=? AND dg.trang_thai=1
");
$stmt->execute([$id]);
$danh_gia = $stmt->fetchAll(PDO::FETCH_ASSOC);



$total_review = count($danh_gia);
$avg_star = $total_review
  ? round(array_sum(array_column($danh_gia, 'so_sao')) / $total_review, 1)
  : 0;

/* ================= SẢN PHẨM LIÊN QUAN ================= */
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

  <!-- TOAST SERVER (flash) -->
  <?php if ($flash): ?>
    <div id="toast_server"
         class="fixed top-6 right-6 z-[9999] max-w-sm w-[92vw] sm:w-auto rounded-xl border px-4 py-3 shadow-lg bg-white">
      <div class="flex items-start gap-3">
        <div class="mt-1 text-sm font-black <?= $flash['type']==='success' ? 'text-green-600' : 'text-red-600' ?>">
          <?= $flash['type']==='success' ? 'OK' : 'LỖI' ?>
        </div>
        <div class="text-sm text-gray-800 font-semibold">
          <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <button type="button"
                class="ml-auto text-gray-500 hover:text-black font-black"
                onclick="document.getElementById('toast_server')?.remove()">×</button>
      </div>
    </div>
    <script>
      setTimeout(() => { const t = document.getElementById('toast_server'); if (t) t.remove(); }, 2500);
    </script>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">

    <!-- LEFT: IMAGE + DESC -->
    <div class="lg:col-span-7 space-y-6">
      <!-- IMAGE (Zoom) -->
      <div class="rounded-2xl border bg-white p-4">
        <div id="zoomWrap" class="relative overflow-hidden rounded-xl bg-gray-50 aspect-[4/3] w-full">
          <img id="zoomImg"
               src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>"
               alt="<?= htmlspecialchars($sp['ten_san_pham']) ?>"
               class="w-full h-full object-cover select-none transition-transform duration-150 will-change-transform"
               draggable="false">
          <div class="pointer-events-none absolute inset-0 ring-1 ring-black/5 rounded-xl"></div>
        </div>
        <p class="mt-3 text-xs text-gray-500">Rê chuột trên ảnh để phóng to.</p>
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

            <!-- YÊU THÍCH (không chuyển trang) -->
            <button type="button"
                    id="btnFav"
                    data-id="<?= (int)$sp['id_san_pham'] ?>"
                    class="shrink-0 inline-flex items-center justify-center
                           w-11 h-11 rounded-full border font-black
                           hover:bg-black hover:text-white transition">
              ♥
            </button>
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
            <div id="sizeBox">
              <p class="font-black mb-2">
                Chọn size
                <span id="sizeHint" class="hidden text-red-600 text-xs font-black ml-2">Vui lòng chọn size</span>
              </p>
              <div class="grid grid-cols-5 gap-2">
                <?php for ($i=36;$i<=45;$i++): ?>
                  <label class="block">
                    <input type="radio" name="size" value="<?= $i ?>" required class="hidden peer">
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

            <!-- BUTTONS (disable until size selected) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button type="submit" name="add_to_cart" data-requires-size="1" disabled
                      class="w-full bg-black text-white py-4 rounded-full font-black
                             hover:opacity-90 active:scale-[0.99] transition opacity-50 cursor-not-allowed">
                Thêm vào giỏ
              </button>

              <button type="submit" name="buy_now" data-requires-size="1" disabled
                      class="w-full border-2 border-black py-4 rounded-full font-black
                             hover:bg-black hover:text-white active:scale-[0.99] transition opacity-50 cursor-not-allowed">
                Mua ngay
              </button>
            </div>

            <!-- Nút yêu thích dạng text -->
            <button type="button"
                    id="btnFavText"
                    data-id="<?= (int)$sp['id_san_pham'] ?>"
                    class="w-full border-2 border-black py-3 rounded-full font-black
                           hover:bg-black hover:text-white transition">
              ❤️ Thêm vào yêu thích
            </button>
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
        <select name="so_sao" required class="border rounded-lg px-3 py-2 mb-3 font-semibold">
          <option value="">Chọn số sao</option>
          <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>"><?= $i ?> sao</option>
          <?php endfor; ?>
        </select>

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

  <!-- SẢN PHẨM LIÊN QUAN -->
  <?php if (!empty($sp_lien_quan)): ?>
    <section class="mt-14 border-t pt-10">
      <div class="flex items-end justify-between mb-6">
        <h2 class="text-2xl font-black">Sản phẩm liên quan</h2>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php foreach ($sp_lien_quan as $p): ?>
          <?php
            $p_goc = (int)($p['gia'] ?? 0);
            $p_km  = (int)($p['gia_khuyen_mai'] ?? 0);
            $p_co_km = ($p_km > 0 && $p_km < $p_goc);
            $p_gia_hl = (int)($p_co_km ? $p_km : $p_goc);
          ?>
          <a href="chi_tiet_san_pham.php?id=<?= (int)$p['id_san_pham'] ?>"
             class="group rounded-2xl border bg-white overflow-hidden hover:shadow-md transition">

            <div class="relative bg-gray-50 aspect-[4/5] overflow-hidden">
              <img src="../assets/img/<?= htmlspecialchars($p['hinh_anh']) ?>"
                   alt="<?= htmlspecialchars($p['ten_san_pham']) ?>"
                   class="w-full h-full object-cover group-hover:scale-[1.03] transition">
              <?php if ($p_co_km): ?>
                <span class="absolute top-2 left-2 text-xs font-black bg-black text-white px-2 py-1 rounded-full">
                  SALE
                </span>
              <?php endif; ?>
            </div>

            <div class="p-3">
              <div class="text-sm font-black leading-5 line-clamp-2 min-h-[40px]">
                <?= htmlspecialchars($p['ten_san_pham']) ?>
              </div>

              <div class="mt-2 flex items-end gap-2">
                <div class="text-sm font-black text-primary">
                  <?= number_format($p_gia_hl) ?>₫
                </div>
                <?php if ($p_co_km): ?>
                  <div class="text-xs text-gray-500 line-through font-bold">
                    <?= number_format($p_goc) ?>₫
                  </div>
                <?php endif; ?>
              </div>
            </div>

          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</main>

<script>
  /* ================= Toast client-side (yêu thích + validate size) ================= */
  function showToast(type, msg) {
    const old = document.getElementById('toast_client');
    if (old) old.remove();

    const wrap = document.createElement('div');
    wrap.id = 'toast_client';
    wrap.className = 'fixed top-6 right-6 z-[9999] max-w-sm w-[92vw] sm:w-auto rounded-xl border px-4 py-3 shadow-lg bg-white';
    wrap.innerHTML = `
      <div class="flex items-start gap-3">
        <div class="mt-1 text-sm font-black ${type === 'success' ? 'text-green-600' : 'text-red-600'}">
          ${type === 'success' ? 'OK' : 'LỖI'}
        </div>
        <div class="text-sm text-gray-800 font-semibold">${msg}</div>
        <button type="button" class="ml-auto text-gray-500 hover:text-black font-black">×</button>
      </div>
    `;
    wrap.querySelector('button').addEventListener('click', () => wrap.remove());
    document.body.appendChild(wrap);
    setTimeout(() => wrap.remove(), 2500);
  }

  /* ================= Hover Zoom theo vị trí chuột ================= */
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
    wrap.addEventListener('mousemove', (e) => setOrigin(e));
    wrap.addEventListener('mouseleave', () => {
      img.style.transformOrigin = `50% 50%`;
      img.style.transform = 'scale(1)';
    });

    // mobile: không zoom để tránh giật
    wrap.addEventListener('touchstart', () => {
      img.style.transform = 'scale(1)';
      img.style.transformOrigin = '50% 50%';
    }, { passive: true });
  })();

  /* ================= Bắt buộc chọn size: enable nút + cảnh báo ================= */
  (function () {
    const form = document.querySelector('form[method="post"]');
    const sizeBox = document.getElementById('sizeBox');
    const sizeHint = document.getElementById('sizeHint');
    const buttons = document.querySelectorAll('[data-requires-size="1"]');
    const sizeInputs = document.querySelectorAll('input[name="size"]');
    if (!form || !sizeBox || !buttons.length) return;

    function hasSize() {
      return !!document.querySelector('input[name="size"]:checked');
    }

    function setButtonsEnabled(enabled) {
      buttons.forEach(btn => {
        btn.disabled = !enabled;
        btn.classList.toggle('opacity-50', !enabled);
        btn.classList.toggle('cursor-not-allowed', !enabled);
      });
    }

    function clearError() {
      sizeBox.classList.remove('ring-2', 'ring-red-500', 'rounded-xl', 'p-2');
      if (sizeHint) sizeHint.classList.add('hidden');
    }

    function showError() {
      sizeBox.classList.add('ring-2', 'ring-red-500', 'rounded-xl', 'p-2');
      if (sizeHint) sizeHint.classList.remove('hidden');
      showToast('error', 'Vui lòng chọn size trước khi mua hoặc thêm vào giỏ.');
    }

    setButtonsEnabled(hasSize());

    sizeInputs.forEach(i => {
      i.addEventListener('change', () => {
        clearError();
        setButtonsEnabled(true);
      });
    });

    form.addEventListener('submit', (e) => {
      if (!hasSize()) {
        e.preventDefault();
        showError();
        setButtonsEnabled(false);
      }
    });
  })();

  /* ================= Yêu thích: không chuyển trang (AJAX) ================= */
  async function addFav(id) {
    try {
      const res = await fetch(`yeu_thich_them.php?id=${encodeURIComponent(id)}&ajax=1`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (data && data.ok) showToast('success', data.msg || 'Đã thêm vào yêu thích.');
      else showToast('error', (data && data.msg) ? data.msg : 'Không thêm được yêu thích.');
    } catch (e) {
      showToast('error', 'Lỗi mạng hoặc lỗi server.');
    }
  }

  (function () {
    const btn1 = document.getElementById('btnFav');
    const btn2 = document.getElementById('btnFavText');

    if (btn1) {
      btn1.addEventListener('click', () => addFav(btn1.dataset.id));
    }
    if (btn2) {
      btn2.addEventListener('click', () => addFav(btn2.dataset.id));
    }
  })();
</script>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
