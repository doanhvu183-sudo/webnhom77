<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$u = $_SESSION['nguoi_dung'] ?? [];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
$email = trim((string)($u['email'] ?? ''));

if ($uid <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  auth_logout();
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
  exit;
}

$err = null;
$ok  = flash_get('ok');

// state đổi mật khẩu (2 bước)
$step = (string)($_SESSION['pw_change_step'] ?? 'init'); // init | otp_sent

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) {
    $err = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
  } else {
    $action = (string)($_POST['action'] ?? '');

    // ===== BƯỚC 1: GỬI OTP =====
    if ($action === 'send_otp') {
      $pw  = (string)($_POST['mat_khau_moi'] ?? '');
      $pw2 = (string)($_POST['mat_khau_moi_2'] ?? '');

      if ($pw === '' || $pw2 === '') $err = 'Vui lòng nhập mật khẩu mới và xác nhận.';
      elseif ($pw !== $pw2) $err = 'Xác nhận mật khẩu không khớp.';
      else {
        $msg = null;
        if (!password_policy_check($pw, $msg)) {
          $err = $msg;
        } else {
          // lưu tạm mật khẩu mới vào session (hash) để bước 2 xác nhận OTP mới update DB
          $_SESSION['pw_change_new_hash'] = password_hash($pw, PASSWORD_BCRYPT);

          // tạo OTP
          $otp = otp_generate_6();
          otp_save_for_user($pdo, $uid, $otp, 600);

          // gửi email OTP
          $subject = "OTP đổi mật khẩu";
          $html = "
            <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
              <h2 style='margin:0 0 10px'>Xác nhận đổi mật khẩu</h2>
              <p>Bạn đang yêu cầu đổi mật khẩu cho tài khoản: <b>{$email}</b></p>
              <p>Mã OTP của bạn:</p>
              <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
              <p>Mã có hiệu lực trong <b>10 phút</b>. Nếu không phải bạn thao tác, hãy bỏ qua email này.</p>
            </div>
          ";

          $sent = send_email($email, $subject, $html);
          if (!$sent) {
            $err = 'Không gửi được OTP. ' . ($GLOBALS['MAIL_LAST_ERROR'] ?? '');
          } else {
            $_SESSION['pw_change_step'] = 'otp_sent';
            $step = 'otp_sent';
            $ok = 'Đã gửi OTP về email. Vui lòng nhập mã để xác nhận đổi mật khẩu.';
          }
        }
      }
    }

    // ===== BƯỚC 2: XÁC NHẬN OTP + ĐỔI MẬT KHẨU =====
    elseif ($action === 'confirm') {
      $otp = trim((string)($_POST['otp'] ?? ''));
      if (!preg_match('/^\d{6}$/', $otp)) {
        $err = 'OTP phải gồm 6 chữ số.';
      } else {
        $new_hash = (string)($_SESSION['pw_change_new_hash'] ?? '');
        if ($new_hash === '') {
          $err = 'Phiên đổi mật khẩu đã hết hạn. Vui lòng thao tác lại.';
          $_SESSION['pw_change_step'] = 'init';
          $step = 'init';
        } else {
          if (!otp_verify_for_user($pdo, $uid, $otp)) {
            $err = 'OTP sai hoặc hết hạn.';
          } else {
            // update mật khẩu: đồng bộ mat_khau + pass_hash như hệ thống bạn đang dùng
            $st = $pdo->prepare("UPDATE nguoidung SET mat_khau=?, pass_hash=?, updated_at=NOW() WHERE id_nguoi_dung=? LIMIT 1");
            $st->execute([$new_hash, $new_hash, $uid]);

            otp_clear_for_user($pdo, $uid);

            unset($_SESSION['pw_change_new_hash'], $_SESSION['pw_change_step']);
            flash_set('ok', 'Đổi mật khẩu thành công.');
            redirect(base_url('trang_nguoi_dung/tai_khoan.php'));
            exit;
          }
        }
      }
    }

    // ===== GỬI LẠI OTP =====
    elseif ($action === 'resend') {
      $new_hash = (string)($_SESSION['pw_change_new_hash'] ?? '');
      if ($new_hash === '') {
        $err = 'Không có yêu cầu đổi mật khẩu đang chờ. Vui lòng nhập lại mật khẩu mới.';
        $_SESSION['pw_change_step'] = 'init';
        $step = 'init';
      } else {
        $otp = otp_generate_6();
        otp_save_for_user($pdo, $uid, $otp, 600);

        $subject = "OTP đổi mật khẩu (gửi lại)";
        $html = "
          <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
            <h2 style='margin:0 0 10px'>OTP đổi mật khẩu</h2>
            <p>Mã OTP mới của bạn:</p>
            <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
            <p>Mã có hiệu lực trong <b>10 phút</b>.</p>
          </div>
        ";

        $sent = send_email($email, $subject, $html);
        if (!$sent) $err = 'Không gửi được OTP. ' . ($GLOBALS['MAIL_LAST_ERROR'] ?? '');
        else $ok = 'Đã gửi lại OTP. Vui lòng kiểm tra email (kể cả Spam).';

        $_SESSION['pw_change_step'] = 'otp_sent';
        $step = 'otp_sent';
      }
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">

    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Đổi mật khẩu</h1>
      <p class="text-slate-600 mb-6">Xác nhận OTP gửi về email để đổi mật khẩu an toàn.</p>

      <?php if ($err): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($err)?></div>
      <?php endif; ?>
      <?php if ($ok): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-700"><?=h($ok)?></div>
      <?php endif; ?>
      <?php if ($f = flash_get('err')): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700"><?=h($f)?></div>
      <?php endif; ?>

      <?php if ($step !== 'otp_sent'): ?>
        <!-- STEP 1 -->
        <form method="post" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="send_otp">

          <div>
            <label class="block text-sm font-semibold mb-1">Email xác nhận</label>
            <input value="<?=h($email)?>" disabled class="w-full rounded-lg border px-4 py-3 bg-slate-50">
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-semibold mb-1">Mật khẩu mới</label>
              <input type="password" name="mat_khau_moi" class="w-full rounded-lg border px-4 py-3" required>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1">Nhập lại mật khẩu mới</label>
              <input type="password" name="mat_khau_moi_2" class="w-full rounded-lg border px-4 py-3" required>
            </div>
          </div>

          <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
            Gửi OTP xác nhận
          </button>

          <a href="<?=h(base_url('trang_nguoi_dung/tai_khoan.php'))?>"
             class="block text-center text-sm underline text-slate-500">
            ← Quay lại tài khoản
          </a>
        </form>

      <?php else: ?>
        <!-- STEP 2 -->
        <form method="post" class="space-y-4">
          <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="confirm">

          <div>
            <label class="block text-sm font-semibold mb-1">Nhập OTP (6 số)</label>
            <input name="otp" inputmode="numeric" maxlength="6"
                   class="w-full rounded-lg border px-4 py-3 text-center text-xl tracking-[0.4em]"
                   placeholder="••••••" required>
          </div>

          <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
            Xác nhận & Đổi mật khẩu
          </button>
        </form>

        <form method="post" class="mt-3">
          <input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="resend">
          <button class="w-full rounded-xl border py-3 font-bold">
            Gửi lại OTP
          </button>
        </form>

        <div class="mt-4 text-sm text-slate-600 flex items-center justify-between">
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/tai_khoan.php'))?>">← Quay lại tài khoản</a>
          <span class="text-slate-500">OTP hiệu lực 10 phút</span>
        </div>
      <?php endif; ?>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>OTP được gửi tới email tài khoản đang đăng nhập.</li>
        <li>Nếu không thấy email, hãy kiểm tra Spam/Quảng cáo.</li>
        <li>Mỗi lần “Gửi lại OTP” sẽ tạo mã mới và mã cũ hết hiệu lực.</li>
      </ul>
    </div>

  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
