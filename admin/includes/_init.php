<?php
// admin/includes/_init.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../cau_hinh/ket_noi.php'; // $pdo

/* ================= Helpers ================= */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $pdo, string $name): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE ?");
  $st->execute([$name]);
  return (bool)$st->fetchColumn();
}

function getCols(PDO $pdo, string $table): array {
  $st = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
  ");
  $st->execute([$table]);
  return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function pickCol(array $cols, array $cands): ?string {
  foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
  return null;
}

function money_vnd($n): string {
  $n = (int)$n;
  return number_format($n, 0, ',', '.') . ' ₫';
}

/* ================= Auth state ================= */
$me = $_SESSION['admin'] ?? null;
$AUTH_OK = is_array($me) && (!empty($me['id']) || !empty($me['id_admin']));
$vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? 'NHAN_VIEN')));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Permission map (có thể mở rộng sau) =================
  - ADMIN: full
  - QUAN_LY: gần full (trừ Cài đặt hệ thống nếu muốn)
  - KE_TOAN: đơn hàng, voucher, báo cáo, nhật ký (xem)
  - KHO: sản phẩm, tồn kho, đơn hàng (xem)
  - CSKH: đơn hàng, khách hàng
  - NHAN_VIEN: sản phẩm, đơn hàng, khách hàng
*/
function can(string $key): bool {
  global $isAdmin, $vaiTro;
  if ($isAdmin) return true;

  $map = [
    'QUAN_LY'   => ['tong_quan','sanpham','theodoi_gia','donhang','khachhang','tonkho','voucher','baocao','nhatky'],
    'KE_TOAN'   => ['tong_quan','donhang','voucher','baocao','nhatky'],
    'KHO'       => ['tong_quan','sanpham','donhang','tonkho'],
    'CSKH'      => ['tong_quan','donhang','khachhang'],
    'NHAN_VIEN' => ['tong_quan','sanpham','donhang','khachhang'],
  ];
  $allow = $map[$vaiTro] ?? ['tong_quan'];
  return in_array($key, $allow, true);
}

function requirePermission(string $key): void {
  if (!can($key)) {
    http_response_code(403);
    echo "<div style='padding:24px;font-family:system-ui'>403 - Bạn không có quyền truy cập.</div>";
    exit;
  }
}

/* ================= Activity log ================= */
function log_action(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, $id_ban_ghi=null, $payload=null): void {
  if (!tableExists($pdo, 'nhatky_hoatdong')) return;

  $me = $_SESSION['admin'] ?? [];
  $id_admin = $me['id_admin'] ?? ($me['id'] ?? null);
  $vai_tro = strtoupper(trim((string)($me['vai_tro'] ?? 'NHAN_VIEN')));

  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

  $json = null;
  if ($payload !== null) {
    $json = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
  }

  $st = $pdo->prepare("
    INSERT INTO nhatky_hoatdong (id_admin, vai_tro, hanh_dong, mo_ta, bang_lien_quan, id_ban_ghi, du_lieu_json, ip, user_agent, ngay_tao)
    VALUES (:id_admin, :vai_tro, :hanh_dong, :mo_ta, :bang, :id_ban_ghi, :json, :ip, :ua, NOW())
  ");
  $st->execute([
    ':id_admin'   => $id_admin,
    ':vai_tro'    => $vai_tro,
    ':hanh_dong'  => $hanh_dong,
    ':mo_ta'      => $mo_ta,
    ':bang'       => $bang,
    ':id_ban_ghi' => $id_ban_ghi,
    ':json'       => $json,
    ':ip'         => $ip,
    ':ua'         => $ua,
  ]);
}
