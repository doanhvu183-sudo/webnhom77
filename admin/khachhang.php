<?php
// admin/khachhang.php
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
  header("Location: khachhang.php".($params?('?'.http_build_query($params)):''));
  exit;
}

/* ================= Detect customer table ================= */
$userTable = null;
foreach (['nguoidung','nguoi_dung','users','khachhang'] as $t){
  if (tableExists($pdo,$t)) { $userTable = $t; break; }
}
if (!$userTable) die("Không tìm thấy bảng khách hàng (nguoidung/users/khachhang...).");

$uCols = getCols($pdo, $userTable);
$U_ID     = pickCol($uCols, ['id_nguoi_dung','id_khach_hang','id','user_id','khachhang_id']);
$U_NAME   = pickCol($uCols, ['ho_ten','ten','full_name','name']);
$U_EMAIL  = pickCol($uCols, ['email']);
$U_PHONE  = pickCol($uCols, ['so_dien_thoai','sdt','phone']);
$U_ADDR   = pickCol($uCols, ['dia_chi','address']);
$U_AVATAR = pickCol($uCols, ['avatar','hinh_anh','anh']);
$U_STATUS = pickCol($uCols, ['trang_thai','is_active','active','status']);
$U_NOTE   = pickCol($uCols, ['ghi_chu','note']);
$U_CREATE = pickCol($uCols, ['ngay_tao','created_at','ngay_dang_ky']);

if (!$U_ID) die("Bảng {$userTable} thiếu cột ID.");

/* ================= Detect orders (donhang) ================= */
$hasDon = tableExists($pdo, 'donhang');
$dhCols = $hasDon ? getCols($pdo,'donhang') : [];
$DH_ID     = $hasDon ? pickCol($dhCols, ['id_don_hang','id','donhang_id']) : null;
$DH_USERID = $hasDon ? pickCol($dhCols, ['id_nguoi_dung','id_khach_hang','user_id','khachhang_id']) : null;
$DH_CODE   = $hasDon ? pickCol($dhCols, ['ma_don_hang','ma_dh','order_code']) : null;
$DH_TOTAL  = $hasDon ? pickCol($dhCols, ['tong_tien','total']) : null;
$DH_STATUS = $hasDon ? pickCol($dhCols, ['trang_thai','status']) : null;
$DH_DATE   = $hasDon ? pickCol($dhCols, ['ngay_dat','ngay_tao','created_at']) : null;

/* ================= POST: update customer (admin only) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền cập nhật khách hàng.']);

  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID khách hàng.']);

  if ($action === 'capnhat') {
    $name  = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['sdt'] ?? '');
    $addr  = trim($_POST['dia_chi'] ?? '');
    $note  = trim($_POST['ghi_chu'] ?? '');
    $status= trim($_POST['trang_thai'] ?? '');

    $set = [];
    $bind = [':id'=>$id];

    if ($U_NAME){ $set[]="{$U_NAME}=:n"; $bind[':n']=$name; }
    if ($U_EMAIL){ $set[]="{$U_EMAIL}=:e"; $bind[':e']=$email; }
    if ($U_PHONE){ $set[]="{$U_PHONE}=:p"; $bind[':p']=$phone; }
    if ($U_ADDR){ $set[]="{$U_ADDR}=:a"; $bind[':a']=$addr; }
    if ($U_NOTE){ $set[]="{$U_NOTE}=:g"; $bind[':g']=$note; }
    if ($U_STATUS){
      $set[]="{$U_STATUS}=:s";
      $bind[':s'] = ($status==='' ? 1 : (is_numeric($status) ? (int)$status : $status));
    }

    if (!$set) redirectWith(['type'=>'error','msg'=>'Bảng khách hàng không có cột để cập nhật.']);

    $sql="UPDATE {$userTable} SET ".implode(', ',$set)." WHERE {$U_ID}=:id";
    $pdo->prepare($sql)->execute($bind);

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật khách hàng.','xem'=>$id]);
  }

  if ($action === 'khoa_mo') {
    if (!$U_STATUS) redirectWith(['type'=>'error','msg'=>'Bảng khách hàng không có cột trạng_thái.']);
    $cur=$pdo->prepare("SELECT {$U_STATUS} FROM {$userTable} WHERE {$U_ID}=? LIMIT 1");
    $cur->execute([$id]);
    $stNow=$cur->fetchColumn();
    $new = (is_numeric($stNow) ? ((int)$stNow ? 0 : 1) : ((string)$stNow==='1'?'0':'1'));
    $pdo->prepare("UPDATE {$userTable} SET {$U_STATUS}=? WHERE {$U_ID}=?")->execute([$new,$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái tài khoản.','xem'=>$id]);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= Filters / pagination ================= */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$where=" WHERE 1 ";
$params=[];

if ($q!==''){
  $conds=[];
  if ($U_NAME){ $conds[]="{$U_NAME} LIKE ?"; $params[]="%{$q}%"; }
  if ($U_EMAIL){ $conds[]="{$U_EMAIL} LIKE ?"; $params[]="%{$q}%"; }
  if ($U_PHONE){ $conds[]="{$U_PHONE} LIKE ?"; $params[]="%{$q}%"; }
  $conds[]="{$U_ID} LIKE ?"; $params[]="%{$q}%";
  $where.=" AND (".implode(" OR ",$conds).") ";
}

$orderBy = $U_CREATE ? $U_CREATE : $U_ID;

/* total */
$st=$pdo->prepare("SELECT COUNT(*) FROM {$userTable} {$where}");
$st->execute($params);
$total=(int)$st->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));

/* list with order stats thật */
$fields=["u.{$U_ID} AS id"];
if ($U_NAME) $fields[]="u.{$U_NAME} AS ho_ten";
if ($U_EMAIL) $fields[]="u.{$U_EMAIL} AS email";
if ($U_PHONE) $fields[]="u.{$U_PHONE} AS sdt";
if ($U_ADDR) $fields[]="u.{$U_ADDR} AS dia_chi";
if ($U_AVATAR) $fields[]="u.{$U_AVATAR} AS avatar";
if ($U_STATUS) $fields[]="u.{$U_STATUS} AS trang_thai";
if ($U_NOTE) $fields[]="u.{$U_NOTE} AS ghi_chu";
if ($U_CREATE) $fields[]="u.{$U_CREATE} AS ngay_tao";

$joinOrders = "";
$selectOrders = "";
if ($hasDon && $DH_USERID && $DH_TOTAL) {
  $selectOrders .= ", COUNT(d.{$DH_ID}) AS so_don, COALESCE(SUM(d.{$DH_TOTAL}),0) AS tong_chi";
  $joinOrders = " LEFT JOIN donhang d ON d.{$DH_USERID} = u.{$U_ID} ";
}

$sql="SELECT ".implode(", ",$fields)." {$selectOrders}
      FROM {$userTable} u
      {$joinOrders}
      {$where}
      GROUP BY u.{$U_ID}
      ORDER BY u.{$orderBy} DESC
      LIMIT {$perPage} OFFSET {$offset}";
$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* stats thật */
$statTotal = $total;
$statActive = 0;
if ($U_STATUS) {
  $statActive = (int)$pdo->query("SELECT COUNT(*) FROM {$userTable} WHERE {$U_STATUS}=1")->fetchColumn();
}
$statNew7 = 0;
if ($U_CREATE) {
  $statNew7 = (int)$pdo->query("SELECT COUNT(*) FROM {$userTable} WHERE {$U_CREATE} >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
}
$statOrders = 0;
$statRevenue = 0;
if ($hasDon && $DH_ID) $statOrders = (int)$pdo->query("SELECT COUNT(*) FROM donhang")->fetchColumn();
if ($hasDon && $DH_TOTAL) $statRevenue = (int)$pdo->query("SELECT COALESCE(SUM({$DH_TOTAL}),0) FROM donhang")->fetchColumn();

/* detail view */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
$orders = [];
if ($viewId>0){
  $st=$pdo->prepare("SELECT * FROM {$userTable} WHERE {$U_ID}=? LIMIT 1");
  $st->execute([$viewId]);
  $view=$st->fetch(PDO::FETCH_ASSOC);

  if ($view && $hasDon && $DH_USERID) {
    $cols = ["*"];
    $orderBy2 = $DH_DATE ? $DH_DATE : ($DH_ID ?: '1');
    $st2=$pdo->prepare("SELECT ".implode(",",$cols)." FROM donhang WHERE {$DH_USERID}=? ORDER BY {$orderBy2} DESC LIMIT 10");
    $st2->execute([$viewId]);
    $orders=$st2->fetchAll(PDO::FETCH_ASSOC);
  }
}

/* flash */
$type=$_GET['type'] ?? '';
$msg=$_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin - Khách hàng</title>

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

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft" href="khachhang.php">
      <span class="material-symbols-outlined">groups</span>
      <span class="text-sm font-bold hidden lg:block">Khách hàng</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="voucher.php">
      <span class="material-symbols-outlined group-hover:text-primary">sell</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Voucher</span>
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
    <h2 class="text-xl font-bold hidden sm:block">Quản lý Khách hàng</h2>

    <div class="flex items-center gap-3">
      <form class="hidden sm:block relative" method="get" action="khachhang.php">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q" value="<?= h($q) ?>"
          class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-80 focus:ring-2 focus:ring-primary/50"
          placeholder="Tìm tên / email / SĐT..." />
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
          <div class="p-3 bg-blue-50 rounded-xl text-primary w-fit"><span class="material-symbols-outlined">groups</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Tổng khách</div>
          <div class="text-2xl font-extrabold"><?= number_format($statTotal) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-green-50 rounded-xl text-green-700 w-fit"><span class="material-symbols-outlined">verified</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Hoạt động</div>
          <div class="text-2xl font-extrabold"><?= number_format($statActive) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-yellow-50 rounded-xl text-yellow-700 w-fit"><span class="material-symbols-outlined">person_add</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Mới 7 ngày</div>
          <div class="text-2xl font-extrabold"><?= number_format($statNew7) ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-purple-50 rounded-xl text-purple-700 w-fit"><span class="material-symbols-outlined">receipt_long</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Tổng đơn / Doanh thu</div>
          <div class="text-xl font-extrabold"><?= number_format($statOrders) ?> • <?= number_format($statRevenue) ?> ₫</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LIST -->
        <div class="lg:col-span-2 bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-extrabold">Danh sách khách hàng</div>
            <div class="text-xs text-slate-500">Bảng: <b><?= h($userTable) ?></b></div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-slate-500 border-b">
                  <th class="py-3 pr-3">Khách</th>
                  <th class="py-3 pr-3">Liên hệ</th>
                  <th class="py-3 pr-3">Đơn / Chi</th>
                  <th class="py-3 pr-3">Trạng thái</th>
                  <th class="py-3 text-right">Xem</th>
                </tr>
              </thead>
              <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="10" class="py-8 text-center text-slate-500">Không có khách hàng.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r): ?>
                <?php
                  $id=(int)$r['id'];
                  $st = true;
                  if ($U_STATUS && isset($r['trang_thai'])) $st = is_numeric($r['trang_thai']) ? ((int)$r['trang_thai']===1) : ((string)$r['trang_thai']!=='0');
                  $badge = $st ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-700';
                ?>
                <tr class="border-b last:border-0 hover:bg-gray-50 <?= ($viewId===$id)?'bg-blue-50/40':'' ?>">
                  <td class="py-3 pr-3">
                    <div class="font-extrabold text-slate-900"><?= h($r['ho_ten'] ?? ('User #'.$id)) ?></div>
                    <div class="text-xs text-slate-500">ID: <?= $id ?></div>
                    <?php if(!empty($r['ngay_tao'])): ?>
                      <div class="text-xs text-slate-500">Tạo: <b><?= h($r['ngay_tao']) ?></b></div>
                    <?php endif; ?>
                  </td>

                  <td class="py-3 pr-3">
                    <div class="text-slate-800 font-bold"><?= h($r['email'] ?? '') ?></div>
                    <div class="text-xs text-slate-500"><?= h($r['sdt'] ?? '') ?></div>
                  </td>

                  <td class="py-3 pr-3">
                    <?php if(isset($r['so_don'])): ?>
                      <div class="font-extrabold text-slate-900"><?= number_format((int)$r['so_don']) ?> đơn</div>
                      <div class="text-xs text-slate-500">Chi: <b><?= number_format((int)$r['tong_chi']) ?> ₫</b></div>
                    <?php else: ?>
                      <div class="text-slate-500">—</div>
                    <?php endif; ?>
                  </td>

                  <td class="py-3 pr-3">
                    <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $badge ?>">
                      <?= $st ? 'Hoạt động' : 'Đã khóa' ?>
                    </span>
                  </td>

                  <td class="py-3 text-right">
                    <a class="px-3 py-2 rounded-xl bg-blue-50 text-primary font-extrabold hover:bg-blue-100 text-xs"
                       href="khachhang.php?<?= h(http_build_query(array_merge($_GET,['xem'=>$id]))) ?>">Chi tiết</a>
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
              $mk=function($p) use($qs){ $qs['page']=$p; return 'khachhang.php?'.http_build_query($qs); };
              ?>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
            </div>
          </div>
        </div>

        <!-- DETAIL -->
        <div class="bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-extrabold">Chi tiết khách</div>
              <div class="text-xs text-slate-500">Chọn 1 khách để xem</div>
            </div>
            <?php if($viewId): ?>
              <a class="text-sm font-extrabold text-primary hover:underline" href="khachhang.php">Bỏ chọn</a>
            <?php endif; ?>
          </div>

          <?php if(!$view): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Chưa chọn khách hàng.
            </div>
          <?php else: ?>
            <?php
              $name = $U_NAME ? ($view[$U_NAME] ?? '') : '';
              $email= $U_EMAIL ? ($view[$U_EMAIL] ?? '') : '';
              $phone= $U_PHONE ? ($view[$U_PHONE] ?? '') : '';
              $addr = $U_ADDR ? ($view[$U_ADDR] ?? '') : '';
              $note = $U_NOTE ? ($view[$U_NOTE] ?? '') : '';
              $st = true;
              if ($U_STATUS && isset($view[$U_STATUS])) $st = is_numeric($view[$U_STATUS]) ? ((int)$view[$U_STATUS]===1) : ((string)$view[$U_STATUS]!=='0');
              $badge = $st ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-700';
            ?>

            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
              <div class="flex items-start justify-between">
                <div>
                  <div class="text-xs text-slate-500">ID</div>
                  <div class="text-xl font-extrabold"><?= (int)$viewId ?></div>
                  <div class="text-sm font-bold text-slate-800"><?= h($name ?: ('User #'.$viewId)) ?></div>
                </div>
                <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $badge ?>">
                  <?= $st ? 'Hoạt động' : 'Đã khóa' ?>
                </span>
              </div>

              <div class="mt-3 text-sm space-y-1">
                <div><span class="text-slate-500">Email:</span> <b><?= h($email) ?></b></div>
                <div><span class="text-slate-500">SĐT:</span> <b><?= h($phone) ?></b></div>
                <div class="text-xs text-slate-500"><?= h($addr) ?></div>
              </div>
            </div>

            <?php if($isAdmin): ?>
              <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="capnhat">
                <input type="hidden" name="id" value="<?= (int)$viewId ?>">

                <?php if($U_NAME): ?>
                <div>
                  <label class="text-sm font-bold">Họ tên</label>
                  <input name="ho_ten" value="<?= h($name) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>

                <?php if($U_EMAIL): ?>
                <div>
                  <label class="text-sm font-bold">Email</label>
                  <input name="email" value="<?= h($email) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>

                <?php if($U_PHONE): ?>
                <div>
                  <label class="text-sm font-bold">SĐT</label>
                  <input name="sdt" value="<?= h($phone) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>

                <?php if($U_ADDR): ?>
                <div>
                  <label class="text-sm font-bold">Địa chỉ</label>
                  <input name="dia_chi" value="<?= h($addr) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                </div>
                <?php endif; ?>

                <?php if($U_NOTE): ?>
                <div>
                  <label class="text-sm font-bold">Ghi chú</label>
                  <textarea name="ghi_chu" rows="3" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"><?= h($note) ?></textarea>
                </div>
                <?php endif; ?>

                <?php if($U_STATUS): ?>
                <div>
                  <label class="text-sm font-bold">Trạng thái</label>
                  <select name="trang_thai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                    <?php $cur = (string)($view[$U_STATUS] ?? '1'); ?>
                    <option value="1" <?= $cur==='1'?'selected':'' ?>>Hoạt động</option>
                    <option value="0" <?= $cur==='0'?'selected':'' ?>>Khóa</option>
                  </select>
                </div>
                <?php endif; ?>

                <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
                  Lưu cập nhật
                </button>

                <?php if($U_STATUS): ?>
                <form method="post" class="mt-2" onsubmit="return confirm('Đổi trạng thái khóa/mở?');">
                  <input type="hidden" name="action" value="khoa_mo">
                  <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                  <button class="w-full px-4 py-3 rounded-2xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200">
                    Khóa / Mở tài khoản
                  </button>
                </form>
                <?php endif; ?>
              </form>
            <?php else: ?>
              <div class="mt-4 p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
                Nhân viên chỉ được xem, không được chỉnh sửa khách hàng.
              </div>
            <?php endif; ?>

            <!-- ORDERS -->
            <div class="mt-6">
              <div class="flex items-center justify-between mb-2">
                <div class="font-extrabold">Lịch sử mua (10 đơn gần nhất)</div>
                <div class="text-xs text-slate-500"><?= $hasDon ? 'Từ bảng donhang' : 'Chưa có bảng donhang' ?></div>
              </div>

              <?php if(!$orders): ?>
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
                  Không có đơn hàng.
                </div>
              <?php else: ?>
                <div class="space-y-3">
                  <?php foreach($orders as $o): ?>
                    <?php
                      $ma = $DH_CODE ? ($o[$DH_CODE] ?? ('#'.($o[$DH_ID] ?? ''))) : ('#'.($o[$DH_ID] ?? ''));
                      $tong = $DH_TOTAL ? (int)($o[$DH_TOTAL] ?? 0) : 0;
                      $stt  = $DH_STATUS ? (string)($o[$DH_STATUS] ?? '') : '';
                      $ngay = $DH_DATE ? (string)($o[$DH_DATE] ?? '') : '';
                    ?>
                    <div class="p-3 rounded-2xl bg-white border border-gray-200">
                      <div class="flex items-start justify-between">
                        <div>
                          <div class="font-extrabold text-slate-900"><?= h($ma) ?></div>
                          <div class="text-xs text-slate-500"><?= h($ngay) ?></div>
                        </div>
                        <div class="text-right">
                          <div class="font-extrabold"><?= number_format($tong) ?> ₫</div>
                          <div class="text-xs text-slate-500"><?= h($stt) ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</main>
</body>
</html>
