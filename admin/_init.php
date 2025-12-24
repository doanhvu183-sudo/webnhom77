<?php
// admin/_init.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE ?");
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $st->execute([$table, $col]);
  return (int)$st->fetchColumn() > 0;
}
function pick_col(PDO $pdo, string $table, array $cands): ?string {
  foreach ($cands as $c) if (col_exists($pdo, $table, $c)) return $c;
  return null;
}

function ensure_tables(PDO $pdo): void {
  if (!table_exists($pdo, 'cai_dat')) {
    $pdo->exec("CREATE TABLE cai_dat (
      khoa VARCHAR(120) PRIMARY KEY,
      gia_tri TEXT NULL,
      mo_ta VARCHAR(255) NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  if (!table_exists($pdo, 'nhat_ky_hoat_dong')) {
    $pdo->exec("CREATE TABLE nhat_ky_hoat_dong (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      actor_id INT NULL,
      actor_email VARCHAR(255) NULL,
      actor_name VARCHAR(255) NULL,
      actor_role VARCHAR(50) NULL,
      hanh_dong VARCHAR(80) NOT NULL,
      doi_tuong VARCHAR(60) NULL,
      doi_tuong_id INT NULL,
      chi_tiet TEXT NULL,
      ip VARCHAR(45) NULL,
      user_agent VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (actor_id),
      INDEX (hanh_dong),
      INDEX (doi_tuong, doi_tuong_id),
      INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
  if (!table_exists($pdo, 'theo_doi_gia')) {
    $pdo->exec("CREATE TABLE theo_doi_gia (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      id_san_pham INT NOT NULL,
      gia_cu INT NULL,
      gia_moi INT NULL,
      gia_sale_cu INT NULL,
      gia_sale_moi INT NULL,
      id_admin INT NULL,
      ghi_chu VARCHAR(255) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (id_san_pham),
      INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}

function get_setting(PDO $pdo, string $key, $default=null) {
  try {
    $st = $pdo->prepare("SELECT gia_tri FROM cai_dat WHERE khoa=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v === false || $v === null || $v === '') ? $default : $v;
  } catch (Throwable $e) {
    return $default;
  }
}
function set_setting(PDO $pdo, string $key, $value, string $desc=null): void {
  $st = $pdo->prepare("
    INSERT INTO cai_dat (khoa, gia_tri, mo_ta)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri), mo_ta=COALESCE(VALUES(mo_ta), mo_ta)
  ");
  $st->execute([$key, (string)$value, $desc]);
}

function log_activity(PDO $pdo, array $admin, string $action, ?string $entity=null, ?int $entityId=null, $detail=null): void {
  try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $detailText = $detail === null ? null : (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE));

    $st = $pdo->prepare("
      INSERT INTO nhat_ky_hoat_dong
      (actor_id, actor_email, actor_name, actor_role, hanh_dong, doi_tuong, doi_tuong_id, chi_tiet, ip, user_agent)
      VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $st->execute([
      $admin['id'] ?? null,
      $admin['email'] ?? null,
      $admin['ho_ten'] ?? null,
      $admin['vai_tro'] ?? null,
      $action,
      $entity,
      $entityId,
      $detailText,
      $ip,
      $ua
    ]);
  } catch (Throwable $e) {
    // không die để tránh phá trang
  }
}

function record_price_change(PDO $pdo, int $idSP, ?int $oldGia, ?int $newGia, ?int $oldSale, ?int $newSale, ?int $adminId=null, ?string $note=null): void {
  try {
    $st = $pdo->prepare("
      INSERT INTO theo_doi_gia (id_san_pham, gia_cu, gia_moi, gia_sale_cu, gia_sale_moi, id_admin, ghi_chu)
      VALUES (?,?,?,?,?,?,?)
    ");
    $st->execute([$idSP, $oldGia, $newGia, $oldSale, $newSale, $adminId, $note]);
  } catch (Throwable $e) {}
}

/* ===== ensure base tables ===== */
ensure_tables($pdo);

/* ================== AUTH (admin pages only) ================== */
if (!isset($_SESSION['admin'])) {
  header("Location: dangnhap.php");
  exit;
}
$ADMIN = $_SESSION['admin'];

$ROLE = strtoupper((string)($ADMIN['vai_tro'] ?? 'ADMIN'));
$IS_ADMIN = (strpos($ROLE, 'ADMIN') !== false) || in_array($ROLE, ['ROOT','SUPERADMIN','SUPER_ADMIN'], true);

/* ================== COMMON COLS for dashboard ================== */
$DH_DATE  = pick_col($pdo, 'donhang', ['ngay_dat','created_at','ngay_tao']);
$DH_TOTAL = pick_col($pdo, 'donhang', ['tong_thanh_toan','tong_tien','tong_cong']);
$DH_STT   = pick_col($pdo, 'donhang', ['trang_thai','status']);

$TK_QTY   = pick_col($pdo, 'tonkho', ['so_luong','ton','qty','quantity']);
