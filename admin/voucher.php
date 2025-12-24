<?php
// admin/voucher.php

/* ================= BOOT ================= */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// ưu tiên dùng helpers/hamChung nếu bạn đã có
if (file_exists(__DIR__ . '/includes/helpers.php')) {
  require_once __DIR__ . '/includes/helpers.php';
} elseif (file_exists(__DIR__ . '/includes/hamChung.php')) {
  require_once __DIR__ . '/includes/hamChung.php';
}

/* ================= SAFE HELPERS (không redeclare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n){
    $n = (int)($n ?? 0);
    return number_format($n, 0, ',', '.') . ' ₫';
  }
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
  function redirectWith($params=[]){
    header("Location: voucher.php".($params?('?'.http_build_query($params)):''));
    exit;
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

/**
 * Log vào nhatky_hoatdong (đúng bảng bạn đang có)
 * Cột theo ảnh: id_log, id_admin, vai_tro, hanh_dong, mo_ta, bang_lien_quan, id_ban_ghi, du_lieu_json, ip, user_agent, ngay_tao
 */
if (!function_exists('nhatky_log')) {
  function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, ?int $id_ban_ghi=null, $data=null){
    if (!tableExists($pdo,'nhatky_hoatdong')) return;

    $cols = getCols($pdo,'nhatky_hoatdong');
    $ID_ADMIN = pickCol($cols,['id_admin','admin_id','id_user']);
    $VAI_TRO  = pickCol($cols,['vai_tro','role']);
    $HANH     = pickCol($cols,['hanh_dong','action']);
    $MOTA     = pickCol($cols,['mo_ta','mo_ta','description']);
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
    if ($HANH){    $fields[]=$HANH;    $vals[]=':act'; $bind[':act']=$hanh_dong; }
    if ($MOTA){    $fields[]=$MOTA;    $vals[]=':des'; $bind[':des']=$mo_ta; }
    if ($BANG && $bang!==null){  $fields[]=$BANG; $vals[]=':tbl'; $bind[':tbl']=$bang; }
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

$ACTIVE = 'voucher';
$PAGE_TITLE = 'Quản lý Voucher';

// nếu bạn có requirePermission thì gọi, không có thì bỏ qua
if (function_exists('requirePermission')) {
  requirePermission('voucher');
}

/* ================= VALIDATE TABLES ================= */
if (!tableExists($pdo,'voucher')) {
  // render tối giản (chưa include layout để không lỗi header)
  die("Thiếu bảng <b>voucher</b>.");
}

$vcCols = getCols($pdo,'voucher');
$VC_ID     = pickCol($vcCols, ['id_voucher','id','voucher_id']);
$VC_CODE   = pickCol($vcCols, ['ma_voucher','ma','code','voucher_code']);
$VC_TYPE   = pickCol($vcCols, ['loai','type','kieu']);
$VC_VALUE  = pickCol($vcCols, ['gia_tri','value','muc_giam','giam']);
$VC_MAX    = pickCol($vcCols, ['giam_toi_da','max_discount','toi_da']);
$VC_START  = pickCol($vcCols, ['ngay_bat_dau','start_date','bat_dau']);
$VC_END    = pickCol($vcCols, ['ngay_ket_thuc','end_date','ket_thuc']);
$VC_ACTIVE = pickCol($vcCols, ['trang_thai','is_active','active','status']);
$VC_LIMIT  = pickCol($vcCols, ['gioi_han','so_luot','luot_dung_toi_da','max_use']);
$VC_USED   = pickCol($vcCols, ['da_dung','used','used_count']);
$VC_CREATED= pickCol($vcCols, ['ngay_tao','created_at']);
$VC_UPDATED= pickCol($vcCols, ['ngay_cap_nhat','updated_at']);

if(!$VC_ID || !$VC_CODE) die("Bảng voucher thiếu cột id/code.");

/* ================= USED COUNT FROM DONHANG (nếu có) ================= */
$hasDH = tableExists($pdo,'donhang');
$dhCols = $hasDH ? getCols($pdo,'donhang') : [];
$DH_VC  = $hasDH ? pickCol($dhCols,['ma_voucher','voucher_code','code_voucher']) : null;

/* ================= POST ACTIONS (BEFORE RENDER) ================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  // helper đọc input
  $code = strtoupper(trim((string)($_POST['ma_voucher'] ?? '')));
  $type = strtolower(trim((string)($_POST['loai'] ?? 'percent'))); // percent | fixed
  $val  = trim((string)($_POST['gia_tri'] ?? '0'));
  $max  = trim((string)($_POST['giam_toi_da'] ?? ''));
  $stt  = (int)($_POST['trang_thai'] ?? 1);
  $start = trim((string)($_POST['ngay_bat_dau'] ?? ''));
  $end   = trim((string)($_POST['ngay_ket_thuc'] ?? ''));
  $limit = trim((string)($_POST['gioi_han'] ?? ''));

  // chuẩn hóa type
  if (!in_array($type,['percent','fixed'],true)) $type='percent';
  $valInt = (int)$val;
  $maxInt = ($max===''? null : (int)$max);
  $limitInt = ($limit===''? null : (int)$limit);

  // validate date dạng YYYY-MM-DD (input type=date)
  $startDb = ($start!=='' ? $start : null);
  $endDb   = ($end!=='' ? $end : null);

  // log wrapper
  $log = function($hanh_dong,$mo_ta,$idrec=null,$data=null) use($pdo){
    nhatky_log($pdo,$hanh_dong,$mo_ta,'voucher',$idrec,$data);
  };

  if ($action==='them') {
    if ($code==='') redirectWith(['type'=>'error','msg'=>'Thiếu mã voucher.']);
    // check trùng code
    $ck=$pdo->prepare("SELECT COUNT(*) FROM voucher WHERE $GLOBALS[VC_CODE]=?");
    $ck->execute([$code]);
    if ((int)$ck->fetchColumn()>0) redirectWith(['type'=>'error','msg'=>'Mã voucher đã tồn tại.']);

    $fields=[];$vals=[];$bind=[];
    $fields[]=$VC_CODE; $vals[]=':c'; $bind[':c']=$code;

    if ($VC_TYPE){  $fields[]=$VC_TYPE;  $vals[]=':t'; $bind[':t']=$type; }
    if ($VC_VALUE){ $fields[]=$VC_VALUE; $vals[]=':v'; $bind[':v']=$valInt; }
    if ($VC_MAX && $maxInt!==null){ $fields[]=$VC_MAX; $vals[]=':m'; $bind[':m']=$maxInt; }
    if ($VC_START && $startDb!==null){ $fields[]=$VC_START; $vals[]=':s'; $bind[':s']=$startDb; }
    if ($VC_END && $endDb!==null){ $fields[]=$VC_END; $vals[]=':e'; $bind[':e']=$endDb; }
    if ($VC_ACTIVE){ $fields[]=$VC_ACTIVE; $vals[]=':a'; $bind[':a']=$stt; }
    if ($VC_LIMIT && $limitInt!==null){ $fields[]=$VC_LIMIT; $vals[]=':l'; $bind[':l']=$limitInt; }

    if ($VC_CREATED){ $fields[]=$VC_CREATED; $vals[]='NOW()'; }
    if ($VC_UPDATED){ $fields[]=$VC_UPDATED; $vals[]='NOW()'; }

    $sql="INSERT INTO voucher(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $newId = (int)$pdo->lastInsertId();

    $log('THEM_VOUCHER',"Thêm voucher $code",$newId,['code'=>$code,'type'=>$type,'value'=>$valInt,'max'=>$maxInt,'start'=>$startDb,'end'=>$endDb,'active'=>$stt,'limit'=>$limitInt]);
    redirectWith(['type'=>'ok','msg'=>'Đã thêm voucher.','xem'=>$newId]);
  }

  if ($action==='sua') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID voucher.']);
    if ($code==='') redirectWith(['type'=>'error','msg'=>'Thiếu mã voucher.']);

    // lấy cũ để log
    $oldRow = $pdo->prepare("SELECT * FROM voucher WHERE $VC_ID=? LIMIT 1");
    $oldRow->execute([$id]);
    $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];

    $set=[];$bind=[':id'=>$id];

    // code
    $set[]="$VC_CODE=:c"; $bind[':c']=$code;

    if ($VC_TYPE){  $set[]="$VC_TYPE=:t";  $bind[':t']=$type; }
    if ($VC_VALUE){ $set[]="$VC_VALUE=:v"; $bind[':v']=$valInt; }

    if ($VC_MAX){
      $set[]="$VC_MAX=:m";
      $bind[':m']=($maxInt===null? null : $maxInt);
    }
    if ($VC_START){
      $set[]="$VC_START=:s";
      $bind[':s']=($startDb===null? null : $startDb);
    }
    if ($VC_END){
      $set[]="$VC_END=:e";
      $bind[':e']=($endDb===null? null : $endDb);
    }
    if ($VC_ACTIVE){
      $set[]="$VC_ACTIVE=:a";
      $bind[':a']=$stt;
    }
    if ($VC_LIMIT){
      $set[]="$VC_LIMIT=:l";
      $bind[':l']=($limitInt===null? null : $limitInt);
    }
    if ($VC_UPDATED){
      $set[]="$VC_UPDATED=NOW()";
    }

    $sql="UPDATE voucher SET ".implode(', ',$set)." WHERE $VC_ID=:id";
    $pdo->prepare($sql)->execute($bind);

    $log('SUA_VOUCHER',"Cập nhật voucher #$id ($code)",$id,[
      'before'=>$old,
      'after'=>['code'=>$code,'type'=>$type,'value'=>$valInt,'max'=>$maxInt,'start'=>$startDb,'end'=>$endDb,'active'=>$stt,'limit'=>$limitInt]
    ]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật voucher.','xem'=>$id]);
  }

  if ($action==='toggle') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID voucher.']);
    if (!$VC_ACTIVE) redirectWith(['type'=>'error','msg'=>'Bảng voucher không có cột trạng thái để bật/tắt.']);

    $st=$pdo->prepare("SELECT $VC_ACTIVE, $VC_CODE FROM voucher WHERE $VC_ID=? LIMIT 1");
    $st->execute([$id]);
    $cur = $st->fetch(PDO::FETCH_ASSOC);
    if(!$cur) redirectWith(['type'=>'error','msg'=>'Voucher không tồn tại.']);

    $now = (int)($cur[$VC_ACTIVE] ?? 1);
    $new = ($now==1?0:1);

    $upd = $VC_UPDATED
      ? "UPDATE voucher SET $VC_ACTIVE=?, $VC_UPDATED=NOW() WHERE $VC_ID=?"
      : "UPDATE voucher SET $VC_ACTIVE=? WHERE $VC_ID=?";
    $pdo->prepare($upd)->execute([$new,$id]);

    $log('BAT_TAT_VOUCHER',"Bật/Tắt voucher #$id ({$cur[$VC_CODE]}): $now → $new",$id,['from'=>$now,'to'=>$new]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái.','xem'=>$id]);
  }

  if ($action==='xoa') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không được xoá voucher.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID voucher.']);

    $st=$pdo->prepare("SELECT $VC_CODE FROM voucher WHERE $VC_ID=? LIMIT 1");
    $st->execute([$id]);
    $codeOld = (string)($st->fetchColumn() ?? '');

    $pdo->prepare("DELETE FROM voucher WHERE $VC_ID=?")->execute([$id]);
    $log('XOA_VOUCHER',"Xoá voucher #$id ($codeOld)",$id,['code'=>$codeOld]);
    redirectWith(['type'=>'ok','msg'=>'Đã xoá voucher.']);
  }

  if ($action==='test') {
    // Dùng thử: nhập số tiền, trả về số tiền giảm (server tính) + log
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID voucher.']);
    $amount = (int)($_POST['amount'] ?? 0);
    if ($amount<=0) redirectWith(['type'=>'error','msg'=>'Nhập số tiền cần thử.','xem'=>$id]);

    $st=$pdo->prepare("SELECT * FROM voucher WHERE $VC_ID=? LIMIT 1");
    $st->execute([$id]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    if(!$v) redirectWith(['type'=>'error','msg'=>'Voucher không tồn tại.','xem'=>$id]);

    $vType = $VC_TYPE ? (string)($v[$VC_TYPE] ?? 'percent') : 'percent';
    $vVal  = $VC_VALUE? (int)($v[$VC_VALUE] ?? 0) : 0;
    $vMax  = ($VC_MAX && isset($v[$VC_MAX]) && $v[$VC_MAX]!==null && $v[$VC_MAX]!=='') ? (int)$v[$VC_MAX] : null;

    $discount = 0;
    if (strtolower($vType)==='percent') {
      $discount = (int)floor($amount * ($vVal/100));
      if ($vMax!==null) $discount = min($discount,$vMax);
    } else {
      $discount = min($amount, $vVal);
    }

    $codeNow = $VC_CODE ? (string)($v[$VC_CODE] ?? '') : '';
    $log('DUNG_THU_VOUCHER',"Dùng thử voucher #$id ($codeNow) trên ".money_vnd($amount)." => giảm ".money_vnd($discount),$id,[
      'amount'=>$amount,'discount'=>$discount,'type'=>$vType,'value'=>$vVal,'max'=>$vMax
    ]);

    redirectWith(['type'=>'ok','msg'=>"Dùng thử: ".money_vnd($amount)." giảm ".money_vnd($discount),'xem'=>$id]);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= FILTER / LIST ================= */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page-1)*$perPage;

$where = " WHERE 1 ";
$params = [];

if ($q!=='') {
  $where .= " AND ($VC_CODE LIKE ?) ";
  $params[] = "%$q%";
}

$st=$pdo->prepare("SELECT COUNT(*) FROM voucher $where");
$st->execute($params);
$total=(int)$st->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));

$fields = ["$VC_ID AS id", "$VC_CODE AS code"];
if ($VC_TYPE)   $fields[] = "$VC_TYPE AS type";
if ($VC_VALUE)  $fields[] = "$VC_VALUE AS value";
if ($VC_MAX)    $fields[] = "$VC_MAX AS max_discount";
if ($VC_START)  $fields[] = "$VC_START AS start_date";
if ($VC_END)    $fields[] = "$VC_END AS end_date";
if ($VC_ACTIVE) $fields[] = "$VC_ACTIVE AS is_active";
if ($VC_LIMIT)  $fields[] = "$VC_LIMIT AS max_use";
if ($VC_USED)   $fields[] = "$VC_USED AS used_count";
if ($VC_UPDATED)$fields[] = "$VC_UPDATED AS updated_at";

$orderBy = $VC_UPDATED ? $VC_UPDATED : $VC_ID;
$sql = "SELECT ".implode(', ',$fields)." FROM voucher $where ORDER BY $orderBy DESC LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* usage count from donhang by ma_voucher (1 query) */
$usedMap = [];
if ($DH_VC && $rows) {
  $codes = array_values(array_filter(array_map(fn($r)=>$r['code'] ?? null, $rows)));
  if ($codes) {
    $in = implode(',', array_fill(0,count($codes),'?'));
    $qUsed = "SELECT $DH_VC AS code, COUNT(*) AS c FROM donhang WHERE $DH_VC IN ($in) GROUP BY $DH_VC";
    $st = $pdo->prepare($qUsed);
    $st->execute($codes);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $u){
      $usedMap[(string)$u['code']] = (int)$u['c'];
    }
  }
}

/* view edit */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
if ($viewId>0){
  $st=$pdo->prepare("SELECT * FROM voucher WHERE $VC_ID=? LIMIT 1");
  $st->execute([$viewId]);
  $view=$st->fetch(PDO::FETCH_ASSOC);
}

/* stats (trong trang để giống ảnh) */
$today = date('Y-m-d');
$totalInPage = count($rows);
$activeInPage = 0;
$expiredInPage = 0;
$usedUpInPage = 0;

foreach($rows as $r){
  $isActive = $VC_ACTIVE ? ((int)($r['is_active'] ?? 1) === 1) : true;
  $sd = $r['start_date'] ?? null;
  $ed = $r['end_date'] ?? null;

  $inRange = true;
  if ($sd && $sd > $today) $inRange = false;
  if ($ed && $ed < $today) $inRange = false;

  $isExpired = ($ed && $ed < $today);
  if ($isExpired) $expiredInPage++;

  if ($isActive && $inRange) $activeInPage++;

  // used-up nếu có limit + used
  $limit = isset($r['max_use']) ? (int)$r['max_use'] : null;
  $used  = null;

  if ($VC_USED && isset($r['used_count']) && $r['used_count']!==null && $r['used_count']!=='') {
    $used = (int)$r['used_count'];
  } elseif ($DH_VC) {
    $used = (int)($usedMap[(string)($r['code'] ?? '')] ?? 0);
  }

  if ($limit && $used!==null && $used >= $limit) $usedUpInPage++;
}

/* flash */
$type=$_GET['type'] ?? '';
$msg=$_GET['msg'] ?? '';

/* ================= RENDER (layout) ================= */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>

<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <?php if($msg): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-4">
        <div class="flex items-start gap-2">
          <span class="material-symbols-outlined <?= $type==='ok'?'text-green-600':($type==='error'?'text-red-600':'text-slate-600') ?>">
            <?= $type==='ok'?'check_circle':($type==='error'?'error':'info') ?>
          </span>
          <div class="text-sm font-bold"><?= h($msg) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center gap-3">
          <div class="size-12 rounded-2xl bg-blue-50 grid place-items-center">
            <span class="material-symbols-outlined text-primary">sell</span>
          </div>
          <div>
            <div class="text-xs text-muted font-bold">Tổng voucher</div>
            <div class="text-xl font-extrabold"><?= number_format($total) ?></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center gap-3">
          <div class="size-12 rounded-2xl bg-green-50 grid place-items-center">
            <span class="material-symbols-outlined text-green-600">verified</span>
          </div>
          <div>
            <div class="text-xs text-muted font-bold">Đang hoạt động</div>
            <div class="text-xl font-extrabold"><?= number_format($activeInPage) ?></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center gap-3">
          <div class="size-12 rounded-2xl bg-amber-50 grid place-items-center">
            <span class="material-symbols-outlined text-amber-600">event_busy</span>
          </div>
          <div>
            <div class="text-xs text-muted font-bold">Hết hạn (trong trang)</div>
            <div class="text-xl font-extrabold"><?= number_format($expiredInPage) ?></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center gap-3">
          <div class="size-12 rounded-2xl bg-red-50 grid place-items-center">
            <span class="material-symbols-outlined text-red-600">block</span>
          </div>
          <div>
            <div class="text-xs text-muted font-bold">Đã dùng hết lượt (trong trang)</div>
            <div class="text-xl font-extrabold"><?= number_format($usedUpInPage) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- LIST -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-base font-extrabold">Danh sách voucher</div>
            <div class="text-xs text-muted font-bold">Bảng: voucher</div>
          </div>

          <form method="get" class="flex items-center gap-2">
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[20px]">search</span>
              <input name="q" value="<?= h($q) ?>" class="pl-10 pr-3 py-2 rounded-xl bg-slate-50 border border-line text-sm w-56"
                placeholder="Tìm mã / tên voucher..." />
            </div>
            <button class="px-3 py-2 rounded-xl bg-primary text-white text-sm font-extrabold">Lọc</button>
          </form>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-muted">
                <th class="text-left py-3 pr-3 font-extrabold">Mã</th>
                <th class="text-left py-3 pr-3 font-extrabold">Giảm</th>
                <th class="text-left py-3 pr-3 font-extrabold">Thời gian</th>
                <th class="text-left py-3 pr-3 font-extrabold">Trạng thái</th>
                <th class="text-left py-3 pr-3 font-extrabold">Dùng</th>
                <th class="text-right py-3 pr-0 font-extrabold">Hành động</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <?php if(!$rows): ?>
                <tr><td colspan="6" class="py-10 text-center text-muted font-bold">Chưa có voucher.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r):
                $code = (string)($r['code'] ?? '');
                $type = strtolower((string)($r['type'] ?? 'percent'));
                $value= (int)($r['value'] ?? 0);
                $maxd = isset($r['max_discount']) && $r['max_discount']!==null && $r['max_discount']!=='' ? (int)$r['max_discount'] : null;

                $sd = $r['start_date'] ?? null;
                $ed = $r['end_date'] ?? null;

                $isActive = $VC_ACTIVE ? ((int)($r['is_active'] ?? 1) === 1) : true;
                $expired = ($ed && $ed < $today);
                $inRange = true;
                if ($sd && $sd > $today) $inRange = false;
                if ($ed && $ed < $today) $inRange = false;

                $badge = ['bg'=>'bg-slate-100','tx'=>'text-slate-700','label'=>'Tắt'];
                if ($expired) $badge = ['bg'=>'bg-red-50','tx'=>'text-red-600','label'=>'Hết hạn'];
                else if ($isActive && $inRange) $badge = ['bg'=>'bg-green-50','tx'=>'text-green-600','label'=>'Hoạt động'];
                else if ($isActive && !$inRange) $badge = ['bg'=>'bg-amber-50','tx'=>'text-amber-700','label'=>'Chưa tới'];

                $used = null;
                if ($VC_USED && isset($r['used_count']) && $r['used_count']!==null && $r['used_count']!=='') $used = (int)$r['used_count'];
                elseif ($DH_VC) $used = (int)($usedMap[$code] ?? 0);

                $limit = isset($r['max_use']) ? (int)$r['max_use'] : null;

                $discountLabel = ($type==='percent') ? ($value.'%') : money_vnd($value);
                if ($type==='percent' && $maxd!==null) $discountLabel .= ' (tối đa '.money_vnd($maxd).')';
              ?>
                <tr class="align-top">
                  <td class="py-4 pr-3">
                    <div class="font-extrabold"><?= h($code) ?></div>
                    <div class="text-xs text-muted font-bold">ID: <?= (int)$r['id'] ?></div>
                  </td>
                  <td class="py-4 pr-3 font-bold"><?= h($discountLabel) ?></td>
                  <td class="py-4 pr-3 text-muted font-bold">
                    <?= h($sd ?: '—') ?> — <?= h($ed ?: '—') ?>
                  </td>
                  <td class="py-4 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $badge['bg'] ?> <?= $badge['tx'] ?>">
                      <?= h($badge['label']) ?>
                    </span>
                  </td>
                  <td class="py-4 pr-3">
                    <?php if($used===null): ?>
                      <span class="text-muted font-bold">—</span>
                    <?php else: ?>
                      <span class="font-extrabold"><?= number_format($used) ?></span>
                      <?php if($limit): ?>
                        <span class="text-muted font-bold">/ <?= number_format($limit) ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td class="py-4 pr-0">
                    <div class="flex items-center justify-end gap-2">
                      <a class="px-3 py-2 rounded-xl bg-slate-100 text-slate-800 text-xs font-extrabold hover:bg-slate-200"
                         href="voucher.php?<?= h(http_build_query(array_merge($_GET,['xem'=>(int)$r['id']])) ) ?>">Sửa</a>

                      <button type="button"
                        class="px-3 py-2 rounded-xl bg-slate-100 text-slate-800 text-xs font-extrabold hover:bg-slate-200"
                        onclick="testVoucher(<?= (int)$r['id'] ?>)">Dùng thử</button>

                      <form method="post" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button class="px-3 py-2 rounded-xl bg-slate-100 text-slate-800 text-xs font-extrabold hover:bg-slate-200">
                          <?= ($isActive? 'Tắt' : 'Bật') ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- pagination -->
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?> · Tổng <?= number_format($total) ?></div>
          <div class="flex gap-2">
            <?php
              $qs=$_GET;
              $mk=function($p) use($qs){ $qs['page']=$p; return 'voucher.php?'.http_build_query($qs); };
            ?>
            <a class="px-3 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
          </div>
        </div>
      </div>

      <!-- FORM -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div>
            <div class="text-base font-extrabold"><?= $view ? 'Sửa voucher' : 'Thêm voucher' ?></div>
            <div class="text-xs text-muted font-bold"><?= $isAdmin ? 'Bạn có quyền CRUD' : 'Nhân viên: chỉ xem / dùng thử' ?></div>
          </div>
          <?php if($view): ?>
            <a class="text-sm font-extrabold text-primary hover:underline" href="voucher.php">Bỏ chọn</a>
          <?php endif; ?>
        </div>

        <form method="post" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="<?= $view?'sua':'them' ?>">
          <?php if($view): ?><input type="hidden" name="id" value="<?= (int)$viewId ?>"><?php endif; ?>

          <?php
            $curCode = $view ? (string)($view[$VC_CODE] ?? '') : '';
            $curType = $view && $VC_TYPE ? (string)($view[$VC_TYPE] ?? 'percent') : 'percent';
            $curVal  = $view && $VC_VALUE ? (int)($view[$VC_VALUE] ?? 0) : 0;
            $curMax  = $view && $VC_MAX ? (string)($view[$VC_MAX] ?? '') : '';
            $curAct  = $view && $VC_ACTIVE ? (int)($view[$VC_ACTIVE] ?? 1) : 1;
            $curS    = $view && $VC_START ? (string)($view[$VC_START] ?? '') : '';
            $curE    = $view && $VC_END ? (string)($view[$VC_END] ?? '') : '';
            $curL    = $view && $VC_LIMIT ? (string)($view[$VC_LIMIT] ?? '') : '';
          ?>

          <div>
            <label class="text-sm font-bold">Mã voucher</label>
            <input name="ma_voucher" value="<?= h($curCode) ?>" placeholder="VD: CROCS10, FREESHIP..." required
              class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-bold">Loại</label>
              <select name="loai" class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
                <option value="percent" <?= strtolower($curType)==='percent'?'selected':'' ?>>percent</option>
                <option value="fixed" <?= strtolower($curType)==='fixed'?'selected':'' ?>>fixed</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-bold">Giá trị giảm</label>
              <input name="gia_tri" type="number" value="<?= (int)$curVal ?>" placeholder="VD: 10 hoặc 50000" required
                class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-bold">Giảm tối đa</label>
              <input name="giam_toi_da" type="number" value="<?= h($curMax) ?>" placeholder="VD: 50000"
                class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
              <div class="text-[11px] text-muted font-bold mt-1">Dùng cho percent (nếu có cột).</div>
            </div>
            <div>
              <label class="text-sm font-bold">Giới hạn lượt dùng</label>
              <input name="gioi_han" type="number" value="<?= h($curL) ?>" placeholder="VD: 100"
                class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
              <div class="text-[11px] text-muted font-bold mt-1">Nếu bảng có cột giới hạn.</div>
            </div>
          </div>

          <div>
            <label class="text-sm font-bold">Trạng thái</label>
            <select name="trang_thai" class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
              <option value="1" <?= $curAct===1?'selected':'' ?>>Bật</option>
              <option value="0" <?= $curAct===0?'selected':'' ?>>Tắt</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-sm font-bold">Ngày bắt đầu</label>
              <input name="ngay_bat_dau" type="date" value="<?= h($curS) ?>"
                class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
            </div>
            <div>
              <label class="text-sm font-bold">Ngày kết thúc</label>
              <input name="ngay_ket_thuc" type="date" value="<?= h($curE) ?>"
                class="mt-1 w-full rounded-xl bg-slate-50 border border-line">
            </div>
          </div>

          <button class="w-full py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90 <?= $isAdmin?'':'opacity-40 pointer-events-none' ?>">
            <?= $view ? 'Lưu voucher' : 'Thêm voucher' ?>
          </button>

          <?php if(!$isAdmin): ?>
            <div class="text-[12px] text-muted font-bold">Nhân viên không được thêm/sửa/xóa voucher.</div>
          <?php endif; ?>
        </form>

        <?php if($view): ?>
          <div class="mt-4 pt-4 border-t border-line space-y-3">
            <div class="text-sm font-extrabold">Dùng thử nhanh</div>
            <button type="button" class="w-full py-2 rounded-xl bg-slate-100 text-slate-800 text-sm font-extrabold hover:bg-slate-200"
              onclick="testVoucher(<?= (int)$viewId ?>)">Nhập số tiền để thử</button>

            <form method="post" onsubmit="return confirm('Xóa voucher này?');">
              <input type="hidden" name="action" value="xoa">
              <input type="hidden" name="id" value="<?= (int)$viewId ?>">
              <button class="w-full py-2 rounded-xl bg-red-600 text-white text-sm font-extrabold hover:bg-red-700 <?= $isAdmin?'':'opacity-40 pointer-events-none' ?>">
                Xóa voucher
              </button>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<script>
function testVoucher(id){
  const v = prompt("Nhập số tiền để dùng thử voucher (VND):", "1000000");
  if(!v) return;
  const amount = parseInt(v,10);
  if(!amount || amount<=0){ alert("Số tiền không hợp lệ"); return; }

  const f = document.createElement('form');
  f.method = 'post';
  f.style.display='none';
  f.innerHTML = `
    <input name="action" value="test">
    <input name="id" value="${id}">
    <input name="amount" value="${amount}">
  `;
  document.body.appendChild(f);
  f.submit();
}
</script>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
