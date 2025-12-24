<?php
// admin/includes/helpers.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ========= BASIC ========= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n){
    $n = (float)$n;
    return number_format($n, 0, ',', '.') . ' ₫';
  }
}

/* ========= DB SCHEMA HELPERS ========= */
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, string $name): bool {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('getCols')) {
  function getCols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
  }
}
if (!function_exists('pickCol')) {
  function pickCol(array $cols, array $cands){
    foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; }
    return null;
  }
}

/* ========= AUTH / ROLE ========= */
if (!function_exists('auth_me')) {
  function auth_me(): array {
    $me = $_SESSION['admin'] ?? null;
    $id = 0;
    $vaiTro = 'NHAN_VIEN';
    if (is_array($me)) {
      $id = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_nguoi_dung'] ?? 0);
      $vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? 'NHAN_VIEN')));
    }
    $isAdmin = ($vaiTro === 'ADMIN');
    return [$me, $id, $vaiTro, $isAdmin];
  }
}

if (!function_exists('role_allow')) {
  function role_allow(string $role): array {
    $role = strtoupper(trim($role));
    // key module theo $ACTIVE
    return match($role){
      'ADMIN'   => ['*'],
      'KE_TOAN' => ['tong_quan','donhang','baocao','nhatky','khachhang'],
      'KHO'     => ['tong_quan','sanpham','tonkho','theodoi_gia','nhatky'],
      'CSKH'    => ['tong_quan','donhang','khachhang','nhatky'],
      default   => ['tong_quan','sanpham','donhang','khachhang','tonkho','nhatky'],
    };
  }
}

if (!function_exists('canAccess')) {
  function canAccess(string $module): bool {
    [$me,$id,$role,$isAdmin] = auth_me();
    if (!$me) return false;
    $allow = role_allow($role);
    return in_array('*',$allow,true) || in_array($module,$allow,true);
  }
}

if (!function_exists('requirePermission')) {
  function requirePermission(string $module): void {
    if (!canAccess($module)) {
      http_response_code(403);
      echo '<div style="padding:24px;font-family:Arial">
        <h2>403 - Không có quyền</h2>
        <div>Tài khoản của bạn không được phép vào mục này.</div>
        <div style="margin-top:12px"><a href="tong_quan.php">Về Tổng quan</a></div>
      </div>';
      exit;
    }
  }
}

/* ========= REDIRECT ========= */
if (!function_exists('redirectWith')) {
  function redirectWith($to = null, $flash = []) {
    // Cho phép gọi redirectWith([flash]) => tự dùng HTTP_REFERER hoặc trang mặc định
    if (is_array($to)) {
      $flash = $to;
      $to = null;
    }

    // Nếu $flash bị truyền nhầm kiểu
    if (!is_array($flash)) $flash = [];

    // Mặc định quay lại trang trước hoặc về file hiện tại
    if (!$to || !is_string($to) || trim($to) === '') {
      $to = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    }

    // Lưu flash
    if (!empty($flash)) {
      // ưu tiên 1 key thống nhất
      $_SESSION['_flash'] = $flash;
    }

    // Redirect an toàn
    if (!headers_sent()) {
      header("Location: " . $to);
      exit;
    }

    // Nếu lỡ có output trước đó, fallback JS
    echo "<script>location.href=" . json_encode($to) . ";</script>";
    exit;
  }
}


/* ========= SETTINGS ========= */
if (!function_exists('get_setting')) {
  function get_setting(PDO $pdo, string $key, $default=null){
    if (!tableExists($pdo,'cai_dat')) return $default;
    $cols = getCols($pdo,'cai_dat');
    $K = pickCol($cols,['khoa','key','setting_key','ten']);
    $V = pickCol($cols,['gia_tri','value','setting_value','noi_dung']);
    if(!$K || !$V) return $default;
    $st = $pdo->prepare("SELECT {$V} FROM cai_dat WHERE {$K}=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v===false || $v===null || $v==='') ? $default : $v;
  }
}

/* ========= ACTIVITY LOG: nhatky_hoatdong ========= */
if (!function_exists('nhatky_log')) {
  function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, ?int $id_ban_ghi=null, array $data=[]): void {
    if (!tableExists($pdo,'nhatky_hoatdong')) return;

    static $map = null;
    if ($map === null) {
      $cols = getCols($pdo,'nhatky_hoatdong');
      $map = [
        'id_admin'       => pickCol($cols,['id_admin','actor_id','user_id']),
        'vai_tro'        => pickCol($cols,['vai_tro','actor_role','role']),
        'hanh_dong'      => pickCol($cols,['hanh_dong','action']),
        'mo_ta'          => pickCol($cols,['mo_ta','mo_ta_chi_tiet','chi_tiet','description']),
        'bang_lien_quan' => pickCol($cols,['bang_lien_quan','doi_tuong','table']),
        'id_ban_ghi'     => pickCol($cols,['id_ban_ghi','doi_tuong_id','record_id']),
        'du_lieu_json'   => pickCol($cols,['du_lieu_json','data_json','chi_tiet_json']),
        'ip'             => pickCol($cols,['ip']),
        'user_agent'     => pickCol($cols,['user_agent','ua']),
        'ngay_tao'       => pickCol($cols,['ngay_tao','created_at']),
      ];
    }

/* ================= TONKHO AUTO APPLY ================= */

if (!function_exists('tonkho_apply_by_order')) {
  /**
   * $direction = -1 (trừ tồn) | +1 (hoàn tồn)
   * Trừ/hoàn tồn theo chitiet_donhang của 1 đơn.
   * - Tự tạo bản ghi tonkho nếu thiếu (so_luong=0)
   * - Có khóa FOR UPDATE để tránh race
   * - Không cho âm kho (nếu trừ mà không đủ -> rollback)
   */
  function tonkho_apply_by_order(PDO $pdo, int $id_don_hang, int $direction = -1): array {
    if ($id_don_hang <= 0) return ['ok'=>false,'msg'=>'Thiếu id_don_hang'];

    if (!tableExists($pdo,'tonkho')) return ['ok'=>false,'msg'=>'Thiếu bảng tonkho'];
    if (!tableExists($pdo,'chitiet_donhang')) return ['ok'=>false,'msg'=>'Thiếu bảng chitiet_donhang'];

    $tkCols = getCols($pdo,'tonkho');
    $ctCols = getCols($pdo,'chitiet_donhang');

    $TK_ID   = pickCol($tkCols, ['id_tonkho','id']);
    $TK_SPID = pickCol($tkCols, ['id_san_pham','sanpham_id','id_sp']);
    $TK_QTY  = pickCol($tkCols, ['so_luong','ton','qty','quantity']);
    $TK_UPD  = pickCol($tkCols, ['ngay_cap_nhat','updated_at']);

    $CT_IDDH = pickCol($ctCols, ['id_don_hang']);
    $CT_SPID = pickCol($ctCols, ['id_san_pham','sanpham_id','id_sp']);
    $CT_QTY  = pickCol($ctCols, ['so_luong','qty','quantity']);
    $CT_SIZE = pickCol($ctCols, ['size','kich_co']);

    $TK_SIZE = pickCol($tkCols, ['size','kich_co']); // nếu tồn kho có size

    if(!$TK_ID || !$TK_SPID || !$TK_QTY) return ['ok'=>false,'msg'=>'Bảng tonkho thiếu cột chính'];
    if(!$CT_IDDH || !$CT_SPID || !$CT_QTY) return ['ok'=>false,'msg'=>'Bảng chitiet_donhang thiếu cột chính'];

    // Nếu cả 2 bên đều có size -> trừ theo (id_san_pham, size). Nếu không -> trừ theo id_san_pham
    $useSize = ($TK_SIZE && $CT_SIZE);

    // Lấy item đơn hàng
    if ($useSize) {
      $sqlItems = "SELECT $CT_SPID AS id_san_pham, $CT_SIZE AS size, SUM($CT_QTY) AS qty
                  FROM chitiet_donhang
                  WHERE $CT_IDDH=?
                  GROUP BY $CT_SPID, $CT_SIZE";
    } else {
      $sqlItems = "SELECT $CT_SPID AS id_san_pham, SUM($CT_QTY) AS qty
                  FROM chitiet_donhang
                  WHERE $CT_IDDH=?
                  GROUP BY $CT_SPID";
    }

    $st = $pdo->prepare($sqlItems);
    $st->execute([$id_don_hang]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) return ['ok'=>false,'msg'=>'Đơn hàng không có chi tiết để trừ tồn'];

    // Begin transaction
    if (!$pdo->inTransaction()) $pdo->beginTransaction();

    try {
      // 1) Tạo bản ghi tồn kho nếu thiếu
      foreach ($items as $it) {
        $id_sp = (int)$it['id_san_pham'];
        if ($id_sp<=0) continue;

        if ($useSize) {
          $sz = (string)$it['size'];
          $ck = $pdo->prepare("SELECT $TK_ID FROM tonkho WHERE $TK_SPID=? AND $TK_SIZE=? LIMIT 1");
          $ck->execute([$id_sp,$sz]);
          $exists = $ck->fetchColumn();
          if (!$exists) {
            $ins = $TK_UPD
              ? "INSERT INTO tonkho($TK_SPID,$TK_SIZE,$TK_QTY,$TK_UPD) VALUES(?,?,0,NOW())"
              : "INSERT INTO tonkho($TK_SPID,$TK_SIZE,$TK_QTY) VALUES(?,?,0)";
            $pdo->prepare($ins)->execute([$id_sp,$sz]);
          }
        } else {
          $ck = $pdo->prepare("SELECT $TK_ID FROM tonkho WHERE $TK_SPID=? LIMIT 1");
          $ck->execute([$id_sp]);
          $exists = $ck->fetchColumn();
          if (!$exists) {
            $ins = $TK_UPD
              ? "INSERT INTO tonkho($TK_SPID,$TK_QTY,$TK_UPD) VALUES(?,0,NOW())"
              : "INSERT INTO tonkho($TK_SPID,$TK_QTY) VALUES(?,0)";
            $pdo->prepare($ins)->execute([$id_sp]);
          }
        }
      }

      // 2) Lock + check đủ tồn (khi trừ)
      $changes = [];
      foreach ($items as $it) {
        $id_sp = (int)$it['id_san_pham'];
        $qty   = (int)$it['qty'];
        if ($qty<=0) continue;

        if ($useSize) {
          $sz = (string)$it['size'];
          $lock = $pdo->prepare("SELECT $TK_ID, $TK_QTY FROM tonkho WHERE $TK_SPID=? AND $TK_SIZE=? FOR UPDATE");
          $lock->execute([$id_sp,$sz]);
          $row = $lock->fetch(PDO::FETCH_ASSOC);
          if(!$row) throw new Exception("Không tìm thấy tonkho cho SP#$id_sp size=$sz");

          $cur = (int)$row[$TK_QTY];
          $new = $cur + ($direction * $qty);
          if ($direction < 0 && $new < 0) {
            throw new Exception("Không đủ tồn: SP#$id_sp size=$sz (tồn=$cur, cần=$qty)");
          }

          $upd = $TK_UPD
            ? "UPDATE tonkho SET $TK_QTY=?, $TK_UPD=NOW() WHERE $TK_ID=?"
            : "UPDATE tonkho SET $TK_QTY=? WHERE $TK_ID=?";
          $pdo->prepare($upd)->execute([$new,(int)$row[$TK_ID]]);

          $changes[] = ['id_san_pham'=>$id_sp,'size'=>$sz,'old'=>$cur,'delta'=>$direction*$qty,'new'=>$new];
        } else {
          $lock = $pdo->prepare("SELECT $TK_ID, $TK_QTY FROM tonkho WHERE $TK_SPID=? FOR UPDATE");
          $lock->execute([$id_sp]);
          $row = $lock->fetch(PDO::FETCH_ASSOC);
          if(!$row) throw new Exception("Không tìm thấy tonkho cho SP#$id_sp");

          $cur = (int)$row[$TK_QTY];
          $new = $cur + ($direction * $qty);
          if ($direction < 0 && $new < 0) {
            throw new Exception("Không đủ tồn: SP#$id_sp (tồn=$cur, cần=$qty)");
          }

          $upd = $TK_UPD
            ? "UPDATE tonkho SET $TK_QTY=?, $TK_UPD=NOW() WHERE $TK_ID=?"
            : "UPDATE tonkho SET $TK_QTY=? WHERE $TK_ID=?";
          $pdo->prepare($upd)->execute([$new,(int)$row[$TK_ID]]);

          $changes[] = ['id_san_pham'=>$id_sp,'old'=>$cur,'delta'=>$direction*$qty,'new'=>$new];
        }
      }

      // 3) Log
      $action = ($direction < 0) ? 'TRU_TON_KHO' : 'HOAN_TON_KHO';
      $desc   = ($direction < 0)
        ? "Tự động trừ tồn theo đơn #{$id_don_hang}"
        : "Tự động hoàn tồn theo đơn #{$id_don_hang}";

      nhatky_log($pdo, $action, $desc, 'donhang', $id_don_hang, ['changes'=>$changes]);

      if ($pdo->inTransaction()) $pdo->commit();
      return ['ok'=>true,'msg'=>'OK','changes'=>$changes];

    } catch(Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      // Log lỗi (nếu được)
      nhatky_log($pdo,'LOI_TON_KHO',"Lỗi trừ/hoàn tồn đơn #{$id_don_hang}: ".$e->getMessage(),'donhang',$id_don_hang,[]);
      return ['ok'=>false,'msg'=>$e->getMessage()];
    }
  }
}

    [$me,$myId,$vaiTro,$isAdmin] = auth_me();
    if (!$me) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $json = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    $fields = [];
    $vals   = [];
    $bind   = [];

    $put = function($k,$v) use (&$fields,&$vals,&$bind,$map){
      if (!empty($map[$k])) { $fields[] = $map[$k]; $vals[] = ":{$k}"; $bind[":{$k}"] = $v; }
    };

    $put('id_admin', $myId);
    $put('vai_tro', $vaiTro);
    $put('hanh_dong', $hanh_dong);
    $put('mo_ta', $mo_ta);
    $put('bang_lien_quan', $bang);
    $put('id_ban_ghi', $id_ban_ghi);
    $put('du_lieu_json', $json);
    $put('ip', $ip);
    $put('user_agent', $ua);

    // ngay_tao
    if (!empty($map['ngay_tao'])) { $fields[] = $map['ngay_tao']; $vals[] = "NOW()"; }

    if (!$fields) return;

    $sql = "INSERT INTO nhatky_hoatdong(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    try { $pdo->prepare($sql)->execute($bind); } catch(Throwable $e) {}
  }
  
}
