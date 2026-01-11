<?php
require_once __DIR__ . '/../includes/auth_core.php';

$err = null;
$ok  = flash_get('ok');

$uid = (int)($_SESSION['pending_verify_uid'] ?? 0);
if ($uid <= 0) {
  flash_set('err', 'Không tìm thấy phiên xác thực. Vui lòng đăng ký lại hoặc gửi lại OTP.');
  redirect(base_url('trang_nguoi_dung/dang_ky.php'));
  exit;
}

// lấy email để hiển thị
$st = $pdo->prepare("SELECT email, ten_dang_nhap, email_verified_at FROM nguoidung WHERE id_nguoi_dung=? LIMIT 1");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  unset($_SESSION['pending_verify_uid']);
  flash_set('err', 'Tài khoản không tồn tại.');
  redirect(base_url('trang_nguoi_dung/dang_ky.php'));
  exit;
}

// nếu đã verified rồi -> cho qua đăng nhập
if (!empty($user['email_verified_at'])) {
  unset($_SESSION['pending_verify_uid']);
  flash_set('ok', 'Email đã được xác thực. Bạn có thể đăng nhập.');
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
  exit;
}

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) $err = 'Phiên làm việc không hợp lệ.';
  else {
    $otp = trim((string)($_POST['otp'] ?? ''));
    if (!preg_match('/^\d{6}$/', $otp)) $err = 'OTP phải gồm 6 chữ số.';
    else if (!otp_verify_for_user($pdo, $uid, $otp)) $err = 'OTP sai hoặc hết hạn.';
    else {
      // cập nhật verified
      $pdo->prepare("UPDATE nguoidung SET email_verified_at=NOW(), updated_at=NOW() WHERE id_nguoi_dung=? LIMIT 1")
          ->execute([$uid]);

      otp_clear_for_user($pdo, $uid);
      unset($_SESSION['pending_verify_uid']);

      flash_set('ok', 'Xác thực email thành công. Vui lòng đăng nhập.');
      redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
      exit;
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-5xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Xác thực email</h1>
      <p class="text-slate-600 mb-6">
        Nhập OTP đã gửi về: <b><?=h($user['email'] ?? '')?></b>
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

      <form method="post" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
        <input name="otp" inputmode="numeric" maxlength="6"
               class="w-full rounded-lg border px-4 py-3 text-center text-xl tracking-[0.4em]"
               placeholder="••••••" required>
        <button class="w-full rounded-xl bg-black text-white py-3 font-bold">Xác thực</button>
      </form>

      <form method="post" action="<?=h(base_url('trang_nguoi_dung/gui_lai_otp_tk.php'))?>" class="mt-3">
        <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
        <button class="w-full rounded-xl border py-3 font-bold">Gửi lại OTP</button>
      </form>

      <div class="mt-4 text-sm text-slate-600 flex items-center justify-between">
        <a class="underline" href="<?=h(base_url('trang_nguoi_dung/dang_ky.php'))?>">Quay lại đăng ký</a>
        <span class="text-slate-500">OTP hiệu lực 10 phút</span>
      </div>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>Kiểm tra cả Spam/Quảng cáo nếu chưa thấy email.</li>
        <li>Mỗi lần gửi lại OTP sẽ tạo mã mới.</li>
      </ul>
    </div>

  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
