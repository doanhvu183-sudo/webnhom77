<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$u = $_SESSION['nguoi_dung'] ?? [];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
if ($uid <= 0) { auth_logout(); redirect(base_url('trang_nguoi_dung/dang_nhap.php')); exit; }

$id_don = (int)($_GET['don'] ?? 0);
if ($id_don <= 0) {
  flash_set('err', 'Đơn hàng không hợp lệ.');
  redirect(base_url('trang_nguoi_dung/trang_chu.php'));
  exit;
}

$st = $pdo->prepare("
  SELECT id_don_hang, ma_don_hang, trang_thai, ngay_dat,
         ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan,
         tong_tien, tien_giam, tong_thanh_toan, phuong_thuc
  FROM donhang
  WHERE id_don_hang=? AND id_nguoi_dung=?
  LIMIT 1
");
$st->execute([$id_don, $uid]);
$don = $st->fetch(PDO::FETCH_ASSOC);

if (!$don) {
  flash_set('err', 'Đơn hàng không hợp lệ hoặc không thuộc tài khoản của bạn.');
  redirect(base_url('trang_nguoi_dung/trang_chu.php'));
  exit;
}

// CHẶN: chưa OTP thì kẹt ở trang OTP
if (($don['trang_thai'] ?? '') === 'CHO_XAC_NHAN_EMAIL') {
  flash_set('err', 'Bạn cần xác nhận OTP để hoàn tất đặt hàng.');
  redirect(base_url('trang_nguoi_dung/xac_nhan_dat_hang.php?don=' . $id_don));
  exit;
}

/**
 * Chi tiết đơn + ảnh sản phẩm
 * - Ưu tiên lấy ảnh từ bảng sanpham.hinh_anh theo id_san_pham
 * - Fallback: nếu thiếu ảnh -> no-image.png
 */
$stCt = $pdo->prepare("
  SELECT
    ct.id_san_pham,
    ct.ten_san_pham,
    ct.size,
    ct.so_luong,
    ct.don_gia,
    ct.thanh_tien,
    sp.hinh_anh
  FROM chitiet_donhang ct
  LEFT JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
  WHERE ct.id_don_hang=?
  ORDER BY ct.id_ct ASC
");
$stCt->execute([$id_don]);
$items = $stCt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-[900px] mx-auto px-4 py-10">
  <div class="rounded-2xl border bg-white p-6">
    <h1 class="text-2xl font-extrabold mb-2">Đặt hàng thành công</h1>
    <p class="text-slate-600 mb-6">
      Đơn hàng <b>#<?=h($don['ma_don_hang'])?></b> đã được xác nhận OTP và ghi nhận trên hệ thống.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <div class="rounded-xl border p-4">
        <div class="text-sm text-slate-500 mb-1">Thông tin nhận hàng</div>
        <div class="font-bold"><?=h($don['ho_ten_nhan'] ?? '')?></div>
        <div><?=h($don['so_dien_thoai_nhan'] ?? '')?></div>
        <div class="text-slate-600"><?=h($don['dia_chi_nhan'] ?? '')?></div>
      </div>

      <div class="rounded-xl border p-4">
        <div class="text-sm text-slate-500 mb-1">Tóm tắt thanh toán</div>
        <div class="flex justify-between">
          <span>Tạm tính</span><b><?=number_format((int)$don['tong_tien'])?>₫</b>
        </div>
        <div class="flex justify-between text-green-700">
          <span>Giảm</span><b>-<?=number_format((int)$don['tien_giam'])?>₫</b>
        </div>
        <div class="flex justify-between text-lg mt-2">
          <span>Tổng</span><b><?=number_format((int)$don['tong_thanh_toan'])?>₫</b>
        </div>
        <div class="text-sm text-slate-600 mt-2">
          Phương thức: <b><?=h($don['phuong_thuc'] ?? '')?></b>
        </div>
      </div>
    </div>

    <div class="rounded-xl border overflow-hidden">
      <div class="bg-slate-50 px-4 py-3 font-bold">Sản phẩm</div>

      <?php if (!$items): ?>
        <div class="p-4 text-slate-500">Không có chi tiết đơn.</div>
      <?php else: ?>
        <div class="divide-y">
          <?php foreach ($items as $it): ?>
            <?php
              $img = trim((string)($it['hinh_anh'] ?? ''));
              if ($img === '') $img = 'no-image.png';
            ?>
            <div class="px-4 py-3 flex items-center justify-between gap-4">
              <div class="flex items-center gap-3 min-w-0">
                <img
                  src="../assets/img/<?=h($img)?>"
                  alt="<?=h($it['ten_san_pham'] ?? '')?>"
                  class="w-14 h-14 rounded-lg border object-contain bg-white"
                  onerror="this.src='../assets/img/no-image.png';"
                >
                <div class="min-w-0">
                  <div class="font-bold truncate"><?=h($it['ten_san_pham'])?></div>
                  <div class="text-sm text-slate-500">
                    <?php if (!empty($it['size'])): ?>Size <?=h($it['size'])?> · <?php endif; ?>
                    SL <?= (int)$it['so_luong'] ?>
                  </div>
                </div>
              </div>

              <div class="text-right shrink-0">
                <div class="font-bold"><?=number_format((int)$it['thanh_tien'])?>₫</div>
                <div class="text-sm text-slate-500"><?=number_format((int)$it['don_gia'])?>₫</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="mt-6 flex flex-col md:flex-row gap-3">
      <a class="rounded-xl border px-4 py-3 font-bold text-center"
         href="<?=h(base_url('trang_nguoi_dung/don_hang.php'))?>">
        Xem đơn hàng của tôi
      </a>
      <a class="rounded-xl bg-black text-white px-4 py-3 font-bold text-center"
         href="<?=h(base_url('trang_nguoi_dung/trang_chu.php'))?>">
        Tiếp tục mua sắm
      </a>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
