<?php
// admin/baocao.php
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
function money($n){ return number_format((int)$n, 0, ',', '.').' ₫'; }
function pctChange($cur, $prev){
  $cur=(float)$cur; $prev=(float)$prev;
  if ($prev == 0) return null;
  return (($cur-$prev)/$prev)*100.0;
}
function dateRangeLabel($key){
  if ($key==='today') return 'Hôm nay';
  if ($key==='7days') return '7 ngày qua';
  if ($key==='month') return 'Tháng này';
  return 'Tuỳ chọn';
}
function buildLinePath(array $vals, $w=500, $h=200, $pad=20){
  // vals: [0..n-1]
  $n = count($vals);
  if ($n<=1) return "M0,".($h-$pad)." L".$w."," . ($h-$pad);
  $max = max($vals);
  $min = min($vals);
  if ($max == $min) { $max = $min + 1; } // tránh chia 0
  $dx = ($w - 2*$pad)/($n-1);

  $pts = [];
  for($i=0;$i<$n;$i++){
    $x = $pad + $i*$dx;
    $y = $h-$pad - (($vals[$i]-$min)/($max-$min))*($h-2*$pad);
    $pts[] = [$x,$y];
  }

  // Path dạng curve nhẹ (Q/T)
  $d = "M".$pts[0][0].",".$pts[0][1]." ";
  for($i=1;$i<$n;$i++){
    $prev = $pts[$i-1];
    $cur  = $pts[$i];
    $cx = ($prev[0]+$cur[0])/2;
    $cy = ($prev[1]+$cur[1])/2;
    if ($i==1){
      $d .= "Q".$cx.",".$prev[1]." ".$cur[0].",".$cur[1]." ";
    }else{
      $d .= "T".$cur[0].",".$cur[1]." ";
    }
  }
  return trim($d);
}

/* ================= Detect tables/columns ================= */
$hasDon = tableExists($pdo,'donhang');
if(!$hasDon){
  die("Thiếu bảng <b>donhang</b> nên không thể chạy báo cáo.");
}
$dhCols = getCols($pdo,'donhang');
$DH_ID     = pickCol($dhCols, ['id_don_hang','id','donhang_id']);
$DH_TOTAL  = pickCol($dhCols, ['tong_tien','total','tongtien']);
$DH_DATE   = pickCol($dhCols, ['ngay_dat','ngay_tao','created_at','ngay_cap_nhat']);
$DH_STATUS = pickCol($dhCols, ['trang_thai','status']);
$DH_PAY    = pickCol($dhCols, ['phuong_thuc','payment_method']);

if(!$DH_TOTAL || !$DH_DATE){
  die("Bảng <b>donhang</b> thiếu cột bắt buộc (tong_tien/ngay_dat).");
}

$ctTable = null;
foreach(['ct_donhang','ct_don_hang','chitietdonhang','ctdonhang'] as $t){
  if(tableExists($pdo,$t)) { $ctTable=$t; break; }
}
$ctCols = $ctTable ? getCols($pdo,$ctTable) : [];
$CT_SP   = $ctTable ? pickCol($ctCols, ['id_san_pham','id_sp','sanpham_id']) : null;
$CT_QTY  = $ctTable ? pickCol($ctCols, ['so_luong','qty','so_luong_mua']) : null;
$CT_DHID = $ctTable ? pickCol($ctCols, ['id_don_hang','donhang_id','id']) : null;

$hasSP = tableExists($pdo,'sanpham');
$spCols = $hasSP ? getCols($pdo,'sanpham') : [];
$SP_ID   = $hasSP ? pickCol($spCols, ['id_san_pham','id','sanpham_id']) : null;
$SP_NAME = $hasSP ? pickCol($spCols, ['ten_san_pham','ten','name']) : null;
$SP_IMG  = $hasSP ? pickCol($spCols, ['hinh_anh','anh','image']) : null;
$SP_GIA  = $hasSP ? pickCol($spCols, ['gia','gia_ban','price']) : null;

$tonTable = tableExists($pdo,'tonkho') ? 'tonkho' : null;
$tonCols  = $tonTable ? getCols($pdo,$tonTable) : [];
$TON_SP   = $tonTable ? pickCol($tonCols, ['id_san_pham','sanpham_id']) : null;
$TON_QTY  = $tonTable ? pickCol($tonCols, ['so_luong','ton','qty']) : null;

$userTable = null;
foreach (['nguoidung','nguoi_dung','users','khachhang'] as $t){
  if (tableExists($pdo,$t)) { $userTable = $t; break; }
}
$uCols = $userTable ? getCols($pdo,$userTable) : [];
$U_ID  = $userTable ? pickCol($uCols, ['id_nguoi_dung','id_khach_hang','id','user_id']) : null;
$U_CREATE = $userTable ? pickCol($uCols, ['ngay_tao','created_at','ngay_dang_ky']) : null;

$voucherTable = null;
foreach (['voucher','khuyenmai','khuyen_mai','ma_giam_gia','uudai'] as $t){
  if (tableExists($pdo,$t)) { $voucherTable = $t; break; }
}

/* ================= Range filter ================= */
$range = $_GET['range'] ?? 'today';
if (!in_array($range,['today','7days','month'],true)) $range='today';

$now = new DateTime('now');
$start = clone $now; $end = clone $now;
$start->setTime(0,0,0);
$end->setTime(23,59,59);

$daysCount = 1;
if ($range==='7days'){
  $start = (new DateTime('now'))->modify('-6 days')->setTime(0,0,0);
  $daysCount = 7;
}
if ($range==='month'){
  $start = (new DateTime('first day of this month'))->setTime(0,0,0);
  $daysCount = (int)$now->format('j'); // số ngày từ đầu tháng đến nay
}

$startStr = $start->format('Y-m-d H:i:s');
$endStr   = $end->format('Y-m-d H:i:s');

/* previous period để so sánh */
$prevEnd = (clone $start)->modify('-1 second');
$prevStart = (clone $prevEnd)->modify('-'.($daysCount-1).' days')->setTime(0,0,0);
$prevStartStr = $prevStart->format('Y-m-d H:i:s');
$prevEndStr   = $prevEnd->format('Y-m-d H:i:s');

/* ================= Summary metrics (real) ================= */
$st = $pdo->prepare("SELECT COALESCE(SUM($DH_TOTAL),0) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?");
$st->execute([$startStr,$endStr]);
$revenue = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?");
$st->execute([$startStr,$endStr]);
$orders = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM($DH_TOTAL),0) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?");
$st->execute([$prevStartStr,$prevEndStr]);
$prevRevenue = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?");
$st->execute([$prevStartStr,$prevEndStr]);
$prevOrders = (int)$st->fetchColumn();

$avgOrder = $orders>0 ? (int)round($revenue/$orders) : 0;
$prevAvgOrder = $prevOrders>0 ? (int)round($prevRevenue/$prevOrders) : 0;

$revChange = pctChange($revenue,$prevRevenue);
$ordChange = pctChange($orders,$prevOrders);
$avgChange = pctChange($avgOrder,$prevAvgOrder);

/* trạng thái đơn */
$statusRows = [];
if ($DH_STATUS){
  $st = $pdo->prepare("SELECT $DH_STATUS AS st, COUNT(*) AS c
                       FROM donhang
                       WHERE $DH_DATE BETWEEN ? AND ?
                       GROUP BY $DH_STATUS
                       ORDER BY c DESC");
  $st->execute([$startStr,$endStr]);
  $statusRows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* doanh thu theo ngày (để vẽ chart) */
$dailyMap = [];
$st = $pdo->prepare("SELECT DATE($DH_DATE) AS d, COALESCE(SUM($DH_TOTAL),0) AS v
                     FROM donhang
                     WHERE $DH_DATE BETWEEN ? AND ?
                     GROUP BY DATE($DH_DATE)
                     ORDER BY d ASC");
$st->execute([$startStr,$endStr]);
foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
  $dailyMap[$r['d']] = (int)$r['v'];
}
$labels = [];
$vals = [];
$tmp = clone $start;
for($i=0;$i<$daysCount;$i++){
  $d = $tmp->format('Y-m-d');
  $labels[] = $tmp->format('d/m');
  $vals[] = (int)($dailyMap[$d] ?? 0);
  $tmp->modify('+1 day');
}
$path = buildLinePath($vals, 500, 200, 20);

/* top bán chạy */
$topProducts = [];
if ($ctTable && $hasSP && $CT_SP && $CT_QTY && $CT_DHID && $SP_ID){
  $sql = "SELECT sp.$SP_ID AS id, ".($SP_NAME ? "sp.$SP_NAME AS ten" : "sp.$SP_ID AS ten").",
                 ".($SP_IMG ? "sp.$SP_IMG AS hinh" : "NULL AS hinh").",
                 SUM(ct.$CT_QTY) AS qty
          FROM $ctTable ct
          JOIN donhang d ON d.$DH_ID = ct.$CT_DHID
          JOIN sanpham sp ON sp.$SP_ID = ct.$CT_SP
          WHERE d.$DH_DATE BETWEEN ? AND ?
          GROUP BY sp.$SP_ID
          ORDER BY qty DESC
          LIMIT 8";
  $st = $pdo->prepare($sql);
  $st->execute([$startStr,$endStr]);
  $topProducts = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* tồn kho thấp */
$lowStock = [];
if ($tonTable && $hasSP && $TON_SP && $TON_QTY && $SP_ID){
  $sql = "SELECT sp.$SP_ID AS id, ".($SP_NAME ? "sp.$SP_NAME AS ten" : "sp.$SP_ID AS ten").",
                 ".($SP_IMG ? "sp.$SP_IMG AS hinh" : "NULL AS hinh").",
                 t.$TON_QTY AS so_luong
          FROM $tonTable t
          JOIN sanpham sp ON sp.$SP_ID = t.$TON_SP
          WHERE t.$TON_QTY <= 5
          ORDER BY t.$TON_QTY ASC
          LIMIT 8";
  $lowStock = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/* counts khác */
$customerCount = 0;
if ($userTable && $U_ID){
  $customerCount = (int)$pdo->query("SELECT COUNT(*) FROM $userTable")->fetchColumn();
}
$newCustomer = 0;
if ($userTable && $U_CREATE){
  $st = $pdo->prepare("SELECT COUNT(*) FROM $userTable WHERE $U_CREATE BETWEEN ? AND ?");
  $st->execute([$startStr,$endStr]);
  $newCustomer = (int)$st->fetchColumn();
}
$voucherCount = 0;
if ($voucherTable){
  $voucherCount = (int)$pdo->query("SELECT COUNT(*) FROM $voucherTable")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin - Báo cáo</title>

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
    boxShadow:{soft:"0 4px 20px -2px rgba(0,0,0,.05)"},
    borderRadius:{'2xl':"1.5rem"}
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
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="voucher.php">
      <span class="material-symbols-outlined group-hover:text-primary">sell</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Voucher</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft" href="baocao.php">
      <span class="material-symbols-outlined">bar_chart</span>
      <span class="text-sm font-bold hidden lg:block">Báo cáo</span>
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
    <div class="flex items-center gap-3">
      <h2 class="text-xl font-bold hidden sm:block">Báo cáo</h2>
      <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-slate-600 font-bold">
        <?= h(dateRangeLabel($range)) ?>
      </span>
    </div>

    <div class="flex items-center gap-2">
      <a class="px-3 py-2 rounded-xl text-sm font-extrabold border bg-white <?= $range==='today'?'border-primary text-primary':'border-gray-200 text-slate-700' ?>"
         href="baocao.php?range=today">Hôm nay</a>
      <a class="px-3 py-2 rounded-xl text-sm font-extrabold border bg-white <?= $range==='7days'?'border-primary text-primary':'border-gray-200 text-slate-700' ?>"
         href="baocao.php?range=7days">7 ngày</a>
      <a class="px-3 py-2 rounded-xl text-sm font-extrabold border bg-white <?= $range==='month'?'border-primary text-primary':'border-gray-200 text-slate-700' ?>"
         href="baocao.php?range=month">Tháng</a>

      <div class="ml-2 text-xs px-3 py-1 rounded-full bg-gray-100 text-slate-600 font-bold">
        <?= $isAdmin ? 'ADMIN' : 'NHÂN VIÊN' ?>
      </div>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

      <!-- CARDS -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-start mb-4">
            <div class="p-3 bg-blue-50 rounded-xl text-primary"><span class="material-symbols-outlined">attach_money</span></div>
            <?php if($revChange!==null): ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg <?= $revChange>=0?'bg-green-50 text-green-700':'bg-red-50 text-red-700' ?>">
                <?= ($revChange>=0?'+':'').number_format($revChange,1) ?>%
              </span>
            <?php else: ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">—</span>
            <?php endif; ?>
          </div>
          <div class="text-slate-500 text-sm font-medium">Doanh thu</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= money($revenue) ?></div>
          <div class="text-xs text-slate-400 mt-2">Kỳ trước: <b class="text-slate-600"><?= money($prevRevenue) ?></b></div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-start mb-4">
            <div class="p-3 bg-purple-50 rounded-xl text-purple-600"><span class="material-symbols-outlined">shopping_cart</span></div>
            <?php if($ordChange!==null): ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg <?= $ordChange>=0?'bg-green-50 text-green-700':'bg-red-50 text-red-700' ?>">
                <?= ($ordChange>=0?'+':'').number_format($ordChange,1) ?>%
              </span>
            <?php else: ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">—</span>
            <?php endif; ?>
          </div>
          <div class="text-slate-500 text-sm font-medium">Đơn hàng</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($orders) ?></div>
          <div class="text-xs text-slate-400 mt-2">Kỳ trước: <b class="text-slate-600"><?= number_format($prevOrders) ?></b></div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-start mb-4">
            <div class="p-3 bg-orange-50 rounded-xl text-orange-600"><span class="material-symbols-outlined">receipt_long</span></div>
            <?php if($avgChange!==null): ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg <?= $avgChange>=0?'bg-green-50 text-green-700':'bg-red-50 text-red-700' ?>">
                <?= ($avgChange>=0?'+':'').number_format($avgChange,1) ?>%
              </span>
            <?php else: ?>
              <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">—</span>
            <?php endif; ?>
          </div>
          <div class="text-slate-500 text-sm font-medium">Giá trị TB / đơn</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= money($avgOrder) ?></div>
          <div class="text-xs text-slate-400 mt-2">Kỳ trước: <b class="text-slate-600"><?= money($prevAvgOrder) ?></b></div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-start mb-4">
            <div class="p-3 bg-cyan-50 rounded-xl text-cyan-600"><span class="material-symbols-outlined">group</span></div>
            <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">
              +<?= number_format($newCustomer) ?>
            </span>
          </div>
          <div class="text-slate-500 text-sm font-medium">Khách hàng</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($customerCount) ?></div>
          <div class="text-xs text-slate-400 mt-2">Voucher: <b class="text-slate-600"><?= number_format($voucherCount) ?></b></div>
        </div>
      </div>

      <!-- CHART + STATUS -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-center mb-4">
            <div>
              <div class="text-lg font-extrabold text-slate-900">Biểu đồ doanh thu</div>
              <div class="text-sm text-slate-500"><?= h(dateRangeLabel($range)) ?> (theo ngày)</div>
            </div>
            <div class="text-xs text-slate-500">
              Nguồn: <b>donhang</b>
            </div>
          </div>

          <div class="w-full aspect-[2.5/1]">
            <svg class="w-full h-full overflow-visible" viewBox="0 0 500 200" preserveAspectRatio="none">
              <line x1="0" y1="0" x2="500" y2="0" stroke="#f1f5f9" stroke-width="1"></line>
              <line x1="0" y1="50" x2="500" y2="50" stroke="#f1f5f9" stroke-width="1"></line>
              <line x1="0" y1="100" x2="500" y2="100" stroke="#f1f5f9" stroke-width="1"></line>
              <line x1="0" y1="150" x2="500" y2="150" stroke="#f1f5f9" stroke-width="1"></line>
              <line x1="0" y1="200" x2="500" y2="200" stroke="#f1f5f9" stroke-width="1"></line>

              <defs>
                <linearGradient id="chartGradientNew" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#137fec" stop-opacity="0.20"></stop>
                  <stop offset="100%" stop-color="#137fec" stop-opacity="0"></stop>
                </linearGradient>
              </defs>

              <!-- area -->
              <?php
                // area path: line path + đóng đáy
                $area = $path;
                $area .= " L480,180 L20,180 Z"; // đáy tương đối, ok cho demo
              ?>
              <path d="<?= h($area) ?>" fill="url(#chartGradientNew)"></path>
              <path d="<?= h($path) ?>" fill="none" stroke="#137fec" stroke-width="3" stroke-linecap="round"></path>
            </svg>

            <div class="flex justify-between mt-3 text-xs text-slate-400 font-medium">
              <?php
                $show = min(7, count($labels));
                if ($show<=1) { echo "<span>{$labels[0]}</span>"; }
                else{
                  // hiển thị tối đa 7 mốc đều nhau
                  $n=count($labels);
                  for($i=0;$i<$show;$i++){
                    $idx = (int)round($i*($n-1)/($show-1));
                    echo "<span>".h($labels[$idx])."</span>";
                  }
                }
              ?>
            </div>
          </div>

          <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-xl bg-gray-50 border border-gray-200">
              <div class="text-xs text-slate-500">Tổng</div>
              <div class="font-extrabold"><?= money(array_sum($vals)) ?></div>
            </div>
            <div class="p-3 rounded-xl bg-gray-50 border border-gray-200">
              <div class="text-xs text-slate-500">Ngày cao nhất</div>
              <div class="font-extrabold"><?= money(max($vals)) ?></div>
            </div>
            <div class="p-3 rounded-xl bg-gray-50 border border-gray-200">
              <div class="text-xs text-slate-500">Ngày thấp nhất</div>
              <div class="font-extrabold"><?= money(min($vals)) ?></div>
            </div>
            <div class="p-3 rounded-xl bg-gray-50 border border-gray-200">
              <div class="text-xs text-slate-500">TB / ngày</div>
              <div class="font-extrabold"><?= money(count($vals)? (int)round(array_sum($vals)/count($vals)) : 0) ?></div>
            </div>
          </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-warning">donut_small</span>
            <div class="text-lg font-extrabold">Trạng thái đơn</div>
          </div>

          <?php if(!$DH_STATUS): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Bảng donhang không có cột trạng_thái nên chưa thống kê được.
            </div>
          <?php elseif(!$statusRows): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Không có dữ liệu trong khoảng.
            </div>
          <?php else: ?>
            <div class="space-y-2">
              <?php
                $sumStatus = 0;
                foreach($statusRows as $sr) $sumStatus += (int)$sr['c'];
                foreach($statusRows as $sr):
                  $c = (int)$sr['c'];
                  $pct = $sumStatus>0 ? (int)round($c*100/$sumStatus) : 0;
              ?>
                <div class="flex items-center justify-between text-sm">
                  <div class="font-bold text-slate-700 truncate max-w-[160px]"><?= h($sr['st'] ?? 'N/A') ?></div>
                  <div class="text-slate-500 font-extrabold"><?= number_format($c) ?> (<?= $pct ?>%)</div>
                </div>
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                  <div class="h-full bg-primary" style="width:<?= $pct ?>%"></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- TOP + STOCK -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-lg font-extrabold">Bán chạy nhất</div>
            <div class="text-xs text-slate-500">
              <?php if(!$ctTable): ?>Thiếu bảng chi tiết đơn<?php else: ?>Nguồn: <?= h($ctTable) ?><?php endif; ?>
            </div>
          </div>

          <?php if(!$topProducts): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Chưa đủ bảng/cột để thống kê (cần ct_donhang + sanpham).
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach($topProducts as $p): ?>
                <div class="flex items-center gap-3 p-3 rounded-2xl border border-gray-200 hover:bg-gray-50">
                  <div class="size-12 rounded-xl bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center">
                    <?php if(!empty($p['hinh'])): ?>
                      <img class="w-full h-full object-cover" src="../assets/img/<?= h($p['hinh']) ?>" alt="">
                    <?php else: ?>
                      <span class="material-symbols-outlined text-slate-400">photo</span>
                    <?php endif; ?>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="font-extrabold truncate"><?= h($p['ten']) ?></div>
                    <div class="text-xs text-slate-500">ID: <?= (int)$p['id'] ?></div>
                  </div>
                  <div class="text-right">
                    <div class="font-extrabold text-slate-900"><?= number_format((int)$p['qty']) ?></div>
                    <div class="text-xs text-slate-500">đã bán</div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-lg font-extrabold">Tồn kho thấp</div>
            <div class="text-xs text-slate-500">
              <?php if(!$tonTable): ?>Chưa có bảng tonkho<?php else: ?>Nguồn: tonkho<?php endif; ?>
            </div>
          </div>

          <?php if(!$lowStock): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Nếu bạn muốn báo cáo tồn kho: cần bảng <b>tonkho</b> có cột <b>id_san_pham</b> và <b>so_luong</b>.
            </div>
          <?php else: ?>
            <div class="space-y-3">
              <?php foreach($lowStock as $p): ?>
                <div class="flex items-center gap-3 p-3 rounded-2xl border border-gray-200 hover:bg-gray-50">
                  <div class="size-12 rounded-xl bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center">
                    <?php if(!empty($p['hinh'])): ?>
                      <img class="w-full h-full object-cover" src="../assets/img/<?= h($p['hinh']) ?>" alt="">
                    <?php else: ?>
                      <span class="material-symbols-outlined text-slate-400">inventory_2</span>
                    <?php endif; ?>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="font-extrabold truncate"><?= h($p['ten']) ?></div>
                    <div class="text-xs text-slate-500">ID: <?= (int)$p['id'] ?></div>
                  </div>
                  <div class="text-right">
                    <div class="font-extrabold text-danger"><?= number_format((int)$p['so_luong']) ?></div>
                    <div class="text-xs text-slate-500">còn lại</div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- TECH NOTE -->
      <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 text-xs text-slate-500">
        <b>Nguồn dữ liệu</b>:
        donhang(<?= h($DH_DATE) ?>, <?= h($DH_TOTAL) ?><?= $DH_STATUS? ", $DH_STATUS":"" ?>)
        <?= $ctTable ? " • $ctTable" : "" ?>
        <?= $hasSP ? " • sanpham" : "" ?>
        <?= $tonTable ? " • tonkho" : "" ?>
        <?= $userTable ? " • $userTable" : "" ?>
        <?= $voucherTable ? " • $voucherTable" : "" ?>
      </div>

    </div>
  </div>
</main>
</body>
</html>
