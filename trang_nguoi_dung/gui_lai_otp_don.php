<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../includes/mailer_smtp.php';

if (!isset($_SESSION['nguoi_dung'])) {
  header('Location: dang_nhap.php');
  exit;
}

$u = $_SESSION['nguoi_dung'];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
$email = (string)($u['email'] ?? '');

$don = (int)($_POST['don'] ?? 0);
if ($uid <= 0 || $don <= 0) {
  $_SESSION['flash'] = ['type'=>'error','msg'=>'Dữ liệu không hợp lệ.'];
  header('Location: don_hang.php');
  exit;
}

// kiểm tra đơn thuộc user + đang chờ xác nhận email
$st = $pdo->prepare("SELECT id_don_hang, ma_don_hang, trang_thai
                     FROM donhang
                     WHERE id_don_hang=? AND id_nguoi_dung=? LIMIT 1");
$st->execute([$don, $uid]);
$dh = $st->fetch(PDO::FETCH_ASSOC);

if (!$dh) {
  $_SESSION['flash'] = ['type'=>'error','msg'=>'Không tìm thấy đơn hàng.'];
  header('Location: don_hang.php');
  exit;
}

if (($dh['trang_thai'] ?? '') !== 'CHO_XAC_NHAN_EMAIL') {
  $_SESSION['flash'] = ['type'=>'error','msg'=>'Đơn này không ở trạng thái chờ xác nhận email.'];
  header('Location: don_hang.php');
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $_SESSION['flash'] = ['type'=>'error','msg'=>'Email tài khoản không hợp lệ.'];
  header('Location: don_hang.php');
  exit;
}

// tạo OTP 6 số + lưu vào nguoidung (dùng cột sẵn có)
$otp  = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$hash = hash('sha256', $otp);

// TTL 10 phút
$ttl = 10;

$up = $pdo->prepare("
  UPDATE nguoidung
  SET verify_token_hash=?, verify_token_expires=DATE_ADD(NOW(), INTERVAL ? MINUTE)
  WHERE id_nguoi_dung=?
");
$up->execute([$hash, $ttl, $uid]);

$ma = $dh['ma_don_hang'] ?? ('#'.$don);

$html = "
  <div style='font-family:Arial,sans-serif;line-height:1.6'>
    <h2>Gửi lại OTP xác nhận đơn hàng</h2>
    <p>Đơn hàng: <b>{$ma}</b></p>
    <p>Mã OTP của bạn:</p>
    <div style='font-size:28px;letter-spacing:6px;font-weight:800;padding:12px 16px;border:1px solid #eee;border-radius:10px;display:inline-block;background:#fafafa'>
      {$otp}
    </div>
    <p>Mã có hiệu lực trong <b>{$ttl} phút</b>.</p>
  </div>
";

$sent = send_mail_smtp($email, "OTP xác nhận đơn hàng {$ma}", $html);

if (!$sent) {
  $_SESSION['flash'] = ['type'=>'error','msg'=>'Gửi OTP thất bại. Vui lòng kiểm tra cấu hình SMTP.'];
  header('Location: don_hang.php');
  exit;
}

$_SESSION['flash'] = ['type'=>'success','msg'=>'Đã gửi lại OTP về email. Vui lòng kiểm tra hộp thư (kể cả Spam).'];
header('Location: xac_nhan_dat_hang.php?don='.$don);
exit;
