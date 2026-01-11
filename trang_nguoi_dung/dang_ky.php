<?php
require_once __DIR__ . '/../includes/auth_core.php';

$err = null;
$ok  = flash_get('ok');
$redirect_to = $_GET['redirect'] ?? base_url('trang_nguoi_dung/trang_chu.php');

$domains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com'];

if (is_post()) {
  if (!csrf_check($_POST['_csrf'] ?? '')) {
    $err = 'Phiên làm việc không hợp lệ, vui lòng thử lại.';
  } else {
    $ten_dang_nhap = trim((string)($_POST['ten_dang_nhap'] ?? ''));
    $ho_ten        = trim((string)($_POST['ho_ten'] ?? ''));
    $gioi_tinh     = (string)($_POST['gioi_tinh'] ?? 'khac');
    $ngay_sinh     = trim((string)($_POST['ngay_sinh'] ?? ''));
    $sdt           = normalize_phone(trim((string)($_POST['so_dien_thoai'] ?? '')));
    $dia_chi       = trim((string)($_POST['dia_chi'] ?? ''));

    $email_local   = trim((string)($_POST['email_local'] ?? ''));
    $email_domain  = trim((string)($_POST['email_domain'] ?? 'gmail.com'));
    $email_custom  = trim((string)($_POST['email_domain_custom'] ?? ''));

    $pw  = (string)($_POST['mat_khau'] ?? '');
    $pw2 = (string)($_POST['mat_khau_2'] ?? '');

    $email_domain_final = ($email_domain === 'custom') ? $email_custom : $email_domain;
    $email_domain_final = strtolower(trim($email_domain_final));
    $email = strtolower($email_local . '@' . $email_domain_final);

    // ===== Validate =====
    if ($ten_dang_nhap === '' || strlen($ten_dang_nhap) < 4) $err = 'Tên đăng nhập tối thiểu 4 ký tự.';
    elseif (!preg_match('/^[a-zA-Z0-9_.-]{4,100}$/', $ten_dang_nhap)) $err = 'Tên đăng nhập chỉ gồm chữ/số và . _ -';
    elseif ($ho_ten === '') $err = 'Vui lòng nhập họ tên.';
    elseif (!in_array($gioi_tinh, ['nam','nu','khac'], true)) $err = 'Giới tính không hợp lệ.';
    elseif ($sdt === '' || !preg_match('/^\d{9,12}$/', $sdt)) $err = 'Số điện thoại 9–12 chữ số.';
    elseif ($dia_chi === '') $err = 'Vui lòng nhập địa chỉ.';
    elseif ($email_local === '' || $email_domain_final === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email không hợp lệ.';
    elseif ($pw === '' || $pw2 === '') $err = 'Vui lòng nhập mật khẩu và xác nhận.';
    elseif ($pw !== $pw2) $err = 'Xác nhận mật khẩu không khớp.';
    else {
      // Validate ngày sinh + CHẶN NGÀY TƯƠNG LAI
      if ($ngay_sinh !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $ngay_sinh);
        $errors = DateTime::getLastErrors();
        if (!$dt || ($errors['warning_count'] ?? 0) || ($errors['error_count'] ?? 0)) {
          $err = 'Ngày sinh không hợp lệ.';
        } else {
          $today = new DateTime('today');
          // Nếu ngày sinh > hôm nay => chặn
          if ($dt > $today) {
            $err = 'Ngày sinh không được lớn hơn ngày hiện tại.';
          }
        }
      }

      if (!$err) {
        $msg = null;
        if (!password_policy_check($pw, $msg)) {
          $err = $msg;
        } else {
          // check trùng email/username
          $st = $pdo->prepare("SELECT id_nguoi_dung FROM nguoidung WHERE email=? LIMIT 1");
          $st->execute([$email]);
          if ($st->fetch()) $err = 'Email đã tồn tại.';
          else {
            $st = $pdo->prepare("SELECT id_nguoi_dung FROM nguoidung WHERE ten_dang_nhap=? LIMIT 1");
            $st->execute([$ten_dang_nhap]);
            if ($st->fetch()) $err = 'Tên đăng nhập đã tồn tại.';
            else {
              $hash = password_hash($pw, PASSWORD_BCRYPT);

              // Insert user (email chưa verified)
              $ins = $pdo->prepare("
                INSERT INTO nguoidung
                  (ten_dang_nhap, mat_khau, pass_hash, ho_ten, email, so_dien_thoai, dia_chi, gioi_tinh, ngay_sinh,
                   avatar, vai_tro, is_active, email_verified_at, created_at, updated_at)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, 'default.png', 'khach', 1, NULL, NOW(), NOW())
              ");
              $ins->execute([
                $ten_dang_nhap,
                $hash,
                $hash,
                $ho_ten,
                $email,
                $sdt,
                $dia_chi,
                $gioi_tinh,
                ($ngay_sinh !== '' ? $ngay_sinh : null),
              ]);

              $new_uid = (int)$pdo->lastInsertId();

              // OTP verify email tài khoản
              $otp = otp_generate_6();
              otp_save_for_user($pdo, $new_uid, $otp, 600);

              $subject = "OTP xác thực tài khoản";
              $html = "
                <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
                  <h2 style='margin:0 0 10px'>Xác thực email</h2>
                  <p>Mã OTP để xác thực tài khoản của bạn:</p>
                  <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
                  <p>Mã có hiệu lực trong <b>10 phút</b>.</p>
                </div>
              ";
              send_email($email, $subject, $html);

              // Lưu pending verify
              $_SESSION['pending_verify_uid'] = $new_uid;

              flash_set('ok', 'Đăng ký thành công. Vui lòng nhập OTP để xác thực email trước khi đăng nhập.');
              redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
              exit;
            }
          }
        }
      }
    }
  }
}

require_once __DIR__ . '/../giao_dien/header.php';
?>
<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
    <div class="border rounded-2xl p-6 bg-white">
      <h1 class="text-2xl font-extrabold mb-2">Tạo tài khoản</h1>
      <p class="text-slate-600 mb-6">Điền thông tin để tạo tài khoản khách hàng.</p>

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
          <label class="block text-sm font-semibold mb-1">Tên đăng nhập</label>
          <input name="ten_dang_nhap" value="<?=h($_POST['ten_dang_nhap'] ?? '')?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="vd: alphax_77" required>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-1">Họ tên</label>
          <input name="ho_ten" value="<?=h($_POST['ho_ten'] ?? '')?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="Nguyễn Văn A" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Giới tính</label>
            <?php $gt = $_POST['gioi_tinh'] ?? 'khac'; ?>
            <select name="gioi_tinh" class="w-full rounded-lg border px-4 py-3">
              <option value="nam"  <?=$gt==='nam'?'selected':''?>>Nam</option>
              <option value="nu"   <?=$gt==='nu'?'selected':''?>>Nữ</option>
              <option value="khac" <?=$gt==='khac'?'selected':''?>>Khác</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">Ngày sinh</label>
            <input type="date" name="ngay_sinh"
                   max="<?= date('Y-m-d') ?>"
                   value="<?=h($_POST['ngay_sinh'] ?? '')?>"
                   class="w-full rounded-lg border px-4 py-3">
            <div class="text-xs text-slate-500 mt-1">Không được chọn ngày trong tương lai.</div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-1">Số điện thoại</label>
          <input type="tel" name="so_dien_thoai" value="<?=h($_POST['so_dien_thoai'] ?? '')?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="vd: 0987654321" required>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-1">Email</label>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
            <input name="email_local" value="<?=h($_POST['email_local'] ?? '')?>"
                   class="w-full rounded-lg border px-4 py-3"
                   placeholder="ten.email" required>
            <?php $ed = $_POST['email_domain'] ?? 'gmail.com'; ?>
            <select name="email_domain" class="w-full rounded-lg border px-4 py-3">
              <?php foreach ($domains as $d): ?>
                <option value="<?=h($d)?>" <?=$ed===$d?'selected':''?>>@<?=h($d)?></option>
              <?php endforeach; ?>
              <option value="custom" <?=$ed==='custom'?'selected':''?>>Khác…</option>
            </select>
            <input name="email_domain_custom" value="<?=h($_POST['email_domain_custom'] ?? '')?>"
                   class="w-full rounded-lg border px-4 py-3"
                   placeholder="vd: company.com">
          </div>
          <div class="text-xs text-slate-500 mt-1">Chọn đuôi email hoặc nhập đuôi khác.</div>
        </div>

        <div>
          <label class="block text-sm font-semibold mb-1">Địa chỉ</label>
          <input name="dia_chi" value="<?=h($_POST['dia_chi'] ?? '')?>"
                 class="w-full rounded-lg border px-4 py-3"
                 placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-semibold mb-1">Mật khẩu</label>
            <input type="password" name="mat_khau"
                   class="w-full rounded-lg border px-4 py-3" required>
          </div>
          <div>
            <label class="block text-sm font-semibold mb-1">Nhập lại mật khẩu</label>
            <input type="password" name="mat_khau_2"
                   class="w-full rounded-lg border px-4 py-3" required>
          </div>
        </div>

        <button class="w-full rounded-xl bg-black text-white py-3 font-bold">
          Đăng ký & Gửi OTP
        </button>

        <p class="text-sm text-slate-600 text-center">
          Đã có tài khoản?
          <a class="font-bold underline"
             href="<?=h(base_url('trang_nguoi_dung/dang_nhap.php?redirect='.urlencode($redirect_to)))?>">
            Đăng nhập
          </a>
        </p>
      </form>
    </div>

    <div class="border rounded-2xl p-6 bg-white">
      <h2 class="text-lg font-extrabold mb-2">Lưu ý</h2>
      <ul class="list-disc pl-5 text-slate-600 space-y-2">
        <li>Sau khi đăng ký, bạn phải xác thực OTP email mới đăng nhập được.</li>
        <li>OTP có hiệu lực 10 phút. Hãy kiểm tra cả Spam/Quảng cáo.</li>
        <li>Ngày sinh không được chọn ngày trong tương lai.</li>
      </ul>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
