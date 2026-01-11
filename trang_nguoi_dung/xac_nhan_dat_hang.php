<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$u = $_SESSION['nguoi_dung'] ?? [];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
if ($uid <= 0) { auth_logout(); redirect(base_url('trang_nguoi_dung/dang_nhap.php')); exit; }

$id_don = (int)($_GET['don'] ?? 0);
if ($id_don <= 0) { flash_set('err','Thiếu mã đơn.'); redirect(base_url('trang_nguoi_dung/thanh_toan.php')); exit; }

$st = $pdo->prepare("SELECT id_don_hang, ma_don_hang, trang_thai, tong_thanh_toan
                     FROM donhang WHERE id_don_hang=? AND id_nguoi_dung=? LIMIT 1");
$st->execute([$id_don,$uid]);
$don = $st->fetch(PDO::FETCH_ASSOC);

if (!$don) { flash_set('err','Đơn không hợp lệ.'); redirect(base_url('trang_nguoi_dung/thanh_toan.php')); exit; }

// Nếu đã xác nhận rồi thì cho sang hoàn tất luôn
if (($don['trang_thai'] ?? '') !== 'CHO_XAC_NHAN_EMAIL') {
  redirect(base_url('trang_nguoi_dung/hoan_tat_don_hang.php?don='.$id_don));
  exit;
}

$err = null;

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) $err = 'Phiên làm việc không hợp lệ.';
  else {
    $otp = trim((string)($_POST['otp'] ?? ''));
    if (!preg_match('/^\d{6}$/', $otp)) $err = 'OTP phải gồm 6 chữ số.';
    else if (!otp_verify_for_user($pdo, $uid, $otp)) $err = 'OTP sai hoặc hết hạn.';
    else {
      // OTP đúng => mở khóa đơn
      $up = $pdo->prepare("UPDATE donhang
                           SET trang_thai='CHO_XU_LY', ngay_cap_nhat=NOW()
                           WHERE id_don_hang=? AND id_nguoi_dung=? LIMIT 1");
      $up->execute([$id_don,$uid]);

      unset($_SESSION['cart'], $_SESSION['voucher']);

      redirect(base_url('trang_nguoi_dung/hoan_tat_don_hang.php?don='.$id_don));
      exit;
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-xl mx-auto px-4 py-10">
  <h1 class="text-2xl font-extrabold mb-2">Xác nhận đơn hàng</h1>
  <p class="text-slate-600 mb-6">Nhập OTP đã gửi email để xác nhận đơn <b>#<?=h($don['ma_don_hang'])?></b>.</p>

  <?php if ($err): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($err)?></div>
  <?php endif; ?>

  <form method="post" class="space-y-4">
    <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
    <input name="otp" inputmode="numeric" maxlength="6"
           class="w-full rounded-lg border px-4 py-3 text-center text-xl tracking-[0.4em]"
           placeholder="••••••" required>
    <button class="w-full rounded-xl bg-black text-white py-3 font-bold">Xác nhận</button>
  </form>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
