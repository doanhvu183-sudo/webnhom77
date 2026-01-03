<?php
// admin/nhanvien.php
// Clean version: dùng hamChung.php (không redeclare), list trái + panel phải, có phân quyền + log

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// dùng helpers nếu có
if (file_exists(__DIR__ . '/includes/helpers.php')) require_once __DIR__ . '/includes/helpers.php';
// dùng hamChung (bạn đã gửi)
if (file_exists(__DIR__ . '/includes/hamChung.php')) require_once __DIR__ . '/includes/hamChung.php';

// ===== Fallback tối thiểu nếu thiếu hamChung (tránh trắng trang) =====
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('qn')) { function qn($s){ return '`'.str_replace('`','',(string)$s).'`'; } }
if (!function_exists('redirectWith')) {
  function redirectWith(array $flash, ?string $to=null): void {
    $_SESSION['flash'] = $flash;
    header('Location: '.($to ?: ($_SERVER['HTTP_REFERER'] ?? 'nhanvien.php')));
    exit;
  }
}
if (!function_exists('flash_get')) {
  function flash_get(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
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
    foreach ($candidates as $c) if (isset($set[$c])) return $c;
    return null;
  }
}
if (!function_exists('auth_me')) {
  function auth_me(): array {
    $me = $_SESSION['admin'] ?? [];
    if (!is_array($me)) $me = [];
    $myId = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_user'] ?? 0);
    $vaiTro = (string)($me['vai_tro'] ?? $me['role'] ?? 'admin');
    $vaiTro = strtolower($vaiTro);
    $isAdmin = ($vaiTro === 'admin');
    return [$me, $myId, $vaiTro, $isAdmin];
  }
}
if (!function_exists('requirePermission')) {
  function requirePermission(string $key, ?PDO $pdo = null): void { /* optional */ }
}
if (!function_exists('nhatky_log')) {
  function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, ?int $id_ban_ghi=null, $data=null): void { /* optional */ }
}

// ===== AUTH =====
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE = 'nhanvien';
$PAGE_TITLE = 'Nhân viên';

// Nếu có hệ thống permission thì check
if (function_exists('requirePermission')) {
  try { requirePermission('nhanvien', $pdo); } catch (Throwable $e) {}
}

// ===== STAFF TABLE =====
$staffTable = null;
if (tableExists($pdo,'admin')) $staffTable='admin';
elseif (tableExists($pdo,'nhanvien')) $staffTable='nhanvien';
else die("Thiếu bảng admin/nhanvien.");

$SCols = getCols($pdo, $staffTable);

$SID    = pickCol($SCols, ['id_admin','id_nhan_vien','id','id_user']);
$SUSER  = pickCol($SCols, ['username','ten_dang_nhap','tai_khoan']);
$SEMAIL = pickCol($SCols, ['email']);
$SNAME  = pickCol($SCols, ['ho_ten','full_name','ten','ten_hien_thi']);
$SPHONE = pickCol($SCols, ['sdt','so_dien_thoai','phone']);
$SROLE  = pickCol($SCols, ['vai_tro','role','quyen']);
$SACT   = pickCol($SCols, ['is_active','trang_thai','kich_hoat','hien_thi']);
$SPASS  = pickCol($SCols, ['mat_khau','password','pass_hash']);
$SCREA  = pickCol($SCols, ['created_at','ngay_tao']);
$SUPD   = pickCol($SCols, ['updated_at','ngay_cap_nhat']);

if (!$SID) die("Bảng {$staffTable} thiếu cột ID.");

// ===== Permission storage (ưu tiên admin_quyen theo requirePermission bạn dán) =====
$permMode = 'NONE';
$permTable = null;
$permCols = [];
$P_ID = $P_KEY = $P_OK = null;

if (tableExists($pdo,'admin_quyen')) {
  $permMode = 'ADMIN_QUYEN';
  $permTable = 'admin_quyen';
  $permCols = getCols($pdo,'admin_quyen');
  $P_ID  = pickCol($permCols, ['id_admin','admin_id','id_user']);
  $P_KEY = pickCol($permCols, ['chuc_nang','quyen','permission_key','key']);
  $P_OK  = pickCol($permCols, ['duoc_phep','allow','is_allow','truy_cap']);
  if (!$P_ID || !$P_KEY || !$P_OK) $permMode = 'NONE';
}

// module keys (khớp sidebar & requirePermission)
$MODULES = [
  'tong_quan'     => ['label'=>'Tổng quan',     'icon'=>'grid_view'],
  'sanpham'       => ['label'=>'Sản phẩm',      'icon'=>'inventory_2'],
  'danhmuc'       => ['label'=>'Danh mục',      'icon'=>'category'],
  'theodoi_gia'   => ['label'=>'Theo dõi giá',  'icon'=>'monitoring'],
  'donhang'       => ['label'=>'Đơn hàng',      'icon'=>'shopping_bag'],
  'khachhang'     => ['label'=>'Khách hàng',    'icon'=>'groups'],
  'tonkho'        => ['label'=>'Tồn kho',       'icon'=>'warehouse'],
  'phieunhap'     => ['label'=>'Phiếu nhập',    'icon'=>'inbox'],
  'phieuxuat'     => ['label'=>'Phiếu xuất',    'icon'=>'outbox'],
  'lichsu_kho'    => ['label'=>'Lịch sử kho',   'icon'=>'swap_horiz'],
  'tonkho_nhatky' => ['label'=>'Tồn kho nhật ký','icon'=>'history'],
  'voucher'       => ['label'=>'Voucher',      'icon'=>'sell'],
  'baocao'        => ['label'=>'Báo cáo',       'icon'=>'bar_chart'],
  'timkiem'       => ['label'=>'Tìm kiếm',      'icon'=>'search'],
  'thongbao'      => ['label'=>'Thông báo',     'icon'=>'notifications'],
  'nhatky'        => ['label'=>'Nhật ký',       'icon'=>'history_edu'],
  'nhanvien'      => ['label'=>'Nhân viên',     'icon'=>'badge'],
  'caidat'        => ['label'=>'Cài đặt',       'icon'=>'settings'],
];

// ===== Helpers =====
function rand_password(int $len=10): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#$%';
  $out='';
  for($i=0;$i<$len;$i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
  return $out;
}

$tab = $_GET['tab'] ?? 'thongtin';
$idSelected = (int)($_GET['id'] ?? 0);

// ===== POST actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $id = (int)($_POST['id'] ?? $idSelected);

  // chỉ ADMIN được CRUD
  $adminActions = ['add_staff','update_staff','toggle_active','reset_pass','perm_toggle'];
  if (in_array($action, $adminActions, true) && !$isAdmin) {
    redirectWith(['type'=>'error','msg'=>'Bạn không có quyền thao tác.']);
  }

  $backTo = function(?int $id=null, ?string $tab=null){
    $qs=[];
    if ($id !== null) $qs[]='id='.(int)$id;
    if ($tab !== null) $qs[]='tab='.urlencode($tab);
    return 'nhanvien.php'.($qs?('?'.implode('&',$qs)):'');
  };

  if ($action === 'add_staff') {
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $name     = trim((string)($_POST['ho_ten'] ?? ''));
    $phone    = trim((string)($_POST['sdt'] ?? ''));
    $role     = trim((string)($_POST['vai_tro'] ?? 'nhanvien'));
    $passRaw  = trim((string)($_POST['mat_khau'] ?? ''));
    if ($passRaw === '') $passRaw = rand_password(10);

    if ($SUSER && $username==='') redirectWith(['type'=>'error','msg'=>'Thiếu username.']);
    if ($SEMAIL && $email==='') redirectWith(['type'=>'error','msg'=>'Thiếu email.']);
    if ($SPASS && $passRaw==='') redirectWith(['type'=>'error','msg'=>'Thiếu mật khẩu.']);

    // unique check
    if ($SUSER && $username!=='') {
      $st=$pdo->prepare("SELECT COUNT(*) FROM ".qn($staffTable)." WHERE ".qn($SUSER)."=?");
      $st->execute([$username]);
      if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Username đã tồn tại.']);
    }
    if ($SEMAIL && $email!=='') {
      $st=$pdo->prepare("SELECT COUNT(*) FROM ".qn($staffTable)." WHERE ".qn($SEMAIL)."=?");
      $st->execute([$email]);
      if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Email đã tồn tại.']);
    }

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

    if (!$fields) redirectWith(['type'=>'error','msg'=>'Schema bảng nhân viên không phù hợp để thêm.']);

    $sql="INSERT INTO ".qn($staffTable)."(".implode(',', array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    $newId = (int)$pdo->lastInsertId();
    nhatky_log($pdo,'THEM_NHANVIEN',"Thêm nhân viên #{$newId}",$staffTable,$newId,['username'=>$username,'email'=>$email,'role'=>$role]);

    redirectWith(['type'=>'ok','msg'=>"Đã thêm nhân viên. Mật khẩu: {$passRaw}"], $backTo($newId,'thongtin'));
  }

  if ($action === 'update_staff') {
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
    $pdo->prepare("UPDATE ".qn($staffTable)." SET ".implode(',',$set)." WHERE ".qn($SID)."=:id")->execute($bind);

    nhatky_log($pdo,'CAP_NHAT_NHANVIEN',"Cập nhật nhân viên #{$id}",$staffTable,$id,['email'=>$email,'ho_ten'=>$name,'sdt'=>$phone,'role'=>$role]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật nhân viên.'], $backTo($id,'thongtin'));
  }

  if ($action === 'toggle_active') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    if (!$SACT) redirectWith(['type'=>'error','msg'=>'Bảng nhân viên không có cột is_active/trang_thai.']);

    $to = (int)($_POST['to'] ?? 0);
    $sql = "UPDATE ".qn($staffTable)." SET ".qn($SACT)."=?".($SUPD?(", ".qn($SUPD)."=NOW()"):'')." WHERE ".qn($SID)."=?";
    $pdo->prepare($sql)->execute([$to,$id]);

    nhatky_log($pdo, $to?'MO_KHOA_NHANVIEN':'KHOA_NHANVIEN', ($to?'Mở khóa':'Khóa')." nhân viên #{$id}",$staffTable,$id,['to'=>$to]);
    redirectWith(['type'=>'ok','msg'=>$to?'Đã mở khóa nhân viên.':'Đã khóa nhân viên.'], $backTo($id,'thongtin'));
  }

  if ($action === 'reset_pass') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    if (!$SPASS) redirectWith(['type'=>'error','msg'=>'Bảng nhân viên không có cột mật khẩu. (‘mat_khau’/‘pass_hash’…)']);

    $newPass = trim((string)($_POST['new_pass'] ?? ''));
    if ($newPass==='') $newPass = rand_password(10);

    $sql = "UPDATE ".qn($staffTable)." SET ".qn($SPASS)."=?".($SUPD?(", ".qn($SUPD)."=NOW()"):'')." WHERE ".qn($SID)."=?";
    $pdo->prepare($sql)->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);

    nhatky_log($pdo,'RESET_MATKHAU_NV',"Reset mật khẩu nhân viên #{$id}",$staffTable,$id);
    redirectWith(['type'=>'ok','msg'=>"Đã reset mật khẩu. Mật khẩu mới: {$newPass}"], $backTo($id,'thongtin'));
  }

  if ($action === 'perm_toggle') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID nhân viên.']);
    $key = trim((string)($_POST['perm_key'] ?? ''));
    $val = (int)($_POST['perm_val'] ?? 0);
    if ($key==='' || !isset($MODULES[$key])) redirectWith(['type'=>'error','msg'=>'Quyền không hợp lệ.']);

    if ($permMode !== 'ADMIN_QUYEN') {
      redirectWith(['type'=>'error','msg'=>'Thiếu bảng admin_quyen để phân quyền (khớp requirePermission).'], $backTo($id,'phanquyen'));
    }

    // upsert
    $st=$pdo->prepare("SELECT COUNT(*) FROM admin_quyen WHERE ".qn($P_ID)."=? AND ".qn($P_KEY)."=?");
    $st->execute([$id,$key]);
    $exists = ((int)$st->fetchColumn() > 0);

    if ($exists) {
      $pdo->prepare("UPDATE admin_quyen SET ".qn($P_OK)."=? WHERE ".qn($P_ID)."=? AND ".qn($P_KEY)."=?")->execute([$val,$id,$key]);
    } else {
      $pdo->prepare("INSERT INTO admin_quyen(".qn($P_ID).",".qn($P_KEY).",".qn($P_OK).") VALUES(?,?,?)")->execute([$id,$key,$val]);
    }

    nhatky_log($pdo,'PHAN_QUYEN_NV',"Cập nhật quyền '{$key}'=".($val?1:0)." cho nhân viên #{$id}",'admin_quyen',null,['id_admin'=>$id,'key'=>$key,'val'=>$val]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật quyền.'], $backTo($id,'phanquyen'));
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

// ===== LIST QUERY =====
$q = trim((string)($_GET['q'] ?? ''));
$filterRole = trim((string)($_GET['role'] ?? ''));
$filterAct  = trim((string)($_GET['act'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$off = ($page-1)*$perPage;

$where=[]; $bind=[];
if ($q!=='') {
  $like='%'.$q.'%';
  $parts=[];
  if ($SNAME)  $parts[] = qn($SNAME)." LIKE :q";
  if ($SEMAIL) $parts[] = qn($SEMAIL)." LIKE :q";
  if ($SUSER)  $parts[] = qn($SUSER)." LIKE :q";
  if ($SPHONE) $parts[] = qn($SPHONE)." LIKE :q";
  if ($parts){ $where[]='('.implode(' OR ',$parts).')'; $bind[':q']=$like; }
}
if ($filterRole!=='' && $SROLE){ $where[] = qn($SROLE)."=:role"; $bind[':role']=$filterRole; }
if (($filterAct==='0' || $filterAct==='1') && $SACT){ $where[] = qn($SACT)."=:act"; $bind[':act']=(int)$filterAct; }

$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$st=$pdo->prepare("SELECT COUNT(*) FROM ".qn($staffTable)." $whereSql");
$st->execute($bind);
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
$st=$pdo->prepare($sqlList);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if ($idSelected<=0 && $rows) $idSelected = (int)$rows[0]['_id'];

// ===== DETAIL =====
$detail = null;
if ($idSelected>0) {
  $st=$pdo->prepare("SELECT * FROM ".qn($staffTable)." WHERE ".qn($SID)."=? LIMIT 1");
  $st->execute([$idSelected]);
  $detail = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ===== ROLE OPTIONS =====
$roleOptions = [];
if ($SROLE) {
  $st=$pdo->query("SELECT DISTINCT ".qn($SROLE)." AS ten FROM ".qn($staffTable)." WHERE ".qn($SROLE)." IS NOT NULL AND ".qn($SROLE)."<>'' ORDER BY 1 ASC");
  $roleOptions = array_map(fn($r)=>(string)$r['ten'], $st->fetchAll(PDO::FETCH_ASSOC));
}

// ===== LOAD STAFF PERMS (admin_quyen) =====
$permMap = [];
if ($permMode === 'ADMIN_QUYEN' && $idSelected>0) {
  $st=$pdo->prepare("SELECT ".qn($P_KEY)." AS k, ".qn($P_OK)." AS v FROM admin_quyen WHERE ".qn($P_ID)."=?");
  $st->execute([$idSelected]);
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $permMap[(string)$r['k']] = ((int)$r['v']===1);
  }
}

nhatky_log($pdo,'XEM_NHANVIEN',"Xem module Nhân viên",'nhanvien',null,['q'=>$q,'role'=>$filterRole,'act'=>$filterAct,'tab'=>$tab,'id'=>$idSelected]);

// ===== INCLUDES =====
$incDau  = file_exists(__DIR__ . '/includes/giaoDienDau.php') ? __DIR__ . '/includes/giaoDienDau.php' : null;
$incBen  = file_exists(__DIR__ . '/includes/thanhBen.php')    ? __DIR__ . '/includes/thanhBen.php'    : (file_exists(__DIR__ . '/includes/thanhben.php') ? __DIR__ . '/includes/thanhben.php' : null);
$incTren = file_exists(__DIR__ . '/includes/thanhTren.php')   ? __DIR__ . '/includes/thanhTren.php'   : null;
$incCuoi = file_exists(__DIR__ . '/includes/giaoDienCuoi.php')? __DIR__ . '/includes/giaoDienCuoi.php': null;

if ($incDau) require_once $incDau;
if ($incBen) require_once $incBen;
if ($incTren) require_once $incTren;

$f = flash_get();
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
        <div class="text-xs text-muted font-bold">Quản lý tài khoản + phân quyền (admin_quyen) + khóa/mở + reset mật khẩu</div>
      </div>
      <div class="flex items-center gap-2">
        <a href="nhanvien.php?tab=them" class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold text-sm shadow-soft">Thêm nhân viên</a>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- LEFT -->
      <div class="lg:col-span-7 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between gap-3">
          <div class="text-base font-extrabold">Danh sách</div>
          <div class="text-xs text-muted font-bold">Bảng: <?= h($staffTable) ?></div>
        </div>

        <form class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3" method="get">
          <input type="hidden" name="tab" value="<?= h($tab) ?>">
          <div class="md:col-span-6">
            <input name="q" value="<?= h($q) ?>" placeholder="Tìm tên / email / sđt / username..."
              class="w-full px-4 py-2.5 rounded-xl border border-line bg-white text-sm font-bold outline-none focus:ring-2 focus:ring-primary/20">
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
                        <div class="text-xs text-muted font-bold">ID: <?= $rid ?><?= (!empty($r['created_at'])?' · Tạo: '.h($r['created_at']):'') ?></div>
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
            <?php $base = "nhanvien.php?tab=".urlencode($tab)."&q=".urlencode($q)."&role=".urlencode($filterRole)."&act=".urlencode($filterAct); ?>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.max(1,$page-1)) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.min($totalPages,$page+1)) ?>">Sau</a>
          </div>
        </div>
      </div>

      <!-- RIGHT -->
      <div class="lg:col-span-5 space-y-6">

        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="flex items-center justify-between">
            <div class="text-base font-extrabold"><?= $tab==='them'?'Thêm nhân viên':'Chi tiết' ?></div>
            <div class="text-xs text-muted font-bold"><?= $detail?('ID: '.$idSelected):'Chọn 1 nhân viên' ?></div>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=thongtin"
              class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='thongtin'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Thông tin</a>
            <a href="nhanvien.php?id=<?= (int)$idSelected ?>&tab=phanquyen"
              class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='phanquyen'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">Phân quyền</a>
            <a href="nhanvien.php?tab=them"
              class="px-3 py-2 rounded-xl border text-xs font-extrabold <?= $tab==='them'?'bg-slate-900 text-white border-slate-900':'bg-white text-slate-700 border-line hover:bg-slate-50' ?>">+ Thêm</a>
          </div>

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
                      <input name="vai_tro" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="admin/nhanvien..." value="nhanvien">
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
                <?php if(!$isAdmin): ?><div class="text-xs text-red-600 font-extrabold">Chỉ ADMIN được thao tác.</div><?php endif; ?>
              </form>

            <?php elseif(!$detail): ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Chưa chọn nhân viên</div>
                <div class="text-xs text-muted font-bold mt-1">Chọn 1 dòng ở danh sách bên trái để xem chi tiết.</div>
              </div>

            <?php elseif($tab==='thongtin'): ?>
              <?php
                $dName = (string)($SNAME ? ($detail[$SNAME] ?? '') : '');
                $dEmail= (string)($SEMAIL?($detail[$SEMAIL]??''):'' );
                $dUser = (string)($SUSER ? ($detail[$SUSER] ?? '') : '');
                $dPhone= (string)($SPHONE?($detail[$SPHONE]??''):'' );
                $dRole = (string)($SROLE ? ($detail[$SROLE] ?? '') : '');
                $dAct  = $SACT ? ((int)($detail[$SACT] ?? 1)===1) : true;
              ?>
              <div class="flex items-center gap-3">
                <div class="size-12 rounded-2xl bg-slate-100 grid place-items-center font-extrabold">
                  <?= h(mb_strtoupper(mb_substr(trim($dName?:($dUser?:('#'.$idSelected))),0,1,'UTF-8'),'UTF-8')) ?>
                </div>
                <div>
                  <div class="text-base font-extrabold"><?= h($dName ?: $dUser ?: ('#'.$idSelected)) ?></div>
                  <div class="text-xs text-muted font-bold"><?= h($dEmail) ?><?= $dPhone?(' · '.h($dPhone)) : '' ?></div>
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
                      <input name="vai_tro" value="<?= h($dRole) ?>" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold">
                    </div>
                  <?php endif; ?>
                </div>

                <button class="w-full px-4 py-3 rounded-xl bg-slate-900 text-white font-extrabold">Lưu thông tin</button>
                <?php if(!$isAdmin): ?><div class="text-xs text-red-600 font-extrabold">Chỉ ADMIN được thao tác.</div><?php endif; ?>
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
                  <?php if(!$isAdmin): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Chỉ ADMIN.</div><?php endif; ?>
                </form>

                <form method="post" class="rounded-2xl border border-line p-4">
                  <input type="hidden" name="action" value="reset_pass">
                  <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                  <div class="text-sm font-extrabold">Reset mật khẩu</div>
                  <div class="text-xs text-muted font-bold mt-1">Để trống sẽ tự sinh.</div>
                  <input name="new_pass" class="mt-3 w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Mật khẩu mới (tuỳ chọn)">
                  <button class="mt-3 w-full px-4 py-2.5 rounded-xl bg-primary text-white font-extrabold">Reset</button>
                  <?php if(!$SPASS): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Bảng thiếu cột mật khẩu.</div><?php endif; ?>
                  <?php if(!$isAdmin): ?><div class="text-[11px] text-red-600 font-extrabold mt-2">Chỉ ADMIN.</div><?php endif; ?>
                </form>
              </div>

            <?php elseif($tab==='phanquyen'): ?>
              <div class="rounded-2xl border border-line bg-slate-50 p-4">
                <div class="text-sm font-extrabold">Phân quyền</div>
                <div class="text-xs text-muted font-bold mt-1">
                  Chế độ: <b><?= h($permMode) ?></b>
                  <?php if($permMode!=='ADMIN_QUYEN'): ?>
                    <div class="mt-2 text-red-600 font-extrabold">
                      Bạn cần bảng <b>admin_quyen</b> để UI này hoạt động đúng với requirePermission().
                    </div>
                  <?php endif; ?>
                </div>

                <?php if($permMode!=='ADMIN_QUYEN'): ?>
                  <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-extrabold text-slate-700">SQL tạo bảng admin_quyen (khớp hamChung.php)</summary>
                    <pre class="mt-2 text-xs bg-white border border-line rounded-xl p-3 overflow-auto"><?php echo h(
"CREATE TABLE admin_quyen (
  id_admin INT NOT NULL,
  chuc_nang VARCHAR(50) NOT NULL,
  duoc_phep TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_admin, chuc_nang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
); ?></pre>
                  </details>
                <?php endif; ?>
              </div>

              <div class="mt-4 grid grid-cols-1 gap-3">
                <?php foreach($MODULES as $k=>$m):
                  $enabled = (bool)($permMap[$k] ?? false);
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

              <?php if(!$isAdmin): ?>
                <div class="mt-3 text-xs text-red-600 font-extrabold">Chỉ ADMIN được chỉnh phân quyền.</div>
              <?php endif; ?>

            <?php else: ?>
              <div class="text-sm text-muted font-bold">Tab không hợp lệ.</div>
            <?php endif; ?>

          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php if ($incCuoi) require_once $incCuoi; ?>
