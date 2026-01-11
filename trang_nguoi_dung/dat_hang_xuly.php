<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

file_put_contents(__DIR__ . '/__checkout_hit.txt', date('c')." HIT\n", FILE_APPEND);

if (!is_post()) {
  redirect(base_url('trang_nguoi_dung/thanh_toan.php'));
  exit;
}

if (!csrf_check($_POST['_csrf'] ?? '')) {
  flash_set('err', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
  redirect(base_url('trang_nguoi_dung/thanh_toan.php'));
  exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
  flash_set('err', 'Giỏ hàng trống.');
  redirect(base_url('trang_nguoi_dung/gio_hang.php'));
  exit;
}

$u = $_SESSION['nguoi_dung'] ?? [];
$uid = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));
if ($uid <= 0) {
  auth_logout();
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
  exit;
}

$ho_ten = trim((string)($_POST['ho_ten'] ?? ''));
$email  = trim((string)($_POST['email'] ?? ''));
$sdt    = normalize_phone(trim((string)($_POST['so_dien_thoai'] ?? '')));
$dia_chi= trim((string)($_POST['dia_chi'] ?? ''));
$pttt   = (string)($_POST['phuong_thuc'] ?? 'COD');

if ($ho_ten === '' || $email === '' || $sdt === '' || $dia_chi === '') {
  flash_set('err', 'Vui lòng nhập đầy đủ thông tin nhận hàng.');
  redirect(base_url('trang_nguoi_dung/thanh_toan.php'));
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('err', 'Email không hợp lệ.');
  redirect(base_url('trang_nguoi_dung/thanh_toan.php'));
  exit;
}

// Tính tiền theo cart đã đồng bộ
$tong_tien = 0;
foreach ($cart as $sp) {
  $don_gia = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
  $qty = max(1, (int)($sp['qty'] ?? 1));
  $tong_tien += $don_gia * $qty;
}

// Voucher
$tien_giam = 0;
$ma_voucher = null;
if (!empty($_SESSION['voucher']) && is_array($_SESSION['voucher'])) {
  $vc = $_SESSION['voucher'];
  $ma_voucher = $vc['ma_voucher'] ?? null;

  $loai = strtoupper((string)($vc['loai'] ?? ''));
  $gia_tri = (int)($vc['gia_tri'] ?? 0);
  $toi_da = (int)($vc['toi_da'] ?? 0);

  if ($loai === 'TIEN') {
    $tien_giam = min(max(0, $gia_tri), $tong_tien);
  } elseif ($loai === 'PHAN_TRAM') {
    $pt = max(0, min(100, $gia_tri));
    $tien_giam = (int)floor($tong_tien * $pt / 100);
    if ($toi_da > 0) $tien_giam = min($tien_giam, $toi_da);
    $tien_giam = min($tien_giam, $tong_tien);
  }
}

$tong_thanh_toan = max(0, $tong_tien - $tien_giam);

// Tạo mã đơn
$ma_don_hang = 'DH' . date('YmdHis') . rand(100, 999);

try {
  $pdo->beginTransaction();

  // ================== INSERT donhang (ĐÚNG THEO DB BẠN CHỤP) ==================
  // donhang có các cột: id_nguoi_dung, ma_don_hang, tong_tien, tien_giam, tong_thanh_toan,
  // trang_thai, phuong_thuc, ngay_dat, ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan,
  // ma_voucher, giam_gia, trang_thai_thanh_toan, da_tru_ton, ngay_cap_nhat...
  $st = $pdo->prepare("
    INSERT INTO donhang
      (id_nguoi_dung, ma_don_hang, tong_tien, tien_giam, tong_thanh_toan,
       trang_thai, phuong_thuc, ngay_dat,
       ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan,
       ma_voucher, giam_gia, trang_thai_thanh_toan, da_tru_ton)
    VALUES
      (?, ?, ?, ?, ?,
       ?, ?, NOW(),
       ?, ?, ?,
       ?, ?, 'Chưa thanh toán', 0)
  ");
  $st->execute([
    $uid,
    $ma_don_hang,
    $tong_tien,
    $tien_giam,
    $tong_thanh_toan,
    'CHO_XAC_NHAN_EMAIL',
    $pttt,
    $ho_ten,
    $sdt,
    $dia_chi,
    $ma_voucher,
    $tien_giam
  ]);

  $id_don = (int)$pdo->lastInsertId();

  // ================== INSERT chitiet_donhang (BỎ created_at) ==================
  $stCt = $pdo->prepare("
    INSERT INTO chitiet_donhang
      (id_don_hang, id_san_pham, ten_san_pham, size, so_luong, don_gia, thanh_tien)
    VALUES
      (?, ?, ?, ?, ?, ?, ?)
  ");

  foreach ($cart as $sp) {
    $idsp = (int)($sp['id'] ?? 0);
    $qty  = max(1, (int)($sp['qty'] ?? 1));
    $dg   = (int)($sp['don_gia'] ?? ($sp['gia'] ?? 0));
    $size = trim((string)($sp['size'] ?? ''));
    $ten_sp = trim((string)($sp['ten'] ?? $sp['ten_san_pham'] ?? ''));

    if ($idsp <= 0) continue;
    if ($ten_sp === '') $ten_sp = 'Sản phẩm #' . $idsp; // tránh NULL

    $tt = $dg * $qty;

    $stCt->execute([
      $id_don,
      $idsp,
      $ten_sp,
      ($size !== '' ? $size : null),
      $qty,
      $dg,
      $tt
    ]);
  }

  // ================== OTP lưu vào nguoidung ==================
  $otp = otp_generate_6();
  otp_save_for_user($pdo, $uid, $otp, 600);

  $pdo->commit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  flash_set('err', 'Lỗi DB: ' . $e->getMessage());
  redirect(base_url('trang_nguoi_dung/thanh_toan.php'));
  exit;
}

// ================== Gửi OTP qua email ==================
$subject = "Mã OTP xác nhận đơn hàng {$ma_don_hang}";
$html = "
  <div style='font-family:Arial,sans-serif;font-size:14px;line-height:1.6'>
    <h2 style='margin:0 0 10px'>Xác nhận đơn hàng</h2>
    <p>Vui lòng nhập mã OTP sau để xác nhận đơn hàng <b>{$ma_don_hang}</b>:</p>
    <div style='font-size:30px;font-weight:800;letter-spacing:6px;margin:14px 0'>{$otp}</div>
    <p>Mã có hiệu lực trong <b>10 phút</b>.</p>
  </div>
";

$sent = send_email($email, $subject, $html);
if (!$sent) {
  flash_set('err', 'Tạo đơn thành công nhưng gửi OTP thất bại. ' . ($GLOBALS['MAIL_LAST_ERROR'] ?? ''));
}

// LUÔN chuyển sang trang nhập OTP
redirect(base_url('trang_nguoi_dung/xac_nhan_dat_hang.php?don=' . $id_don));
exit;
