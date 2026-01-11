<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$u = $_SESSION['nguoi_dung'] ?? [];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
if ($uid <= 0) { auth_logout(); redirect(base_url('trang_nguoi_dung/dang_nhap.php')); exit; }

$id_don = (int)($_GET['don'] ?? ($_POST['don'] ?? 0));
if ($id_don <= 0) { flash_set('err','Thiếu mã đơn.'); redirect(base_url('trang_nguoi_dung/don_hang.php')); exit; }

$st = $pdo->prepare("
  SELECT id_don_hang, ma_don_hang, trang_thai, tong_thanh_toan, ngay_dat
  FROM donhang
  WHERE id_don_hang=? AND id_nguoi_dung=?
  LIMIT 1
");
$st->execute([$id_don, $uid]);
$don = $st->fetch(PDO::FETCH_ASSOC);

if (!$don) { flash_set('err','Đơn không hợp lệ.'); redirect(base_url('trang_nguoi_dung/don_hang.php')); exit; }

if (($don['trang_thai'] ?? '') !== 'CHO_XAC_NHAN_EMAIL') {
  redirect(base_url('trang_nguoi_dung/hoan_tat_don_hang.php?don='.(int)$id_don));
  exit;
}

$err = null;
$ok  = flash_get('ok');

if (is_post() && isset($_POST['do_verify'])) {
  if (!csrf_check($_POST['_csrf'] ?? '')) $err = 'Phiên làm việc không hợp lệ.';
  else {
    $otp = trim((string)($_POST['otp'] ?? ''));
    if (!preg_match('/^\d{6}$/', $otp)) $err = 'OTP phải gồm 6 chữ số.';
    else if (!otp_verify_for_user($pdo, $uid, $otp)) $err = 'OTP sai hoặc hết hạn.';
    else {
      $hasNgayCapNhat = false;
      try {
        $q = $pdo->query("SHOW COLUMNS FROM donhang LIKE 'ngay_cap_nhat'");
        $hasNgayCapNhat = (bool)$q->fetch(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {}

      if ($hasNgayCapNhat) {
        $up = $pdo->prepare("UPDATE donhang SET trang_thai='CHO_XU_LY', ngay_cap_nhat=NOW() WHERE id_don_hang=? AND id_nguoi_dung=? LIMIT 1");
      } else {
        $up = $pdo->prepare("UPDATE donhang SET trang_thai='CHO_XU_LY' WHERE id_don_hang=? AND id_nguoi_dung=? LIMIT 1");
      }
      $up->execute([$id_don, $uid]);

      otp_clear_for_user($pdo, $uid);
      unset($_SESSION['cart'], $_SESSION['voucher']);

      redirect(base_url('trang_nguoi_dung/hoan_tat_don_hang.php?don='.(int)$id_don));
      exit;
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-5xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Xác thực email để ghi nhận đơn</h1>
      <p class="text-slate-600 mb-6">
        Nhập OTP đã gửi về email để xác nhận đơn
        <b>#<?=h($don['ma_don_hang'] ?? $don['id_don_hang'])?></b>.
      </p>

      <?php if ($err): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($err)?></div>
      <?php endif; ?>
      <?php if ($ok): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-700"><?=h($ok)?></div>
      <?php endif; ?>
      <?php if ($f = flash_get('err')): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($f)?></div>
      <?php endif; ?>

      <div class="space-y-4">
        <!-- FORM XÁC THỰC -->
        <form method="post" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="don" value="<?= (int)$id_don ?>">
          <input type="hidden" name="do_verify" value="1">

          <input name="otp" inputmode="numeric" maxlength="6"
                 class="w-full rounded-lg border px-4 py-3 text-center text-xl tracking-[0.4em]"
                 placeholder="••••••" required>

          <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
            Xác thực đơn hàng
          </button>
        </form>

        <!-- FORM GỬI LẠI OTP (NẰM NGAY CẠNH, KHÔNG LỒNG FORM) -->
        <form method="post" action="<?=h(base_url('trang_nguoi_dung/gui_lai_otp_don.php'))?>">
          <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="don" value="<?= (int)$id_don ?>">
          <button class="w-full rounded-xl border py-3 font-bold">
            Gửi lại OTP
          </button>
        </form>

        <div class="text-sm text-slate-600 flex items-center justify-between">
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/don_hang.php'))?>">Quay lại đơn hàng</a>
          <span class="text-slate-500">OTP hiệu lực 10 phút</span>
        </div>
      </div>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>Nếu chưa thấy email, kiểm tra Spam/Quảng cáo.</li>
        <li>Mỗi lần “Gửi lại OTP” sẽ tạo mã mới, mã cũ hết hiệu lực.</li>
        <li>Nếu không xác thực, đơn sẽ nằm ở trạng thái “Chờ xác nhận email”.</li>
      </ul>
    </div>

  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
