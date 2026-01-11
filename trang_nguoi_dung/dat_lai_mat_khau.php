<?php
require_once __DIR__ . '/../includes/auth_core.php';

$err = null;
$ok  = flash_get('ok');

$login = trim((string)($_GET['login'] ?? ($_POST['login'] ?? '')));
if ($login === '') {
  flash_set('err', 'Vui lòng nhập email/tên đăng nhập trước.');
  redirect(base_url('trang_nguoi_dung/quen_mat_khau.php'));
}

$u = find_user_by_login($pdo, $login);
$uid = (int)($u['id_nguoi_dung'] ?? 0);
$email = (string)($u['email'] ?? '');

// Không tiết lộ thông tin tài khoản (anti-enumeration)
// Nhưng để user thao tác được, vẫn cho vào form nhập OTP.
$account_exists = ($uid > 0);

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) {
    $err = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
  } else {
    $otp = trim((string)($_POST['otp'] ?? ''));
    $pw  = (string)($_POST['mat_khau'] ?? '');
    $pw2 = (string)($_POST['mat_khau_2'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) $err = 'OTP phải gồm 6 chữ số.';
    elseif ($pw === '' || $pw2 === '') $err = 'Vui lòng nhập mật khẩu mới và xác nhận.';
    elseif ($pw !== $pw2) $err = 'Xác nhận mật khẩu không khớp.';
    else {
      $msg = null;
      if (!password_policy_check($pw, $msg)) {
        $err = $msg;
      } else {
        // Nếu account không tồn tại: vẫn báo chung chung
        if (!$account_exists) {
          $err = 'OTP sai hoặc hết hạn.';
        } else {
          // Verify OTP theo user
          if (!otp_verify_for_user($pdo, $uid, $otp)) {
            $err = 'OTP sai hoặc hết hạn.';
          } else {
            // OTP đúng -> update mật khẩu
            $hash = password_hash($pw, PASSWORD_BCRYPT);

            try {
              $st = $pdo->prepare("
                UPDATE nguoidung
                SET pass_hash = ?, mat_khau = ?, updated_at = NOW()
                WHERE id_nguoi_dung = ?
                LIMIT 1
              ");
              $st->execute([$hash, $hash, $uid]);

              // Clear OTP để tránh dùng lại
              otp_clear_for_user($pdo, $uid);

              flash_set('ok', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập.');
              redirect(base_url('trang_nguoi_dung/dang_nhap.php?login=' . urlencode($email ?: $login)));
            } catch (Throwable $e) {
              $err = 'Lỗi DB: ' . $e->getMessage();
            }
          }
        }
      }
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-5xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Đặt lại mật khẩu</h1>
      <p class="text-slate-600 mb-6">
        Nhập OTP đã gửi về email và tạo mật khẩu mới.
        <?php if ($account_exists && $email): ?>
          <br><span class="text-xs text-slate-500">Email nhận OTP: <b><?=h($email)?></b></span>
        <?php endif; ?>
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
        <input type="hidden" name="login" value="<?=h($login)?>">

        <div>
          <label class="block text-sm font-semibold mb-1">OTP (6 số)</label>
          <input name="otp"
                 inputmode="numeric"
                 maxlength="6"
                 class="w-full rounded-lg border px-4 py-3 text-center text-xl tracking-[0.4em]"
                 placeholder="••••••"
                 required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Mật khẩu mới</label>
            <input type="password" name="mat_khau" class="w-full rounded-lg border px-4 py-3" required>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">Nhập lại mật khẩu</label>
            <input type="password" name="mat_khau_2" class="w-full rounded-lg border px-4 py-3" required>
          </div>
        </div>

        <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
          Xác nhận & Đổi mật khẩu
        </button>
      </form>

      <div class="mt-4 flex items-center justify-between text-sm text-slate-600">
        <a class="underline" href="<?=h(base_url('trang_nguoi_dung/quen_mat_khau.php'))?>">Gửi lại OTP</a>
        <a class="underline" href="<?=h(base_url('trang_nguoi_dung/dang_nhap.php'))?>">Quay lại đăng nhập</a>
      </div>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Yêu cầu mật khẩu</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>Tối thiểu 8 ký tự</li>
        <li>Có ít nhất 1 chữ hoa, 1 chữ thường</li>
        <li>Có ít nhất 1 chữ số</li>
        <li>OTP có hiệu lực 10 phút</li>
      </ul>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
