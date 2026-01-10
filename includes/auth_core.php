<?php
declare(strict_types=1);

/**
 * includes/auth_core.php
 * Core helpers: base_url / redirect / flash / csrf / auth / otp / email wrapper
 * - KHÔNG require mailer ở đầu file để tránh Fatal nếu thiếu.
 * - OTP dùng: nguoidung.verify_token_hash + verify_token_expires (không thêm bảng/cột)
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');
  session_start();
}

/* ================== DB ================== */
require_once __DIR__ . '/../cau_hinh/ket_noi.php'; // $pdo

/* ================== BASE URL ================== */
if (!defined('BASE_URL')) {
  define('BASE_URL', 'http://localhost/webnhom77');
}

function base_url(string $path = ''): string {
  $base = rtrim((string)BASE_URL, '/');
  $path = ltrim($path, '/');
  return $path === '' ? $base . '/' : $base . '/' . $path;
}

function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool {
  return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/* ================== FLASH ================== */
function flash_set(string $key, string $msg): void {
  $_SESSION['_flash'][$key] = $msg;
}
function flash_get(string $key): ?string {
  if (!isset($_SESSION['_flash'][$key])) return null;
  $v = (string)$_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);
  return $v;
}

/* ================== CSRF ================== */
function csrf_token(): string {
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION['_csrf'];
}
function csrf_check(string $token): bool {
  if (empty($_SESSION['_csrf'])) return false;
  return hash_equals((string)$_SESSION['_csrf'], (string)$token);
}

/* ================== PHONE ================== */
function normalize_phone(string $s): string {
  $s = preg_replace('/\D+/', '', $s ?? '');
  return $s ? (string)$s : '';
}

/* ================== SAFE REDIRECT ================== */
function safe_redirect_target(string $url): string {
  $url = trim($url);
  if ($url === '') return base_url('trang_nguoi_dung/trang_chu.php');

  if (str_starts_with($url, '/')) {
    if (str_starts_with($url, '/webnhom77')) return 'http://localhost' . $url;
    return base_url('trang_nguoi_dung/trang_chu.php');
  }

  if (preg_match('~^https?://~i', $url)) {
    $parts = parse_url($url);
    $host  = $parts['host'] ?? '';
    $path  = $parts['path'] ?? '';
    if ($host === 'localhost' && str_starts_with($path, '/webnhom77')) return $url;
    return base_url('trang_nguoi_dung/trang_chu.php');
  }

  return base_url($url);
}

/* ================== AUTH ================== */
function auth_user(): ?array {
  return $_SESSION['nguoi_dung'] ?? null;
}

function require_login(): void {
  if (empty($_SESSION['nguoi_dung'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? base_url('trang_nguoi_dung/trang_chu.php');
    redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
  }
}

function auth_logout(): void {
  unset($_SESSION['nguoi_dung']);
}

function auth_login(array $u): void {
  $id = (int)($u['id_nguoi_dung'] ?? ($u['id'] ?? 0));

  $_SESSION['nguoi_dung'] = [
    'id'            => $id, // tương thích header.php
    'id_nguoi_dung' => $id,

    'ten_dang_nhap' => (string)($u['ten_dang_nhap'] ?? ''),
    'email'         => (string)($u['email'] ?? ''),
    'ho_ten'        => (string)($u['ho_ten'] ?? ''),
    'so_dien_thoai' => (string)($u['so_dien_thoai'] ?? ''),
    'dia_chi'       => (string)($u['dia_chi'] ?? ''),
    'avatar'        => (string)($u['avatar'] ?? 'default.png'),
    'vai_tro'       => (string)($u['vai_tro'] ?? ''),
    'is_active'     => (int)($u['is_active'] ?? 1),
    'email_verified_at' => $u['email_verified_at'] ?? null,
  ];
}

/* ================== USER FETCH ================== */
function find_user_by_login(PDO $pdo, string $login): ?array {
  $login = trim($login);
  if ($login === '') return null;

  $st = $pdo->prepare("
    SELECT *
    FROM nguoidung
    WHERE email = ? OR ten_dang_nhap = ?
    LIMIT 1
  ");
  $st->execute([$login, $login]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  return $u ?: null;
}

function verify_password_row(array $u, string $plain): bool {
  $plain = (string)$plain;

  $hash = (string)($u['pass_hash'] ?? '');
  if ($hash !== '' && password_verify($plain, $hash)) return true;

  $hash2 = (string)($u['mat_khau'] ?? '');
  if ($hash2 !== '' && password_verify($plain, $hash2)) return true;

  return false;
}

/* ================== PASSWORD POLICY ================== */
function password_policy_check(string $pw, ?string &$msg = null): bool {
  $pw = (string)$pw;
  if (strlen($pw) < 8) { $msg = 'Mật khẩu tối thiểu 8 ký tự.'; return false; }
  if (!preg_match('/[A-Z]/', $pw)) { $msg = 'Mật khẩu cần ít nhất 1 chữ hoa.'; return false; }
  if (!preg_match('/[a-z]/', $pw)) { $msg = 'Mật khẩu cần ít nhất 1 chữ thường.'; return false; }
  if (!preg_match('/\d/', $pw))    { $msg = 'Mật khẩu cần ít nhất 1 chữ số.'; return false; }
  $msg = null;
  return true;
}

/* ================== OTP ==================
 * Using: nguoidung.verify_token_hash + verify_token_expires
 */
function otp_generate_6(): string {
  return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otp_save_for_user(PDO $pdo, int $uid, string $otp, int $ttl_seconds = 600): void {
  $uid = (int)$uid;
  if ($uid <= 0) return;

  $hash = hash('sha256', $otp);
  $ttl  = max(60, (int)$ttl_seconds);
  $mins = (int)ceil($ttl / 60);

  $st = $pdo->prepare("
    UPDATE nguoidung
    SET verify_token_hash = ?,
        verify_token_expires = DATE_ADD(NOW(), INTERVAL ? MINUTE)
    WHERE id_nguoi_dung = ?
    LIMIT 1
  ");
  $st->execute([$hash, $mins, $uid]);
}

function otp_verify_for_user(PDO $pdo, int $uid, string $otp): bool {
  $uid = (int)$uid;
  if ($uid <= 0) return false;

  $otp = trim($otp);
  if (!preg_match('/^\d{6}$/', $otp)) return false;

  $st = $pdo->prepare("
    SELECT verify_token_hash, verify_token_expires
    FROM nguoidung
    WHERE id_nguoi_dung = ?
    LIMIT 1
  ");
  $st->execute([$uid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) return false;

  $hash_db = (string)($row['verify_token_hash'] ?? '');
  $exp     = (string)($row['verify_token_expires'] ?? '');

  if ($hash_db === '' || $exp === '') return false;
  if (strtotime($exp) < time()) return false;

  $hash = hash('sha256', $otp);
  return hash_equals($hash_db, $hash);
}

function otp_clear_for_user(PDO $pdo, int $uid): void {
  $uid = (int)$uid;
  if ($uid <= 0) return;

  $st = $pdo->prepare("
    UPDATE nguoidung
    SET verify_token_hash = NULL,
        verify_token_expires = NULL
    WHERE id_nguoi_dung = ?
    LIMIT 1
  ");
  $st->execute([$uid]);
}

/* ================== EMAIL WRAPPER ==================
 * Không require mailer khi load auth_core.
 * Chỉ require khi gọi send_email.
 */
$GLOBALS['MAIL_LAST_ERROR'] = '';

function send_email(string $to, string $subject, string $html): bool {
  $to = trim($to);
  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $GLOBALS['MAIL_LAST_ERROR'] = 'Email người nhận không hợp lệ.';
    return false;
  }

  // Ưu tiên mailer_smtp.php
  $smtp = __DIR__ . '/mailer_smtp.php';
  if (is_file($smtp)) {
    require_once $smtp;
    if (function_exists('send_mail_smtp')) {
      try {
        $ok = (bool)send_mail_smtp($to, $subject, $html);
        if (!$ok) $GLOBALS['MAIL_LAST_ERROR'] = 'send_mail_smtp() trả về false.';
        return $ok;
      } catch (Throwable $e) {
        $GLOBALS['MAIL_LAST_ERROR'] = $e->getMessage();
        return false;
      }
    }
  }

  // Fallback mailer.php (nếu bạn có)
  $mailer = __DIR__ . '/mailer.php';
  if (is_file($mailer)) {
    require_once $mailer;
    if (function_exists('send_mail')) {
      try {
        $ok = (bool)send_mail($to, $subject, $html);
        if (!$ok) $GLOBALS['MAIL_LAST_ERROR'] = 'send_mail() trả về false.';
        return $ok;
      } catch (Throwable $e) {
        $GLOBALS['MAIL_LAST_ERROR'] = $e->getMessage();
        return false;
      }
    }
  }

  $GLOBALS['MAIL_LAST_ERROR'] = 'Không tìm thấy mailer_smtp.php hoặc mailer.php trong /includes.';
  return false;
}
