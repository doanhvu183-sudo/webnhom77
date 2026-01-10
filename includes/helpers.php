<?php
// includes/helpers.php
require_once __DIR__ . '/session_boot.php';

if (!function_exists('app_config')) {
  function app_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $cfg = require __DIR__ . '/../cau_hinh/config.php';
    return $cfg;
  }
}

if (!function_exists('h')) {
  function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('base_url')) {
  function base_url(string $path = ''): string {
    $cfg = app_config();
    $base = rtrim($cfg['app']['base_url'] ?? 'http://localhost/webnhom77', '/');
    return $base . ($path ? '/' . ltrim($path, '/') : '');
  }
}

if (!function_exists('redirect')) {
  function redirect(string $to): never { header('Location: '.$to); exit; }
}

if (!function_exists('is_post')) {
  function is_post(): bool { return (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'); }
}

if (!function_exists('flash_set')) {
  function flash_set(string $k, string $v): void { $_SESSION['_flash'][$k] = $v; }
}
if (!function_exists('flash_get')) {
  function flash_get(string $k): ?string {
    $v = $_SESSION['_flash'][$k] ?? null;
    if ($v !== null) unset($_SESSION['_flash'][$k]);
    return $v;
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
  }
}
if (!function_exists('csrf_check')) {
  function csrf_check(?string $t): bool {
    return isset($_SESSION['_csrf']) && is_string($t) && hash_equals($_SESSION['_csrf'], $t);
  }
}

if (!function_exists('client_ip')) {
  function client_ip(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP']
      ?? $_SERVER['HTTP_X_FORWARDED_FOR']
      ?? $_SERVER['REMOTE_ADDR']
      ?? '0.0.0.0';
  }
}
if (!function_exists('user_agent')) {
  function user_agent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
  }
}

if (!function_exists('normalize_phone')) {
  function normalize_phone(string $s): string {
    $d = preg_replace('/\D+/', '', $s);
    return $d ?: '';
  }
}

if (!function_exists('safe_redirect_target')) {
  function safe_redirect_target(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return base_url('trang_nguoi_dung/trang_chu.php');
    if (str_starts_with($raw,'http://') || str_starts_with($raw,'https://') || str_starts_with($raw,'//')) {
      return base_url('trang_nguoi_dung/trang_chu.php');
    }
    return $raw;
  }
}

if (!function_exists('password_policy_check')) {
  // “Thoải mái cho khách”: không bắt buộc ký tự đặc biệt, nhưng vẫn an toàn.
  function password_policy_check(string $pw, ?string &$msg=null): bool {
    $len = strlen($pw);
    if ($len < 8 || $len > 64) { $msg='Mật khẩu cần 8–64 ký tự.'; return false; }
    if (!preg_match('/[a-z]/', $pw)) { $msg='Mật khẩu cần ít nhất 1 chữ thường.'; return false; }
    if (!preg_match('/[A-Z]/', $pw)) { $msg='Mật khẩu cần ít nhất 1 chữ hoa.'; return false; }
    if (!preg_match('/\d/', $pw))     { $msg='Mật khẩu cần ít nhất 1 số.'; return false; }
    return true;
  }
}
