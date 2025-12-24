<?php
// admin/voucher.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================= AUTH ================= */
if (empty($_SESSION['admin']) || (!isset($_SESSION['admin']['id']) && !isset($_SESSION['admin']['id_admin']))) {
  header("Location: dang_nhap.php"); exit;
}
$me = $_SESSION['admin'];
$vaiTro = strtoupper(trim($me['vai_tro'] ?? 'ADMIN'));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tableExists(PDO $pdo, $name){
  $st=$pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$name]);
  return (bool)$st->fetchColumn();
}
function getCols(PDO $pdo, $table){
  $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?");
  $st->execute([$table]);
  return $st->fetchAll(PDO::FETCH_COLUMN);
}
function pickCol(array $cols, array $cands){
  foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; }
  return null;
}
function redirectWith($params=[]){
  header("Location: voucher.php".($params?('?'.http_build_query($params)):''));
  exit;
}

/* ================= Detect voucher table ================= */
$voucherTable = null;
foreach (['voucher','khuyenmai','khuyen_mai','ma_giam_gia','uudai'] as $t){
  if (tableExists($pdo,$t)) { $voucherTable = $t; break; }
}
if (!$voucherTable) die("Không tìm thấy bảng voucher/khuyến mãi trong DB (voucher/khuyenmai/ma_giam_gia...).");

$vCols = getCols($pdo, $voucherTable);

$V_ID      = pickCol($vCols, ['id_voucher','id_khuyenmai','id_km','id','voucher_id','khuyenmai_id']);
$V_CODE    = pickCol($vCols, ['ma','ma_voucher','ma_khuyen_mai','code','voucher_code']);
$V_NAME    = pickCol($vCols, ['ten','ten_voucher','ten_khuyen_mai','tieu_de','name','title']);
$V_DESC    = pickCol($vCols, ['mo_ta','noi_dung','description','content']);
$V_TYPE    = pickCol($vCols, ['loai','kieu','type']); // percent/fixed
$V_VALUE   = pickCol($vCols, ['gia_tri','giam_gia','discount','value','so_tien_giam']);
$V_MIN     = pickCol($vCols, ['don_toi_thieu','gia_tri_toi_thieu','min_order','min_total']);
$V_MAX     = pickCol($vCols, ['giam_toi_da','max_discount','max_value']);
$V_LIMIT   = pickCol($vCols, ['so_lan','gioi_han','usage_limit','so_luong']);
$V_USED    = pickCol($vCols, ['da_dung','so_lan_da_dung','used','used_count']);
$V_START   = pickCol($vCols, ['ngay_bat_dau','bat_dau','start_date','tu_ngay']);
$V_END     = pickCol($vCols, ['ngay_ket_thuc','ket_thuc','end_date','den_ngay']);
$V_STATUS  = pickCol($vCols, ['trang_thai','is_active','active','status']);
$V_CREATE  = pickCol($vCols, ['ngay_tao','created_at']);

if (!$V_ID || !$V_CODE) die("Bảng <b>{$voucherTable}</b> thiếu cột bắt buộc: id / mã voucher.");

/* ================= POST: add/edit/delete/toggle ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  // Gom dữ liệu form
  $code  = trim($_POST['ma'] ?? '');
  $name  = trim($_POST['ten'] ?? '');
  $desc  = trim($_POST['mo_ta'] ?? '');
  $type  = trim($_POST['loai'] ?? '');
  $value = trim($_POST['gia_tri'] ?? '');
  $min   = trim($_POST['don_toi_thieu'] ?? '');
  $max   = trim($_POST['giam_toi_da'] ?? '');
  $limit = trim($_POST['gioi_han'] ?? '');
  $start = trim($_POST['ngay_bat_dau'] ?? '');
  $end   = trim($_POST['ngay_ket_thuc'] ?? '');
  $status= trim($_POST['trang_thai'] ?? '');

  // Normalize số
  $toInt = function($x){
    if ($x === '' || $x === null) return null;
    $x = preg_replace('/[^\d\-]/','',$x);
    if ($x === '' || $x === '-') return null;
    return (int)$x;
  };

  if ($action === 'them') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền thêm voucher.']);
    if ($code === '') redirectWith(['type'=>'error','msg'=>'Vui lòng nhập Mã voucher.']);

    // Check trùng
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$voucherTable} WHERE {$V_CODE}=?");
    $st->execute([$code]);
    if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Mã voucher đã tồn tại.']);

    $fields = [];
    $vals   = [];
    $bind   = [];

    $fields[] = $V_CODE; $vals[]=':code'; $bind[':code']=$code;

    if ($V_NAME && $name!==''){ $fields[]=$V_NAME; $vals[]=':name'; $bind[':name']=$name; }
    if ($V_DESC && $desc!==''){ $fields[]=$V_DESC; $vals[]=':desc'; $bind[':desc']=$desc; }
    if ($V_TYPE && $type!==''){ $fields[]=$V_TYPE; $vals[]=':type'; $bind[':type']=$type; }

    if ($V_VALUE){ $fields[]=$V_VALUE; $vals[]=':val'; $bind[':val']=$toInt($value) ?? 0; }
    if ($V_MIN){ $fields[]=$V_MIN; $vals[]=':min'; $bind[':min']=$toInt($min); }
    if ($V_MAX){ $fields[]=$V_MAX; $vals[]=':max'; $bind[':max']=$toInt($max); }
    if ($V_LIMIT){ $fields[]=$V_LIMIT; $vals[]=':lim'; $bind[':lim']=$toInt($limit); }

    if ($V_START && $start!==''){ $fields[]=$V_START; $vals[]=':st'; $bind[':st']=$start; }
    if ($V_END && $end!==''){ $fields[]=$V_END; $vals[]=':en'; $bind[':en']=$end; }

    if ($V_STATUS){
      $fields[]=$V_STATUS; $vals[]=':ac';
      // ưu tiên 1/0 nếu cột numeric, còn string thì vẫn ok (db tự ép hoặc bạn sửa)
      $bind[':ac'] = ($status==='' ? 1 : (is_numeric($status) ? (int)$status : $status));
    }

    if ($V_CREATE){ $fields[]=$V_CREATE; $vals[]='NOW()'; }

    $sql = "INSERT INTO {$voucherTable}(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    redirectWith(['type'=>'ok','msg'=>'Đã thêm voucher.']);
  }

  if ($action === 'sua') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền sửa voucher.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    if ($code==='') redirectWith(['type'=>'error','msg'=>'Mã voucher không được trống.']);

    // Check trùng code (trừ chính nó)
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$voucherTable} WHERE {$V_CODE}=? AND {$V_ID}<>?");
    $st->execute([$code,$id]);
    if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Mã voucher bị trùng với voucher khác.']);

    $set = [];
    $bind = [':id'=>$id, ':code'=>$code];

    $set[] = "{$V_CODE}=:code";
    if ($V_NAME){ $set[]="{$V_NAME}=:name"; $bind[':name']=$name; }
    if ($V_DESC){ $set[]="{$V_DESC}=:desc"; $bind[':desc']=$desc; }
    if ($V_TYPE){ $set[]="{$V_TYPE}=:type"; $bind[':type']=$type; }
    if ($V_VALUE){ $set[]="{$V_VALUE}=:val"; $bind[':val']=$toInt($value) ?? 0; }
    if ($V_MIN){ $set[]="{$V_MIN}=:min"; $bind[':min']=$toInt($min); }
    if ($V_MAX){ $set[]="{$V_MAX}=:max"; $bind[':max']=$toInt($max); }
    if ($V_LIMIT){ $set[]="{$V_LIMIT}=:lim"; $bind[':lim']=$toInt($limit); }
    if ($V_START){ $set[]="{$V_START}=:st"; $bind[':st']=($start!==''?$start:null); }
    if ($V_END){ $set[]="{$V_END}=:en"; $bind[':en']=($end!==''?$end:null); }
    if ($V_STATUS){
      $set[]="{$V_STATUS}=:ac";
      $bind[':ac'] = ($status==='' ? 1 : (is_numeric($status) ? (int)$status : $status));
    }

    $sql = "UPDATE {$voucherTable} SET ".implode(', ', $set)." WHERE {$V_ID}=:id";
    $pdo->prepare($sql)->execute($bind);

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật voucher.']);
  }

  if ($action === 'xoa') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền xoá voucher.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    $pdo->prepare("DELETE FROM {$voucherTable} WHERE {$V_ID}=?")->execute([$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã xoá voucher.']);
  }

  if ($action === 'bat_tat') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền bật/tắt voucher.']);
    if (!$V_STATUS) redirectWith(['type'=>'error','msg'=>'Bảng không có cột trạng_thái để bật/tắt.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);

    $cur = $pdo->prepare("SELECT {$V_STATUS} AS st FROM {$voucherTable} WHERE {$V_ID}=? LIMIT 1");
    $cur->execute([$id]);
    $stNow = $cur->fetchColumn();

    // nếu numeric: đảo 0/1; nếu string: set 1/0
    $new = (is_numeric($stNow) ? ((int)$stNow ? 0 : 1) : ((string)$stNow === '1' ? '0' : '1'));
    $pdo->prepare("UPDATE {$voucherTable} SET {$V_STATUS}=? WHERE {$V_ID}=?")->execute([$new,$id]);

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái voucher.']);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= Filters ================= */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$where = " WHERE 1 ";
$params = [];

if ($q !== '') {
  $conds = [];
  $conds[] = "{$V_CODE} LIKE ?";
  $params[] = "%{$q}%";
  if ($V_NAME){ $conds[] = "{$V_NAME} LIKE ?"; $params[] = "%{$q}%"; }
  if ($V_DESC){ $conds[] = "{$V_DESC} LIKE ?"; $params[] = "%{$q}%"; }
  $where .= " AND (".implode(" OR ",$conds).") ";
}

$orderBy = $V_CREATE ? $V_CREATE : $V_ID;

/* count */
$st = $pdo->prepare("SELECT COUNT(*) FROM {$voucherTable} {$where}");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalPages = max(1,(int)ceil($total/$perPage));

/* list fields */
$fields = ["{$V_ID} AS id", "{$V_CODE} AS ma"];
if ($V_NAME) $fields[]="{$V_NAME} AS ten";
if ($V_DESC) $fields[]="{$V_DESC} AS mo_ta";
if ($V_TYPE) $fields[]="{$V_TYPE} AS loai";
if ($V_VALUE) $fields[]="{$V_VALUE} AS gia_tri";
if ($V_MIN) $fields[]="{$V_MIN} AS don_toi_thieu";
if ($V_MAX) $fields[]="{$V_MAX} AS giam_toi_da";
if ($V_LIMIT) $fields[]="{$V_LIMIT} AS gioi_han";
if ($V_USED) $fields[]="{$V_USED} AS da_dung";
if ($V_START) $fields[]="{$V_START} AS ngay_bat_dau";
if ($V_END) $fields[]="{$V_END} AS ngay_ket_thuc";
if ($V_STATUS) $fields[]="{$V_STATUS} AS trang_thai";
if ($V_CREATE) $fields[]="{$V_CREATE} AS ngay_tao";

$sql = "SELECT ".implode(", ",$fields)." FROM {$voucherTable} {$where} ORDER BY {$orderBy} DESC LIMIT {$perPage} OFFSET {$offset}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* stats thật */
$statTotal = $total;
$statActive = 0; $statExpired = 0; $statUsedUp = 0;

$now = date('Y-m-d H:i:s');
foreach($rows as $r){
  // active: nếu có trạng thái numeric/string
  $active = true;
  if ($V_STATUS && isset($r['trang_thai'])) {
    $active = is_numeric($r['trang_thai']) ? ((int)$r['trang_thai']===1) : ((string)$r['trang_thai']!=='0');
  }
  if ($V_END && !empty($r['ngay_ket_thuc']) && $r['ngay_ket_thuc'] < $now) $active = false;
  if ($active) $statActive++;

  if ($V_END && !empty($r['ngay_ket_thuc']) && $r['ngay_ket_thuc'] < $now) $statExpired++;

  if ($V_LIMIT && $V_USED && isset($r['gioi_han']) && isset($r['da_dung']) && is_numeric($r['gioi_han']) && is_numeric($r['da_dung'])) {
    if ((int)$r['gioi_han']>0 && (int)$r['da_dung'] >= (int)$r['gioi_han']) $statUsedUp++;
  }
}

/* detail/edit */
$viewId = (int)($_GET['xem'] ?? 0);
$edit = null;
if ($viewId>0){
  $st = $pdo->prepare("SELECT * FROM {$voucherTable} WHERE {$V_ID}=? LIMIT 1");
  $st->execute([$viewId]);
  $edit = $st->fetch(PDO::FETCH_ASSOC);
}

/* flash */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

/* đường dẫn “Dùng thử” (mở trang giỏ hàng phía user với voucher) */
$DUONG_DUNGTHU = '../gio_hang.php?voucher='; // nếu file giỏ hàng bạn nằm chỗ khác, sửa ở đây
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin - Voucher</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  theme:{extend:{
    colors:{primary:"#137fec","background-light":"#f8f9fa",success:"#10b981",warning:"#f59e0b",danger:"#ef4444"},
    fontFamily:{display:["Manrope","sans-serif"]},
    boxShadow:{soft:"0 4px 20px -2px rgba(0,0,0,.05)"}
  }}
}
</script>
<style>
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
</style>
</head>

<body class="font-display bg-background-light text-slate-800 h-screen overflow-hidden flex">

<!-- SIDEBAR -->
<aside class="w-20 lg:w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full flex-shrink-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-gray-100">
    <div class="size-8 rounded bg-primary flex items-center justify-center text-white font-bold text-xl">C</div>
    <span class="ml-3 font-bold text-lg hidden lg:block text-slate-900">Crocs Admin</span>
  </div>

  <nav class="flex-1 overflow-y-auto py-6 px-3 flex flex-col gap-2">
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="index.php">
      <span class="material-symbols-outlined group-hover:text-primary">grid_view</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Tổng quan</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="sanpham.php">
      <span class="material-symbols-outlined group-hover:text-primary">inventory_2</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Sản phẩm</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="donhang.php">
      <span class="material-symbols-outlined group-hover:text-primary">shopping_bag</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Đơn hàng</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="khachhang.php">
      <span class="material-symbols-outlined group-hover:text-primary">groups</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Khách hàng</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft" href="voucher.php">
      <span class="material-symbols-outlined">sell</span>
      <span class="text-sm font-bold hidden lg:block">Voucher</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="baocao.php">
      <span class="material-symbols-outlined group-hover:text-primary">bar_chart</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Báo cáo</span>
    </a>

    <?php if($isAdmin): ?>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="nhanvien.php">
      <span class="material-symbols-outlined group-hover:text-primary">badge</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Nhân viên</span>
    </a>
    <?php endif; ?>

    <div class="mt-auto pt-6 border-t border-gray-100">
      <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="dang_xuat.php">
        <span class="material-symbols-outlined group-hover:text-primary">logout</span>
        <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Đăng xuất</span>
      </a>
    </div>
  </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col h-full overflow-hidden">

  <!-- TOPBAR -->
  <header class="bg-white/80 backdrop-blur-md border-b border-gray-200 h-16 flex items-center justify-between px-6 sticky top-0 z-20">
    <h2 class="text-xl font-bold hidden sm:block">Quản lý Voucher</h2>

    <div class="flex items-center gap-3">
      <form class="hidden sm:block relative" method="get" action="voucher.php">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q" value="<?= h($q) ?>"
          class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-80 focus:ring-2 focus:ring-primary/50"
          placeholder="Tìm mã / tên voucher..." />
      </form>

      <div class="text-xs px-3 py-1 rounded-full bg-gray-100 text-slate-600 font-bold">
        <?= $isAdmin ? 'ADMIN' : 'NHÂN VIÊN' ?>
      </div>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

      <?php if($msg): ?>
      <div class="p-4 rounded-2xl border shadow-soft bg-white <?= $type==='ok'?'border-green-200':($type==='error'?'border-red-200':'border-gray-200') ?>">
        <div class="flex gap-2 items-start">
          <span class="material-symbols-outlined <?= $type==='ok'?'text-green-600':($type==='error'?'text-red-600':'text-slate-600') ?>">
            <?= $type==='ok'?'check_circle':($type==='error'?'error':'info') ?>
          </span>
          <div class="text-sm font-semibold"><?= h($msg) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-blue-50 rounded-xl text-primary w-fit"><span class="material-symbols-outlined">sell</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Tổng voucher</div>
          <div class="text-2xl font-extrabold"><?= number_format($statTotal) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-green-50 rounded-xl text-green-700 w-fit"><span class="material-symbols-outlined">check_circle</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đang hoạt động</div>
          <div class="text-2xl font-extrabold"><?= number_format($statActive) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-yellow-50 rounded-xl text-yellow-700 w-fit"><span class="material-symbols-outlined">event_busy</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Hết hạn (trong trang)</div>
          <div class="text-2xl font-extrabold"><?= number_format($statExpired) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-red-50 rounded-xl text-red-700 w-fit"><span class="material-symbols-outlined">block</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đã dùng hết lượt (trong trang)</div>
          <div class="text-2xl font-extrabold"><?= number_format($statUsedUp) ?></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LIST -->
        <div class="lg:col-span-2 bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-extrabold">Danh sách voucher</div>
            <div class="text-xs text-slate-500">Bảng: <b><?= h($voucherTable) ?></b></div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-slate-500 border-b">
                  <th class="py-3 pr-3">Mã</th>
                  <th class="py-3 pr-3">Giảm</th>
                  <th class="py-3 pr-3">Thời gian</th>
                  <th class="py-3 pr-3">Trạng thái</th>
                  <th class="py-3 pr-3">Dùng</th>
                  <th class="py-3 text-right">Hành động</th>
                </tr>
              </thead>
              <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="10" class="py-8 text-center text-slate-500">Không có voucher.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r): ?>
                <?php
                  $id = (int)$r['id'];
                  $active = true;
                  if ($V_STATUS && isset($r['trang_thai'])) $active = is_numeric($r['trang_thai']) ? ((int)$r['trang_thai']===1) : ((string)$r['trang_thai']!=='0');
                  if ($V_END && !empty($r['ngay_ket_thuc']) && $r['ngay_ket_thuc'] < date('Y-m-d H:i:s')) $active=false;

                  $badge = $active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-700';

                  $giam = '';
                  if (isset($r['gia_tri'])) {
                    $giam = number_format((int)$r['gia_tri']).' ₫';
                    if (!empty($r['loai'])) {
                      $loai = strtolower((string)$r['loai']);
                      if (strpos($loai,'phan')!==false || strpos($loai,'%')!==false || $loai==='percent') $giam = (int)$r['gia_tri'].'%';
                    }
                  }

                  $time = '';
                  if (!empty($r['ngay_bat_dau']) || !empty($r['ngay_ket_thuc'])) {
                    $time = trim(($r['ngay_bat_dau'] ?? '').' → '.($r['ngay_ket_thuc'] ?? ''));
                  }
                  $usedText = '';
                  if (isset($r['da_dung']) || isset($r['gioi_han'])) {
                    $usedText = (int)($r['da_dung'] ?? 0).' / '.(int)($r['gioi_han'] ?? 0);
                  }
                ?>
                <tr class="border-b last:border-0 hover:bg-gray-50 <?= ($viewId===$id)?'bg-blue-50/40':'' ?>">
                  <td class="py-3 pr-3">
                    <div class="font-extrabold text-slate-900"><?= h($r['ma']) ?></div>
                    <div class="text-xs text-slate-500"><?= h($r['ten'] ?? '') ?></div>
                  </td>

                  <td class="py-3 pr-3 font-extrabold text-slate-900"><?= h($giam) ?></td>

                  <td class="py-3 pr-3 text-slate-600">
                    <?= $time ? h($time) : '—' ?>
                    <?php if(!empty($r['don_toi_thieu'])): ?>
                      <div class="text-xs text-slate-500">Tối thiểu: <b><?= number_format((int)$r['don_toi_thieu']) ?> ₫</b></div>
                    <?php endif; ?>
                  </td>

                  <td class="py-3 pr-3">
                    <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $badge ?>">
                      <?= $active ? 'Hoạt động' : 'Tạm tắt / Hết hạn' ?>
                    </span>
                  </td>

                  <td class="py-3 pr-3 text-slate-700 font-bold"><?= $usedText ?: '—' ?></td>

                  <td class="py-3 text-right">
                    <div class="flex justify-end gap-2">
                      <a class="px-3 py-2 rounded-xl bg-blue-50 text-primary font-extrabold hover:bg-blue-100 text-xs"
                         href="voucher.php?<?= h(http_build_query(array_merge($_GET,['xem'=>$id]))) ?>">Sửa</a>

                      <a class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200 text-xs"
                         target="_blank"
                         href="<?= h($DUONG_DUNGTHU.urlencode($r['ma'])) ?>">Dùng thử</a>

                      <?php if($isAdmin && $V_STATUS): ?>
                        <form method="post">
                          <input type="hidden" name="action" value="bat_tat">
                          <input type="hidden" name="id" value="<?= $id ?>">
                          <button class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200 text-xs">
                            Bật/Tắt
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

          <!-- PAGINATION -->
          <div class="flex items-center justify-between mt-4">
            <div class="text-xs text-slate-500">Trang <?= $page ?>/<?= $totalPages ?> • Tổng <?= number_format($total) ?></div>
            <div class="flex gap-2">
              <?php
              $qs=$_GET;
              $mk=function($p) use($qs){ $qs['page']=$p; return 'voucher.php?'.http_build_query($qs); };
              ?>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
            </div>
          </div>
        </div>

        <!-- FORM -->
        <div class="bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-extrabold"><?= $edit ? 'Sửa voucher' : 'Thêm voucher' ?></div>
              <div class="text-xs text-slate-500"><?= $isAdmin ? 'Bạn có quyền CRUD' : 'Nhân viên chỉ xem' ?></div>
            </div>
            <?php if($edit): ?>
              <a class="text-sm font-extrabold text-primary hover:underline" href="voucher.php">Bỏ chọn</a>
            <?php endif; ?>
          </div>

          <?php if(!$isAdmin): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Nhân viên không được thêm/sửa/xoá voucher.
            </div>
          <?php else: ?>
            <form method="post" class="space-y-3" onsubmit="return true;">
              <input type="hidden" name="action" value="<?= $edit?'sua':'them' ?>">
              <?php if($edit): ?><input type="hidden" name="id" value="<?= (int)$viewId ?>"><?php endif; ?>

              <div>
                <label class="text-sm font-bold">Mã voucher</label>
                <input name="ma" required
                  value="<?= $edit ? h($edit[$V_CODE] ?? '') : '' ?>"
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                  placeholder="VD: CROCS10, FREESHIP...">
              </div>

              <?php if($V_NAME): ?>
              <div>
                <label class="text-sm font-bold">Tên voucher</label>
                <input name="ten"
                  value="<?= $edit ? h($edit[$V_NAME] ?? '') : '' ?>"
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                  placeholder="VD: Giảm 10% đơn hàng">
              </div>
              <?php endif; ?>

              <?php if($V_DESC): ?>
              <div>
                <label class="text-sm font-bold">Mô tả</label>
                <textarea name="mo_ta" rows="3" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                  placeholder="Điều kiện áp dụng..."><?= $edit ? h($edit[$V_DESC] ?? '') : '' ?></textarea>
              </div>
              <?php endif; ?>

              <div class="grid grid-cols-2 gap-3">
                <?php if($V_TYPE): ?>
                <div>
                  <label class="text-sm font-bold">Loại</label>
                  <select name="loai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                    <?php
                      $curType = $edit ? (string)($edit[$V_TYPE] ?? '') : '';
                      $opts = array_unique(array_filter([$curType,'percent','fixed','%','phan_tram','tien_mat']));
                      if (!$opts) $opts = ['percent','fixed'];
                      foreach($opts as $op):
                    ?>
                      <option value="<?= h($op) ?>" <?= ($curType===$op)?'selected':'' ?>><?= h($op) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>

                <?php if($V_VALUE): ?>
                <div>
                  <label class="text-sm font-bold">Giá trị giảm</label>
                  <input name="gia_tri"
                    value="<?= $edit ? h($edit[$V_VALUE] ?? '') : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                    placeholder="VD: 10 hoặc 50000">
                </div>
                <?php endif; ?>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <?php if($V_MIN): ?>
                <div>
                  <label class="text-sm font-bold">Đơn tối thiểu</label>
                  <input name="don_toi_thieu"
                    value="<?= $edit ? h($edit[$V_MIN] ?? '') : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                    placeholder="VD: 200000">
                </div>
                <?php endif; ?>

                <?php if($V_MAX): ?>
                <div>
                  <label class="text-sm font-bold">Giảm tối đa</label>
                  <input name="giam_toi_da"
                    value="<?= $edit ? h($edit[$V_MAX] ?? '') : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                    placeholder="VD: 50000">
                </div>
                <?php endif; ?>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <?php if($V_LIMIT): ?>
                <div>
                  <label class="text-sm font-bold">Giới hạn lượt</label>
                  <input name="gioi_han"
                    value="<?= $edit ? h($edit[$V_LIMIT] ?? '') : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                    placeholder="VD: 100">
                </div>
                <?php endif; ?>

                <?php if($V_STATUS): ?>
                <div>
                  <label class="text-sm font-bold">Trạng thái</label>
                  <select name="trang_thai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                    <?php
                      $cur = $edit ? (string)($edit[$V_STATUS] ?? '1') : '1';
                    ?>
                    <option value="1" <?= $cur==='1'?'selected':'' ?>>Bật</option>
                    <option value="0" <?= $cur==='0'?'selected':'' ?>>Tắt</option>
                  </select>
                </div>
                <?php endif; ?>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <?php if($V_START): ?>
                <div>
                  <label class="text-sm font-bold">Ngày bắt đầu</label>
                  <input name="ngay_bat_dau" type="datetime-local"
                    value="<?= $edit ? h(str_replace(' ','T', (string)($edit[$V_START] ?? ''))) : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>
                <?php if($V_END): ?>
                <div>
                  <label class="text-sm font-bold">Ngày kết thúc</label>
                  <input name="ngay_ket_thuc" type="datetime-local"
                    value="<?= $edit ? h(str_replace(' ','T', (string)($edit[$V_END] ?? ''))) : '' ?>"
                    class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>
              </div>

              <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
                <?= $edit ? 'Lưu thay đổi' : 'Thêm voucher' ?>
              </button>

              <?php if($edit): ?>
              <div class="grid grid-cols-2 gap-2">
                <a class="w-full text-center px-4 py-3 rounded-2xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200"
                   target="_blank" href="<?= h($DUONG_DUNGTHU.urlencode($edit[$V_CODE] ?? '')) ?>">Dùng thử</a>

                <form method="post" onsubmit="return confirm('Xóa voucher này?');">
                  <input type="hidden" name="action" value="xoa">
                  <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                  <button class="w-full px-4 py-3 rounded-2xl bg-red-600 text-white font-extrabold hover:bg-red-700">
                    Xóa
                  </button>
                </form>
              </div>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</main>
</body>
</html>
