<?php
// admin/includes/hamChung.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ================= Helpers (safe declare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, string $name): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
  }
}

if (!function_exists('getCols')) {
  function getCols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
}

if (!function_exists('pickCol')) {
  function pickCol(array $cols, array $cands): ?string {
    foreach ($cands as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return null;
  }
}
if (!function_exists('money_vnd')) {
  function money_vnd($amount): string {
    if ($amount === null || $amount === '') return '0 ₫';
    // Ép về số an toàn (tránh chuỗi có ký tự)
    $n = (int)preg_replace('/[^\d\-]/', '', (string)$amount);
    return number_format($n, 0, ',', '.') . ' ₫';
  }
}

/* ================= Permission ================= */
if (!function_exists('requirePermission')) {
  function requirePermission(string $permKey): void {
    if (empty($_SESSION['admin'])) {
      header("Location: dang_nhap.php");
      exit;
    }

    $me   = $_SESSION['admin'];
    $role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'NHANVIEN')));
    $dept = strtoupper(trim((string)($me['bo_phan'] ?? $me['phong_ban'] ?? '')));

    if ($role === 'ADMIN') return;

    $allowBase = ['tong_quan','sanpham','theodoi_gia','donhang','khachhang','tonkho','voucher'];
    $allowKetoan = ['baocao','nhatky'];

    if (in_array($permKey, $allowBase, true)) return;
    if (in_array($permKey, $allowKetoan, true) && ($role === 'KETOAN' || $dept === 'KETOAN')) return;

    header("Location: tong_quan.php?type=error&msg=" . urlencode("Bạn không có quyền truy cập"));
    exit;
  }
}
