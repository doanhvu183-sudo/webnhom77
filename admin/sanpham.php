<?php
ob_start();
session_start();

require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

// fallback nếu dự án dùng $conn
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) $pdo = $conn;

if (!($pdo instanceof PDO)) {
  die('Lỗi kết nối DB: $pdo không tồn tại. Kiểm tra ../cau_hinh/ket_noi.php');
}

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) {
  header("Location: dang_nhap.php");
  exit;
}
$me = $_SESSION['admin'];
$vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
$isAdmin = ($vaiTro === 'ADMIN');
$myId = (int)($me['id'] ?? $me['id_admin'] ?? $me['id_nguoi_dung'] ?? 0);

/* ================= Permission ================= */
if (function_exists('requirePermission')) requirePermission('sanpham');

/* ================= Helpers (guard) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n){ return number_format((float)($n ?? 0), 0, ',', '.') . ' ₫'; }
}
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, $name){
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('getCols')) {
  function getCols(PDO $pdo, $table){
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

/* ================= Image path ================= */
function img_src($img){
  $img = trim((string)$img);
  if ($img === '') return '';
  if (preg_match('~^https?://~i', $img)) return $img;
  if ($img[0] === '/') return $img;
  return "../assets/img/" . rawurlencode($img);
}

/* ================= Detect activity log table (optional) ================= */
function detect_log_table(PDO $pdo): array {
  $cands = ['nhatky_hoatdong','nhat_ky_hoat_dong','nhatky_admin','nhat_ky_admin','nhatky'];
  foreach($cands as $t){
    if (tableExists($pdo,$t)) return [$t, getCols($pdo,$t)];
  }
  return [null, []];
}
function log_event(PDO $pdo, string $action, string $desc, array $ctx=[]): void {
  static $cached = null;
  if ($cached === null) $cached = detect_log_table($pdo);
  [$t,$cols] = $cached;
  if(!$t) return;

  $C_ACTION = pickCol($cols, ['hanh_dong','action','tieu_de','noi_dung']);
  $C_DESC   = pickCol($cols, ['mo_ta','description','chi_tiet','noi_dung','ghi_chu']);
  $C_USER   = pickCol($cols, ['id_admin','id_nguoi_dung','id_user','nguoi_thuc_hien']);
  $C_ROLE   = pickCol($cols, ['vai_tro','role']);
  $C_TIME   = pickCol($cols, ['ngay_tao','created_at','thoi_gian','time']);

  $fields=[]; $vals=[]; $bind=[];
  if($C_ACTION){ $fields[]=$C_ACTION; $vals[]=':a'; $bind[':a']=$action; }
  if($C_DESC){   $fields[]=$C_DESC;   $vals[]=':d'; $bind[':d']=$desc; }
  if($C_USER && isset($ctx['user_id'])){ $fields[]=$C_USER; $vals[]=':u'; $bind[':u']=(int)$ctx['user_id']; }
  if($C_ROLE && isset($ctx['role'])){   $fields[]=$C_ROLE; $vals[]=':r'; $bind[':r']=(string)$ctx['role']; }
  if($C_TIME){   $fields[]=$C_TIME; $vals[]='NOW()'; }

  if(!$fields) return;

  $sql="INSERT INTO $t(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
  try { $pdo->prepare($sql)->execute($bind); } catch(Throwable $e) {}
}

/* ================= Detect price tracking table (optional) ================= */
function detect_price_table(PDO $pdo): array {
  $cands = ['theo_doi_gia','theodoi_gia','gia_log','price_log'];
  foreach($cands as $t){
    if (tableExists($pdo,$t)) return [$t, getCols($pdo,$t)];
  }
  return [null, []];
}
function price_log(PDO $pdo, int $idSp, $old, $new, int $userId, string $note=''): void {
  static $cached = null;
  if ($cached === null) $cached = detect_price_table($pdo);
  [$t,$cols] = $cached;
  if(!$t) return;

  $C_IDSP = pickCol($cols, ['id_san_pham','sanpham_id','id_sp']);
  $C_OLD  = pickCol($cols, ['gia_cu','old_price','gia_truoc']);
  $C_NEW  = pickCol($cols, ['gia_moi','new_price','gia_sau']);
  $C_USER = pickCol($cols, ['id_admin','id_nguoi_dung','id_user','nguoi_thuc_hien']);
  $C_NOTE = pickCol($cols, ['ghi_chu','note','mo_ta','description']);
  $C_TIME = pickCol($cols, ['thoi_gian','ngay_tao','created_at','time']);

  $fields=[]; $vals=[]; $bind=[];
  if($C_IDSP){ $fields[]=$C_IDSP; $vals[]=':sp'; $bind[':sp']=$idSp; }
  if($C_OLD){  $fields[]=$C_OLD;  $vals[]=':o';  $bind[':o']=$old; }
  if($C_NEW){  $fields[]=$C_NEW;  $vals[]=':n';  $bind[':n']=$new; }
  if($C_USER){ $fields[]=$C_USER; $vals[]=':u';  $bind[':u']=$userId; }
  if($C_NOTE){ $fields[]=$C_NOTE; $vals[]=':t';  $bind[':t']=$note; }
  if($C_TIME){ $fields[]=$C_TIME; $vals[]='NOW()'; }

  if(!$fields) return;
  $sql="INSERT INTO $t(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
  try { $pdo->prepare($sql)->execute($bind); } catch(Throwable $e) {}
}

/* ================= Redirect helper ================= */
function redirect_to($params=[]){
  $base = 'sanpham.php';
  header("Location: ".$base.($params?('?'.http_build_query($params)):'')); 
  exit;
}

/* ================= Upload image ================= */
function upload_image_if_any(string $field): ?string {
  if (empty($_FILES[$field]['name'])) return null;

  $f = $_FILES[$field];
  if ($f['error'] !== UPLOAD_ERR_OK) return null;

  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $allow = ['jpg','jpeg','png','webp'];
  if (!in_array($ext,$allow,true)) redirect_to(['type'=>'error','msg'=>'Ảnh chỉ hỗ trợ jpg/jpeg/png/webp.']);

  if ((int)$f['size'] > 5*1024*1024) redirect_to(['type'=>'error','msg'=>'Ảnh quá lớn (tối đa 5MB).']);

  $base = preg_replace('/[^a-zA-Z0-9_\-]/','_', pathinfo($f['name'], PATHINFO_FILENAME));
  $newName = $base.'_'.date('Ymd_His').'_'.rand(100,999).'.'.$ext;

  $destDir = __DIR__ . '/../assets/img';
  if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
  $dest = rtrim($destDir,'\\/').DIRECTORY_SEPARATOR.$newName;

  if (!move_uploaded_file($f['tmp_name'], $dest)) {
    redirect_to(['type'=>'error','msg'=>'Upload ảnh thất bại. Kiểm tra quyền thư mục assets/img']);
  }
  return $newName;
}

/* ================= Schema detect: sanpham + danhmuc ================= */
$fatalError = null;

if (!tableExists($pdo,'sanpham')) {
  $fatalError = "Thiếu bảng <b>sanpham</b>.";
}

$SP_ID=$SP_NAME=$SP_PRICE=$SP_GOC=$SP_KM=$SP_IMG=$SP_DESC=$SP_QTY=$SP_ACTIVE=$SP_CREATED=$SP_UPDATED=$SP_CAT_ID=$SP_CAT_TX=null;
$dmOk=false; $DM_ID=$DM_NAME=$DM_ACTIVE=null; $categories=[];

if (!$fatalError) {
  $spCols = getCols($pdo,'sanpham');

  $SP_ID   = pickCol($spCols, ['id_san_pham','id','sanpham_id']);
  $SP_NAME = pickCol($spCols, ['ten_san_pham','ten','name']);
  $SP_PRICE= pickCol($spCols, ['gia','gia_ban','price']);
  $SP_GOC  = pickCol($spCols, ['gia_goc']);
  $SP_KM   = pickCol($spCols, ['gia_khuyen_mai','gia_km']);
  $SP_IMG  = pickCol($spCols, ['hinh_anh','anh','image']);
  $SP_DESC = pickCol($spCols, ['mo_ta','mota','description','noi_dung']);
  $SP_QTY  = pickCol($spCols, ['so_luong','ton_kho','quantity']);
  $SP_ACTIVE = pickCol($spCols, ['trang_thai','is_active','active','status','hien_thi']);
  $SP_CREATED= pickCol($spCols, ['ngay_tao','created_at']);
  $SP_UPDATED= pickCol($spCols, ['ngay_cap_nhat','updated_at']);

  $SP_CAT_ID = pickCol($spCols, ['id_danh_muc','danh_muc_id','category_id']);
  $SP_CAT_TX = pickCol($spCols, ['loai','danh_muc','category']);

  if(!$SP_ID) $fatalError = "Bảng sanpham thiếu cột ID (id_san_pham).";

  $dmOk = tableExists($pdo,'danhmuc');
  if ($dmOk) {
    $dmCols = getCols($pdo,'danhmuc');
    $DM_ID   = pickCol($dmCols, ['id_danh_muc','id','danhmuc_id']);
    $DM_NAME = pickCol($dmCols, ['ten_danh_muc','ten','name']);
    $DM_ACTIVE = pickCol($dmCols, ['trang_thai','is_active','active','status','hien_thi']);

    if ($DM_ID && $DM_NAME) {
      try{
        $sql = "SELECT $DM_ID AS id, $DM_NAME AS ten".($DM_ACTIVE?(", $DM_ACTIVE AS st"):"")." FROM danhmuc";
        if ($DM_ACTIVE) $sql .= " ORDER BY $DM_ACTIVE DESC, $DM_NAME ASC";
        else $sql .= " ORDER BY $DM_NAME ASC";
        $categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch(Throwable $e) {}
    }
  }
}

/* ================= POST actions (MUST be before render) ================= */
if (!$fatalError && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  // Thêm danh mục nhanh (ADMIN)
  if ($action === 'add_category') {
    if (!$isAdmin) redirect_to(['type'=>'error','msg'=>'Chỉ ADMIN được thêm danh mục.']);
    if (!($dmOk && $DM_NAME)) redirect_to(['type'=>'error','msg'=>'Thiếu bảng/cột danhmuc.']);

    $ten = trim((string)($_POST['dm_ten'] ?? ''));
    if ($ten==='') redirect_to(['type'=>'error','msg'=>'Tên danh mục không được trống.']);

    $st = $DM_ACTIVE ? (int)($_POST['dm_st'] ?? 1) : 1;

    $fields = [$DM_NAME];
    $vals = [':ten']; $bind = [':ten'=>$ten];
    if ($DM_ACTIVE) { $fields[]=$DM_ACTIVE; $vals[]=':st'; $bind[':st']=$st; }

    $sql="INSERT INTO danhmuc(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    log_event($pdo, 'THÊM DANH MỤC', "Thêm danh mục: $ten", ['user_id'=>$myId, 'role'=>$vaiTro]);
    redirect_to(['type'=>'ok','msg'=>'Đã thêm danh mục.']);
  }

  // Toggle trạng thái sản phẩm
  if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) redirect_to(['type'=>'error','msg'=>'Thiếu ID sản phẩm.']);
    if (!$SP_ACTIVE) redirect_to(['type'=>'error','msg'=>'Bảng sanpham chưa có cột trang_thai/is_active nên không bật/tắt được.']);

    $cur = $pdo->prepare("SELECT $SP_ACTIVE FROM sanpham WHERE $SP_ID=? LIMIT 1");
    $cur->execute([$id]);
    $v = $cur->fetchColumn();
    $new = ((string)$v === '1') ? 0 : 1;

    $sql = "UPDATE sanpham SET $SP_ACTIVE=?".($SP_UPDATED?(", $SP_UPDATED=NOW()"):"")." WHERE $SP_ID=?";
    $pdo->prepare($sql)->execute([$new,$id]);

    log_event($pdo, 'CẬP NHẬT TRẠNG THÁI SP', "SP#$id => ".($new? 'HIỂN THỊ':'ẨN'), ['user_id'=>$myId,'role'=>$vaiTro]);
    redirect_to(['type'=>'ok','msg'=>'Đã cập nhật trạng thái.']);
  }

  // Xóa sản phẩm (ADMIN)
  if ($action === 'delete') {
    if (!$isAdmin) redirect_to(['type'=>'error','msg'=>'Nhân viên không được xoá sản phẩm.']);
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) redirect_to(['type'=>'error','msg'=>'Thiếu ID sản phẩm.']);

    $pdo->prepare("DELETE FROM sanpham WHERE $SP_ID=?")->execute([$id]);
    log_event($pdo, 'XÓA SẢN PHẨM', "Đã xoá SP#$id", ['user_id'=>$myId,'role'=>$vaiTro]);
    redirect_to(['type'=>'ok','msg'=>'Đã xoá sản phẩm.']);
  }

  // Lưu (thêm / sửa)
  if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);

    $ten = trim((string)($_POST['ten'] ?? ''));
    if ($SP_NAME && $ten==='') redirect_to(['type'=>'error','msg'=>'Tên sản phẩm không được trống.']);

    $gia = (float)($_POST['gia'] ?? 0);
    $gia_goc = ($_POST['gia_goc'] ?? '') === '' ? null : (float)$_POST['gia_goc'];
    $gia_km  = ($_POST['gia_km'] ?? '') === '' ? null : (float)$_POST['gia_km'];
    $qty = ($_POST['qty'] ?? '') === '' ? null : (int)$_POST['qty'];
    $mo_ta = trim((string)($_POST['mo_ta'] ?? ''));
    $st = $SP_ACTIVE ? (int)($_POST['st'] ?? 1) : 1;

    $catId = null;
    $catTx = null;
    if ($SP_CAT_ID) {
      $tmp = (int)($_POST['cat_id'] ?? 0);
      $catId = ($tmp>0) ? $tmp : null;
    }
    if ($SP_CAT_TX) {
      $catTx = trim((string)($_POST['cat_tx'] ?? ''));
      if ($catTx === '') $catTx = null;
    }

    $uploaded = upload_image_if_any('hinh_anh');

    // lấy giá cũ để log
    $oldPrice = null;
    if ($id>0 && $SP_PRICE) {
      $stt = $pdo->prepare("SELECT $SP_PRICE FROM sanpham WHERE $SP_ID=? LIMIT 1");
      $stt->execute([$id]);
      $oldPrice = $stt->fetchColumn();
      $oldPrice = ($oldPrice===null) ? null : (float)$oldPrice;
    }

    if ($id <= 0) {
      $fields=[]; $vals=[]; $bind=[];

      if ($SP_NAME){  $fields[]=$SP_NAME;  $vals[]=':ten'; $bind[':ten']=$ten; }
      if ($SP_PRICE){ $fields[]=$SP_PRICE; $vals[]=':gia'; $bind[':gia']=$gia; }
      if ($SP_GOC){   $fields[]=$SP_GOC;   $vals[]=':goc'; $bind[':goc']=$gia_goc; }
      if ($SP_KM){    $fields[]=$SP_KM;    $vals[]=':km';  $bind[':km']=$gia_km; }
      if ($SP_QTY){   $fields[]=$SP_QTY;   $vals[]=':q';   $bind[':q']=$qty; }
      if ($SP_DESC){  $fields[]=$SP_DESC;  $vals[]=':mt';  $bind[':mt']=$mo_ta; }
      if ($SP_IMG && $uploaded){ $fields[]=$SP_IMG; $vals[]=':img'; $bind[':img']=$uploaded; }
      if ($SP_ACTIVE){$fields[]=$SP_ACTIVE;$vals[]=':st';  $bind[':st']=$st; }

      if ($SP_CAT_ID){ $fields[]=$SP_CAT_ID; $vals[]=':cid'; $bind[':cid']=$catId; }
      else if ($SP_CAT_TX){ $fields[]=$SP_CAT_TX; $vals[]=':ctx'; $bind[':ctx']=$catTx; }

      if ($SP_CREATED){ $fields[]=$SP_CREATED; $vals[]='NOW()'; }
      if ($SP_UPDATED){ $fields[]=$SP_UPDATED; $vals[]='NOW()'; }

      if(!$fields) redirect_to(['type'=>'error','msg'=>'Không có cột phù hợp để thêm sản phẩm.']);

      $sql="INSERT INTO sanpham(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
      $pdo->prepare($sql)->execute($bind);
      $newId = (int)$pdo->lastInsertId();

      log_event($pdo, 'THÊM SẢN PHẨM', "Thêm SP#$newId: $ten", ['user_id'=>$myId,'role'=>$vaiTro]);
      price_log($pdo, $newId, null, $gia, $myId, 'Giá khi tạo sản phẩm');

      redirect_to(['type'=>'ok','msg'=>'Đã thêm sản phẩm.','xem'=>$newId]);
    } else {
      $set=[]; $bind=[':id'=>$id];

      if ($SP_NAME){  $set[]="$SP_NAME=:ten"; $bind[':ten']=$ten; }
      if ($SP_PRICE){ $set[]="$SP_PRICE=:gia"; $bind[':gia']=$gia; }
      if ($SP_GOC){   $set[]="$SP_GOC=:goc"; $bind[':goc']=$gia_goc; }
      if ($SP_KM){    $set[]="$SP_KM=:km";   $bind[':km']=$gia_km; }
      if ($SP_QTY){   $set[]="$SP_QTY=:q";   $bind[':q']=$qty; }
      if ($SP_DESC){  $set[]="$SP_DESC=:mt"; $bind[':mt']=$mo_ta; }
      if ($SP_ACTIVE){$set[]="$SP_ACTIVE=:st";$bind[':st']=$st; }

      if ($SP_CAT_ID){ $set[]="$SP_CAT_ID=:cid"; $bind[':cid']=$catId; }
      else if ($SP_CAT_TX){ $set[]="$SP_CAT_TX=:ctx"; $bind[':ctx']=$catTx; }

      if ($SP_IMG && $uploaded){ $set[]="$SP_IMG=:img"; $bind[':img']=$uploaded; }
      if ($SP_UPDATED){ $set[]="$SP_UPDATED=NOW()"; }

      if(!$set) redirect_to(['type'=>'error','msg'=>'Không có dữ liệu để cập nhật.']);

      $sql="UPDATE sanpham SET ".implode(', ',$set)." WHERE $SP_ID=:id";
      $pdo->prepare($sql)->execute($bind);

      log_event($pdo, 'CẬP NHẬT SẢN PHẨM', "Cập nhật SP#$id: $ten", ['user_id'=>$myId,'role'=>$vaiTro]);

      if ($SP_PRICE && $oldPrice !== null && (float)$oldPrice !== (float)$gia) {
        price_log($pdo, $id, $oldPrice, $gia, $myId, 'Đổi giá');
      }

      redirect_to(['type'=>'ok','msg'=>'Đã cập nhật sản phẩm.','xem'=>$id]);
    }
  }

  redirect_to(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= Query list ================= */
$q = trim((string)($_GET['q'] ?? ''));
$stFilter = trim((string)($_GET['st'] ?? 'all')); // all|1|0
$catFilter = (int)($_GET['cat'] ?? 0);
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$rows = [];
$total = 0;
$totalPages = 1;

if (!$fatalError) {
  $where = " WHERE 1 ";
  $params = [];

  if ($q !== '') {
    $conds = [];
    if ($SP_NAME) { $conds[] = "s.$SP_NAME LIKE ?"; $params[] = "%$q%"; }
    if ($SP_CAT_TX) { $conds[] = "s.$SP_CAT_TX LIKE ?"; $params[] = "%$q%"; }
    if (!$conds) { $conds[] = "s.$SP_ID = ?"; $params[] = (int)$q; }
    $where .= " AND (" . implode(" OR ", $conds) . ") ";
  }

  if ($SP_ACTIVE && ($stFilter==='1' || $stFilter==='0')) {
    $where .= " AND s.$SP_ACTIVE = ? ";
    $params[] = (int)$stFilter;
  }

  if ($SP_CAT_ID && $catFilter>0) {
    $where .= " AND s.$SP_CAT_ID = ? ";
    $params[] = $catFilter;
  } elseif ($SP_CAT_TX && $catFilter>0 && $dmOk && $categories) {
    foreach($categories as $c){
      if ((int)$c['id'] === $catFilter) {
        $where .= " AND s.$SP_CAT_TX = ? ";
        $params[] = (string)$c['ten'];
        break;
      }
    }
  }

  $countSql = "SELECT COUNT(*) FROM sanpham s $where";
  $stt = $pdo->prepare($countSql);
  $stt->execute($params);
  $total = (int)$stt->fetchColumn();
  $totalPages = max(1,(int)ceil($total/$perPage));

  $fields = [
    "s.$SP_ID AS id",
    ($SP_NAME ? "s.$SP_NAME AS ten" : "s.$SP_ID AS ten"),
    ($SP_PRICE ? "s.$SP_PRICE AS gia" : "0 AS gia"),
    ($SP_GOC ? "s.$SP_GOC AS gia_goc" : "NULL AS gia_goc"),
    ($SP_KM ? "s.$SP_KM AS gia_km" : "NULL AS gia_km"),
    ($SP_IMG ? "s.$SP_IMG AS img" : "NULL AS img"),
    ($SP_QTY ? "s.$SP_QTY AS qty" : "NULL AS qty"),
    ($SP_ACTIVE ? "s.$SP_ACTIVE AS st" : "NULL AS st"),
    ($SP_CAT_TX ? "s.$SP_CAT_TX AS cat_tx" : "NULL AS cat_tx"),
    ($SP_UPDATED ? "s.$SP_UPDATED AS upd" : ($SP_CREATED ? "s.$SP_CREATED AS upd" : "NULL AS upd"))
  ];

  $join = "";
  $dmNameSel = "";
  if ($dmOk && $DM_ID && $DM_NAME && $SP_CAT_ID) {
    $join = " LEFT JOIN danhmuc dm ON dm.$DM_ID = s.$SP_CAT_ID ";
    $dmNameSel = ", dm.$DM_NAME AS dm_ten ";
  }

  $sql = "SELECT ".implode(', ', $fields).$dmNameSel."
          FROM sanpham s
          $join
          $where
          ORDER BY ".($SP_UPDATED ? "s.$SP_UPDATED" : "s.$SP_ID")." DESC
          LIMIT $perPage OFFSET $offset";
  $stt = $pdo->prepare($sql);
  $stt->execute($params);
  $rows = $stt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* ================= View item ================= */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
if (!$fatalError && $viewId>0){
  $stt = $pdo->prepare("SELECT * FROM sanpham WHERE $SP_ID=? LIMIT 1");
  $stt->execute([$viewId]);
  $view = $stt->fetch(PDO::FETCH_ASSOC);
}

/* ================= Recent price logs (optional) ================= */
$priceRows = [];
[$priceTable,$priceCols] = detect_price_table($pdo);
if (!$fatalError && $priceTable) {
  $P_IDSP = pickCol($priceCols, ['id_san_pham','sanpham_id','id_sp']);
  $P_OLD  = pickCol($priceCols, ['gia_cu','old_price','gia_truoc']);
  $P_NEW  = pickCol($priceCols, ['gia_moi','new_price','gia_sau']);
  $P_TIME = pickCol($priceCols, ['thoi_gian','ngay_tao','created_at','time']);
  if ($P_IDSP && $P_NEW && $P_TIME) {
    try{
      $priceRows = $pdo->query("SELECT $P_IDSP AS id_sp, ".($P_OLD?("$P_OLD AS oldp"):"NULL AS oldp").", $P_NEW AS newp, $P_TIME AS t
                                FROM $priceTable ORDER BY $P_TIME DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch(Throwable $e) {}
  }
}

/* ================= Page meta & render layout ================= */
$ACTIVE = 'sanpham';
$PAGE_TITLE = 'Sản phẩm';
$PAGE_HEADING = 'Sản phẩm';
$PAGE_DESC = 'Danh sách, thêm/sửa/xoá sản phẩm';

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

$type = (string)($_GET['type'] ?? '');
$msg  = (string)($_GET['msg'] ?? '');
?>

<?php if($fatalError): ?>
  <div class="max-w-7xl mx-auto">
    <div class="p-6 bg-white rounded-2xl border border-line shadow-card">
      <div class="text-lg font-extrabold mb-2">Không thể tải trang</div>
      <div class="text-sm text-muted font-bold"><?= $fatalError ?></div>
    </div>
  </div>
  <?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ob_end_flush(); exit; ?>
<?php endif; ?>

<div class="flex items-start justify-between gap-4 mb-6">
  <div>
    <div class="text-sm text-muted font-bold">Quản lý sản phẩm</div>
    <div class="text-2xl md:text-3xl font-extrabold mt-1">Danh sách & cập nhật</div>
    <div class="text-sm text-muted mt-2">Ảnh lấy từ <b>assets/img</b>. Đổi giá sẽ ghi theo dõi giá nếu có bảng.</div>
  </div>

  <div class="flex items-center gap-2">
    <?php if($isAdmin && $dmOk): ?>
      <a href="danhmuc.php" class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">Danh mục</a>
    <?php endif; ?>
    <a href="sanpham.php" class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">Thêm mới</a>
  </div>
</div>

<?php if($msg): ?>
  <div class="mb-6 p-4 rounded-2xl border shadow-card bg-white <?= $type==='ok'?'border-green-200':($type==='error'?'border-red-200':'border-line') ?>">
    <div class="flex gap-2 items-start">
      <span class="material-symbols-outlined <?= $type==='ok'?'text-green-600':($type==='error'?'text-red-600':'text-slate-600') ?>">
        <?= $type==='ok'?'check_circle':($type==='error'?'error':'info') ?>
      </span>
      <div class="text-sm font-extrabold"><?= h($msg) ?></div>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">

  <div class="xl:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
      <div>
        <div class="text-lg font-extrabold">Sản phẩm</div>
        <div class="text-sm text-muted font-bold mt-1">Tổng: <?= number_format($total) ?> sản phẩm</div>
      </div>

      <form class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center" method="get" action="sanpham.php">
        <div class="relative">
          <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[20px]">search</span>
          <input name="q" value="<?= h($q) ?>"
                 class="pl-10 pr-4 py-2 rounded-xl border border-line bg-[#f3f6fb] text-sm w-full sm:w-72 focus:ring-2 focus:ring-primary/20"
                 placeholder="Tìm theo tên / loại..." />
        </div>

        <select name="st" class="px-3 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">
          <option value="all" <?= $stFilter==='all'?'selected':'' ?>>Tất cả trạng thái</option>
          <option value="1" <?= $stFilter==='1'?'selected':'' ?>>Hiển thị</option>
          <option value="0" <?= $stFilter==='0'?'selected':'' ?>>Ẩn</option>
        </select>

        <?php if($dmOk && !empty($categories)): ?>
          <select name="cat" class="px-3 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">
            <option value="0">Tất cả danh mục</option>
            <?php foreach($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $catFilter===(int)$c['id']?'selected':'' ?>><?= h($c['ten']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <button class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-extrabold">Lọc</button>
      </form>
    </div>

    <?php if(!$SP_ACTIVE): ?>
      <div class="mt-4 p-4 rounded-2xl border border-yellow-200 bg-yellow-50">
        <div class="font-extrabold">Thiếu cột trạng thái</div>
        <div class="text-sm text-muted font-bold mt-1">Bảng <b>sanpham</b> chưa có <b>trang_thai/is_active</b> nên chức năng Ẩn/Hiện chưa dùng được.</div>
      </div>
    <?php endif; ?>

    <div class="mt-5 overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-muted">
            <th class="py-3 pr-3 font-extrabold">Ảnh</th>
            <th class="py-3 pr-3 font-extrabold">Sản phẩm</th>
            <th class="py-3 pr-3 font-extrabold">Giá</th>
            <th class="py-3 pr-3 font-extrabold"><?= $SP_QTY ? 'Tồn' : '' ?></th>
            <th class="py-3 pr-3 font-extrabold">Trạng thái</th>
            <th class="py-3 pr-3 font-extrabold">Cập nhật</th>
            <th class="py-3 pr-0 font-extrabold text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          <?php if(!$rows): ?>
            <tr>
              <td colspan="7" class="py-6">
                <div class="p-4 rounded-2xl border border-line bg-[#fbfdff] text-muted font-bold">Không có sản phẩm.</div>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach($rows as $r):
            $img = (string)($r['img'] ?? '');
            $src = $img ? img_src($img) : '';
            $ten = (string)($r['ten'] ?? '');
            $catLabel = (string)($r['dm_ten'] ?? ($r['cat_tx'] ?? ''));
            $stVal = $SP_ACTIVE ? (string)($r['st'] ?? '1') : '1';
            $stText = ($SP_ACTIVE && $stVal==='0') ? 'Ẩn' : 'Hiển thị';
            $stCls  = ($SP_ACTIVE && $stVal==='0') ? 'bg-slate-100 text-slate-700' : 'bg-green-50 text-green-700';
          ?>
            <tr class="hover:bg-[#f7faff] transition group"
                data-preview-name="<?= h($ten) ?>"
                data-preview-img="<?= h($src) ?>"
                data-preview-price="<?= h(money_vnd($r['gia'] ?? 0)) ?>"
                data-preview-qty="<?= h($SP_QTY ? (string)($r['qty'] ?? 0) : '') ?>"
                data-preview-status="<?= h($stText) ?>">
              <td class="py-3 pr-3">
                <div class="size-11 rounded-xl border border-line bg-[#f1f5f9] overflow-hidden grid place-items-center">
                  <?php if($src): ?>
                    <img src="<?= h($src) ?>" class="w-full h-full object-cover" alt="">
                  <?php else: ?>
                    <span class="material-symbols-outlined text-slate-400">photo</span>
                  <?php endif; ?>
                </div>
              </td>

              <td class="py-3 pr-3">
                <div class="font-extrabold text-slate-900 truncate max-w-[320px]"><?= h($ten) ?></div>
                <div class="text-xs text-muted font-bold mt-1">
                  ID: <?= (int)$r['id'] ?>
                  <?php if($catLabel!==''): ?>
                    <span class="mx-2">•</span><?= h($catLabel) ?>
                  <?php endif; ?>
                </div>
              </td>

              <td class="py-3 pr-3">
                <div class="font-extrabold"><?= money_vnd($r['gia'] ?? 0) ?></div>
                <?php if(!empty($r['gia_km'])): ?>
                  <div class="text-xs font-extrabold text-danger mt-1">KM: <?= money_vnd($r['gia_km']) ?></div>
                <?php endif; ?>
              </td>

              <td class="py-3 pr-3">
                <?php if($SP_QTY): ?>
                  <span class="font-extrabold"><?= number_format((int)($r['qty'] ?? 0)) ?></span>
                <?php endif; ?>
              </td>

              <td class="py-3 pr-3">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $stCls ?>"><?= h($stText) ?></span>
              </td>

              <td class="py-3 pr-3">
                <div class="text-xs text-muted font-bold"><?= h((string)($r['upd'] ?? '')) ?></div>
              </td>

              <td class="py-3 pr-0">
                <div class="flex items-center justify-end gap-2">
                  <a href="sanpham.php?<?= h(http_build_query(array_merge($_GET,['xem'=>(int)$r['id']])) ) ?>"
                     class="px-3 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">Sửa</a>

                  <?php if($SP_ACTIVE): ?>
                    <form method="post" class="inline">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="px-3 py-2 rounded-xl bg-primary text-white text-sm font-extrabold hover:opacity-90">
                        <?= ($stVal==='0') ? 'Hiện' : 'Ẩn' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between mt-5">
      <div class="text-sm text-muted font-extrabold">Trang <?= $page ?>/<?= $totalPages ?></div>
      <div class="flex gap-2">
        <?php
          $qs=$_GET;
          $mk=function($p) use($qs){ $qs['page']=$p; return 'sanpham.php?'.http_build_query($qs); };
        ?>
        <a class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
           href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
        <a class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
           href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
      </div>
    </div>
  </div>

  <div class="flex flex-col gap-4 md:gap-6">

    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between">
        <div class="text-lg font-extrabold">Xem nhanh</div>
        <div class="text-xs text-muted font-extrabold">Hover dòng bên trái</div>
      </div>

      <div class="mt-4 grid grid-cols-5 gap-4 items-start">
        <div class="col-span-2">
          <div class="aspect-square rounded-2xl border border-line bg-[#f1f5f9] overflow-hidden grid place-items-center">
            <img id="pv_img" src="" alt="" class="w-full h-full object-cover hidden">
            <span id="pv_empty" class="material-symbols-outlined text-slate-400">photo</span>
          </div>
        </div>
        <div class="col-span-3">
          <div id="pv_name" class="font-extrabold text-slate-900 leading-snug">—</div>
          <div id="pv_price" class="mt-2 text-lg font-extrabold text-primary">—</div>
          <div class="mt-2 text-sm text-muted font-bold">
            <span id="pv_qty_wrap" class="<?= $SP_QTY ? '' : 'hidden' ?>">Tồn: <span id="pv_qty" class="font-extrabold text-slate-900">—</span></span>
          </div>
          <div class="mt-2">
            <span id="pv_status" class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">—</span>
          </div>
        </div>
      </div>

      <div class="mt-4 text-xs text-muted font-bold">
        Nếu ảnh không hiện: kiểm tra file ảnh trong <b>assets/img</b> và giá trị cột ảnh trong DB.
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-lg font-extrabold"><?= $view ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></div>
          <div class="text-sm text-muted font-bold mt-1"><?= $view ? ('ID: '.(int)$viewId) : 'ID tự tăng theo DB' ?></div>
        </div>
        <?php if($view): ?>
          <a href="sanpham.php" class="text-sm font-extrabold text-primary hover:underline">Bỏ chọn</a>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $view ? (int)$viewId : 0 ?>">

        <?php if($SP_NAME): ?>
          <div>
            <label class="text-sm font-extrabold">Tên sản phẩm</label>
            <input name="ten" required
                   value="<?= $view ? h($view[$SP_NAME] ?? '') : '' ?>"
                   class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20">
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <?php if($SP_PRICE): ?>
            <div>
              <label class="text-sm font-extrabold">Giá</label>
              <input type="number" name="gia" required
                     value="<?= $view ? (float)($view[$SP_PRICE] ?? 0) : 0 ?>"
                     class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20">
            </div>
          <?php endif; ?>

          <?php if($SP_QTY): ?>
            <div>
              <label class="text-sm font-extrabold">Tồn kho</label>
              <input type="number" name="qty"
                     value="<?= $view ? h($view[$SP_QTY] ?? '') : '' ?>"
                     class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20"
                     placeholder="Để trống nếu không dùng">
            </div>
          <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <?php if($SP_GOC): ?>
            <div>
              <label class="text-sm font-extrabold">Giá gốc</label>
              <input type="number" name="gia_goc"
                     value="<?= $view ? h($view[$SP_GOC] ?? '') : '' ?>"
                     class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20"
                     placeholder="Để trống nếu không dùng">
            </div>
          <?php endif; ?>

          <?php if($SP_KM): ?>
            <div>
              <label class="text-sm font-extrabold">Giá khuyến mãi</label>
              <input type="number" name="gia_km"
                     value="<?= $view ? h($view[$SP_KM] ?? '') : '' ?>"
                     class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20"
                     placeholder="Để trống nếu không dùng">
            </div>
          <?php endif; ?>
        </div>

        <?php if($SP_CAT_ID && $dmOk && !empty($categories)): ?>
          <div>
            <label class="text-sm font-extrabold">Danh mục</label>
            <?php $curCid = (int)($view[$SP_CAT_ID] ?? 0); ?>
            <select name="cat_id" class="mt-1 w-full rounded-xl border border-line bg-white text-sm font-extrabold">
              <option value="0">— Chọn danh mục —</option>
              <?php foreach($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $curCid===(int)$c['id']?'selected':'' ?>><?= h($c['ten']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php elseif($SP_CAT_TX): ?>
          <div>
            <label class="text-sm font-extrabold">Loại / Danh mục</label>
            <input name="cat_tx"
                   value="<?= $view ? h($view[$SP_CAT_TX] ?? '') : '' ?>"
                   class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20"
                   placeholder="VD: classic / phu_kien...">
          </div>
        <?php endif; ?>

        <?php if($SP_DESC): ?>
          <div>
            <label class="text-sm font-extrabold">Mô tả</label>
            <textarea name="mo_ta" rows="4"
                      class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20"><?= $view ? h($view[$SP_DESC] ?? '') : '' ?></textarea>
          </div>
        <?php endif; ?>

        <?php if($SP_IMG): ?>
          <div>
            <label class="text-sm font-extrabold">Ảnh sản phẩm</label>
            <input type="file" name="hinh_anh" accept=".jpg,.jpeg,.png,.webp"
                   class="mt-1 w-full rounded-xl border border-line bg-white text-sm font-bold">
            <?php if($view && !empty($view[$SP_IMG])): ?>
              <div class="mt-2 text-xs text-muted font-bold">Hiện tại: <b><?= h($view[$SP_IMG]) ?></b></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if($SP_ACTIVE): ?>
          <div>
            <label class="text-sm font-extrabold">Trạng thái</label>
            <?php $curSt = (string)($view[$SP_ACTIVE] ?? '1'); ?>
            <select name="st" class="mt-1 w-full rounded-xl border border-line bg-white text-sm font-extrabold">
              <option value="1" <?= $curSt==='1'?'selected':'' ?>>Hiển thị</option>
              <option value="0" <?= $curSt==='0'?'selected':'' ?>>Ẩn</option>
            </select>
          </div>
        <?php endif; ?>

        <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
          <?= $view ? 'Lưu cập nhật' : 'Thêm sản phẩm' ?>
        </button>
      </form>

      <?php if($view): ?>
        <form method="post" class="mt-3" onsubmit="return confirm('Xóa sản phẩm này?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$viewId ?>">
          <button class="w-full px-4 py-3 rounded-2xl bg-danger text-white font-extrabold hover:opacity-90 <?= $isAdmin?'':'opacity-40 pointer-events-none' ?>">
            Xóa sản phẩm
          </button>
          <?php if(!$isAdmin): ?>
            <div class="text-xs text-muted font-bold mt-2">Nhân viên không được xoá.</div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>

    <?php if($isAdmin && $dmOk && $DM_NAME): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-lg font-extrabold">Thêm danh mục</div>
            <div class="text-sm text-muted font-bold mt-1">Chỉ ADMIN</div>
          </div>
          <a href="danhmuc.php" class="text-sm font-extrabold text-primary hover:underline">Quản lý</a>
        </div>

        <form method="post" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="add_category">
          <div>
            <label class="text-sm font-extrabold">Tên danh mục</label>
            <input name="dm_ten" required class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb] focus:ring-2 focus:ring-primary/20">
          </div>

          <?php if($DM_ACTIVE): ?>
            <div>
              <label class="text-sm font-extrabold">Trạng thái</label>
              <select name="dm_st" class="mt-1 w-full rounded-xl border border-line bg-white text-sm font-extrabold">
                <option value="1" selected>Hiển thị</option>
                <option value="0">Ẩn</option>
              </select>
            </div>
          <?php endif; ?>

          <button class="w-full px-4 py-3 rounded-2xl bg-slate-900 text-white font-extrabold hover:opacity-90">
            Thêm danh mục
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between">
        <div class="text-lg font-extrabold">Theo dõi giá</div>
        <a href="theodoi_gia.php" class="text-sm font-extrabold text-primary hover:underline">Xem trang</a>
      </div>

      <div class="mt-4 space-y-3">
        <?php if(empty($priceRows)): ?>
          <div class="p-4 rounded-2xl border border-line bg-[#fbfdff] text-muted font-bold">
            Chưa có dữ liệu theo dõi giá (hoặc chưa tạo bảng theo dõi giá).
          </div>
        <?php else: ?>
          <?php foreach($priceRows as $pr): ?>
            <div class="p-3 rounded-2xl border border-line bg-white">
              <div class="flex items-center justify-between">
                <div class="text-sm font-extrabold">SP#<?= (int)($pr['id_sp'] ?? 0) ?></div>
                <div class="text-xs text-muted font-bold"><?= h((string)($pr['t'] ?? '')) ?></div>
              </div>
              <div class="mt-1 text-sm">
                <?php if($pr['oldp'] !== null): ?>
                  <span class="text-muted font-bold"><?= money_vnd($pr['oldp']) ?></span>
                  <span class="mx-2 text-muted font-bold">→</span>
                <?php endif; ?>
                <span class="font-extrabold text-primary"><?= money_vnd($pr['newp']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const pvImg = document.getElementById('pv_img');
  const pvEmpty = document.getElementById('pv_empty');
  const pvName = document.getElementById('pv_name');
  const pvPrice = document.getElementById('pv_price');
  const pvQty = document.getElementById('pv_qty');
  const pvQtyWrap = document.getElementById('pv_qty_wrap');
  const pvStatus = document.getElementById('pv_status');

  function setPreview(row){
    const img = row.getAttribute('data-preview-img') || '';
    const name = row.getAttribute('data-preview-name') || '—';
    const price = row.getAttribute('data-preview-price') || '—';
    const qty = row.getAttribute('data-preview-qty') || '';
    const status = row.getAttribute('data-preview-status') || '—';

    pvName.textContent = name;
    pvPrice.textContent = price;

    if (pvQtyWrap) {
      if (qty !== '') { pvQtyWrap.classList.remove('hidden'); pvQty.textContent = qty; }
      else { pvQtyWrap.classList.add('hidden'); }
    }

    pvStatus.textContent = status;
    if (status.toLowerCase().includes('ẩn')) {
      pvStatus.className = 'px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700';
    } else {
      pvStatus.className = 'px-3 py-1 rounded-full text-xs font-extrabold bg-green-50 text-green-700';
    }

    if (img) {
      pvImg.src = img;
      pvImg.classList.remove('hidden');
      pvEmpty.classList.add('hidden');
    } else {
      pvImg.classList.add('hidden');
      pvEmpty.classList.remove('hidden');
    }
  }

  document.querySelectorAll('tr[data-preview-name]').forEach(tr => {
    tr.addEventListener('mouseenter', () => setPreview(tr));
  });
})();
</script>

<?php
require_once __DIR__ . '/includes/giaoDienCuoi.php';
ob_end_flush();
