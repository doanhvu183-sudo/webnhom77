<?php
// admin/includes/hamChung.php
declare(strict_types=1);

if (!function_exists('h')) {
  function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('money_vnd')) {
  function money_vnd($n): string {
    if ($n === null || $n === '') return '0 ₫';
    $x = (int)$n;
    return number_format($x, 0, ',', '.') . ' ₫';
  }
}

if (!function_exists('user_ip')) {
  function user_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '';
  }
}

if (!function_exists('user_ua')) {
  function user_ua(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
  }
}

if (!function_exists('flash_get')) {
  function flash_get(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
  }
}

if (!function_exists('flash_set')) {
  function flash_set(array $f): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['flash'] = $f;
  }
}

/**
 * redirectWith($flash, $to)
 * - $flash: ['type'=>'ok|error|info', 'msg'=>'...']
 * - $to: string URL (mặc định quay lại referer/current)
 */
if (!function_exists('redirectWith')) {
  function redirectWith(array $flash, ?string $to = null): void {
    flash_set($flash);
    if (!$to) {
      $to = $_SERVER['HTTP_REFERER'] ?? '';
      if (!$to) $to = $_SERVER['REQUEST_URI'] ?? 'tong_quan.php';
    }
    header('Location: ' . $to);
    exit;
  }
}

if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
  }
}

if (!function_exists('getCols')) {
  function getCols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
}

if (!function_exists('pickCol')) {
  function pickCol(array $cols, array $candidates): ?string {
    $set = array_flip($cols);
    foreach ($candidates as $c) {
      if (isset($set[$c])) return $c;
    }
    return null;
  }
}

if (!function_exists('auth_me')) {
  /**
   * Trả về: [$me, $myId, $vaiTro, $isAdmin]
   * - $me: mảng session admin
   */
  function auth_me(): array {
    $me = $_SESSION['admin'] ?? [];
    if (!is_array($me)) $me = [];
    $myId = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_user'] ?? 0);
    $vaiTro = (string)($me['vai_tro'] ?? $me['role'] ?? 'admin');
    if ($vaiTro !== 'admin' && $vaiTro !== 'nhanvien') {
      // fallback: nếu có 'is_admin'
      if (!empty($me['is_admin'])) $vaiTro = 'admin';
      else $vaiTro = 'admin';
    }
    $isAdmin = ($vaiTro === 'admin');
    return [$me, $myId, $vaiTro, $isAdmin];
  }
}

if (!function_exists('require_login_admin')) {
  function require_login_admin(): void {
    if (empty($_SESSION['admin'])) {
      header("Location: dang_nhap.php");
      exit;
    }
  }
}

if (!function_exists('requirePermission')) {
  /**
   * Quyền tối thiểu:
   * - Admin: luôn được
   * - Nhân viên: nếu có bảng admin_quyen (id_admin, chuc_nang, duoc_phep) thì check
   *            nếu không có bảng quyền -> mặc định CHẶN
   */
  function requirePermission(string $key, ?PDO $pdo = null): void {
    [$me, $myId, $vaiTro, $isAdmin] = auth_me();
    if ($isAdmin) return;

    if (!$pdo) {
      http_response_code(403);
      die("<div style='padding:16px;font-family:Arial'>Thiếu kết nối DB để kiểm tra quyền.</div>");
    }

    // ưu tiên bảng admin_quyen
    if (tableExists($pdo, 'admin_quyen')) {
      $cols = getCols($pdo, 'admin_quyen');
      $C_ID = pickCol($cols, ['id_admin','admin_id','id']);
      $C_KEY = pickCol($cols, ['chuc_nang','quyen','permission_key','key']);
      $C_OK = pickCol($cols, ['duoc_phep','allow','is_allow','truy_cap']);
      if ($C_ID && $C_KEY && $C_OK) {
        $st = $pdo->prepare("SELECT {$C_OK} FROM admin_quyen WHERE {$C_ID}=? AND {$C_KEY}=? LIMIT 1");
        $st->execute([$myId, $key]);
        $ok = (int)($st->fetchColumn() ?? 0);
        if ($ok === 1) return;
      }
    }

    http_response_code(403);
    die("<div class='p-6 bg-white rounded-2xl border' style='font-family:Arial'>
      <b>Không đủ quyền.</b><div style='margin-top:6px'>Nhân viên không được truy cập mục: <b>".h($key)."</b></div>
    </div>");
  }
}

if (!function_exists('nhatky_log')) {
  /**
   * Ghi log vào nhatky_hoatdong (đúng schema bạn gửi)
   */
  function nhatky_log(
    PDO $pdo,
    string $hanh_dong,
    string $mo_ta,
    ?string $bang_lien_quan = null,
    ?int $id_ban_ghi = null,
    $du_lieu_json = null
  ): void {
    if (!tableExists($pdo, 'nhatky_hoatdong')) return;

    [$me, $myId, $vaiTro, $isAdmin] = auth_me();

    $jsonStr = null;
    if ($du_lieu_json !== null) {
      $jsonStr = json_encode($du_lieu_json, JSON_UNESCAPED_UNICODE);
    }

    $sql = "INSERT INTO nhatky_hoatdong
      (id_admin, vai_tro, hanh_dong, mo_ta, bang_lien_quan, id_ban_ghi, du_lieu_json, ip, user_agent)
      VALUES
      (:id_admin, :vai_tro, :hanh_dong, :mo_ta, :bang, :id_ban_ghi, :json, :ip, :ua)";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':id_admin'   => ($myId > 0 ? $myId : null),
      ':vai_tro'    => ($vaiTro === 'nhanvien' ? 'nhanvien' : 'admin'),
      ':hanh_dong'  => $hanh_dong,
      ':mo_ta'      => mb_substr($mo_ta, 0, 255),
      ':bang'       => $bang_lien_quan,
      ':id_ban_ghi' => $id_ban_ghi,
      ':json'       => $jsonStr,
      ':ip'         => user_ip(),
      ':ua'         => user_ua(),
    ]);
  }
}
