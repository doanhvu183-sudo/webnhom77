<?php
// admin/baocao.php

/* ================= BOOT ================= */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// nếu dự án bạn có helpers/hamChung thì ưu tiên dùng (tránh thiếu hàm)
if (file_exists(__DIR__ . '/includes/helpers.php')) {
  require_once __DIR__ . '/includes/helpers.php';
} elseif (file_exists(__DIR__ . '/includes/hamChung.php')) {
  require_once __DIR__ . '/includes/hamChung.php';
}

/* ================= SAFE HELPERS (không redeclare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
if (!function_exists('money_vnd')) {
  function money_vnd($n){
    $n = (float)($n ?? 0);
    return number_format($n, 0, ',', '.') . ' ₫';
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
 * Log vào nhatky_hoatdong (theo đúng bảng bạn đang có)
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

$ACTIVE = 'baocao';
$PAGE_TITLE = 'Báo cáo';

// nếu hệ thống có requirePermission thì gọi, không có thì bỏ qua
if (function_exists('requirePermission')) {
  requirePermission('baocao');
}

/* ================= VALIDATE CORE TABLE ================= */
if (!tableExists($pdo,'donhang')) {
  die("Thiếu bảng <b>donhang</b> nên không thể xem báo cáo.");
}

/* ================= MAP COLUMNS ================= */
$dhCols = getCols($pdo,'donhang');
$DH_ID     = pickCol($dhCols, ['id_don_hang','id']);
$DH_USER   = pickCol($dhCols, ['id_nguoi_dung','id_khach_hang','id_user']);
$DH_TOTAL  = pickCol($dhCols, ['tong_thanh_toan','tong_tien','tong_cong','tong']);
$DH_STATUS = pickCol($dhCols, ['trang_thai','status']);
$DH_DATE   = pickCol($dhCols, ['ngay_dat','created_at','ngay_tao','date_created']);
$DH_VOUCH  = pickCol($dhCols, ['ma_voucher','voucher_code','code_voucher']);
if(!$DH_ID || !$DH_TOTAL || !$DH_DATE){
  die("Bảng donhang thiếu cột cần thiết (id / tổng tiền / ngày).");
}

$ctOk = tableExists($pdo,'chitiet_donhang');
$ctCols = $ctOk ? getCols($pdo,'chitiet_donhang') : [];
$CT_IDDH  = $ctOk ? pickCol($ctCols, ['id_don_hang']) : null;
$CT_IDSP  = $ctOk ? pickCol($ctCols, ['id_san_pham']) : null;
$CT_NAME  = $ctOk ? pickCol($ctCols, ['ten_san_pham','ten']) : null;
$CT_QTY   = $ctOk ? pickCol($ctCols, ['so_luong','qty','quantity']) : null;
$CT_TOTAL = $ctOk ? pickCol($ctCols, ['thanh_tien','line_total','tong']) : null;

$spOk = tableExists($pdo,'sanpham');
$spCols = $spOk ? getCols($pdo,'sanpham') : [];
$SP_ID    = $spOk ? pickCol($spCols, ['id_san_pham','id']) : null;
$SP_NAME  = $spOk ? pickCol($spCols, ['ten_san_pham','ten']) : null;
$SP_IMG   = $spOk ? pickCol($spCols, ['hinh_anh','anh','image']) : null;
$SP_COST  = $spOk ? pickCol($spCols, ['gia_nhap','gia_von','cost']) : null;
$SP_PRICE = $spOk ? pickCol($spCols, ['gia','gia_ban','price']) : null;

$tkOk = tableExists($pdo,'tonkho');
$tkCols = $tkOk ? getCols($pdo,'tonkho') : [];
$TK_IDSP = $tkOk ? pickCol($tkCols, ['id_san_pham']) : null;
$TK_QTY  = $tkOk ? pickCol($tkCols, ['so_luong','ton','qty']) : null;

$ndOk = tableExists($pdo,'nguoidung');
$vcOk = tableExists($pdo,'voucher');
$cdOk = tableExists($pdo,'cai_dat');

/* ================= SETTINGS ================= */
$lowStock = 5;
if ($cdOk) {
  $cdCols = getCols($pdo,'cai_dat');
  $CD_KEY = pickCol($cdCols, ['khoa','key','ten']);
  $CD_VAL = pickCol($cdCols, ['gia_tri','value','noi_dung']);
  if ($CD_KEY && $CD_VAL) {
    $st=$pdo->prepare("SELECT $CD_VAL FROM cai_dat WHERE $CD_KEY=? LIMIT 1");
    $st->execute(['low_stock_threshold']);
    $v=$st->fetchColumn();
    if ($v!==false && $v!==null && $v!=='') $lowStock = max(1,(int)$v);
  }
}

/* ================= RANGE ================= */
$range = $_GET['range'] ?? 'today';
if (!in_array($range, ['today','7days','month'], true)) $range='today';

$tz = new DateTimeZone('Asia/Ho_Chi_Minh');
$now = new DateTime('now', $tz);
$today = (new DateTime('today', $tz));

if ($range==='today') {
  $start = clone $today;
  $end = clone $now;
  $labelRange = 'Hôm nay';
  $prevStart = (clone $start)->modify('-1 day');
  $prevEnd   = (clone $end)->modify('-1 day');
}
elseif ($range==='7days') {
  $start = (clone $today)->modify('-6 day'); // 7 ngày gồm hôm nay
  $end = clone $now;
  $labelRange = '7 ngày';
  $prevStart = (clone $start)->modify('-7 day');
  $prevEnd   = (clone $end)->modify('-7 day');
}
else { // month
  $start = (clone $today)->modify('first day of this month');
  $end = clone $now;
  $labelRange = 'Tháng';
  $prevStart = (clone $start)->modify('first day of last month');
  $prevEnd   = (clone $end)->modify('last day of last month')->setTime(23,59,59);
}

// SQL datetime strings
$startStr = $start->format('Y-m-d 00:00:00');
$endStr   = $end->format('Y-m-d 23:59:59');
$prevStartStr = $prevStart->format('Y-m-d 00:00:00');
$prevEndStr   = $prevEnd->format('Y-m-d 23:59:59');

/* ================= KPI QUERIES ================= */
$sumSql = "SELECT IFNULL(SUM($DH_TOTAL),0) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?";
$cntSql = "SELECT COUNT(*) FROM donhang WHERE $DH_DATE BETWEEN ? AND ?";

$st=$pdo->prepare($sumSql); $st->execute([$startStr,$endStr]); $revenue = (float)$st->fetchColumn();
$st=$pdo->prepare($sumSql); $st->execute([$prevStartStr,$prevEndStr]); $revenuePrev = (float)$st->fetchColumn();

$st=$pdo->prepare($cntSql); $st->execute([$startStr,$endStr]); $orders = (int)$st->fetchColumn();
$st=$pdo->prepare($cntSql); $st->execute([$prevStartStr,$prevEndStr]); $ordersPrev = (int)$st->fetchColumn();

$avg = $orders>0 ? ($revenue/$orders) : 0;
$avgPrev = $ordersPrev>0 ? ($revenuePrev/$ordersPrev) : 0;

$customersTotal = 0;
if ($ndOk) {
  $st=$pdo->query("SELECT COUNT(*) FROM nguoidung");
  $customersTotal = (int)$st->fetchColumn();
} else {
  // fallback: distinct user in donhang (nếu có)
  if ($DH_USER) {
    $st=$pdo->prepare("SELECT COUNT(DISTINCT $DH_USER) FROM donhang");
    $st->execute();
    $customersTotal = (int)$st->fetchColumn();
  }
}
$vouchersTotal = 0;
if ($vcOk) {
  $st=$pdo->query("SELECT COUNT(*) FROM voucher");
  $vouchersTotal = (int)$st->fetchColumn();
}

/* ================= TREND % ================= */
$trendPct = function($cur,$prev){
  if ($prev<=0) return ($cur>0?100:0);
  return round((($cur-$prev)/$prev)*100, 1);
};
$revPct = $trendPct($revenue,$revenuePrev);
$ordPct = $trendPct($orders,$ordersPrev);
$avgPct = $trendPct($avg,$avgPrev);

/* ================= CHART DATA (daily) ================= */
$groupDaily = function($from,$to) use($pdo,$DH_DATE,$DH_TOTAL){
  $sql = "
    SELECT DATE($DH_DATE) AS d, IFNULL(SUM($DH_TOTAL),0) AS s
    FROM donhang
    WHERE $DH_DATE BETWEEN ? AND ?
    GROUP BY DATE($DH_DATE)
    ORDER BY d ASC
  ";
  $st=$pdo->prepare($sql);
  $st->execute([$from,$to]);
  $map=[];
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $map[$r['d']] = (float)$r['s'];
  }
  return $map;
};

$mapCur = $groupDaily($startStr,$endStr);
$mapPrev= $groupDaily($prevStartStr,$prevEndStr);

// build labels by day between start and end
$labels=[]; $dataCur=[]; $dataPrev=[];
$it = new DateTime($start->format('Y-m-d'), $tz);
$itEnd = new DateTime($end->format('Y-m-d'), $tz);
$days=0;
while($it <= $itEnd && $days<400){
  $d = $it->format('Y-m-d');
  $labels[] = $it->format('d/m');
  $dataCur[] = (float)($mapCur[$d] ?? 0);

  // map previous by index: same offset from prevStart
  $dPrev = (clone $prevStart)->modify("+$days day")->format('Y-m-d');
  $dataPrev[] = (float)($mapPrev[$dPrev] ?? 0);

  $it->modify('+1 day'); $days++;
}

/* ================= STATUS BREAKDOWN ================= */
$statusRows = [];
if ($DH_STATUS){
  $st=$pdo->prepare("SELECT $DH_STATUS AS st, COUNT(*) AS c FROM donhang WHERE $DH_DATE BETWEEN ? AND ? GROUP BY $DH_STATUS ORDER BY c DESC");
  $st->execute([$startStr,$endStr]);
  $statusRows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= BEST SELLERS ================= */
$best = [];
$bestNote = '';
if ($ctOk && $CT_IDDH && $CT_QTY){
  $nameExpr = $CT_NAME ? "MAX(ct.$CT_NAME)" : "''";
  $lineTotalExpr = $CT_TOTAL ? "IFNULL(SUM(ct.$CT_TOTAL),0)" : "IFNULL(SUM(ct.$CT_QTY * $DH_TOTAL / NULLIF(1,1)),0)"; // fallback (won't run)
  $sql = "
    SELECT
      ct.$CT_IDSP AS id_sp,
      $nameExpr AS ten,
      IFNULL(SUM(ct.$CT_QTY),0) AS so_luong,
      ".($CT_TOTAL ? "IFNULL(SUM(ct.$CT_TOTAL),0)" : "0")." AS doanh_thu
    FROM chitiet_donhang ct
    JOIN donhang dh ON dh.$DH_ID = ct.$CT_IDDH
    WHERE dh.$DH_DATE BETWEEN ? AND ?
    GROUP BY ct.$CT_IDSP
    ORDER BY so_luong DESC
    LIMIT 5
  ";
  $st=$pdo->prepare($sql);
  $st->execute([$startStr,$endStr]);
  $best = $st->fetchAll(PDO::FETCH_ASSOC);

  // attach image + price info
  if ($spOk && $SP_ID){
    $ids = array_values(array_filter(array_map(fn($r)=> (int)($r['id_sp'] ?? 0), $best)));
    if ($ids){
      $in = implode(',', array_fill(0,count($ids),'?'));
      $colsNeed = [$SP_ID];
      if ($SP_NAME) $colsNeed[]=$SP_NAME;
      if ($SP_IMG)  $colsNeed[]=$SP_IMG;
      if ($SP_PRICE)$colsNeed[]=$SP_PRICE;
      $q="SELECT ".implode(',',$colsNeed)." FROM sanpham WHERE $SP_ID IN ($in)";
      $st=$pdo->prepare($q); $st->execute($ids);
      $spMap=[];
      foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $spMap[(int)$r[$SP_ID]] = $r;
      }
      foreach($best as &$b){
        $idsp = (int)($b['id_sp'] ?? 0);
        $sp = $spMap[$idsp] ?? [];
        $b['_img'] = ($SP_IMG && isset($sp[$SP_IMG])) ? (string)$sp[$SP_IMG] : '';
        $b['_gia'] = ($SP_PRICE && isset($sp[$SP_PRICE])) ? (float)$sp[$SP_PRICE] : null;
        if (($b['ten'] ?? '')==='' && $SP_NAME && isset($sp[$SP_NAME])) $b['ten'] = $sp[$SP_NAME];
      }
      unset($b);
    }
  }
} else {
  $bestNote = 'Thiếu bảng/cột chi tiết đơn để thống kê bán chạy.';
}

/* ================= LOW STOCK ================= */
$low = [];
$lowNote = '';
if ($tkOk && $TK_IDSP && $TK_QTY){
  $sql="SELECT $TK_IDSP AS id_sp, $TK_QTY AS so_luong FROM tonkho WHERE $TK_QTY <= ? ORDER BY $TK_QTY ASC LIMIT 8";
  $st=$pdo->prepare($sql); $st->execute([$lowStock]);
  $low=$st->fetchAll(PDO::FETCH_ASSOC);

  // join sanpham for name/image
  if ($spOk && $SP_ID){
    $ids = array_values(array_filter(array_map(fn($r)=> (int)($r['id_sp'] ?? 0), $low)));
    if ($ids){
      $in = implode(',', array_fill(0,count($ids),'?'));
      $colsNeed=[$SP_ID];
      if ($SP_NAME) $colsNeed[]=$SP_NAME;
      if ($SP_IMG)  $colsNeed[]=$SP_IMG;
      $q="SELECT ".implode(',',$colsNeed)." FROM sanpham WHERE $SP_ID IN ($in)";
      $st=$pdo->prepare($q); $st->execute($ids);
      $spMap=[];
      foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
        $spMap[(int)$r[$SP_ID]]=$r;
      }
      foreach($low as &$r){
        $sp=$spMap[(int)$r['id_sp']] ?? [];
        $r['_ten'] = ($SP_NAME && isset($sp[$SP_NAME])) ? (string)$sp[$SP_NAME] : ('#'.(int)$r['id_sp']);
        $r['_img'] = ($SP_IMG && isset($sp[$SP_IMG])) ? (string)$sp[$SP_IMG] : '';
      }
      unset($r);
    }
  }
} else {
  $lowNote = 'Nếu bạn muốn báo cáo tồn kho: cần bảng tonkho có cột id_san_pham và so_luong.';
}

/* ================= LOG VIEW ================= */
nhatky_log($pdo,'XEM_BAO_CAO',"Xem báo cáo ($labelRange)",'baocao',null,['range'=>$range,'from'=>$startStr,'to'=>$endStr]);

/* ================= RENDER ================= */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

function pill($key,$label,$active){
  $is = ($key===$active);
  $cls = $is ? "bg-primary/10 text-primary border-primary/20" : "bg-white text-slate-700 border-line hover:bg-slate-50";
  $href = "baocao.php?range=".$key;
  echo '<a href="'.h($href).'" class="px-3 py-2 rounded-xl border text-sm font-extrabold '.$cls.'">'.$label.'</a>';
}
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="text-xl font-extrabold">Báo cáo</div>
        <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700"><?= h($labelRange) ?></span>
      </div>

      <div class="flex items-center gap-2">
        <?php pill('today','Hôm nay',$range); ?>
        <?php pill('7days','7 ngày',$range); ?>
        <?php pill('month','Tháng',$range); ?>
        <a href="lichsu_kho.php" class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">Lịch sử kho</a>

      </div>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-primary/10 grid place-items-center">
            <span class="material-symbols-outlined text-primary">paid</span>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $revPct>=0?'bg-green-50 text-green-600':'bg-red-50 text-red-600' ?>">
            <?= ($revPct>=0?'+':'').h($revPct) ?>%
          </span>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Doanh thu</div>
        <div class="mt-1 text-2xl font-extrabold"><?= money_vnd($revenue) ?></div>
        <div class="mt-2 text-xs text-muted font-bold">Kỳ trước: <?= money_vnd($revenuePrev) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-purple-50 grid place-items-center">
            <span class="material-symbols-outlined text-purple-600">shopping_cart</span>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $ordPct>=0?'bg-green-50 text-green-600':'bg-red-50 text-red-600' ?>">
            <?= ($ordPct>=0?'+':'').h($ordPct) ?>%
          </span>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Đơn hàng</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($orders) ?></div>
        <div class="mt-2 text-xs text-muted font-bold">Kỳ trước: <?= number_format($ordersPrev) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-amber-50 grid place-items-center">
            <span class="material-symbols-outlined text-amber-700">receipt_long</span>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $avgPct>=0?'bg-green-50 text-green-600':'bg-red-50 text-red-600' ?>">
            <?= ($avgPct>=0?'+':'').h($avgPct) ?>%
          </span>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Giá trị TB / đơn</div>
        <div class="mt-1 text-2xl font-extrabold"><?= money_vnd($avg) ?></div>
        <div class="mt-2 text-xs text-muted font-bold">Kỳ trước: <?= money_vnd($avgPrev) ?></div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-start justify-between">
          <div class="size-12 rounded-2xl bg-cyan-50 grid place-items-center">
            <span class="material-symbols-outlined text-cyan-700">group</span>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
            +0
          </span>
        </div>
        <div class="mt-4 text-sm text-muted font-bold">Khách hàng</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($customersTotal) ?></div>
        <div class="mt-2 text-xs text-muted font-bold">Voucher: <?= number_format($vouchersTotal) ?></div>
      </div>
    </div>

    <!-- Chart + Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div>
            <div class="text-base font-extrabold">Biểu đồ doanh thu</div>
            <div class="text-xs text-muted font-bold"><?= h($labelRange) ?> (theo ngày)</div>
          </div>
          <div class="text-xs text-muted font-bold">Nguồn: donhang</div>
        </div>

        <div class="mt-4">
          <canvas id="revChart" height="110"></canvas>
        </div>

        <?php
          $totalCur = array_sum($dataCur);
          $maxDay = max($dataCur ?: [0]);
          $minDay = min($dataCur ?: [0]);
          $avgDay = count($dataCur) ? ($totalCur / count($dataCur)) : 0;
        ?>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="rounded-2xl border border-line p-3">
            <div class="text-xs text-muted font-bold">Tổng</div>
            <div class="text-sm font-extrabold"><?= money_vnd($totalCur) ?></div>
          </div>
          <div class="rounded-2xl border border-line p-3">
            <div class="text-xs text-muted font-bold">Ngày cao nhất</div>
            <div class="text-sm font-extrabold"><?= money_vnd($maxDay) ?></div>
          </div>
          <div class="rounded-2xl border border-line p-3">
            <div class="text-xs text-muted font-bold">Ngày thấp nhất</div>
            <div class="text-sm font-extrabold"><?= money_vnd($minDay) ?></div>
          </div>
          <div class="rounded-2xl border border-line p-3">
            <div class="text-xs text-muted font-bold">TB / ngày</div>
            <div class="text-sm font-extrabold"><?= money_vnd($avgDay) ?></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Trạng thái đơn</div>
          <span class="material-symbols-outlined text-amber-600">info</span>
        </div>

        <div class="mt-4 space-y-4">
          <?php if(!$DH_STATUS): ?>
            <div class="text-sm text-muted font-bold">Bảng donhang không có cột trạng_thái.</div>
          <?php elseif(!$statusRows): ?>
            <div class="text-sm text-muted font-bold">Chưa có dữ liệu trong kỳ.</div>
          <?php else: ?>
            <?php
              $sumSt = array_sum(array_map(fn($r)=>(int)$r['c'], $statusRows));
              foreach($statusRows as $r):
                $stt = (string)$r['st'];
                $c = (int)$r['c'];
                $pct = $sumSt>0 ? round(($c/$sumSt)*100) : 0;
            ?>
              <div>
                <div class="flex items-center justify-between text-sm font-extrabold">
                  <div class="uppercase"><?= h($stt) ?></div>
                  <div class="text-muted"><?= number_format($c) ?> (<?= $pct ?>%)</div>
                </div>
                <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div class="h-2 bg-primary" style="width: <?= (int)$pct ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Best sellers + Low stock -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Bán chạy nhất</div>
          <div class="text-xs text-muted font-bold"><?= $bestNote ? h($bestNote) : '' ?></div>
        </div>

        <div class="mt-4 space-y-3">
          <?php if(!$best): ?>
            <div class="text-sm text-muted font-bold"><?= $bestNote ? h($bestNote) : 'Chưa có dữ liệu bán chạy.' ?></div>
          <?php else: ?>
            <?php foreach($best as $b):
              $img = (string)($b['_img'] ?? '');
              $ten = (string)($b['ten'] ?? ('SP #'.(int)$b['id_sp']));
              $qty = (int)($b['so_luong'] ?? 0);
              $gia = $b['_gia'] ?? null;
              $doanhthu = (float)($b['doanh_thu'] ?? 0);
            ?>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="size-12 rounded-2xl bg-slate-100 overflow-hidden grid place-items-center">
                    <?php if($img): ?>
                      <img src="<?= h($img) ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                      <span class="material-symbols-outlined text-slate-500">image</span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="text-sm font-extrabold"><?= h($ten) ?></div>
                    <div class="text-xs text-muted font-bold">
                      SL: <?= number_format($qty) ?>
                      <?php if($gia!==null): ?> · Giá: <?= money_vnd($gia) ?><?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="text-sm font-extrabold"><?= money_vnd($doanhthu) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Tồn kho thấp</div>
          <div class="text-xs text-muted font-bold">Ngưỡng: <?= (int)$lowStock ?></div>
        </div>

        <div class="mt-4 space-y-3">
          <?php if(!$low): ?>
            <div class="text-sm text-muted font-bold"><?= $lowNote ? h($lowNote) : 'Không có sản phẩm tồn thấp trong ngưỡng.' ?></div>
          <?php else: ?>
            <?php foreach($low as $r):
              $img = (string)($r['_img'] ?? '');
              $ten = (string)($r['_ten'] ?? ('SP #'.(int)$r['id_sp']));
              $qty = (int)($r['so_luong'] ?? 0);
            ?>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="size-12 rounded-2xl bg-slate-100 overflow-hidden grid place-items-center">
                    <?php if($img): ?>
                      <img src="<?= h($img) ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                      <span class="material-symbols-outlined text-slate-500">inventory_2</span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="text-sm font-extrabold"><?= h($ten) ?></div>
                    <div class="text-xs text-muted font-bold">ID: <?= (int)$r['id_sp'] ?></div>
                  </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $qty<=max(1,(int)($lowStock/2)) ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700' ?>">
                  <?= number_format($qty) ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Chart.js (CDN). Nếu máy bạn offline thì chart sẽ không hiện nhưng phần số liệu vẫn chạy -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const el = document.getElementById('revChart');
  if(!el || typeof Chart==='undefined') return;

  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const cur = <?= json_encode($dataCur, JSON_UNESCAPED_UNICODE) ?>;
  const prev = <?= json_encode($dataPrev, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(el, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Tháng này',
          data: cur,
          tension: 0.35,
          borderWidth: 3,
          pointRadius: 3
        },
        {
          label: 'Tháng trước',
          data: prev,
          tension: 0.35,
          borderWidth: 2,
          pointRadius: 0,
          borderDash: [6,6]
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', align: 'end' },
        tooltip: { callbacks: {
          label: (ctx)=> {
            const v = Number(ctx.raw||0);
            return ctx.dataset.label + ': ' + v.toLocaleString('vi-VN') + ' ₫';
          }
        }}
      },
      scales: {
        y: { ticks: {
          callback: (v)=> Number(v).toLocaleString('vi-VN')
        }},
        x: { grid: { display:false } }
      }
    }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
