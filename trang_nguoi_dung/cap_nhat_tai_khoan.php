<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$uSess = $_SESSION['nguoi_dung'] ?? [];
$uid   = (int)($uSess['id_nguoi_dung'] ?? ($uSess['id'] ?? 0));
if ($uid <= 0) {
  auth_logout();
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
}

if (!is_post()) {
  redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
  exit;
}

if (!csrf_check($_POST['_csrf'] ?? '')) {
  flash_set('err', 'Phiên làm việc không hợp lệ, vui lòng thử lại.');
  redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
  exit;
}

// input
$ho_ten        = trim((string)($_POST['ho_ten'] ?? ''));
$so_dien_thoai = trim((string)($_POST['so_dien_thoai'] ?? ''));
$dia_chi       = trim((string)($_POST['dia_chi'] ?? ''));
$gioi_tinh     = (string)($_POST['gioi_tinh'] ?? 'khac');
$ngay_sinh     = trim((string)($_POST['ngay_sinh'] ?? ''));

if ($ho_ten === '' || $dia_chi === '') {
  flash_set('err', 'Vui lòng nhập đầy đủ Họ tên và Địa chỉ.');
  redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
  exit;
}
if (!in_array($gioi_tinh, ['nam','nu','khac'], true)) $gioi_tinh = 'khac';
if ($ngay_sinh !== '' && !DateTime::createFromFormat('Y-m-d', $ngay_sinh)) {
  flash_set('err', 'Ngày sinh không hợp lệ.');
  redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
  exit;
}

// xử lý avatar (nếu có)
$newAvatar = null;
if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
  $err = (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_OK);

  if ($err !== UPLOAD_ERR_OK) {
    $map = [
      UPLOAD_ERR_INI_SIZE   => 'File vượt giới hạn upload_max_filesize trong php.ini.',
      UPLOAD_ERR_FORM_SIZE  => 'File vượt giới hạn form.',
      UPLOAD_ERR_PARTIAL    => 'File upload chưa hoàn tất.',
      UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm upload.',
      UPLOAD_ERR_CANT_WRITE => 'Không ghi được file lên ổ đĩa.',
      UPLOAD_ERR_EXTENSION  => 'Upload bị chặn bởi extension.',
    ];
    flash_set('err', $map[$err] ?? ('Upload avatar lỗi mã: '.$err));
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }

  // giới hạn 2MB
  $size = (int)($_FILES['avatar']['size'] ?? 0);
  if ($size <= 0 || $size > 2 * 1024 * 1024) {
    flash_set('err', 'Upload avatar thất bại: dung lượng tối đa 2MB.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }

  $tmp = (string)($_FILES['avatar']['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    flash_set('err', 'Upload avatar thất bại: file tạm không hợp lệ.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }

  // kiểm tra ảnh thật bằng getimagesize
  $info = @getimagesize($tmp);
  if (!$info || empty($info['mime'])) {
    flash_set('err', 'Upload avatar thất bại: file không phải hình ảnh hợp lệ.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }

  $mime = strtolower($info['mime']);
  $extMap = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/pjpeg'=> 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
  ];
  if (!isset($extMap[$mime])) {
    flash_set('err', 'Upload avatar thất bại: chỉ nhận JPG/PNG/WEBP.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }
  $ext = $extMap[$mime];

  // thư mục lưu
  $dir = realpath(__DIR__ . '/../assets') ?: (__DIR__ . '/../assets');
  $uploadDir = $dir . DIRECTORY_SEPARATOR . 'avatar';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
  }
  if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    flash_set('err', 'Upload avatar thất bại: thư mục assets/avatar không ghi được.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }

  $newAvatar = 'avatar_'.$uid.'_'.time().'.'.$ext;
  $dest = $uploadDir . DIRECTORY_SEPARATOR . $newAvatar;

  if (!move_uploaded_file($tmp, $dest)) {
    flash_set('err', 'Upload avatar thất bại: không thể lưu file.');
    redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile'));
    exit;
  }
}

// update DB
$sql = "UPDATE nguoidung
        SET ho_ten=?, so_dien_thoai=?, dia_chi=?, gioi_tinh=?, ngay_sinh=?, updated_at=NOW()";

$params = [$ho_ten, $so_dien_thoai, $dia_chi, $gioi_tinh, ($ngay_sinh ?: null)];

if ($newAvatar) {
  $sql .= ", avatar=?";
  $params[] = $newAvatar;
}

$sql .= " WHERE id_nguoi_dung=?";
$params[] = $uid;

$st = $pdo->prepare($sql);
$st->execute($params);

// cập nhật session để header hiển thị ngay
$_SESSION['nguoi_dung']['ho_ten'] = $ho_ten;
$_SESSION['nguoi_dung']['so_dien_thoai'] = $so_dien_thoai;
$_SESSION['nguoi_dung']['dia_chi'] = $dia_chi;
$_SESSION['nguoi_dung']['gioi_tinh'] = $gioi_tinh;
$_SESSION['nguoi_dung']['ngay_sinh'] = ($ngay_sinh ?: null);
if ($newAvatar) $_SESSION['nguoi_dung']['avatar'] = $newAvatar;

flash_set('ok', 'Cập nhật thành công.');
redirect(base_url('trang_nguoi_dung/tai_khoan.php?tab=profile&updated=1'));
