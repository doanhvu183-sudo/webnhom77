<?php
// gio_hang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== GIỎ HÀNG ================== */
$cart = $_SESSION['cart'] ?? [];
$tong_tien = 0;
$tong_so_luong = 0;

foreach ($cart as $sp) {
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));

    $tong_so_luong += $qty;
    $tong_tien += $don_gia * $qty;
}

/* ================== HELPER ================== */
function _redirect_cart_() {
    header("Location: gio_hang.php");
    exit;
}

function _get_uid_() {
    return $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? null);
}

/**
 * Validate voucher by code for current cart total and user
 * Returns: [ok(bool), vc(array|null), err(string|null)]
 */
function _validate_voucher_($pdo, $ma, $tong_tien, $uid) {
    $ma = trim($ma);
    if ($ma === '') return [false, null, 'Vui lòng nhập/chọn mã voucher'];

    // voucher must be active + within date range (start <= today, end >= today)
    $stmt = $pdo->prepare("
        SELECT *
        FROM voucher
        WHERE ma_voucher = ?
          AND trang_thai = 1
          AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= CURDATE())
          AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$ma]);
    $vc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vc) return [false, null, 'Voucher không tồn tại hoặc đã hết hạn'];

    // usage limit check: so_luot NULL or 0 => unlimited; else so_luot_da_dung < so_luot
    $so_luot = $vc['so_luot'];
    $so_luot_da_dung = (int)($vc['so_luot_da_dung'] ?? 0);
    if ($so_luot !== null) {
        $so_luot_int = (int)$so_luot;
        if ($so_luot_int > 0 && $so_luot_da_dung >= $so_luot_int) {
            return [false, null, 'Voucher đã hết lượt sử dụng'];
        }
    }

    // minimum order condition
    $dk = (int)($vc['dieu_kien_toi_thieu'] ?? 0);
    if ($dk > 0 && $tong_tien < $dk) {
        return [false, null, 'Đơn hàng chưa đạt điều kiện tối thiểu ' . number_format($dk) . '₫'];
    }

    // check user already used this voucher
    if ($uid) {
        $check = $pdo->prepare("
            SELECT 1
            FROM voucher_nguoidung
            WHERE id_nguoi_dung = ? AND ma_voucher = ?
            LIMIT 1
        ");
        $check->execute([$uid, $ma]);
        if ($check->fetch()) {
            return [false, null, 'Bạn đã sử dụng voucher này'];
        }
    }

    return [true, $vc, null];
}

/* ================== ÁP DỤNG VOUCHER (1 NÚT - nhập hoặc chọn) ================== */
if (isset($_POST['ap_dung_voucher'])) {
    $ma = trim($_POST['voucher'] ?? '');
    $uid = _get_uid_();

    // Nếu rỗng => không dùng voucher
    if ($ma === '') {
        unset($_SESSION['voucher']);
        unset($_SESSION['voucher_error']);
        _redirect_cart_();
    }

    [$ok, $vc, $err] = _validate_voucher_($pdo, $ma, $tong_tien, $uid);

    if (!$ok) {
        $_SESSION['voucher_error'] = $err;
        _redirect_cart_();
    }

    $_SESSION['voucher'] = $vc;
    unset($_SESSION['voucher_error']);
    _redirect_cart_();
}

/* ================== HỦY VOUCHER ================== */
if (isset($_POST['huy_voucher'])) {
    unset($_SESSION['voucher']);
    unset($_SESSION['voucher_error']);
    _redirect_cart_();
}

/* ================== LẤY DANH SÁCH VOUCHER (CHO DROPDOWN) ================== */
$voucher_co_the_dung = [];
$uid = _get_uid_();

if ($uid) {
    // Lấy voucher còn hiệu lực + còn lượt + user chưa dùng
    $stmt = $pdo->prepare("
        SELECT v.*
        FROM voucher v
        LEFT JOIN voucher_nguoidung vu
          ON vu.ma_voucher = v.ma_voucher AND vu.id_nguoi_dung = ?
        WHERE v.trang_thai = 1
          AND (v.ngay_bat_dau IS NULL OR v.ngay_bat_dau <= CURDATE())
          AND (v.ngay_ket_thuc IS NULL OR v.ngay_ket_thuc >= CURDATE())
          AND (
                v.so_luot IS NULL
                OR v.so_luot = 0
                OR v.so_luot_da_dung < v.so_luot
              )
          AND vu.id IS NULL
        ORDER BY (v.ngay_ket_thuc IS NULL) ASC, v.ngay_ket_thuc ASC, v.ma_voucher ASC
        LIMIT 100
    ");
    $stmt->execute([$uid]);
    $voucher_co_the_dung = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ================== RE-VALIDATE VOUCHER IN SESSION ================== */
if (!empty($_SESSION['voucher'])) {
    $current_code = $_SESSION['voucher']['ma_voucher'] ?? '';
    if ($current_code) {
        [$ok, $vc, $err] = _validate_voucher_($pdo, $current_code, $tong_tien, $uid);
        if (!$ok) {
            unset($_SESSION['voucher']);
            // chỉ set error nếu giỏ không rỗng (tránh spam khi trống)
            if (!empty($cart)) $_SESSION['voucher_error'] = $err;
        } else {
            // refresh data from DB (phòng admin sửa voucher)
            $_SESSION['voucher'] = $vc;
        }
    }
}

/* ================== TÍNH GIẢM GIÁ ================== */
$tien_giam = 0;

if (!empty($_SESSION['voucher'])) {
    $vc = $_SESSION['voucher'];
    $loai = $vc['loai'] ?? '';
    $gia_tri = (int)($vc['gia_tri'] ?? 0);
    $giam_toi_da = (int)($vc['giam_toi_da'] ?? 0);

    if ($loai === 'TIEN') {
        $tien_giam = max(0, $gia_tri);
        if ($giam_toi_da > 0) $tien_giam = min($tien_giam, $giam_toi_da);
    } elseif ($loai === 'PHAN_TRAM') {
        $tien_giam = (int)floor($tong_tien * $gia_tri / 100);
        if ($giam_toi_da > 0) $tien_giam = min($tien_giam, $giam_toi_da);
    }

    $tien_giam = min($tien_giam, $tong_tien);
}

$tong_thanh_toan = max(0, $tong_tien - $tien_giam);
?>

<?php require_once __DIR__ . '/../giao_dien/header.php'; ?>

<main class="max-w-[1200px] mx-auto px-6 py-10">

<h1 class="text-3xl font-black uppercase mb-8">Giỏ hàng</h1>

<?php if (empty($cart)): ?>
<div class="border rounded-xl p-12 text-center text-gray-500">
    <p class="text-lg mb-4">Giỏ hàng của bạn đang trống</p>
    <a href="trang_chu.php"
       class="bg-black text-white px-6 py-3 rounded-full font-bold">
        Tiếp tục mua sắm
    </a>
</div>

<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

<!-- ================= DANH SÁCH SẢN PHẨM ================= -->
<div class="lg:col-span-8">
<div class="overflow-x-auto border rounded-xl">
<table class="w-full text-sm">
<thead class="bg-gray-100 uppercase text-xs font-bold">
<tr>
    <th class="p-4 text-left">Sản phẩm</th>
    <th class="p-4 text-center">Size</th>
    <th class="p-4 text-right">Giá</th>
    <th class="p-4 text-center">SL</th>
    <th class="p-4 text-right">Thành tiền</th>
    <th></th>
</tr>
</thead>
<tbody>

<?php foreach ($cart as $key => $sp):
    $qty = max(1, (int)($sp['qty'] ?? 1));
    $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
    $thanh_tien = $don_gia * $qty;
?>
<tr class="border-t">
<td class="p-4 flex gap-4 items-center">
    <img src="../assets/img/<?= htmlspecialchars($sp['anh'] ?? 'no-image.png') ?>"
         class="w-16 h-16 object-contain border rounded">
    <div>
        <p class="font-bold uppercase"><?= htmlspecialchars($sp['ten'] ?? '') ?></p>
    </div>
</td>

<td class="p-4 text-center"><?= htmlspecialchars($sp['size'] ?? '-') ?></td>

<td class="p-4 text-right font-bold"><?= number_format($don_gia) ?>₫</td>

<td class="p-4 text-center">
    <div class="inline-flex items-center border rounded">
        <a href="gio_hang_capnhat.php?id=<?= urlencode($key) ?>&type=minus" class="px-3">−</a>
        <span class="px-3 font-bold"><?= $qty ?></span>
        <a href="gio_hang_capnhat.php?id=<?= urlencode($key) ?>&type=plus" class="px-3">+</a>
    </div>
</td>

<td class="p-4 text-right font-extrabold text-primary">
    <?= number_format($thanh_tien) ?>₫
</td>

<td class="p-4 text-center">
    <a href="gio_hang_xoa.php?id=<?= urlencode($key) ?>"
       class="text-red-600 text-xs underline">Xóa</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
</div>

<!-- ================= TÓM TẮT ĐƠN HÀNG ================= -->
<div class="lg:col-span-4">
<div class="border rounded-xl p-6 sticky top-24 bg-white">

<h2 class="text-xl font-black mb-4">Tóm tắt đơn hàng</h2>

<div class="flex justify-between mb-2">
    <span>Tổng sản phẩm</span>
    <span class="font-bold"><?= $tong_so_luong ?></span>
</div>

<div class="flex justify-between mb-2">
    <span>Tạm tính</span>
    <span class="font-bold"><?= number_format($tong_tien) ?>₫</span>
</div>

<?php if ($tien_giam > 0): ?>
<div class="flex justify-between mb-2 text-green-600">
    <span>Giảm giá</span>
    <span>-<?= number_format($tien_giam) ?>₫</span>
</div>
<?php endif; ?>

<!-- ================= VOUCHER (1 Ô DUY NHẤT) ================= -->
<div class="border-t pt-4 mt-4 space-y-3">

  <label class="block text-sm font-bold">Mã giảm giá</label>

  <?php if (!$uid): ?>
    <div class="text-sm text-gray-500 italic">
      Đăng nhập để xem voucher có thể sử dụng.
    </div>
  <?php endif; ?>

  <form method="post" class="space-y-2">
    <div class="flex gap-2">
      <!-- Input mã -->
      <input
        id="voucherInput"
        name="voucher"
        value="<?= htmlspecialchars($_SESSION['voucher']['ma_voucher'] ?? '') ?>"
        placeholder="Nhập mã voucher hoặc chọn bên phải"
        class="flex-1 border rounded px-3 py-2 text-sm"
        autocomplete="off"
      >

      <!-- Dropdown chọn voucher -->
      <div class="relative">
        <select
  id="voucherSelect"
  class="border rounded px-3 py-2 text-sm pr-8 appearance-none w-full"
  style="max-width: 100%;"
  <?= !$uid ? 'disabled' : '' ?>
        >
          <option value="NONE">Không dùng voucher</option>

          <?php if ($uid && !empty($voucher_co_the_dung)): ?>
            <?php foreach ($voucher_co_the_dung as $v): ?>
              <?php
                $ma = $v['ma_voucher'] ?? '';
                $loai = $v['loai'] ?? '';
                $gt = (int)($v['gia_tri'] ?? 0);
                $dk = (int)($v['dieu_kien_toi_thieu'] ?? 0);
                $max = (int)($v['giam_toi_da'] ?? 0);
                $het_han = $v['ngay_ket_thuc'] ?? null;

                $mo_ta = '';
                if ($loai === 'TIEN') {
                  $mo_ta = 'Giảm ' . number_format($gt) . '₫';
                  if ($max > 0) $mo_ta .= ' (tối đa ' . number_format($max) . '₫)';
                } elseif ($loai === 'PHAN_TRAM') {
                  $mo_ta = 'Giảm ' . $gt . '%';
                  if ($max > 0) $mo_ta .= ' (tối đa ' . number_format($max) . '₫)';
                } else {
                  $mo_ta = 'Voucher';
                }

                if ($dk > 0) $mo_ta .= ' • ĐH ≥ ' . number_format($dk) . '₫';
                if ($het_han) $mo_ta .= ' • HSD ' . htmlspecialchars($het_han);

                $disabled = ($dk > 0 && $tong_tien < $dk);
                $selected = (!empty($_SESSION['voucher']) && (($_SESSION['voucher']['ma_voucher'] ?? '') === $ma));
              ?>
              <option
                value="<?= htmlspecialchars($ma) ?>"
                <?= $selected ? 'selected' : '' ?>
                <?= $disabled ? 'disabled' : '' ?>
              >
                <?= htmlspecialchars($ma) ?> — <?= htmlspecialchars($mo_ta) ?><?= $disabled ? ' (Chưa đủ điều kiện)' : '' ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>

        <!-- Icon mũi tên -->
        <span
          class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
          style="font-size:12px;"
        >▼</span>
      </div>
    </div>

    <div class="flex gap-2">
      <!-- ÁP DỤNG -->
      <button
        type="submit"
        name="ap_dung_voucher"
        class="bg-black text-white px-4 py-2 rounded font-bold text-sm"
      >
        Áp dụng
      </button>

      <!-- HỦY -->
      <button
        type="submit"
        name="huy_voucher"
        class="border border-black px-4 py-2 rounded font-bold text-sm hover:bg-black hover:text-white transition"
      >
        Hủy
      </button>
    </div>

    <!-- Thông báo -->
    <?php if (!empty($_SESSION['voucher_error'])): ?>
      <p class="text-red-600 text-sm">
        <?= htmlspecialchars($_SESSION['voucher_error']); unset($_SESSION['voucher_error']); ?>
      </p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['voucher'])): ?>
      <p class="text-green-600 text-sm font-bold">
        ✔ Đã áp dụng <?= htmlspecialchars($_SESSION['voucher']['ma_voucher'] ?? '') ?>
      </p>
    <?php endif; ?>
  </form>

  <script>
  (function(){
    var sel = document.getElementById('voucherSelect');
    var inp = document.getElementById('voucherInput');
    if(!sel || !inp) return;

    sel.addEventListener('change', function(){
      var v = sel.value || '';
      if(v === 'NONE'){
        inp.value = '';
      } else {
        inp.value = v;
      }
    });
  })();
  </script>

</div>

<div class="border-t pt-4 mt-4 flex justify-between text-lg font-black">
    <span>Tổng cộng</span>
    <span class="text-primary"><?= number_format($tong_thanh_toan) ?>₫</span>
</div>

<a href="thanh_toan.php"
   class="mt-6 block text-center bg-primary text-white py-4 rounded-full font-black uppercase">
    Tiến hành thanh toán
</a>

</div>
</div>

</div>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
