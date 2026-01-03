<?php
// admin/tong_quan.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

requirePermission('tong_quan');

$ACTIVE = 'tong_quan';
$PAGE_TITLE = 'Bảng điều khiển';

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

/* ================= Fallback get_setting ================= */
if (!function_exists('get_setting')) {
  function get_setting(PDO $pdo, string $key, $default=null) {
    if (!tableExists($pdo, 'cai_dat')) return $default;
    $cols = getCols($pdo,'cai_dat');
    $K = pickCol($cols, ['khoa','key','ten']);
    $V = pickCol($cols, ['gia_tri','value','noi_dung']);
    if(!$K || !$V) return $default;
    $st = $pdo->prepare("SELECT {$V} FROM cai_dat WHERE {$K}=? LIMIT 1");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return ($v===false || $v===null || $v==='') ? $default : $v;
  }
}

/* ================= Detect schema ================= */
if (!tableExists($pdo,'donhang')) {
  echo "<div class='p-6 bg-white rounded-2xl border border-line'>Thiếu bảng <b>donhang</b>.</div>";
  require_once __DIR__ . '/includes/giaoDienCuoi.php';
  exit;
}

$dhCols = getCols($pdo,'donhang');
$DH_ID     = pickCol($dhCols, ['id_don_hang','id']);
$DH_CODE   = pickCol($dhCols, ['ma_don_hang']);
$DH_TOTAL  = pickCol($dhCols, ['tong_thanh_toan','tong_tien']);
$DH_STATUS = pickCol($dhCols, ['trang_thai','status']);
$DH_DATE   = pickCol($dhCols, ['ngay_dat','ngay_tao','created_at']);
$DH_UPD    = pickCol($dhCols, ['ngay_cap_nhat','updated_at']);
if(!$DH_ID) die("Bảng donhang thiếu cột id.");

/* chi tiết đơn */
$ctOk = tableExists($pdo,'chitiet_donhang');
$ctCols = $ctOk ? getCols($pdo,'chitiet_donhang') : [];
$CT_IDDH  = $ctOk ? pickCol($ctCols, ['id_don_hang']) : null;
$CT_IDSP  = $ctOk ? pickCol($ctCols, ['id_san_pham']) : null;
$CT_NAME  = $ctOk ? pickCol($ctCols, ['ten_san_pham']) : null;
$CT_QTY   = $ctOk ? pickCol($ctCols, ['so_luong']) : null;
$CT_PRICE = $ctOk ? pickCol($ctCols, ['don_gia']) : null;
$CT_TOTAL = $ctOk ? pickCol($ctCols, ['thanh_tien']) : null;

/* sản phẩm */
$spOk = tableExists($pdo,'sanpham');
$spCols = $spOk ? getCols($pdo,'sanpham') : [];
$SP_ID    = $spOk ? pickCol($spCols, ['id_san_pham','id']) : null;
$SP_NAME  = $spOk ? pickCol($spCols, ['ten_san_pham','ten']) : null;
$SP_IMG   = $spOk ? pickCol($spCols, ['hinh_anh','anh','image']) : null;
$SP_QTY   = $spOk ? pickCol($spCols, ['so_luong','ton_kho','qty']) : null;
$SP_COST  = $spOk ? pickCol($spCols, ['gia_nhap','gia_von','cost']) : null;

$dateCol   = $DH_DATE ?: ($DH_UPD ?: null);
$totalCol  = $DH_TOTAL ?: null;
$statusCol = $DH_STATUS ?: null;

/* ================= Conditions ================= */
function sql_is_cancelled(string $col): string {
  return "(LOWER($col) LIKE '%huy%' OR LOWER($col) LIKE '%cancel%')";
}
function sql_is_completed(string $col): string {
  return "(LOWER($col) LIKE '%hoan%' OR LOWER($col) LIKE '%complete%')";
}
function sql_is_processing(string $col): string {
  return "NOT ".sql_is_cancelled($col)." AND NOT ".sql_is_completed($col);
}

/* ================= KPI calculations ================= */
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$revenueToday = 0;
$revenueYesterday = 0;
$orderToday = 0;
$processingCount = 0;
$profitToday = 0;
$profitMargin = null;

if ($dateCol && $totalCol) {
  // revenue today (exclude cancelled)
  $sql = "SELECT IFNULL(SUM($totalCol),0) FROM donhang WHERE DATE($dateCol)=? ";
  $params = [$today];
  if ($statusCol) $sql .= " AND NOT ".sql_is_cancelled($statusCol);
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $revenueToday = (int)$st->fetchColumn();

  // revenue yesterday
  $sql = "SELECT IFNULL(SUM($totalCol),0) FROM donhang WHERE DATE($dateCol)=? ";
  $params = [$yesterday];
  if ($statusCol) $sql .= " AND NOT ".sql_is_cancelled($statusCol);
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $revenueYesterday = (int)$st->fetchColumn();
}

if ($dateCol) {
  // orders today (exclude cancelled)
  $sql = "SELECT COUNT(*) FROM donhang WHERE DATE($dateCol)=? ";
  $params = [$today];
  if ($statusCol) $sql .= " AND NOT ".sql_is_cancelled($statusCol);
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $orderToday = (int)$st->fetchColumn();
}

if ($statusCol) {
  $sql = "SELECT COUNT(*) FROM donhang WHERE ".sql_is_processing($statusCol);
  $st = $pdo->prepare($sql);
  $st->execute();
  $processingCount = (int)$st->fetchColumn();
}

/* ===== Gross profit (today, completed only, requires chi_tiet + gia_nhap) ===== */
$profitOk = $ctOk && $CT_IDDH && $CT_IDSP && $CT_QTY && $CT_PRICE && $spOk && $SP_ID && $SP_COST;
if ($profitOk && $dateCol && $statusCol) {
  $sql = "
    SELECT IFNULL(SUM( (ct.$CT_PRICE - sp.$SP_COST) * ct.$CT_QTY ),0) AS gp
    FROM chitiet_donhang ct
    JOIN donhang d ON d.$DH_ID = ct.$CT_IDDH
    JOIN sanpham sp ON sp.$SP_ID = ct.$CT_IDSP
    WHERE DATE(d.$dateCol)=?
      AND ".sql_is_completed("d.$statusCol")."
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$today]);
  $profitToday = (int)$st->fetchColumn();

  if ($revenueToday > 0) {
    $profitMargin = round(($profitToday / $revenueToday) * 100, 1);
  }
}

/* ===== Stock total + low stock (FIX CHUẨN) ===== */
$lowThreshold = (int)get_setting($pdo, 'low_stock_threshold', 5);

$totalStock = 0;
$lowStockCount = 0;
$lowStockList = [];

$tkOk = tableExists($pdo,'tonkho');
$tkHasData = false;

$TK_IDSP = $TK_QTY = $TK_MIN = null;

if ($tkOk) {
  try {
    $tkCols = getCols($pdo,'tonkho');
    $TK_IDSP = pickCol($tkCols, ['id_san_pham','sanpham_id','id_sp']);
    $TK_QTY  = pickCol($tkCols, ['so_luong','ton','qty','ton_kho']);
    $TK_MIN  = pickCol($tkCols, ['min','ton_toi_thieu','muc_toi_thieu','low_stock']);

    $st = $pdo->query("SELECT COUNT(*) FROM tonkho");
    $tkHasData = ((int)$st->fetchColumn() > 0);
  } catch(Throwable $e) {
    $tkHasData = false;
  }
}

try {
  // 1) TOTAL STOCK
  if ($tkOk && $tkHasData && $TK_QTY) {
    $st = $pdo->prepare("SELECT IFNULL(SUM($TK_QTY),0) FROM tonkho");
    $st->execute();
    $totalStock = (int)$st->fetchColumn();
  } elseif ($spOk && $SP_QTY) {
    $st = $pdo->prepare("SELECT IFNULL(SUM($SP_QTY),0) FROM sanpham");
    $st->execute();
    $totalStock = (int)$st->fetchColumn();
  }

  // 2) LOW STOCK COUNT + LIST
  // ƯU TIÊN tonkho nếu có dữ liệu, nếu không thì fallback sang sanpham
  if ($tkOk && $tkHasData && $TK_IDSP && $TK_QTY) {
    // low stock theo tổng tồn của sản phẩm (gộp các dòng tonkho nếu có nhiều kho)
    $minExpr = $TK_MIN ? "MAX(COALESCE(tk.$TK_MIN, :gmin))" : ":gmin";
    $sub = "
      SELECT tk.$TK_IDSP AS id,
             SUM(tk.$TK_QTY) AS qty,
             $minExpr AS min_qty
      FROM tonkho tk
      GROUP BY tk.$TK_IDSP
      HAVING qty <= min_qty
    ";

    // count
    $sql = "SELECT COUNT(*) FROM ($sub) x";
    $st = $pdo->prepare($sql);
    $st->execute([':gmin'=>$lowThreshold]);
    $lowStockCount = (int)$st->fetchColumn();

    // list
    $imgSel = ($spOk && $SP_IMG) ? ", sp.$SP_IMG AS img" : ", NULL AS img";
    $nameSel = ($spOk && $SP_NAME) ? "COALESCE(sp.$SP_NAME, CONCAT('#',x.id))" : "CONCAT('#',x.id)";
    $join = ($spOk && $SP_ID) ? "LEFT JOIN sanpham sp ON sp.$SP_ID = x.id" : "";

    $sql = "
      SELECT x.id,
             $nameSel AS ten,
             x.qty
             $imgSel
      FROM ($sub) x
      $join
      ORDER BY x.qty ASC
      LIMIT 5
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':gmin'=>$lowThreshold]);
    $lowStockList = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
  elseif ($spOk && $SP_QTY && $SP_NAME && $SP_ID) {
    // fallback theo sanpham.so_luong
    $st = $pdo->prepare("SELECT COUNT(*) FROM sanpham WHERE $SP_QTY <= ?");
    $st->execute([$lowThreshold]);
    $lowStockCount = (int)$st->fetchColumn();

    $selImg = ($SP_IMG ? ", $SP_IMG AS img" : ", NULL AS img");
    $st = $pdo->prepare("
      SELECT $SP_ID AS id, $SP_NAME AS ten, $SP_QTY AS qty $selImg
      FROM sanpham
      WHERE $SP_QTY <= ?
      ORDER BY $SP_QTY ASC
      LIMIT 5
    ");
    $st->execute([$lowThreshold]);
    $lowStockList = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch(Throwable $e) {
  // giữ mặc định 0 nếu lỗi query
}

/* ===== Trends ===== */
function pct_change(int $now, int $prev): float {
  if ($prev <= 0) return ($now>0 ? 100.0 : 0.0);
  return round((($now - $prev) / $prev) * 100, 1);
}
$revPct = pct_change($revenueToday, $revenueYesterday);

/* ================= Chart revenue: this month vs last month ================= */
$chartOk = ($dateCol && $totalCol);
$labels = [];
$curData = [];
$prevData = [];

if ($chartOk) {
  $year = (int)date('Y');
  $month = (int)date('m');
  $startCur = sprintf('%04d-%02d-01', $year, $month);
  $startPrev = date('Y-m-01', strtotime('-1 month', strtotime($startCur)));
  $endCur = date('Y-m-t', strtotime($startCur));
  $endPrev = date('Y-m-t', strtotime($startPrev));

  $daysInCur = (int)date('t', strtotime($startCur));
  for ($d=1;$d<=$daysInCur;$d++){
    $labels[] = str_pad((string)$d,2,'0',STR_PAD_LEFT);
    $curData[] = 0;
    $prevData[] = 0;
  }

  $condCancel = ($statusCol ? " AND NOT ".sql_is_cancelled($statusCol) : "");

  // current month
  $st = $pdo->prepare("
    SELECT DAY($dateCol) AS d, IFNULL(SUM($totalCol),0) AS v
    FROM donhang
    WHERE DATE($dateCol) BETWEEN ? AND ?
    $condCancel
    GROUP BY DAY($dateCol)
    ORDER BY DAY($dateCol)
  ");
  $st->execute([$startCur, $endCur]);
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $idx = (int)$r['d'] - 1;
    if ($idx>=0 && $idx<count($curData)) $curData[$idx] = (int)$r['v'];
  }

  // previous month
  $st = $pdo->prepare("
    SELECT DAY($dateCol) AS d, IFNULL(SUM($totalCol),0) AS v
    FROM donhang
    WHERE DATE($dateCol) BETWEEN ? AND ?
    $condCancel
    GROUP BY DAY($dateCol)
    ORDER BY DAY($dateCol)
  ");
  $st->execute([$startPrev, $endPrev]);
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $idx = (int)$r['d'] - 1;
    if ($idx>=0 && $idx<count($prevData)) $prevData[$idx] = (int)$r['v'];
  }
}

/* ================= Top selling ================= */
$topSelling = [];
if ($ctOk && $CT_IDSP && $CT_QTY && $CT_IDDH && $dateCol && $spOk && $SP_ID && $SP_NAME) {
  $startCur = date('Y-m-01');
  $endCur = date('Y-m-t');
  $condCancel = ($statusCol ? " AND NOT ".sql_is_cancelled("d.$statusCol") : "");

  $imgSel = ($SP_IMG ? ", sp.$SP_IMG AS img" : ", NULL AS img");

  $sql = "
    SELECT ct.$CT_IDSP AS id_sanpham,
           COALESCE(sp.$SP_NAME, MAX(ct.$CT_NAME)) AS ten,
           SUM(ct.$CT_QTY) AS sl
           $imgSel
    FROM chitiet_donhang ct
    JOIN donhang d ON d.$DH_ID = ct.$CT_IDDH
    LEFT JOIN sanpham sp ON sp.$SP_ID = ct.$CT_IDSP
    WHERE DATE(d.$dateCol) BETWEEN ? AND ?
    $condCancel
    GROUP BY ct.$CT_IDSP
    ORDER BY sl DESC
    LIMIT 3
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$startCur, $endCur]);
  $topSelling = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // add delta vs previous month
  $startPrev = date('Y-m-01', strtotime('-1 month'));
  $endPrev = date('Y-m-t', strtotime('-1 month'));
  $prevMap = [];

  $sqlPrev = "
    SELECT ct.$CT_IDSP AS id_sanpham, SUM(ct.$CT_QTY) AS sl
    FROM chitiet_donhang ct
    JOIN donhang d ON d.$DH_ID = ct.$CT_IDDH
    WHERE DATE(d.$dateCol) BETWEEN ? AND ?
    $condCancel
    GROUP BY ct.$CT_IDSP
  ";
  $st = $pdo->prepare($sqlPrev);
  $st->execute([$startPrev, $endPrev]);
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $prevMap[(int)$r['id_sanpham']] = (int)$r['sl'];
  }

  foreach($topSelling as &$t){
    $id = (int)$t['id_sanpham'];
    $prev = $prevMap[$id] ?? 0;
    $cur = (int)$t['sl'];
    $t['pct'] = ($prev>0) ? round((($cur-$prev)/$prev)*100,1) : ($cur>0 ? 100.0 : 0.0);
  }
  unset($t);
}

/* ================= Recent activity ================= */
$recentLogs = [];
if (tableExists($pdo,'nhatky_hoatdong')) {
  try {
    $st = $pdo->prepare("
      SELECT hanh_dong, mo_ta, ngay_tao
      FROM nhatky_hoatdong
      ORDER BY ngay_tao DESC
      LIMIT 6
    ");
    $st->execute();
    $recentLogs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch(Throwable $e) {}
}

/* ================= UI Helpers ================= */
function trend_badge(float $pct): array {
  if ($pct > 0) return ['bg'=>'bg-green-50','tx'=>'text-green-600','icon'=>'trending_up'];
  if ($pct < 0) return ['bg'=>'bg-red-50','tx'=>'text-red-600','icon'=>'trending_down'];
  return ['bg'=>'bg-slate-100','tx'=>'text-slate-600','icon'=>'trending_flat'];
}
$revTrend = trend_badge($revPct);

$stockLabel = ($lowStockCount > 0) ? 'Cảnh báo' : 'Ổn định';
$stockLabelCls = ($lowStockCount > 0) ? 'bg-danger/10 text-danger' : 'bg-slate-100 text-slate-700';

$me = $_SESSION['admin'] ?? [];
?>

<!-- ===== Hero row ===== -->
<div class="flex items-start justify-between gap-4 mb-6">
  <div>
    <div class="text-sm text-muted font-bold">Chào, <?= h($me['ho_ten'] ?? $me['username'] ?? 'Admin') ?></div>
    <div class="text-2xl md:text-3xl font-extrabold mt-1">Tổng quan hoạt động hôm nay</div>
    <div class="text-sm text-muted mt-2">Các chỉ số được lấy trực tiếp từ hệ thống đơn hàng và kho.</div>

    <?php if($tkOk && !$tkHasData): ?>
      <div class="mt-3 text-xs font-extrabold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 inline-block">
        Lưu ý: bảng <b>tonkho</b> đang trống, dashboard đang fallback theo <b>sanpham</b>.
      </div>
    <?php endif; ?>
  </div>

  <div class="hidden md:flex items-center gap-2">
    <div class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold text-slate-700">
      Hôm nay
      <span class="material-symbols-outlined align-middle text-[18px] ml-1 text-slate-500">expand_more</span>
    </div>
  </div>
</div>

<!-- ===== KPI Cards ===== -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6">
  <!-- Revenue -->
  <div class="bg-white rounded-2xl border border-line shadow-card p-5 transition hover:-translate-y-0.5 hover:shadow-soft">
    <div class="flex items-start justify-between">
      <div class="size-12 rounded-2xl bg-primary/10 grid place-items-center">
        <span class="material-symbols-outlined text-primary">paid</span>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $revTrend['bg'] ?> <?= $revTrend['tx'] ?>">
        <?= ($revPct>=0?'+':'') . $revPct ?>%
        <span class="material-symbols-outlined align-middle text-[16px] ml-1"><?= $revTrend['icon'] ?></span>
      </span>
    </div>
    <div class="mt-4 text-sm text-muted font-bold">Doanh thu hôm nay</div>
    <div class="mt-1 text-2xl font-extrabold"><?= money_vnd($revenueToday) ?></div>
    <div class="mt-2 text-xs text-muted font-bold">So với hôm qua <?= money_vnd($revenueYesterday) ?></div>
  </div>

  <!-- Orders -->
  <div class="bg-white rounded-2xl border border-line shadow-card p-5 transition hover:-translate-y-0.5 hover:shadow-soft">
    <div class="flex items-start justify-between">
      <div class="size-12 rounded-2xl bg-purple-50 grid place-items-center">
        <span class="material-symbols-outlined text-purple-600">shopping_cart</span>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-green-50 text-green-600">
        +<?= max(0, $orderToday) ?> <span class="material-symbols-outlined align-middle text-[16px] ml-1">add</span>
      </span>
    </div>
    <div class="mt-4 text-sm text-muted font-bold">Số đơn hàng mới</div>
    <div class="mt-1 text-2xl font-extrabold"><?= number_format($orderToday) ?></div>
    <div class="mt-2 text-xs text-muted font-bold">Đơn đang xử lý <?= number_format($processingCount) ?></div>
  </div>

  <!-- Profit -->
  <div class="bg-white rounded-2xl border border-line shadow-card p-5 transition hover:-translate-y-0.5 hover:shadow-soft">
    <div class="flex items-start justify-between">
      <div class="size-12 rounded-2xl bg-orange-50 grid place-items-center">
        <span class="material-symbols-outlined text-orange-600">donut_large</span>
      </div>
      <?php
        $pm = ($profitMargin===null ? null : (float)$profitMargin);
        $pmBadge = $pm===null ? ['bg'=>'bg-slate-100','tx'=>'text-slate-600','txt'=>'Chưa đủ dữ liệu']
                              : ['bg'=>'bg-slate-100','tx'=>'text-slate-700','txt'=>($pm.'%')];
      ?>
      <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $pmBadge['bg'] ?> <?= $pmBadge['tx'] ?>">
        <?= h($pmBadge['txt']) ?>
      </span>
    </div>
    <div class="mt-4 text-sm text-muted font-bold">Lợi nhuận gộp</div>
    <div class="mt-1 text-2xl font-extrabold"><?= money_vnd($profitToday) ?></div>
    <div class="mt-2 text-xs text-muted font-bold">
      <?= $profitOk ? ('Biên lợi nhuận '.($profitMargin??0).'%') : 'Thiếu cột gia_nhap/chi_tiet để tính' ?>
    </div>
  </div>

  <!-- Stock -->
  <div class="bg-white rounded-2xl border border-line shadow-card p-5 transition hover:-translate-y-0.5 hover:shadow-soft">
    <div class="flex items-start justify-between">
      <div class="size-12 rounded-2xl bg-sky-50 grid place-items-center">
        <span class="material-symbols-outlined text-sky-600">inventory_2</span>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $stockLabelCls ?>">
        <?= h($stockLabel) ?>
      </span>
    </div>
    <div class="mt-4 text-sm text-muted font-bold">Tổng tồn kho</div>
    <div class="mt-1 text-2xl font-extrabold"><?= number_format($totalStock) ?></div>
    <div class="mt-2 text-xs text-muted font-bold">
      Sản phẩm sắp hết <span class="text-danger font-extrabold"><?= number_format($lowStockCount) ?></span>
      <span class="ml-2 text-[11px] text-slate-400 font-extrabold">(ngưỡng: <?= (int)$lowThreshold ?>)</span>
    </div>
  </div>
</div>

<!-- ===== Middle: chart + alerts + top selling ===== -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6 mt-6">
  <!-- Chart -->
  <div class="xl:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5">
    <div class="flex items-start justify-between">
      <div>
        <div class="text-lg font-extrabold">Biểu đồ doanh thu</div>
        <div class="text-sm text-muted font-bold mt-1">Tháng này so với tháng trước</div>
      </div>
      <div class="flex items-center gap-3 text-xs font-extrabold text-muted">
        <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-primary inline-block"></span> Tháng này</span>
        <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-slate-300 inline-block"></span> Tháng trước</span>
      </div>
    </div>

    <div class="mt-4">
      <?php if(!$chartOk): ?>
        <div class="p-6 rounded-2xl border border-line bg-[#fbfdff] text-muted font-bold">
          Không đủ cột (ngày đặt / tổng tiền) để vẽ biểu đồ.
        </div>
      <?php else: ?>
        <canvas id="revChart" height="120"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column -->
  <div class="flex flex-col gap-4 md:gap-6">
    <!-- Alerts -->
    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-warning">warning</span>
        <div class="text-lg font-extrabold">Cảnh báo & Chú ý</div>
      </div>

      <div class="mt-4 space-y-3">
        <div class="p-4 rounded-2xl border border-red-200 bg-red-50">
          <div class="flex items-center justify-between">
            <div class="font-extrabold text-slate-900">Tồn kho thấp (<?= (int)$lowStockCount ?>)</div>
            <a href="tonkho.php" class="text-sm font-extrabold text-primary hover:underline">Xem</a>
          </div>
          <div class="text-sm text-muted font-bold mt-1">
            Ngưỡng cảnh báo: <?= (int)$lowThreshold ?>.
          </div>

          <?php if(!empty($lowStockList)): ?>
            <div class="mt-3 space-y-2">
              <?php foreach($lowStockList as $p): ?>
                <div class="flex items-center justify-between text-sm">
                  <div class="font-bold truncate pr-2"><?= h($p['ten'] ?? '') ?></div>
                  <div class="font-extrabold text-danger"><?= (int)($p['qty'] ?? 0) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="p-4 rounded-2xl border border-yellow-200 bg-yellow-50">
          <div class="flex items-center justify-between">
            <div class="font-extrabold text-slate-900">Đơn hàng đang xử lý (<?= (int)$processingCount ?>)</div>
            <a href="donhang.php" class="text-sm font-extrabold text-primary hover:underline">Xem</a>
          </div>
          <div class="text-sm text-muted font-bold mt-1">
            Các đơn chưa hoàn tất/huỷ cần theo dõi tiến độ.
          </div>
        </div>
      </div>
    </div>

    <!-- Top selling -->
    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between">
        <div class="text-lg font-extrabold">Bán chạy nhất</div>
        <a href="baocao.php" class="text-sm font-extrabold text-primary hover:underline">Xem thêm</a>
      </div>

      <div class="mt-4 space-y-3">
        <?php if(empty($topSelling)): ?>
          <div class="p-4 rounded-2xl border border-line bg-[#fbfdff] text-muted font-bold">
            Chưa đủ dữ liệu chi tiết đơn hàng để thống kê.
          </div>
        <?php else: ?>
          <?php foreach($topSelling as $t):
            $img = $t['img'] ?? null;
            $imgSrc = $img ? ("../assets/img/".h((string)$img)) : null;
            $pct = (float)($t['pct'] ?? 0);
            $pctCls = $pct>=0 ? 'text-green-600' : 'text-red-600';
          ?>
            <div class="flex items-center gap-3">
              <?php if($imgSrc): ?>
                <img src="<?= $imgSrc ?>" class="size-11 rounded-xl object-cover border border-line bg-white" alt="">
              <?php else: ?>
                <div class="size-11 rounded-xl border border-line bg-[#f1f5f9] grid place-items-center text-slate-400">
                  <span class="material-symbols-outlined">photo</span>
                </div>
              <?php endif; ?>

              <div class="flex-1 min-w-0">
                <div class="font-extrabold truncate"><?= h($t['ten'] ?? '') ?></div>
                <div class="text-xs text-muted font-bold"><?= (int)($t['sl'] ?? 0) ?> sản phẩm</div>
              </div>

              <div class="text-sm font-extrabold <?= $pctCls ?>">
                <?= ($pct>=0?'+':'') . $pct ?>%
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ===== Recent activity ===== -->
<div class="bg-white rounded-2xl border border-line shadow-card p-5 mt-6">
  <div class="flex items-center justify-between">
    <div>
      <div class="text-lg font-extrabold">Hoạt động gần đây</div>
      <div class="text-sm text-muted font-bold mt-1">Lấy từ bảng nhật ký hoạt động</div>
    </div>
    <a href="nhatky.php" class="text-sm font-extrabold text-primary hover:underline">Xem tất cả</a>
  </div>

  <div class="mt-4">
    <?php if(empty($recentLogs)): ?>
      <div class="p-4 rounded-2xl border border-line bg-[#fbfdff] text-muted font-bold">
        Chưa có dữ liệu nhật ký hoặc chưa tạo bảng <b>nhatky_hoatdong</b>.
      </div>
    <?php else: ?>
      <div class="divide-y divide-line rounded-2xl border border-line overflow-hidden">
        <?php foreach($recentLogs as $lg): ?>
          <div class="p-4 flex items-start justify-between gap-4 bg-white">
            <div class="min-w-0">
              <div class="font-extrabold"><?= h($lg['hanh_dong'] ?? '') ?></div>
              <div class="text-sm text-muted font-bold mt-1 truncate"><?= h($lg['mo_ta'] ?? '') ?></div>
            </div>
            <div class="text-xs text-muted font-extrabold whitespace-nowrap"><?= h($lg['ngay_tao'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if($chartOk): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const ctx = document.getElementById('revChart');
  if(!ctx) return;

  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  const cur = <?= json_encode($curData, JSON_UNESCAPED_UNICODE) ?>;
  const prev = <?= json_encode($prevData, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Tháng này', data: cur, tension: 0.35, borderWidth: 3, pointRadius: 0 },
        { label: 'Tháng trước', data: prev, tension: 0.35, borderWidth: 2, borderDash: [6,6], pointRadius: 0 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => {
              const v = ctx.raw || 0;
              return `${ctx.dataset.label}: ` + new Intl.NumberFormat('vi-VN').format(v) + ' ₫';
            }
          }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
        y: { grid: { color: '#e7edf5' }, ticks: {
          callback: (v) => new Intl.NumberFormat('vi-VN').format(v)
        }}
      }
    }
  });
})();
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/giaoDienCuoi.php';
