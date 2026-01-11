<?php
require_once __DIR__ . '/../includes/auth_core.php';

$err = null;
$ok  = flash_get('ok');
$redirect_to = $_GET['redirect'] ?? base_url('trang_nguoi_dung/trang_chu.php');

// Prefill login nếu có ?login=
$prefill = trim((string)($_GET['login'] ?? ''));

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) {
    $err = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
  } else {
    $login = trim((string)($_POST['login'] ?? ''));
    $pw    = (string)($_POST['mat_khau'] ?? '');
    $redirect_to = safe_redirect_target((string)($_POST['redirect'] ?? $redirect_to));

    if ($login === '' || $pw === '') {
      $err = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
      $u = find_user_by_login($pdo, $login);

      if (!$u || !verify_password_row($u, $pw)) {
        $err = 'Sai email/tên đăng nhập hoặc mật khẩu.';
      } elseif ((int)($u['is_active'] ?? 0) !== 1) {
        $err = 'Tài khoản đang bị khóa hoặc không hoạt động.';
      } else {
        // CHẶN: chưa xác thực email thì không cho login
        if (empty($u['email_verified_at'])) {
          $_SESSION['pending_verify_uid'] = (int)($u['id_nguoi_dung'] ?? 0);
          $err = 'Tài khoản chưa xác thực email. Vui lòng nhập OTP để xác thực.';
          // đẩy sang trang OTP luôn (nếu bạn muốn)
          redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
          exit;
        }

        // migrate: nếu pass_hash trống mà mat_khau có hash
        if (empty($u['pass_hash']) && !empty($u['mat_khau'])) {
          $st = $pdo->prepare("UPDATE nguoidung SET pass_hash=?, updated_at=NOW() WHERE id_nguoi_dung=?");
          $st->execute([(string)$u['mat_khau'], (int)$u['id_nguoi_dung']]);
        }

        auth_login($u);
        redirect($redirect_to);
        exit;
      }
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Đăng nhập</h1>
      <p class="text-slate-600 mb-6">Chào mừng bạn quay lại.</p>

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
        <input type="hidden" name="redirect" value="<?=h($redirect_to)?>">

        <div>
          <label class="block text-sm font-semibold mb-1">Email hoặc Tên đăng nhập</label>
          <input name="login"
                 value="<?=h($_POST['login'] ?? $prefill)?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="email@domain.com hoặc username"
                 required>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-1">Mật khẩu</label>
          <input type="password" name="mat_khau"
                 class="w-full rounded-lg border px-4 py-3"
                 required>
        </div>

        <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
          Đăng nhập
        </button>

        <div class="flex items-center justify-between text-sm text-slate-600">
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/quen_mat_khau.php'))?>">Quên mật khẩu?</a>
          <a class="underline" href="<?=h(base_url('trang_nguoi_dung/dang_ky.php?redirect='.urlencode($redirect_to)))?>">Tạo tài khoản</a>
        </div>
      </form>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>Nếu tài khoản chưa xác thực email, hệ thống sẽ yêu cầu OTP trước khi đăng nhập.</li>
        <li>Nếu bạn không nhận được OTP, hãy kiểm tra Spam/Quảng cáo.</li>
      </ul>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
