<?php
// admin/nhanvien.php
// UI: danh sách bên trái + panel chi tiết bên phải (thông tin / phân quyền / công việc / nghỉ phép)
// Có log vào nhatky_hoatdong (nếu bảng tồn tại)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// Ưu tiên helpers/hamChung nếu có (để đồng bộ hệ thống)
if (file_exists(__DIR__ . '/includes/helpers.php')) require_once __DIR__ . '/includes/helpers.php';
if (file_exists(__DIR__ . '/includes/hamChung.php')) require_once __DIR__ . '/includes/hamChung.php';
/* ================= Redirect wrapper (compatible with helpers.php) ================= */
if (!function_exists('go')) {
  function go(array $flash, ?string $to = null) {
    $to = $to ?: ($_SERVER['HTTP_REFERER'] ?? 'nhanvien.php');

    // Nếu helpers.php có redirectWith() nhưng khác chữ ký, thử 2 kiểu gọi
    if (function_exists('redirectWith')) {
      try {
        // kiểu A: redirectWith($flash, $to)
        redirectWith($flash, $to);
        exit;
      } catch (TypeError $e) {}

      try {
        // kiểu B: redirectWith($to, $flash)
        redirectWith($to, $flash);
        exit;
      } catch (TypeError $e) {}

      // fallback: nếu redirectWith chỉ cần $to
      try {
        redirectWith($to);
        exit;
      } catch (Throwable $e) {}
    }

    // Fallback thuần PHP
    $_SESSION['_flash'] = $flash;
    header("Location: {$to}");
    exit;
  }
}

if (!function_exists('flashPull')) {
  function flashPull() {
    // Ưu tiên flash theo chuẩn bạn đang dùng trong project
    if (isset($_SESSION['_flash'])) {
      $f = $_SESSION['_flash'];
      unset($_SESSION['_flash']);
      return $f;
    }
    if (isset($_SESSION['flash'])) {
      $f = $_SESSION['flash'];
      unset($_SESSION['flash']);
      return $f;
    }
    return null;
  }
}

/* ================= SAFE HELPERS (không redeclare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('qn')) {
  function qn($s){ return '`'.str_replace('`','', (string)$s).'`'; }
}
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, $name){
    $st=$pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$name]);
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
if (!function_exists('redirectWith')) {
  function redirectWith(array $flash, string $to=null){
    $_SESSION['_flash'] = $flash;
    header("Location: ".($to ?: ($_SERVER['HTTP_REFERER'] ?? 'nhanvien.php')));
    exit;
  }
}
if (!function_exists('flash')) {
  function flash(){
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
  }
}
if (!function_exists('auth_me')) {
  function auth_me(){
    $me = $_SESSION['admin'] ?? [];
    $id = (int)($me['id'] ?? $me['id_admin'] ?? $me['id_nguoi_dung'] ?? 0);
    $vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
    $isAdmin = ($vaiTro === 'ADMIN');
    return [$me,$id,$vaiTro,$isAdmin];
  }
}
if (!function_exists('rand_password')) {
  function rand_password($len=10){
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#$%';
    $out='';
    for($i=0;$i<$len;$i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $out;
  }
}

/**
 * Log chuẩn theo bảng nhatky_hoatdong của bạn:
 * id_log, id_admin, vai_tro, hanh_dong, mo_ta, bang_lien_quan, id_ban_ghi, du_lieu_json, ip, user_agent, ngay_tao
 */
if (!function_exists('nhatky_log')) {
  function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, ?int $id_ban_ghi=null, $data=null){
    if (!tableExists($pdo,'nhatky_hoatdong')) return;

    $cols = getCols($pdo,'nhatky_hoatdong');
    $ID_ADMIN = pickCol($cols,['id_admin','admin_id','id_user']);
    $VAI_TRO  = pickCol($cols,['vai_tro','role']);
    $HANH     = pickCol($cols,['hanh_dong','action']);
    $MOTA     = pickCol($cols,['mo_ta','description']);
    $BANG     = pickCol($cols,['bang_lien_quan','doi_tuong','table_name']);
    $IDREC    = pickCol($cols,['id_ban_ghi','doi_tuong_id','record_id']);
    $JSON     = pickCol($cols,['du_lieu_json','json','data_json']);
    $IP       = pickCol($cols,['ip']);
    $UA       = pickCol($cols,['user_agent']);
    $NGAY     = pickCol($cols,['ngay_tao','created_at']);

    $fields=[]; $vals=[]; $bind=[];
    [$me,$myId,$vaiTro,$isAdmin] = auth_me();

    if ($ID_ADMIN){ $fields[]=$ID_ADMIN; $vals[]=':aid'; $bind[':aid']=$myId?:null; }
    if ($VAI_TRO){  $fields[]=$VAI_TRO;  $vals[]=':role'; $bind[':role']=strtolower($vaiTro?:'admin'); }
    if ($HANH){     $fields[]=$HANH;     $vals[]=':act'; $bind[':act']=$hanh_dong; }
    if ($MOTA){     $fields[]=$MOTA;     $vals[]=':des'; $bind[':des']=$mo_ta; }
    if ($BANG && $bang!==null){ $fields[]=$BANG; $vals[]=':tbl'; $bind[':tbl']=$bang; }
    if ($IDREC && $id_ban_ghi!==null){ $fields[]=$IDREC; $vals[]=':rid'; $bind[':rid']=$id_ban_ghi; }
    if ($JSON){
      $fields[]=$JSON; $vals[]=':js';
      $bind[':js']= is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    if ($IP){ $fields[]=$IP; $vals[]=':ip'; $bind[':ip']=($_SERVER['REMOTE_ADDR'] ?? null); }
    if ($UA){ $fields[]=$UA; $vals[]=':ua'; $bind[':ua']=($_SERVER['HTTP_USER_AGENT'] ?? null); }
    if ($NGAY){ $fields[]=$NGAY; $vals[]='NOW()'; }

    if (!$fields) return;
    $sql="INSERT INTO nhatky_hoatdong(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
  }
}

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE = 'nhanvien';
$PAGE_TITLE = 'Nhân viên';

// Nếu hệ thống có requirePermission thì dùng, không có thì bỏ qua
if (function_exists('requirePermission')) {
  requirePermission('nhanvien');
}

/* ================= PICK STAFF TABLE (ưu tiên admin) ================= */
$staffTable = null;
if (tableExists($pdo,'admin')) $staffTable = 'admin';
elseif (tableExists($pdo,'nhanvien')) $staffTable = 'nhanvien';
elseif (tableExists($pdo,'nguoidung')) $staffTable = 'nguoidung';

if (!$staffTable) {
  die("Thiếu bảng nhân sự (admin/nhanvien/nguoidung).");
}

$SCols = getCols($pdo, $staffTable);
$SID   = pickCol($SCols, ['id_admin','id_nhan_vien','id_nguoi_dung','id']);
$SUSER = pickCol($SCols, ['username','ten_dang_nhap','tai_khoan']);
$SEMAIL= pickCol($SCols, ['email']);
$SNAME = pickCol($SCols, ['ho_ten','full_name','ten','ten_hien_thi']);
$SPHONE= pickCol($SCols, ['sdt','so_dien_thoai','phone']);
$SROLE = pickCol($SCols, ['vai_tro','role','quyen']);
$SACT  = pickCol($SCols, ['is_active','trang_thai','kich_hoat','hien_thi']);
$SPASS = pickCol($SCols, ['mat_khau','password','pass_hash']);
$SCREA = pickCol($SCols, ['created_at','ngay_tao']);
$SUPD  = pickCol($SCols, ['updated_at','ngay_cap_nhat']);
$SLAST = pickCol($SCols, ['last_login','lan_dang_nhap_cuoi']);

if (!$SID) die("Bảng {$staffTable} thiếu cột ID.");

/* ================= PERMISSION STORAGE (ROLE TABLES or JSON) ================= */
$hasRoleTables = tableExists($pdo,'vaitro') && tableExists($pdo,'chucnang') && tableExists($pdo,'vaitro_chucnang');
$permJsonCol = pickCol($SCols, ['quyen_json','permissions_json','perm_json','access_json']);

/* ================= TASK/LEAVE TABLE (không bắt buộc) ================= */
$taskTableCandidates = ['nhanvien_congviec','cong_viec','phan_cong','tasks','task'];
$leaveTableCandidates = ['nhanvien_nghiphep','nghi_phep','don_xin_nghi','leave_requests','nghiphep'];

$taskTable = null;
foreach($taskTableCandidates as $t){ if(tableExists($pdo,$t)){ $taskTable=$t; break; } }
$leaveTable = null;
foreach($leaveTableCandidates as $t){ if(tableExists($pdo,$t)){ $leaveTable=$t; break; } }

$taskCols = $taskTable ? getCols($pdo,$taskTable) : [];
$leaveCols= $leaveTable ? getCols($pdo,$leaveTable) : [];

/* task col mapping */
$T_ID   = $taskTable ? pickCol($taskCols,['id_task','id_cong_viec','id','id_phan_cong']) : null;
$T_AID  = $taskTable ? pickCol($taskCols,['id_admin','id_nhan_vien','assignee_id','id_user']) : null;
$T_TIEU = $taskTable ? pickCol($taskCols,['tieu_de','title','ten']) : null;
$T_MOTA = $taskTable ? pickCol($taskCols,['mo_ta','description','ghi_chu','note']) : null;
$T_STAT = $taskTable ? pickCol($taskCols,['trang_thai','status']) : null;
$T_PRIO = $taskTable ? pickCol($taskCols,['muc_do','priority','do_uu_tien']) : null;
$T_DUE  = $taskTable ? pickCol($taskCols,['han_chot','due_date','deadline']) : null;
$T_CREA = $taskTable ? pickCol($taskCols,['created_at','ngay_tao']) : null;
$T_UPD  = $taskTable ? pickCol($taskCols,['updated_at','ngay_cap_nhat']) : null;

/* leave col mapping */
$L_ID   = $leaveTable ? pickCol($leaveCols,['id_nghi','id_don','id','id_leave']) : null;
$L_AID  = $leaveTable ? pickCol($leaveCols,['id_admin','id_nhan_vien','id_user']) : null;
$L_FROM = $leaveTable ? pickCol($leaveCols,['tu_ngay','from_date','ngay_bat_dau']) : null;
$L_TO   = $leaveTable ? pickCol($leaveCols,['den_ngay','to_date','ngay_ket_thuc']) : null;
$L_LYDO = $leaveTable ? pickCol($leaveCols,['ly_do','reason','mo_ta']) : null;
$L_STAT = $leaveTable ? pickCol($leaveCols,['trang_thai','status']) : null;
$L_APPR = $leaveTable ? pickCol($leaveCols,['nguoi_duyet_id','approved_by','id_admin_duyet']) : null;
$L_CREA = $leaveTable ? pickCol($leaveCols,['created_at','ngay_tao']) : null;

/* ================= MODULE LIST (đồng bộ sidebar) ================= */
$MODULES = [
  'tong_quan'   => ['label'=>'Tổng quan',    'icon'=>'dashboard'],
  'sanpham'     => ['label'=>'Sản phẩm',     'icon'=>'inventory_2'],
  'theodoi_gia' => ['label'=>'Theo dõi giá', 'icon'=>'monitoring'],
  'donhang'     => ['label'=>'Đơn hàng',     'icon'=>'receipt_long'],
  'khachhang'   => ['label'=>'Khách hàng',   'icon'=>'group'],
  'tonkho'      => ['label'=>'Tồn kho',      'icon'=>'warehouse'],
  'voucher'     => ['label'=>'Voucher',      'icon'=>'sell'],
  'baocao'      => ['label'=>'Báo cáo',      'icon'=>'bar_chart'],
  'nhatky'      => ['label'=>'Nhật ký',      'icon'=>'history'],
  'nhanvien'    => ['label'=>'Nhân viên',    'icon'=>'badge'],
  'caidat'      => ['label'=>'Cài đặt',      'icon'=>'settings'],
];

/* ================= POST actions (MUST be before render) ================= */
$fatal = false;
if (!isset($pdo) || !($pdo instanceof PDO)) $fatal = true;

$tab = $_GET['tab'] ?? 'thongtin';
$idSelected = (int)($_GET['id'] ?? 0);

if (!$fatal && $_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? $idSelected);

  // chỉ admin mới được CRUD nhân viên (nhân viên chỉ xem)
  $adminOnlyActions = ['add_staff','update_staff','toggle_active','reset_pass','assign_role','perm_toggle','task_add','task_update','task_delete','leave_approve','leave_reject'];
  if (in_array($action,$adminOnlyActions,true) && !$isAdmin) {
    redirectWith(['type'=>'error','msg'=>'Bạn không có quyền thao tác.']);
  }

  // helper for redirect back
  $backTo = function($id=null, $tab=null){
    $qs = [];
    if ($id!==null) $qs[]='id='.(int)$id;
    if ($tab!==null) $qs[]='tab='.urlencode($tab);
    return 'nhanvien.php'.($qs?('?'.implode('&',$qs)):'');
  };

  /* -------- ADD STAFF -------- */
  if ($action==='add_staff') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $name     = trim((string)($_POST['ho_ten'] ?? ''));
    $phone    = trim((string)($_POST['sdt'] ?? ''));
    $role     = trim((string)($_POST['vai_tro'] ?? 'staff'));
    $passRaw  = trim((string)($_POST['mat_khau'] ?? ''));
    if ($passRaw==='') $passRaw = rand_password(10);

    if ($SUSER && $username==='') redirectWith(['type'=>'error','msg'=>'Thiếu username.']);
    if ($SEMAIL && $email==='' )  redirectWith(['type'=>'error','msg'=>'Thiếu email.']);
    if ($SPASS && $passRaw==='')  redirectWith(['type'=>'error','msg'=>'Thiếu mật khẩu.']);

    $fields=[]; $vals=[]; $bind=[];
    if ($SUSER){ $fields[]=$SUSER; $vals[]=':u'; $bind[':u']=$username; }
    if ($SEMAIL){$fields[]=$SEMAIL;$vals[]=':e'; $bind[':e']=$email; }
    if ($SNAME){ $fields[]=$SNAME; $vals[]=':n'; $bind[':n']=$name; }
    if ($SPHONE){$fields[]=$SPHONE;$vals[]=':p'; $bind[':p']=$phone; }
    if ($SROLE){ $fields[]=$SROLE; $vals[]=':r'; $bind[':r']=$role; }
    if ($SACT){  $fields[]=$SACT;  $vals[]=':a'; $bind[':a']=1; }
    if ($SPASS){
      $fields[]=$SPASS; $vals[]=':pw';
      $bind[':pw']= password_hash($passRaw, PASSWORD_BCRYPT);
    }
    if ($SCREA){ $fields[]=$SCREA; $vals[]='NOW()'; }
    if (!$fields) redirectWith(['type'=>'error','msg'=>'Không có cột để thêm nhân viên (schema không phù hợp).']);

    // check unique (username/email)
    if ($SUSER && $username!==''){
      $st=$pdo->prepare("SELECT COUNT(*) FROM ".qn($staffTable)." WHERE ".qn($SUSER)."=?");
      $st->execute([$username]);
      if ((int)$st->fetchColumn()>0) redirectWith(['type'=>'error','msg'=>'Username đã tồn tại.']);
    }
    if ($SEMAIL && $email!==''){
      $st=$pdo->prepare("SELECT COUNT(*) FROM ".qn($staffTable)." WHERE ".qn($SEMAIL)."=?");
      $st->execute([$email]);
      if ((int)$st->fetchColumn()>0) redirectWith(['type'=>'error','msg'=>'Email đã tồn tại.']);
    }

    $sql="INSERT INTO ".qn($staffTable)."(".implode(',', array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $newId = (int)$pdo->lastInsertId();

    nhatky_log($pdo,'THEM_NHANVIEN',"Thêm nhân viên #{$newId}",$staffTable,$newId,['username'=>$username,'email'=>$email,'role'=>$role]);
    redirectWith(['type'=>'ok','msg'=>"Đã thêm nhân viên. Mật khẩu: {$passRaw}",'xem'=>$newId], $backTo($newId,'thongtin'));
  }

  /* -------- UPDATE STAFF -------- */
  if ($action==='update_staff') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);

    $email = trim((string)($_POST['email'] ?? ''));
    $name  = trim((string)($_POST['ho_ten'] ?? ''));
    $phone = trim((string)($_POST['sdt'] ?? ''));
    $role  = trim((string)($_POST['vai_tro'] ?? ''));

    $set=[]; $bind=[':id'=>$id];
    if ($SEMAIL){ $set[]=qn($SEMAIL)."=:e"; $bind[':e']=$email; }
    if ($SNAME){  $set[]=qn($SNAME)."=:n";  $bind[':n']=$name; }
    if ($SPHONE){ $set[]=qn($SPHONE)."=:p"; $bind[':p']=$phone; }
    if ($SROLE && $role!==''){ $set[]=qn($SROLE)."=:r"; $bind[':r']=$role; }
    if ($SUPD){ $set[]=qn($SUPD)."=NOW()"; }

    if (!$set) redirectWith(['type'=>'error','msg'=>'Không có cột để cập nhật.']);
    $sql="UPDATE ".qn($staffTable)." SET ".implode(',',$set)." WHERE ".qn($SID)."=:id";
    $pdo->prepare($sql)->execute($bind);

    nhatky_log($pdo,'CAP_NHAT_NHANVIEN',"Cập nhật nhân viên #{$id}",$staffTable,$id,['email'=>$email,'ho_ten'=>$name,'sdt'=>$phone,'role'=>$role]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật nhân viên.'], $backTo($id,'thongtin'));
  }

  /* -------- TOGGLE ACTIVE -------- */
  if ($action==='toggle_active') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    if (!$SACT) redirectWith(['type'=>'error','msg'=>'Bảng nhân viên không có cột trạng thái (is_active).']);

    $to = (int)($_POST['to'] ?? 0);
    $pdo->prepare("UPDATE ".qn($staffTable)." SET ".qn($SACT)."=?".($SUPD?(", ".qn($SUPD)."=NOW()"):'')." WHERE ".qn($SID)."=?")
        ->execute([$to,$id]);

    nhatky_log($pdo, $to? 'MO_KHOA_NHANVIEN':'KHOA_NHANVIEN', ($to?'Mở khóa':'Khóa')." nhân viên #{$id}", $staffTable,$id,['to'=>$to]);
    redirectWith(['type'=>'ok','msg'=> $to?'Đã mở khóa nhân viên.':'Đã khóa nhân viên.'], $backTo($id,'thongtin'));
  }

  /* -------- RESET PASSWORD -------- */
  if ($action==='reset_pass') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    if (!$SPASS) redirectWith(['type'=>'error','msg'=>'Bảng nhân viên không có cột mật khẩu.']);

    $newPass = trim((string)($_POST['new_pass'] ?? ''));
    if ($newPass==='') $newPass = rand_password(10);

    $pdo->prepare("UPDATE ".qn($staffTable)." SET ".qn($SPASS)."=?, ".($SUPD?qn($SUPD)."=NOW()":"1=1")." WHERE ".qn($SID)."=?")
        ->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);

    nhatky_log($pdo,'RESET_MATKHAU_NV',"Reset mật khẩu nhân viên #{$id}",$staffTable,$id);
    redirectWith(['type'=>'ok','msg'=>"Đã reset mật khẩu. Mật khẩu mới: {$newPass}"], $backTo($id,'thongtin'));
  }

  /* -------- ASSIGN ROLE -------- */
  if ($action==='assign_role') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    if (!$SROLE) redirectWith(['type'=>'error','msg'=>'Bảng nhân viên không có cột vai_tro.']);
    $role = trim((string)($_POST['vai_tro'] ?? 'staff'));
    $pdo->prepare("UPDATE ".qn($staffTable)." SET ".qn($SROLE)."=?".($SUPD?(", ".qn($SUPD)."=NOW()"):'')." WHERE ".qn($SID)."=?")
        ->execute([$role,$id]);

    nhatky_log($pdo,'GAN_VAI_TRO',"Gán vai trò {$role} cho nhân viên #{$id}",$staffTable,$id,['role'=>$role]);
    redirectWith(['type'=>'ok','msg'=>'Đã gán vai trò.'], $backTo($id,'thongtin'));
  }

  /* -------- PERMISSION TOGGLE (role tables OR json) -------- */
  if ($action==='perm_toggle') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    $key = trim((string)($_POST['perm_key'] ?? ''));
    $val = (int)($_POST['perm_val'] ?? 0);
    if ($key==='' || !isset($MODULES[$key])) redirectWith(['type'=>'error','msg'=>'Quyền không hợp lệ.']);

    // role tables mode: cấp theo vai trò
    if ($hasRoleTables && $SROLE) {
      // lấy vai trò hiện tại của nhân viên
      $st=$pdo->prepare("SELECT ".qn($SROLE)." FROM ".qn($staffTable)." WHERE ".qn($SID)."=? LIMIT 1");
      $st->execute([$id]);
      $roleName = (string)($st->fetchColumn() ?? '');
      if ($roleName==='') redirectWith(['type'=>'error','msg'=>'Nhân viên chưa có vai trò.']);

      // map vaitro id by tên/ma
      $vrCols=getCols($pdo,'vaitro');
      $VR_ID=pickCol($vrCols,['id_vai_tro','id']);
      $VR_TEN=pickCol($vrCols,['ten_vai_tro','ten','name']);
      $VR_MA=pickCol($vrCols,['ma_vai_tro','ma','code']);
      if(!$VR_ID) redirectWith(['type'=>'error','msg'=>'Bảng vaitro thiếu id.']);

      $vrId=null;
      if ($VR_TEN){
        $st=$pdo->prepare("SELECT ".qn($VR_ID)." FROM vaitro WHERE ".qn($VR_TEN)."=? LIMIT 1");
        $st->execute([$roleName]);
        $vrId=$st->fetchColumn();
      }
      if($vrId===null && $VR_MA){
        $st=$pdo->prepare("SELECT ".qn($VR_ID)." FROM vaitro WHERE ".qn($VR_MA)."=? LIMIT 1");
        $st->execute([$roleName]);
        $vrId=$st->fetchColumn();
      }
      if($vrId===null) redirectWith(['type'=>'error','msg'=>'Không tìm thấy vai trò trong bảng vaitro.']);

      // map chucnang id by key
      $cnCols=getCols($pdo,'chucnang');
      $CN_ID=pickCol($cnCols,['id_chuc_nang','id']);
      $CN_MA=pickCol($cnCols,['ma_chuc_nang','ma','code','key']);
      if(!$CN_ID || !$CN_MA) redirectWith(['type'=>'error','msg'=>'Bảng chucnang thiếu cột id/ma.']);

      $st=$pdo->prepare("SELECT ".qn($CN_ID)." FROM chucnang WHERE ".qn($CN_MA)."=? LIMIT 1");
      $st->execute([$key]);
      $cnId=$st->fetchColumn();
      if($cnId===false || $cnId===null){
        // nếu chưa có chucnang mã này thì không tự tạo, báo rõ
        redirectWith(['type'=>'error','msg'=>"Thiếu chucnang mã '{$key}' (không tự tạo)."]);
      }

      // vaitro_chucnang insert/delete
      $vcCols=getCols($pdo,'vaitro_chucnang');
      $VC_VR=pickCol($vcCols,['id_vai_tro']);
      $VC_CN=pickCol($vcCols,['id_chuc_nang']);
      if(!$VC_VR || !$VC_CN) redirectWith(['type'=>'error','msg'=>'Bảng vaitro_chucnang thiếu cột map.']);

      if ($val===1){
        $st=$pdo->prepare("SELECT COUNT(*) FROM vaitro_chucnang WHERE ".qn($VC_VR)."=? AND ".qn($VC_CN)."=?");
        $st->execute([(int)$vrId,(int)$cnId]);
        if ((int)$st->fetchColumn()===0){
          $pdo->prepare("INSERT INTO vaitro_chucnang(".qn($VC_VR).",".qn($VC_CN).") VALUES(?,?)")
              ->execute([(int)$vrId,(int)$cnId]);
        }
      } else {
        $pdo->prepare("DELETE FROM vaitro_chucnang WHERE ".qn($VC_VR)."=? AND ".qn($VC_CN)."=?")
            ->execute([(int)$vrId,(int)$cnId]);
      }

      nhatky_log($pdo,'PHAN_QUYEN_VAI_TRO',"Cập nhật quyền '{$key}'=".($val?1:0)." cho vai trò '{$roleName}'",$staffTable,$id,['role'=>$roleName,'perm'=>$key,'val'=>$val]);
      redirectWith(['type'=>'ok','msg'=>'Đã cập nhật quyền (theo vai trò).'], $backTo($id,'phanquyen'));
    }

    // json mode: cấp theo từng nhân viên
    if ($permJsonCol){
      $st=$pdo->prepare("SELECT ".qn($permJsonCol)." FROM ".qn($staffTable)." WHERE ".qn($SID)."=? LIMIT 1");
      $st->execute([$id]);
      $cur = (string)($st->fetchColumn() ?? '');
      $arr = [];
      if ($cur!==''){
        $tmp = json_decode($cur,true);
        if (is_array($tmp)) $arr = $tmp;
      }
      $arr[$key] = ($val===1);

      $pdo->prepare("UPDATE ".qn($staffTable)." SET ".qn($permJsonCol)."=?".($SUPD?(", ".qn($SUPD)."=NOW()"):'')." WHERE ".qn($SID)."=?")
          ->execute([json_encode($arr, JSON_UNESCAPED_UNICODE), $id]);

      nhatky_log($pdo,'PHAN_QUYEN_NV',"Cập nhật quyền '{$key}'=".($val?1:0)." cho nhân viên #{$id}",$staffTable,$id,['perm'=>$key,'val'=>$val]);
      redirectWith(['type'=>'ok','msg'=>'Đã cập nhật quyền (theo nhân viên).'], $backTo($id,'phanquyen'));
    }

    redirectWith(['type'=>'error','msg'=>'Hệ thống chưa có cấu hình phân quyền (thiếu bảng role hoặc cột quyen_json).'], $backTo($id,'phanquyen'));
  }

  /* -------- TASKS (nếu có bảng) -------- */
  if ($action==='task_add') {
    if (!$taskTable || !$T_AID || !$T_TIEU) redirectWith(['type'=>'error','msg'=>'Chưa cấu hình bảng công việc.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);

    $title = trim((string)($_POST['tieu_de'] ?? ''));
    $desc  = trim((string)($_POST['mo_ta'] ?? ''));
    $prio  = trim((string)($_POST['muc_do'] ?? 'MED'));
    $due   = trim((string)($_POST['han_chot'] ?? ''));
    $stat  = trim((string)($_POST['trang_thai'] ?? 'TODO'));
    if ($title==='') redirectWith(['type'=>'error','msg'=>'Thiếu tiêu đề công việc.']);

    $fields=[];$vals=[];$bind=[];
    $fields[]=$T_AID; $vals[]=':aid'; $bind[':aid']=$id;
    $fields[]=$T_TIEU; $vals[]=':t'; $bind[':t']=$title;
    if ($T_MOTA){ $fields[]=$T_MOTA; $vals[]=':d'; $bind[':d']=$desc; }
    if ($T_PRIO){ $fields[]=$T_PRIO; $vals[]=':p'; $bind[':p']=$prio; }
    if ($T_DUE && $due!==''){ $fields[]=$T_DUE; $vals[]=':due'; $bind[':due']=$due; }
    if ($T_STAT){ $fields[]=$T_STAT; $vals[]=':s'; $bind[':s']=$stat; }
    if ($T_CREA){ $fields[]=$T_CREA; $vals[]='NOW()'; }

    $sql="INSERT INTO ".qn($taskTable)."(".implode(',', array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $tid = (int)$pdo->lastInsertId();

    nhatky_log($pdo,'THEM_CONG_VIEC',"Thêm công việc #{$tid} cho nhân viên #{$id}",$taskTable,$tid,['assignee'=>$id,'title'=>$title,'priority'=>$prio,'status'=>$stat,'due'=>$due]);
    redirectWith(['type'=>'ok','msg'=>'Đã thêm công việc.'], $backTo($id,'congviec'));
  }

  if ($action==='task_update') {
    if (!$taskTable || !$T_ID) redirectWith(['type'=>'error','msg'=>'Chưa cấu hình bảng công việc.']);
    $tid = (int)($_POST['task_id'] ?? 0);
    if ($tid<=0) redirectWith(['type'=>'error','msg'=>'Thiếu task_id.']);

    $newStat = trim((string)($_POST['trang_thai'] ?? ''));
    $set=[];$bind=[':id'=>$tid];
    if ($T_STAT && $newStat!==''){ $set[]=qn($T_STAT)."=:s"; $bind[':s']=$newStat; }
    if ($T_UPD){ $set[]=qn($T_UPD)."=NOW()"; }
    if (!$set) redirectWith(['type'=>'error','msg'=>'Không có cột để cập nhật công việc.']);

    $pdo->prepare("UPDATE ".qn($taskTable)." SET ".implode(',',$set)." WHERE ".qn($T_ID)."=:id")->execute($bind);
    nhatky_log($pdo,'CAP_NHAT_CONG_VIEC',"Cập nhật công việc #{$tid}",$taskTable,$tid,['status'=>$newStat]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật công việc.'], $backTo($idSelected,'congviec'));
  }

  if ($action==='task_delete') {
    if (!$taskTable || !$T_ID) redirectWith(['type'=>'error','msg'=>'Chưa cấu hình bảng công việc.']);
    $tid = (int)($_POST['task_id'] ?? 0);
    if ($tid<=0) redirectWith(['type'=>'error','msg'=>'Thiếu task_id.']);
    $pdo->prepare("DELETE FROM ".qn($taskTable)." WHERE ".qn($T_ID)."=?")->execute([$tid]);
    nhatky_log($pdo,'XOA_CONG_VIEC',"Xóa công việc #{$tid}",$taskTable,$tid);
    redirectWith(['type'=>'ok','msg'=>'Đã xóa công việc.'], $backTo($idSelected,'congviec'));
  }

  /* -------- LEAVE (nếu có bảng) -------- */
  if ($action==='leave_submit') {
    if (!$leaveTable || !$L_AID || !$L_FROM || !$L_TO || !$L_LYDO) redirectWith(['type'=>'error','msg'=>'Chưa cấu hình bảng nghỉ phép.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);

    $from = trim((string)($_POST['tu_ngay'] ?? ''));
    $to   = trim((string)($_POST['den_ngay'] ?? ''));
    $reason = trim((string)($_POST['ly_do'] ?? ''));
    if ($from==='' || $to==='' || $reason==='') redirectWith(['type'=>'error','msg'=>'Thiếu thông tin nghỉ phép (từ/đến/lý do).']);

    $fields=[];$vals=[];$bind=[];
    $fields[]=$L_AID; $vals[]=':aid'; $bind[':aid']=$id;
    $fields[]=$L_FROM;$vals[]=':f'; $bind[':f']=$from;
    $fields[]=$L_TO;  $vals[]=':t'; $bind[':t']=$to;
    $fields[]=$L_LYDO;$vals[]=':r'; $bind[':r']=$reason;
    if ($L_STAT){ $fields[]=$L_STAT; $vals[]=':s'; $bind[':s']='PENDING'; }
    if ($L_CREA){ $fields[]=$L_CREA; $vals[]='NOW()'; }

    $sql="INSERT INTO ".qn($leaveTable)."(".implode(',', array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $lid=(int)$pdo->lastInsertId();

    nhatky_log($pdo,'GUI_NGHI_PHEP',"Gửi nghỉ phép #{$lid} cho nhân viên #{$id}",$leaveTable,$lid,['assignee'=>$id,'from'=>$from,'to'=>$to,'reason'=>$reason]);
    redirectWith(['type'=>'ok','msg'=>'Đã gửi yêu cầu nghỉ phép.'], $backTo($id,'nghiphep'));
  }

  if ($action==='leave_approve' || $action==='leave_reject') {
    if (!$leaveTable || !$L_ID) redirectWith(['type'=>'error','msg'=>'Chưa cấu hình bảng nghỉ phép.']);
    $lid=(int)($_POST['leave_id'] ?? 0);
    if ($lid<=0) redirectWith(['type'=>'error','msg'=>'Thiếu leave_id.']);
    $toStatus = ($action==='leave_approve') ? 'APPROVED' : 'REJECTED';

    $set=[]; $bind=[':id'=>$lid, ':s'=>$toStatus];
    if ($L_STAT) $set[]=qn($L_STAT)."=:s";
    if ($L_APPR) { $set[]=qn($L_APPR)."=:ap"; $bind[':ap']=$myId?:null; }
    if (!$set) redirectWith(['type'=>'error','msg'=>'Không có cột để duyệt/từ chối.']);

    $pdo->prepare("UPDATE ".qn($leaveTable)." SET ".implode(',',$set)." WHERE ".qn($L_ID)."=:id")->execute($bind);

    nhatky_log($pdo, $action==='leave_approve'?'DUYET_NGHI_PHEP':'TU_CHOI_NGHI_PHEP',
      ($action==='leave_approve'?'Duyệt':'Từ chối')." nghỉ phép #{$lid}", $leaveTable,$lid, ['status'=>$toStatus,'approved_by'=>$myId]);

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật yêu cầu nghỉ phép.'], $backTo($idSelected,'nghiphep'));
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= LIST QUERY ================= */
$q = trim((string)($_GET['q'] ?? ''));
$filterRole = trim((string)($_GET['role'] ?? ''));
$filterAct  = trim((string)($_GET['act'] ?? '')); // 1/0

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$off = ($page-1)*$perPage;

$where=[]; $bind=[];
if ($q!=='') {
  $like = '%'.$q.'%';
  $parts=[];
  if ($SNAME)  { $parts[] = qn($SNAME)." LIKE :q"; }
  if ($SEMAIL) { $parts[] = qn($SEMAIL)." LIKE :q"; }
  if ($SUSER)  { $parts[] = qn($SUSER)." LIKE :q"; }
  if ($SPHONE) { $parts[] = qn($SPHONE)." LIKE :q"; }
  if ($parts){
    $where[]='('.implode(' OR ',$parts).')';
    $bind[':q']=$like;
  }
}
if ($filterRole!=='' && $SROLE){
  $where[]=qn($SROLE)."=:role";
  $bind[':role']=$filterRole;
}
if (($filterAct==='0' || $filterAct==='1') && $SACT){
  $where[]=qn($SACT)."=:act";
  $bind[':act']=(int)$filterAct;
}
$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$countSql = "SELECT COUNT(*) FROM ".qn($staffTable)." $whereSql";
$st=$pdo->prepare($countSql); $st->execute($bind);
$totalRows = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows/$perPage));

$listCols = [qn($SID)." AS _id"];
if ($SNAME)  $listCols[] = qn($SNAME)." AS ho_ten";
if ($SEMAIL) $listCols[] = qn($SEMAIL)." AS email";
if ($SUSER)  $listCols[] = qn($SUSER)." AS username";
if ($SPHONE) $listCols[] = qn($SPHONE)." AS sdt";
if ($SROLE)  $listCols[] = qn($SROLE)." AS vai_tro";
if ($SACT)   $listCols[] = qn($SACT)." AS is_active";
if ($SCREA)  $listCols[] = qn($SCREA)." AS created_at";

$orderBy = $SCREA ? qn($SCREA)." DESC" : qn($SID)." DESC";
$sqlList = "SELECT ".implode(',',$listCols)." FROM ".qn($staffTable)." $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $off";
$st=$pdo->prepare($sqlList); $st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* pick selected */
if ($idSelected<=0 && $rows) $idSelected = (int)$rows[0]['_id'];

/* ================= FETCH SELECTED DETAIL ================= */
$detail = null;
if ($idSelected>0) {
  $st=$pdo->prepare("SELECT * FROM ".qn($staffTable)." WHERE ".qn($SID)."=? LIMIT 1");
  $st->execute([$idSelected]);
  $detail = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ================= LOAD STAFF PERM (json) ================= */
$permJson = [];
if ($detail && $permJsonCol){
  $cur = (string)($detail[$permJsonCol] ?? '');
  if ($cur!==''){
    $tmp=json_decode($cur,true);
    if (is_array($tmp)) $permJson=$tmp;
  }
}

/* ================= ROLE LIST (simple) ================= */
$roleOptions = [];
if ($hasRoleTables){
  $vrCols=getCols($pdo,'vaitro');
  $VR_ID=pickCol($vrCols,['id_vai_tro','id']);
  $VR_TEN=pickCol($vrCols,['ten_vai_tro','ten','name']);
  $VR_MA=pickCol($vrCols,['ma_vai_tro','ma','code']);
  if ($VR_TEN){
    $st=$pdo->query("SELECT ".qn($VR_TEN)." AS ten FROM vaitro ORDER BY 1 ASC");
    $roleOptions = array_map(fn($r)=>(string)$r['ten'], $st->fetchAll(PDO::FETCH_ASSOC));
  } elseif ($VR_MA){
    $st=$pdo->query("SELECT ".qn($VR_MA)." AS ten FROM vaitro ORDER BY 1 ASC");
    $roleOptions = array_map(fn($r)=>(string)$r['ten'], $st->fetchAll(PDO::FETCH_ASSOC));
  }
} elseif ($SROLE) {
  // fallback: distinct role trong bảng nhân viên
  $st=$pdo->query("SELECT DISTINCT ".qn($SROLE)." AS ten FROM ".qn($staffTable)." WHERE ".qn($SROLE)." IS NOT NULL AND ".qn($SROLE)."<>'' ORDER BY 1 ASC");
  $roleOptions = array_map(fn($r)=>(string)$r['ten'], $st->fetchAll(PDO::FETCH_ASSOC));
}

/* ================= TASK/LEAVE data for selected ================= */
$tasks = [];
if ($taskTable && $detail && $T_AID && $T_ID) {
  $cols = [qn($T_ID)." AS id"];
  if ($T_TIEU) $cols[] = qn($T_TIEU)." AS tieu_de";
  if ($T_MOTA) $cols[] = qn($T_MOTA)." AS mo_ta";
  if ($T_STAT) $cols[] = qn($T_STAT)." AS trang_thai";
  if ($T_PRIO) $cols[] = qn($T_PRIO)." AS muc_do";
  if ($T_DUE)  $cols[] = qn($T_DUE)." AS han_chot";
  if ($T_CREA) $cols[] = qn($T_CREA)." AS created_at";
  $sql="SELECT ".implode(',',$cols)." FROM ".qn($taskTable)." WHERE ".qn($T_AID)."=? ORDER BY ".($T_CREA?qn($T_CREA):qn($T_ID))." DESC LIMIT 50";
  $st=$pdo->prepare($sql); $st->execute([$idSelected]);
  $tasks=$st->fetchAll(PDO::FETCH_ASSOC);
}

$leaves = [];
if ($leaveTable && $detail && $L_AID && $L_ID) {
  $cols=[qn($L_ID)." AS id"];
  if ($L_FROM) $cols[] = qn($L_FROM)." AS tu_ngay";
  if ($L_TO)   $cols[] = qn($L_TO)." AS den_ngay";
  if ($L_LYDO) $cols[] = qn($L_LYDO)." AS ly_do";
  if ($L_STAT) $cols[] = qn($L_STAT)." AS trang_thai";
  if ($L_APPR) $cols[] = qn($L_APPR)." AS nguoi_duyet_id";
  if ($L_CREA) $cols[] = qn($L_CREA)." AS created_at";
  $sql="SELECT ".implode(',',$cols)." FROM ".qn($leaveTable)." WHERE ".qn($L_AID)."=? ORDER BY ".($L_CREA?qn($L_CREA):qn($L_ID))." DESC LIMIT 50";
  $st=$pdo->prepare($sql); $st->execute([$idSelected]);
  $leaves=$st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= STATS (cards) ================= */
$staffTotal = $totalRows;
$staffActive = 0;
if ($SACT){
  $st=$pdo->query("SELECT COUNT(*) FROM ".qn($staffTable)." WHERE ".qn($SACT)."=1");
  $staffActive = (int)$st->fetchColumn();
} else {
  $staffActive = $staffTotal;
}
$pendingLeave = 0;
if ($leaveTable && $L_STAT){
  $st=$pdo->query("SELECT COUNT(*) FROM ".qn($leaveTable)." WHERE ".qn($L_STAT)."='PENDING'");
  $pendingLeave = (int)$st->fetchColumn();
}
$openTasks = 0;
if ($taskTable && $T_STAT){
  $st=$pdo->query("SELECT COUNT(*) FROM ".qn($taskTable)." WHERE ".qn($T_STAT)." IN ('TODO','DOING','OPEN')");
  $openTasks = (int)$st->fetchColumn();
}

/* ================= LOG VIEW ================= */
nhatky_log($pdo,'XEM_NHANVIEN',"Xem module Nhân viên",'nhanvien',null,['q'=>$q,'role'=>$filterRole,'act'=>$filterAct,'tab'=>$tab,'id'=>$idSelected]);

/* ================= INCLUDES (đồng bộ tên file) ================= */
$incDau  = file_exists(__DIR__ . '/includes/giaoDienDau.php') ? __DIR__ . '/includes/giaoDienDau.php' : null;
$incBen  = file_exists(__DIR__ . '/includes/thanhBen.php')    ? __DIR__ . '/includes/thanhBen.php'    : (file_exists(__DIR__ . '/includes/thanhben.php') ? __DIR__ . '/includes/thanhben.php' : null);
$incTren = file_exists(__DIR__ . '/includes/thanhTren.php')   ? __DIR__ . '/includes/thanhTren.php'   : null;
$incCuoi = file_exists(__DIR__ . '/includes/giaoDienCuoi.php')? __DIR__ . '/includes/giaoDienCuoi.php': null;

if ($incDau) require_once $incDau;
if ($incBen) require_once $incBen;
if ($incTren) require_once $incTren;

$f = flash();
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <?php if($f): ?>
      <div class="rounded-2xl border border-line bg-white shadow-card p-4">
        <div class="text-sm font-extrabold <?= ($f['type']??'')==='ok'?'text-green-600':'text-red-600' ?>">
          <?= h($f['msg'] ?? '') ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
      <div>
        <div class="text-xl font-extrabold">Nhân viên</div>
        <div class="text-xs text-muted font-bold">Quản lý tài khoản, phân quyền, công việc, nghỉ phép</div>
      </div>

      <div class="flex items-center gap-2">
        <a href="nhanvien.php?tab=them" class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold text-sm shadow-soft">Thêm nhân viên</a>
      </div>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-primary/10 grid place-items-center">
            <span class="material-symbols-outlined text-primary">badge</span>
          </div>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Tổng nhân viên</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($staffTotal) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-green-50 grid place-items-center">
            <span class="material-symbols-outlined text-green-600">verified_user</span>
          </div>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Đang hoạt động</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($staffActive) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-amber-50 grid place-items-center">
            <span class="material-symbols-outlined text-amber-700">event_busy</span>
          </div>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Nghỉ phép chờ duyệt</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($pendingLeave) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-purple-50 grid place-items-center">
            <span class="material-symbols-outlined text-purple-600">assignment</span>
          </div>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Công việc mở</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($openTasks) ?></div>
      </div>
    </div>

    <!-- Main layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- LEFT: list -->
      <div class="lg:col-span-7 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between gap-3">
          <div class="text-base font-extrabold">Danh sách nhân viên</div>
          <div class="text-xs text-muted font-bold">Bảng: <?= h($staffTable) ?></div>
        </div>

        <form class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3" method="get">
          <input type="hidden" name="tab" value="<?= h($tab) ?>">
          <div class="md:col-span-6">
            <input name="q" value="<?= h($q) ?>" placeholder="Tìm tên / email / sđt / username..." class="w-full px-4 py-2.5 rounded-xl border border-line bg-white text-sm font-bold outline-none focus:ring-2 focus:ring-primary/20">
          </div>
          <div class="md:col-span-3">
            <select name="role" class="w-full px-4 py-2.5 rounded-xl border border-line bg-white text-sm font-extrabold">
              <option value="">Tất cả vai trò</option>
              <?php foreach($roleOptions as $ro): ?>
                <option value="<?= h($ro) ?>" <?= $filterRole===$ro?'selected':'' ?>><?= h($ro) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <select name="act" class="w-full px-4 py-2.5 rounded-xl border border-line bg-white text-sm font-extrabold">
              <option value="">Tất cả trạng thái</option>
              <option value="1" <?= $filterAct==='1'?'selected':'' ?>>Hoạt động</option>
              <option value="0" <?= $filterAct==='0'?'selected':'' ?>>Tạm khóa</option>
            </select>
          </div>
          <div class="md:col-span-1">
            <button class="w-full px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-extrabold">Lọc</button>
          </div>
        </form>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Nhân viên</th>
                <th class="text-left py-3 pr-3">Liên hệ</th>
                <th class="text-left py-3 pr-3">Vai trò</th>
                <th class="text-left py-3 pr-3">Trạng thái</th>
                <th class="text-right py-3 pr-0">Xem</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($rows as $r):
                $rid=(int)$r['_id'];
                $name=(string)($r['ho_ten'] ?? $r['username'] ?? ('#'.$rid));
                $emailRow=(string)($r['email'] ?? '');
                $phoneRow=(string)($r['sdt'] ?? '');
                $roleRow=(string)($r['vai_tro'] ?? '');
                $isAct = isset($r['is_active']) ? ((int)$r['is_active']===1) : true;
                $isSel = ($rid===$idSelected);
                $initial = mb_strtoupper(mb_substr(trim($name),0,1,'UTF-8'),'UTF-8');
              ?>
                <tr class="<?= $isSel?'bg-primary/5':'' ?>">
                  <td class="py-3 pr-3">
                    <div class="flex items-center gap-3">
                      <div class="size-10 rounded-2xl bg-slate-100 grid place-items-center font-extrabold"><?= h($initial) ?></div>
                      <div>
                        <div class="font-extrabold"><?= h($name) ?></div>
                        <div class="text-xs text-muted font-bold">ID: <?= $rid ?><?= isset($r['created_at']) && $r['created_at']?(' · Tạo: '.h($r['created_at'])):'' ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 pr-3">
                    <div class="font-bold"><?= h($emailRow) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($phoneRow) ?></div>
                  </td>
                  <td class="py-3 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700"><?= h($roleRow ?: '-') ?></span>
                  </td>
                  <td class="py-3 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $isAct?'bg-green-50 text-green-600':'bg-red-50 text-red-600' ?>">
                      <?= $isAct?'Hoạt động':'Tạm khóa' ?>
                    </span>
                  </td>
                  <td class="py-3 pr-0 text-right">
                    <a href="nhanvien.php?id=<?= $rid ?>&tab=<?= h($tab) ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-900 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$rows): ?>
                <tr><td colspan="5" class="py-8 text-center text-slate-500 font-bold">Không có dữ liệu</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?> · Tổng <?= number_format($totalRows) ?></div>
          <div class="flex items-center gap-2">
            <?php
              $base = "nhanvien.php?tab=".urlencode($tab)."&q=".urlencode($q)."&role=".urlencode($filterRole)."&act=".urlencode($filterAct);
            ?>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.max(1,$page-1)) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.min($totalPages,$page+1)) ?>">Sau</a>
          </div>
        </div>
      </div>

      <!-- RIGHT: detail -->
      <div class="lg:col-span-5 space-y-6">

        <!-- Tabs -->
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="flex items-center justify-between">
            <div class="text-base font-extrabold"><?= $tab==='them'?'Thêm nhân viên':'Chi tiết nhân viên' ?></div>
            <div class="text-xs text-muted font-bold"><?= $detail?('ID: '.$idSelected):'Chọn 1 nhân viên' ?></div>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=thongtin" class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='thongtin'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Thông tin</a>
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=phanquyen" class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='phanquyen'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Phân quyền</a>
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=congviec" class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='congviec'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Công việc</a>
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=nghiphep" class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='nghiphep'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Nghỉ phép</a>
            <a href="nhanvien.php?tab=them" class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='them'?'bg-slate-900 text-white border-slate-900':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">+ Thêm</a>
          </div>

          <!-- Tab content -->
          <div class="mt-5">

            <?php if($tab==='them'): ?>
              <form method="post" class="space-y-3">
                <input type="hidden" name="action" value="add_staff">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <?php if($SUSER): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Username</div>
                      <input name="username" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: nv01">
                    </div>
                  <?php endif; ?>
                  <?php if($SEMAIL): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Email</div>
                      <input name="email" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: nv01@gmail.com">
                    </div>
                  <?php endif; ?>
                  <?php if($SNAME): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Họ tên</div>
                      <input name="ho_ten" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: Nguyễn Văn A">
                    </div>
                  <?php endif; ?>
                  <?php if($SPHONE): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Số điện thoại</div>
                      <input name="sdt" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: 09xxxxxxxx">
                    </div>
                  <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <?php if($SROLE): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Vai trò</div>
                      <input name="vai_tro" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="admin / staff" value="staff">
                      <div class="text-[11px] text-muted font-bold mt-1">Nếu bạn có bảng vaitro thì nên dùng đúng tên/ma vai trò.</div>
                    </div>
                  <?php endif; ?>
                  <?php if($SPASS): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Mật khẩu</div>
                      <input name="mat_khau" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Để trống sẽ tự sinh">
                    </div>
                  <?php endif; ?>
                </div>

                <button class="w-full px-4 py-3 rounded-xl bg-primary text-white font-extrabold">Thêm nhân viên</button>
                <?php if(!$isAdmin): ?>
                  <div class="text-xs text-red-600 font-extrabold">Tài khoản của bạn không phải ADMIN nên sẽ không thêm được.</div>
                <?php endif; ?>
              </form>

            <?php elseif(!$detail): ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Chưa chọn nhân viên</div>
                <div class="text-xs text-muted font-bold mt-1">Chọn 1 dòng ở danh sách bên trái để xem chi tiết.</div>
              </div>

            <?php elseif($tab==='thongtin'): ?>
              <?php
                $dName = (string)($SNAME ? ($detail[$SNAME] ?? '') : '');
                $dEmail= (string)($SEMAIL?($detail[$SEMAIL]??''):'');
                $dUser = (string)($SUSER ? ($detail[$SUSER] ?? '') : '');
                $dPhone= (string)($SPHONE?($detail[$SPHONE]??''):'');
                $dRole = (string)($SROLE ? ($detail[$SROLE] ?? '') : '');
                $dAct  = $SACT ? ((int)($detail[$SACT] ?? 1)===1) : true;
              ?>
              <div class="flex items-center gap-3">
                <div class="size-12 rounded-2xl bg-slate-100 grid place-items-center font-extrabold">
                  <?= h(mb_strtoupper(mb_substr(trim($dName?:($dUser?:('#'.$idSelected))),0,1,'UTF-8'),'UTF-8')) ?>
                </div>
                <div>
                  <div class="text-base font-extrabold"><?= h($dName ?: $dUser ?: ('#'.$idSelected)) ?></div>
                  <div class="text-xs text-muted font-bold"><?= h($dEmail) ?><?= $dPhone?(' · '.h($dPhone)):'' ?></div>
                </div>
              </div>

              <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_staff">
                <input type="hidden" name="id" value="<?= (int)$idSelected ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <?php if($SNAME): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Họ tên</div>
                      <input name="ho_ten" value="<?= h($dName) ?>" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold">
                    </div>
                  <?php endif; ?>
                  <?php if($SEMAIL): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Email</div>
                      <input name="email" value="<?= h($dEmail) ?>" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold">
                    </div>
                  <?php endif; ?>
                  <?php if($SPHONE): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Số điện thoại</div>
                      <input name="sdt" value="<?= h($dPhone) ?>" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold">
                    </div>
                  <?php endif; ?>
                  <?php if($SROLE): ?>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Vai trò</div>
                      <input name="vai_tro" value="<?= h($dRole) ?>" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="admin/staff...">
                    </div>
                  <?php endif; ?>
                </div>

                <button class="w-full px-4 py-3 rounded-xl bg-slate-900 text-white font-extrabold">Lưu thông tin</button>
                <?php if(!$isAdmin): ?>
                  <div class="text-xs text-red-600 font-extrabold">Chỉ ADMIN được cập nhật thông tin nhân viên.</div>
                <?php endif; ?>
              </form>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <form method="post" class="rounded-2xl border border-line p-4">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                  <input type="hidden" name="to" value="<?= $dAct?0:1 ?>">
                  <div class="text-sm font-extrabold">Trạng thái</div>
                  <div class="text-xs text-muted font-bold mt-1"><?= $dAct?'Đang hoạt động':'Tạm khóa' ?></div>
                  <button class="mt-3 w-full px-4 py-2.5 rounded-xl <?= $dAct?'bg-red-50 text-red-600':'bg-green-50 text-green-600' ?> font-extrabold">
                    <?= $dAct?'Khóa nhân viên':'Mở khóa nhân viên' ?>
                  </button>
                  <?php if(!$isAdmin): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Chỉ ADMIN được thao tác.</div><?php endif; ?>
                </form>

                <form method="post" class="rounded-2xl border border-line p-4">
                  <input type="hidden" name="action" value="reset_pass">
                  <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                  <div class="text-sm font-extrabold">Reset mật khẩu</div>
                  <div class="text-xs text-muted font-bold mt-1">Để trống sẽ tự sinh mật khẩu.</div>
                  <input name="new_pass" class="mt-3 w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Mật khẩu mới (tuỳ chọn)">
                  <button class="mt-3 w-full px-4 py-2.5 rounded-xl bg-primary text-white font-extrabold">Reset</button>
                  <?php if(!$SPASS): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Bảng thiếu cột mật khẩu.</div><?php endif; ?>
                  <?php if(!$isAdmin): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Chỉ ADMIN được thao tác.</div><?php endif; ?>
                </form>
              </div>

            <?php elseif($tab==='phanquyen'): ?>
              <?php
                $dRole = (string)($SROLE ? ($detail[$SROLE] ?? '') : '');
                $mode = $hasRoleTables ? 'ROLE_TABLES' : ($permJsonCol ? 'JSON' : 'NONE');
              ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Chế độ phân quyền: <?= h($mode) ?></div>
                <div class="text-xs text-muted font-bold mt-1">
                  <?php if($mode==='ROLE_TABLES'): ?>
                    Quyền áp dụng theo <b>vai trò</b> (vaitro / chucnang / vaitro_chucnang).
                  <?php elseif($mode==='JSON'): ?>
                    Quyền áp dụng theo <b>từng nhân viên</b> (cột <?= h($permJsonCol) ?>).
                  <?php else: ?>
                    Chưa có cấu hình phân quyền (thiếu bảng role hoặc cột json).
                  <?php endif; ?>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 gap-3">
                <?php foreach($MODULES as $k=>$m):
                  $enabled = false;

                  if ($mode==='JSON'){
                    $enabled = (bool)($permJson[$k] ?? false);
                  } elseif ($mode==='ROLE_TABLES' && $dRole!==''){
                    // kiểm tra quyền theo vai trò (không cache, đơn giản)
                    $vrCols=getCols($pdo,'vaitro');
                    $VR_ID=pickCol($vrCols,['id_vai_tro','id']);
                    $VR_TEN=pickCol($vrCols,['ten_vai_tro','ten','name']);
                    $VR_MA=pickCol($vrCols,['ma_vai_tro','ma','code']);
                    $cnCols=getCols($pdo,'chucnang');
                    $CN_ID=pickCol($cnCols,['id_chuc_nang','id']);
                    $CN_MA=pickCol($cnCols,['ma_chuc_nang','ma','code','key']);
                    $vcCols=getCols($pdo,'vaitro_chucnang');
                    $VC_VR=pickCol($vcCols,['id_vai_tro']);
                    $VC_CN=pickCol($vcCols,['id_chuc_nang']);

                    if ($VR_ID && $CN_ID && $CN_MA && $VC_VR && $VC_CN){
                      $vrId=null;
                      if ($VR_TEN){
                        $st=$pdo->prepare("SELECT ".qn($VR_ID)." FROM vaitro WHERE ".qn($VR_TEN)."=? LIMIT 1");
                        $st->execute([$dRole]); $vrId=$st->fetchColumn();
                      }
                      if($vrId===null && $VR_MA){
                        $st=$pdo->prepare("SELECT ".qn($VR_ID)." FROM vaitro WHERE ".qn($VR_MA)."=? LIMIT 1");
                        $st->execute([$dRole]); $vrId=$st->fetchColumn();
                      }

                      if($vrId!==null){
                        $st=$pdo->prepare("SELECT ".qn($CN_ID)." FROM chucnang WHERE ".qn($CN_MA)."=? LIMIT 1");
                        $st->execute([$k]); $cnId=$st->fetchColumn();
                        if($cnId!==null){
                          $st=$pdo->prepare("SELECT COUNT(*) FROM vaitro_chucnang WHERE ".qn($VC_VR)."=? AND ".qn($VC_CN)."=?");
                          $st->execute([(int)$vrId,(int)$cnId]);
                          $enabled = ((int)$st->fetchColumn()>0);
                        }
                      }
                    }
                  }
                ?>
                  <div class="rounded-2xl border border-line p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <div class="size-10 rounded-2xl bg-slate-100 grid place-items-center">
                        <span class="material-symbols-outlined text-slate-600"><?= h($m['icon']) ?></span>
                      </div>
                      <div>
                        <div class="text-sm font-extrabold"><?= h($m['label']) ?></div>
                        <div class="text-xs text-muted font-bold">Key: <?= h($k) ?></div>
                      </div>
                    </div>

                    <form method="post">
                      <input type="hidden" name="action" value="perm_toggle">
                      <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                      <input type="hidden" name="perm_key" value="<?= h($k) ?>">
                      <input type="hidden" name="perm_val" value="<?= $enabled?0:1 ?>">
                      <button class="px-4 py-2 rounded-xl text-xs font-extrabold <?= $enabled?'bg-green-50 text-green-600':'bg-slate-100 text-slate-700' ?>">
                        <?= $enabled?'Đang cho phép':'Đang chặn' ?>
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if($mode==='ROLE_TABLES' && !$dRole): ?>
                <div class="mt-3 text-xs text-red-600 font-extrabold">Nhân viên chưa có vai trò, hãy gán vai trò ở tab Thông tin.</div>
              <?php endif; ?>
              <?php if(!$isAdmin): ?>
                <div class="mt-3 text-xs text-red-600 font-extrabold">Chỉ ADMIN được chỉnh phân quyền.</div>
              <?php endif; ?>

            <?php elseif($tab==='congviec'): ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Phân công việc</div>
                <div class="text-xs text-muted font-bold mt-1">
                  <?php if(!$taskTable): ?>
                    Chưa có bảng công việc. Nếu bạn muốn dùng tính năng này, tạo 1 bảng theo mẫu SQL ở cuối file.
                  <?php else: ?>
                    Bảng: <?= h($taskTable) ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if($taskTable && $T_AID && $T_TIEU): ?>
                <form method="post" class="mt-4 space-y-3">
                  <input type="hidden" name="action" value="task_add">
                  <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                  <div>
                    <div class="text-xs font-extrabold text-slate-600 mb-1">Tiêu đề</div>
                    <input name="tieu_de" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: Xử lý đơn tồn, cập nhật sản phẩm...">
                  </div>
                  <div>
                    <div class="text-xs font-extrabold text-slate-600 mb-1">Mô tả</div>
                    <textarea name="mo_ta" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Ghi chú chi tiết..."></textarea>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Mức độ</div>
                      <select name="muc_do" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-extrabold">
                        <option value="LOW">LOW</option>
                        <option value="MED" selected>MED</option>
                        <option value="HIGH">HIGH</option>
                        <option value="URGENT">URGENT</option>
                      </select>
                    </div>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Trạng thái</div>
                      <select name="trang_thai" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-extrabold">
                        <option value="TODO" selected>TODO</option>
                        <option value="DOING">DOING</option>
                        <option value="DONE">DONE</option>
                        <option value="CANCELED">CANCELED</option>
                      </select>
                    </div>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Hạn chót</div>
                      <input name="han_chot" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="YYYY-MM-DD hoặc datetime">
                    </div>
                  </div>
                  <button class="w-full px-4 py-3 rounded-xl bg-primary text-white font-extrabold">Giao việc</button>
                  <?php if(!$isAdmin): ?><div class="text-xs text-red-600 font-extrabold">Chỉ ADMIN được giao việc.</div><?php endif; ?>
                </form>

                <div class="mt-5">
                  <div class="text-sm font-extrabold mb-2">Danh sách (50 gần nhất)</div>
                  <div class="space-y-3">
                    <?php foreach($tasks as $t):
                      $tid=(int)($t['id'] ?? 0);
                      $tt=(string)($t['tieu_de'] ?? '');
                      $stt=(string)($t['trang_thai'] ?? '');
                      $prio=(string)($t['muc_do'] ?? '');
                      $due=(string)($t['han_chot'] ?? '');
                      $desc=(string)($t['mo_ta'] ?? '');
                      $badge = ($stt==='DONE')?'bg-green-50 text-green-600':(($stt==='DOING')?'bg-amber-50 text-amber-700':(($stt==='CANCELED')?'bg-red-50 text-red-600':'bg-slate-100 text-slate-700'));
                    ?>
                      <div class="rounded-2xl border border-line p-4">
                        <div class="flex items-start justify-between gap-3">
                          <div>
                            <div class="font-extrabold text-sm"><?= h($tt ?: ('Task #'.$tid)) ?></div>
                            <div class="text-xs text-muted font-bold mt-1">
                              <?= $prio?('Ưu tiên: '.h($prio).' · '):'' ?>
                              <?= $due?('Hạn: '.h($due).' · '):'' ?>
                              <?= isset($t['created_at']) && $t['created_at']?('Tạo: '.h($t['created_at'])):'' ?>
                            </div>
                          </div>
                          <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $badge ?>"><?= h($stt ?: 'TODO') ?></span>
                        </div>

                        <?php if($desc): ?>
                          <div class="mt-2 text-sm text-slate-700 font-bold"><?= h($desc) ?></div>
                        <?php endif; ?>

                        <div class="mt-3 flex items-center justify-between gap-2">
                          <form method="post" class="flex items-center gap-2">
                            <input type="hidden" name="action" value="task_update">
                            <input type="hidden" name="task_id" value="<?= $tid ?>">
                            <select name="trang_thai" class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold">
                              <option value="TODO" <?= $stt==='TODO'?'selected':'' ?>>TODO</option>
                              <option value="DOING" <?= $stt==='DOING'?'selected':'' ?>>DOING</option>
                              <option value="DONE" <?= $stt==='DONE'?'selected':'' ?>>DONE</option>
                              <option value="CANCELED" <?= $stt==='CANCELED'?'selected':'' ?>>CANCELED</option>
                            </select>
                            <button class="px-3 py-2 rounded-xl bg-slate-900 text-white text-xs font-extrabold">Cập nhật</button>
                          </form>

                          <form method="post" onsubmit="return confirm('Xóa công việc này?');">
                            <input type="hidden" name="action" value="task_delete">
                            <input type="hidden" name="task_id" value="<?= $tid ?>">
                            <button class="px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-extrabold">Xóa</button>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                    <?php if(!$tasks): ?>
                      <div class="text-sm text-muted font-bold">Chưa có công việc.</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php elseif($tab==='nghiphep'): ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Nghỉ phép</div>
                <div class="text-xs text-muted font-bold mt-1">
                  <?php if(!$leaveTable): ?>
                    Chưa có bảng nghỉ phép. Nếu bạn muốn dùng tính năng này, tạo 1 bảng theo mẫu SQL ở cuối file.
                  <?php else: ?>
                    Bảng: <?= h($leaveTable) ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if($leaveTable && $L_AID && $L_FROM && $L_TO && $L_LYDO): ?>
                <form method="post" class="mt-4 space-y-3">
                  <input type="hidden" name="action" value="leave_submit">
                  <input type="hidden" name="id" value="<?= (int)$idSelected ?>">

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Từ ngày</div>
                      <input name="tu_ngay" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="YYYY-MM-DD">
                    </div>
                    <div>
                      <div class="text-xs font-extrabold text-slate-600 mb-1">Đến ngày</div>
                      <input name="den_ngay" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="YYYY-MM-DD">
                    </div>
                  </div>
                  <div>
                    <div class="text-xs font-extrabold text-slate-600 mb-1">Lý do</div>
                    <textarea name="ly_do" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="vd: việc cá nhân, ốm..."></textarea>
                  </div>
                  <button class="w-full px-4 py-3 rounded-xl bg-primary text-white font-extrabold">Gửi yêu cầu</button>
                </form>

                <div class="mt-5">
                  <div class="text-sm font-extrabold mb-2">Lịch sử (50 gần nhất)</div>
                  <div class="space-y-3">
                    <?php foreach($leaves as $lv):
                      $lid=(int)($lv['id'] ?? 0);
                      $from=(string)($lv['tu_ngay'] ?? '');
                      $to=(string)($lv['den_ngay'] ?? '');
                      $reason=(string)($lv['ly_do'] ?? '');
                      $stt=(string)($lv['trang_thai'] ?? 'PENDING');
                      $badge = ($stt==='APPROVED')?'bg-green-50 text-green-600':(($stt==='REJECTED')?'bg-red-50 text-red-600':'bg-amber-50 text-amber-700');
                    ?>
                      <div class="rounded-2xl border border-line p-4">
                        <div class="flex items-start justify-between gap-3">
                          <div>
                            <div class="font-extrabold text-sm">Đơn #<?= $lid ?> · <?= h($from) ?> → <?= h($to) ?></div>
                            <div class="text-xs text-muted font-bold mt-1"><?= h($reason) ?></div>
                            <div class="text-xs text-muted font-bold mt-1"><?= isset($lv['created_at']) && $lv['created_at']?('Tạo: '.h($lv['created_at'])):'' ?></div>
                          </div>
                          <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $badge ?>"><?= h($stt) ?></span>
                        </div>

                        <?php if($isAdmin && $stt==='PENDING'): ?>
                          <div class="mt-3 flex items-center gap-2">
                            <form method="post" class="flex-1">
                              <input type="hidden" name="action" value="leave_approve">
                              <input type="hidden" name="leave_id" value="<?= $lid ?>">
                              <button class="w-full px-3 py-2 rounded-xl bg-green-50 text-green-600 text-xs font-extrabold">Duyệt</button>
                            </form>
                            <form method="post" class="flex-1">
                              <input type="hidden" name="action" value="leave_reject">
                              <input type="hidden" name="leave_id" value="<?= $lid ?>">
                              <button class="w-full px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-extrabold">Từ chối</button>
                            </form>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    <?php if(!$leaves): ?>
                      <div class="text-sm text-muted font-bold">Chưa có đơn nghỉ phép.</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <div class="text-sm text-muted font-bold">Tab không hợp lệ.</div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Gợi ý cấu hình nếu thiếu bảng -->
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="text-sm font-extrabold">Gợi ý (nếu bạn muốn bật đủ tính năng)</div>
          <div class="text-xs text-muted font-bold mt-1">
            Nếu chưa có bảng <b>công việc</b> / <b>nghỉ phép</b> thì tạo theo mẫu dưới (không tự tạo trong code).
          </div>

          <details class="mt-3">
            <summary class="cursor-pointer text-xs font-extrabold text-slate-700">SQL mẫu: nhanvien_congviec</summary>
            <pre class="mt-2 text-xs bg-slate-50 border border-line rounded-xl p-3 overflow-auto"><?php echo h(
"CREATE TABLE nhanvien_congviec (
  id_task INT AUTO_INCREMENT PRIMARY KEY,
  id_admin INT NOT NULL,
  tieu_de VARCHAR(255) NOT NULL,
  mo_ta TEXT,
  muc_do VARCHAR(20) DEFAULT 'MED',
  han_chot DATETIME NULL,
  trang_thai VARCHAR(20) DEFAULT 'TODO',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);"
); ?></pre>
          </details>

          <details class="mt-3">
            <summary class="cursor-pointer text-xs font-extrabold text-slate-700">SQL mẫu: nhanvien_nghiphep</summary>
            <pre class="mt-2 text-xs bg-slate-50 border border-line rounded-xl p-3 overflow-auto"><?php echo h(
"CREATE TABLE nhanvien_nghiphep (
  id_nghi INT AUTO_INCREMENT PRIMARY KEY,
  id_admin INT NOT NULL,
  tu_ngay DATE NOT NULL,
  den_ngay DATE NOT NULL,
  ly_do TEXT NOT NULL,
  trang_thai VARCHAR(20) DEFAULT 'PENDING',
  nguoi_duyet_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);"
); ?></pre>
          </details>

          <div class="text-[11px] text-muted font-bold mt-3">
            Nếu bạn muốn phân quyền “đúng chuẩn” theo hệ thống: dùng vaitro / chucnang / vaitro_chucnang.
            Nếu muốn phân quyền theo từng nhân viên: thêm 1 cột JSON (quyen_json) vào bảng <?= h($staffTable) ?>.
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php if ($incCuoi) require_once $incCuoi; ?>
