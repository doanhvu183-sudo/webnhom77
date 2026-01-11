<?php
require_once __DIR__ . '/../includes/auth_core.php';

if (!is_post()) {
  redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
}

if (!csrf_check($_POST['_csrf'] ?? '')) {
  flash_set('err', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
  redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
  exit;
}

$uid = (int)($_SESSION['pending_verify_uid'] ?? 0);
if ($uid <= 0) {
  flash_set('err', 'Không tìm thấy phiên xác thực.');
  redirect(base_url('trang_nguoi_dung/dang_ky.php'));
  exit;
}

$st = $pdo->prepare("SELECT email, email_verified_at FROM nguoidung WHERE id_nguoi_dung=? LIMIT 1");
$st->execute([$uid]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if (!$u) {
  unset($_SESSION['pending_verify_uid']);
  flash_set('err', 'Tài khoản không tồn tại.');
  redirect(base_url('trang_nguoi_dung/dang_ky.php'));
  exit;
}

if (!empty($u['email_verified_at'])) {
  unset($_SESSION['pending_verify_uid']);
  flash_set('ok', 'Email đã được xác thực. Bạn có thể đăng nhập.');
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
  exit;
}

$email = (string)($u['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('err', 'Email tài khoản không hợp lệ.');
  redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
  exit;
}

$otp = otp_generate_6();
otp_save_for_user($pdo, $uid, $otp, 600);

$subject = "OTP xác thực tài khoản";
$html = "
  <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
    <h2 style='margin:0 0 10px'>Xác thực email</h2>
    <p>Mã OTP mới của bạn:</p>
    <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
    <p>Mã có hiệu lực trong <b>10 phút</b>.</p>
  </div>
";

$sent = send_email($email, $subject, $html);
if (!$sent) {
  flash_set('err', 'Không gửi được OTP. ' . ($GLOBALS['MAIL_LAST_ERROR'] ?? ''));
} else {
  flash_set('ok', 'Đã gửi lại OTP về email. Vui lòng kiểm tra hộp thư (kể cả Spam).');
}

redirect(base_url('trang_nguoi_dung/xac_thuc_email_tk.php'));
