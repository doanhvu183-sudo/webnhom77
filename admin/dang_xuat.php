<?php
// admin/dang_xuat.php
session_start();

// cố gắng log trước khi huỷ session
$me = $_SESSION['admin'] ?? null;

try {
  require_once __DIR__ . '/../cau_hinh/ket_noi.php';

  // fallback helper tối thiểu để ghi log đúng bảng nhat_ky_hoat_dong
  if (!function_exists('tableExists')) {
    function tableExists(PDO $pdo, $name){
      $st=$pdo->prepare("SHOW TABLES LIKE ?");
      $st->execute([$name]);
      return (bool)$st->fetchColumn();
    }
  }
  if (!function_exists('getCols')) {
    function getCols(PDO $pdo, $table){
      $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?");
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

  if ($me && isset($pdo) && ($pdo instanceof PDO)) {
    $table = null;
    if (tableExists($pdo,'nhat_ky_hoat_dong')) $table='nhat_ky_hoat_dong';
    else if (tableExists($pdo,'nhatky_hoatdong')) $table='nhatky_hoatdong';

    if ($table) {
      $cols = getCols($pdo,$table);

      $C_IDADMIN   = pickCol($cols, ['id_admin','actor_id','admin_id','user_id']);
      $C_ROLE      = pickCol($cols, ['vai_tro','actor_role','role']);
      $C_ACTION    = pickCol($cols, ['hanh_dong','action','su_kien']);
      $C_DESC      = pickCol($cols, ['mo_ta','chi_tiet','description','noi_dung']);
      $C_TABLE     = pickCol($cols, ['bang_lien_quan','doi_tuong','table_name','bang']);
      $C_ROWID     = pickCol($cols, ['id_ban_ghi','doi_tuong_id','row_id','id_lien_quan']);
      $C_JSON      = pickCol($cols, ['du_lieu_json','chi_tiet_json','data_json','json']);
      $C_IP        = pickCol($cols, ['ip']);
      $C_UA        = pickCol($cols, ['user_agent']);
      $C_CREATED   = pickCol($cols, ['ngay_tao','created_at']);

      $idAdmin = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_nguoi_dung'] ?? 0);
      $role = (string)($me['vai_tro'] ?? $me['role'] ?? 'admin');
      $ip = $_SERVER['REMOTE_ADDR'] ?? '';
      $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

      $fields = [];
      $vals   = [];
      $bind   = [];

      $add = function($col,$ph,$val) use (&$fields,&$vals,&$bind){
        if(!$col) return;
        $fields[]=$col; $vals[]=$ph;
        if($ph!=='NOW()') $bind[$ph]=$val;
      };

      $add($C_IDADMIN, ':aid', $idAdmin ?: null);
      $add($C_ROLE,    ':role', $role ?: null);
      $add($C_ACTION,  ':act',  'DANG_XUAT');
      $add($C_DESC,    ':desc', 'Đăng xuất hệ thống');
      $add($C_TABLE,   ':tbl',  'admin');
      $add($C_ROWID,   ':rid',  $idAdmin ?: null);
      $add($C_JSON,    ':js',   null);
      $add($C_IP,      ':ip',   $ip);
      $add($C_UA,      ':ua',   $ua);
      if ($C_CREATED) { $fields[]=$C_CREATED; $vals[]='NOW()'; }

      if ($fields) {
        $sql = "INSERT INTO {$table} (".implode(',',$fields).") VALUES (".implode(',',$vals).")";
        $pdo->prepare($sql)->execute($bind);
      }
    }
  }
} catch (\Throwable $e) {
  // bỏ qua lỗi log để không chặn đăng xuất
}

// Huỷ session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"],
    $params["secure"], $params["httponly"]
  );
}
session_destroy();

// Redirect
header("Location: dang_nhap.php");
exit;
