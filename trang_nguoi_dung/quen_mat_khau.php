<?php
require_once __DIR__ . '/../includes/auth_core.php';

$err = null;
$ok  = flash_get('ok');

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) {
    $err = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
  } else {
    $login = trim((string)($_POST['login'] ?? ''));

    if ($login === '') {
      $err = 'Vui lòng nhập email hoặc tên đăng nhập.';
    } else {
      $u = find_user_by_login($pdo, $login);

      // Không tiết lộ có tồn tại hay không để tránh dò tài khoản
      if (!$u) {
        flash_set('ok', 'Nếu tài khoản tồn tại, OTP đã được gửi về email.');
        redirect(base_url('trang_nguoi_dung/doi_mat_khau.php?login=' . urlencode($login)));
      }

      $uid   = (int)($u['id_nguoi_dung'] ?? 0);
      $email = (string)($u['email'] ?? '');

      if ($uid <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // vẫn không tiết lộ
        flash_set('ok', 'Nếu tài khoản tồn tại, OTP đã được gửi về email.');
        redirect(base_url('trang_nguoi_dung/doi_mat_khau.php?login=' . urlencode($login)));
      }

      // Tạo OTP + lưu vào nguoidung.verify_token_hash/verify_token_expires
      $otp = otp_generate_6();
      otp_save_for_user($pdo, $uid, $otp, 600);

      // Gửi OTP
      $subject = "OTP đặt lại mật khẩu";
      $html = "
        <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
          <h2 style='margin:0 0 10px'>Đặt lại mật khẩu</h2>
          <p>Bạn vừa yêu cầu đặt lại mật khẩu. Mã OTP của bạn là:</p>
          <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
          <p>Mã có hiệu lực trong <b>10 phút</b>. Nếu không phải bạn, hãy bỏ qua email này.</p>
        </div>
      ";

      $sent = send_email($email, $subject, $html);
      if (!$sent) {
        // Không lộ chi tiết hệ thống, nhưng có thể báo nhẹ
        $err = 'Không gửi được OTP. Vui lòng thử lại sau. ' . (($GLOBALS['MAIL_LAST_ERROR'] ?? '') ? ('(' . $GLOBALS['MAIL_LAST_ERROR'] . ')') : '');
      } else {
        flash_set('ok', 'Đã gửi OTP về email. Vui lòng kiểm tra hộp thư (kể cả Spam).');
        redirect(base_url('trang_nguoi_dung/dat_lai_mat_khau.php?login=' . urlencode($login)));

      }
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-5xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Quên mật khẩu</h1>
      <p class="text-slate-600 mb-6">Nhập email hoặc tên đăng nhập. Chúng tôi sẽ gửi OTP để bạn đặt lại mật khẩu.</p>

      <?php if ($err): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($err)?></div>
      <?php endif; ?>
      <?php if ($ok): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-700"><?=h($ok)?></div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">

        <div>
          <label class="block text-sm font-semibold mb-1">Email hoặc Tên đăng nhập</label>
          <input name="login"
                 value="<?=h($_POST['login'] ?? '')?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="email@domain.com hoặc username"
                 required>
        </div>

        <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
          Gửi OTP
        </button>

        <div class="text-sm text-slate-600 flex items-center justify-between">
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/dang_nhap.php'))?>">Quay lại đăng nhập</a>
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/dang_ky.php'))?>">Tạo tài khoản</a>
        </div>
      </form>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>OTP có hiệu lực 10 phút.</li>
        <li>Nếu không thấy email, hãy kiểm tra mục Spam/Quảng cáo.</li>
        <li>Mỗi lần bấm “Gửi OTP” sẽ tạo mã mới.</li>
      </ul>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
