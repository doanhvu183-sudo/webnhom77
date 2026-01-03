<?php
// admin/lichsu_kho.php (VIEW ONLY) - Lịch sử nhập / xuất / bán (trừ kho theo đơn)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) $pdo = $conn;

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }

/* ================= Include helpers if exists ================= */
if (file_exists(__DIR__ . '/includes/helpers.php')) {
  require_once __DIR__ . '/includes/helpers.php';
} elseif (file_exists(__DIR__ . '/includes/hamChung.php')) {
  require_once __DIR__ . '/includes/hamChung.php';
}

/* ================= SAFE HELPERS (no redeclare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n){
    $n = (float)($n ?? 0);
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
if (!function_exists('detectTable')) {
  function detectTable(PDO $pdo, array $cands): ?string {
    foreach($cands as $t){ if(tableExists($pdo,$t)) return $t; }
    return null;
  }
}
if (!function_exists('img_src')) {
  function img_src($img){
    $img = trim((string)$img);
    if ($img === '') return '';
    if (preg_match('~^https?://~i', $img)) return $img;
    if ($img[0] === '/') return $img;
    return "../assets/img/" . rawurlencode($img);
  }
}
if (!function_exists('requirePermission')) {
  function requirePermission(string $key): void { return; }
}

/* ================= Page meta ================= */
$ACTIVE = 'lichsu_kho';
$PAGE_TITLE = 'Lịch sử kho';
if (function_exists('requirePermission')) {
  // nếu bạn chưa khai báo permission key này thì có thể đổi thành 'baocao'
  requirePermission('lichsu_kho');
}

/* ================= Validate PDO ================= */
if (!($pdo instanceof PDO)) {
  die("Thiếu kết nối CSDL (\$pdo). Kiểm tra cau_hinh/ket_noi.php");
}

/* ================= Detect core tables ================= */
$spOk = tableExists($pdo,'sanpham');
$SP_ID=$SP_NAME=$SP_IMG=null;
if ($spOk){
  $spCols = getCols($pdo,'sanpham');
  $SP_ID   = pickCol($spCols, ['id_san_pham','id','sanpham_id']);
  $SP_NAME = pickCol($spCols, ['ten_san_pham','ten','name']);
  $SP_IMG  = pickCol($spCols, ['hinh_anh','anh','image']);
}

/* ====== NHẬP ====== */
$PN_T  = detectTable($pdo, ['phieunhap','phieu_nhap']);
$CTPN_T= detectTable($pdo, ['chitiet_phieunhap','chi_tiet_phieu_nhap','ct_phieunhap']);

$PN_ID=$PN_DATE=$PN_NOTE=null;
$CTPN_PN=$CTPN_SP=$CTPN_QTY=$CTPN_PRICE=$CTPN_TOTAL=null;

if ($PN_T && $CTPN_T){
  $pnCols = getCols($pdo,$PN_T);
  $PN_ID   = pickCol($pnCols, ['id_phieu_nhap','id','phieunhap_id']);
  $PN_DATE = pickCol($pnCols, ['ngay_nhap','ngay_tao','created_at','date_created']);
  $PN_NOTE = pickCol($pnCols, ['ghi_chu','mo_ta','note']);

  $ctCols = getCols($pdo,$CTPN_T);
  $CTPN_PN    = pickCol($ctCols, ['id_phieu_nhap','phieunhap_id','id_phieu']);
  $CTPN_SP    = pickCol($ctCols, ['id_san_pham','sanpham_id','id_sp']);
  $CTPN_QTY   = pickCol($ctCols, ['so_luong','qty','quantity']);
  $CTPN_PRICE = pickCol($ctCols, ['don_gia','gia_nhap','gia','price']);
  $CTPN_TOTAL = pickCol($ctCols, ['thanh_tien','line_total','tong']);
}

/* ====== XUẤT ====== */
$PX_T  = detectTable($pdo, ['phieuxuat','phieu_xuat']);
$CTPX_T= detectTable($pdo, ['chitiet_phieuxuat','chi_tiet_phieu_xuat','ct_phieuxuat']);

$PX_ID=$PX_DATE=$PX_NOTE=$PX_TYPE=null;
$CTPX_PX=$CTPX_SP=$CTPX_QTY=$CTPX_PRICE=$CTPX_TOTAL=null;

if ($PX_T && $CTPX_T){
  $pxCols = getCols($pdo,$PX_T);
  $PX_ID   = pickCol($pxCols, ['id_phieu_xuat','id','phieuxuat_id']);
  $PX_DATE = pickCol($pxCols, ['ngay_xuat','ngay_tao','created_at','date_created']);
  $PX_NOTE = pickCol($pxCols, ['ghi_chu','mo_ta','note']);
  $PX_TYPE = pickCol($pxCols, ['loai_xuat','loai','type']);

  $ctCols = getCols($pdo,$CTPX_T);
  $CTPX_PX    = pickCol($ctCols, ['id_phieu_xuat','phieuxuat_id','id_phieu']);
  $CTPX_SP    = pickCol($ctCols, ['id_san_pham','sanpham_id','id_sp']);
  $CTPX_QTY   = pickCol($ctCols, ['so_luong','qty','quantity']);
  $CTPX_PRICE = pickCol($ctCols, ['don_gia','gia_xuat','gia','price']);
  $CTPX_TOTAL = pickCol($ctCols, ['thanh_tien','line_total','tong']);
}

/* ====== BÁN (đơn hàng) ====== */
$DH_T   = detectTable($pdo, ['donhang','don_hang']);
$CTDH_T = detectTable($pdo, ['chitiet_donhang','chi_tiet_don_hang','ct_donhang']);

$DH_ID=$DH_DATE=$DH_STATUS=null;
$CTDH_DH=$CTDH_SP=$CTDH_QTY=$CTDH_TOTAL=$CTDH_NAME=null;

if ($DH_T && $CTDH_T){
  $dhCols = getCols($pdo,$DH_T);
  $DH_ID     = pickCol($dhCols, ['id_don_hang','id','donhang_id']);
  $DH_DATE   = pickCol($dhCols, ['ngay_dat','created_at','ngay_tao','date_created']);
  $DH_STATUS = pickCol($dhCols, ['trang_thai','status']);

  $ctCols = getCols($pdo,$CTDH_T);
  $CTDH_DH    = pickCol($ctCols, ['id_don_hang','donhang_id','id_dh']);
  $CTDH_SP    = pickCol($ctCols, ['id_san_pham','sanpham_id','id_sp']);
  $CTDH_QTY   = pickCol($ctCols, ['so_luong','qty','quantity']);
  $CTDH_TOTAL = pickCol($ctCols, ['thanh_tien','line_total','tong']);
  $CTDH_NAME  = pickCol($ctCols, ['ten_san_pham','ten','name']);
}

/* ================= Filters ================= */
$q = trim((string)($_GET['q'] ?? ''));
$t = $_GET['t'] ?? 'all'; // all|NHAP|XUAT|BAN

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$tz = new DateTimeZone('Asia/Ho_Chi_Minh');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
  $from = (new DateTime('today', $tz))->modify('-29 day')->format('Y-m-d'); // mặc định 30 ngày
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
  $to = (new DateTime('today', $tz))->format('Y-m-d');
}
$fromStr = $from.' 00:00:00';
$toStr   = $to.' 23:59:59';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page-1)*$perPage;

$parts = [];

/* ================= Build UNION parts ================= */
// NHẬP
if ($PN_T && $CTPN_T && $PN_ID && $PN_DATE && $CTPN_PN && $CTPN_SP && $CTPN_QTY){
  $uPrice = $CTPN_PRICE ? "ct.$CTPN_PRICE" : "NULL";
  $uTotal = $CTPN_TOTAL ? "ct.$CTPN_TOTAL" : ($CTPN_PRICE ? "(ct.$CTPN_QTY * ct.$CTPN_PRICE)" : "NULL");
  $note   = $PN_NOTE ? "pn.$PN_NOTE" : "''";

  $nameExpr = ($spOk && $SP_ID && $SP_NAME) ? "sp.$SP_NAME" : "CONCAT('#', ct.$CTPN_SP)";
  $imgExpr  = ($spOk && $SP_ID && $SP_IMG) ? "sp.$SP_IMG" : "NULL";

  $joinSp = ($spOk && $SP_ID) ? "LEFT JOIN sanpham sp ON sp.$SP_ID = ct.$CTPN_SP" : "";

  $parts[] = "
    SELECT
      'NHAP' AS loai,
      pn.$PN_DATE AS thoi_gian,
      CONCAT('PN#', pn.$PN_ID) AS chung_tu,
      ct.$CTPN_SP AS id_sp,
      $nameExpr AS ten_sp,
      $imgExpr AS img_sp,
      ct.$CTPN_QTY AS qty_in,
      0 AS qty_out,
      ct.$CTPN_QTY AS delta,
      $uPrice AS don_gia,
      $uTotal AS thanh_tien,
      $note AS ghi_chu
    FROM $PN_T pn
    JOIN $CTPN_T ct ON ct.$CTPN_PN = pn.$PN_ID
    $joinSp
    WHERE pn.$PN_DATE BETWEEN :from AND :to
  ";
}

// XUẤT
if ($PX_T && $CTPX_T && $PX_ID && $PX_DATE && $CTPX_PX && $CTPX_SP && $CTPX_QTY){
  $uPrice = $CTPX_PRICE ? "ct.$CTPX_PRICE" : "NULL";
  $uTotal = $CTPX_TOTAL ? "ct.$CTPX_TOTAL" : ($CTPX_PRICE ? "(ct.$CTPX_QTY * ct.$CTPX_PRICE)" : "NULL");
  $note   = $PX_NOTE ? "px.$PX_NOTE" : "''";
  $typePx = $PX_TYPE ? "px.$PX_TYPE" : "''";

  $nameExpr = ($spOk && $SP_ID && $SP_NAME) ? "sp.$SP_NAME" : "CONCAT('#', ct.$CTPX_SP)";
  $imgExpr  = ($spOk && $SP_ID && $SP_IMG) ? "sp.$SP_IMG" : "NULL";
  $joinSp = ($spOk && $SP_ID) ? "LEFT JOIN sanpham sp ON sp.$SP_ID = ct.$CTPX_SP" : "";

  $parts[] = "
    SELECT
      'XUAT' AS loai,
      px.$PX_DATE AS thoi_gian,
      CONCAT('PX#', px.$PX_ID, IF($typePx<>'', CONCAT(' (', $typePx, ')'), '')) AS chung_tu,
      ct.$CTPX_SP AS id_sp,
      $nameExpr AS ten_sp,
      $imgExpr AS img_sp,
      0 AS qty_in,
      ct.$CTPX_QTY AS qty_out,
      (0 - ct.$CTPX_QTY) AS delta,
      $uPrice AS don_gia,
      $uTotal AS thanh_tien,
      $note AS ghi_chu
    FROM $PX_T px
    JOIN $CTPX_T ct ON ct.$CTPX_PX = px.$PX_ID
    $joinSp
    WHERE px.$PX_DATE BETWEEN :from AND :to
  ";
}

// BÁN
if ($DH_T && $CTDH_T && $DH_ID && $DH_DATE && $CTDH_DH && $CTDH_SP && $CTDH_QTY){
  $uTotal = $CTDH_TOTAL ? "ct.$CTDH_TOTAL" : "NULL";
  $note = $DH_STATUS ? "dh.$DH_STATUS" : "''";

  $nameExpr = ($spOk && $SP_ID && $SP_NAME) ? "sp.$SP_NAME" : ($CTDH_NAME ? "MAX(ct.$CTDH_NAME)" : "CONCAT('#', ct.$CTDH_SP)");
  $imgExpr  = ($spOk && $SP_ID && $SP_IMG) ? "sp.$SP_IMG" : "NULL";
  $joinSp = ($spOk && $SP_ID) ? "LEFT JOIN sanpham sp ON sp.$SP_ID = ct.$CTDH_SP" : "";

  // dùng GROUP BY để tránh lỗi nếu nameExpr là MAX(ct.ten)
  $parts[] = "
    SELECT
      'BAN' AS loai,
      dh.$DH_DATE AS thoi_gian,
      CONCAT('DH#', dh.$DH_ID) AS chung_tu,
      ct.$CTDH_SP AS id_sp,
      $nameExpr AS ten_sp,
      $imgExpr AS img_sp,
      0 AS qty_in,
      SUM(ct.$CTDH_QTY) AS qty_out,
      (0 - SUM(ct.$CTDH_QTY)) AS delta,
      NULL AS don_gia,
      ".($CTDH_TOTAL ? "SUM(ct.$CTDH_TOTAL)" : "NULL")." AS thanh_tien,
      $note AS ghi_chu
    FROM $DH_T dh
    JOIN $CTDH_T ct ON ct.$CTDH_DH = dh.$DH_ID
    $joinSp
    WHERE dh.$DH_DATE BETWEEN :from AND :to
    GROUP BY dh.$DH_ID, ct.$CTDH_SP
  ";
}

if (!$parts){
  die("Không đủ bảng/cột để dựng lịch sử kho. Cần tối thiểu: phieunhap+chitiet_phieunhap hoặc phieuxuat+chitiet_phieuxuat hoặc donhang+chitiet_donhang.");
}

$unionSql = implode(" UNION ALL ", $parts);

/* ================= Outer filters ================= */
$where = " WHERE 1 ";
$bind = [
  ':from' => $fromStr,
  ':to'   => $toStr,
];

if (in_array($t, ['NHAP','XUAT','BAN'], true)){
  $where .= " AND loai = :loai ";
  $bind[':loai'] = $t;
}

if ($q !== ''){
  $where .= " AND (ten_sp LIKE :q OR chung_tu LIKE :q OR id_sp = :qid) ";
  $bind[':q'] = "%$q%";
  $bind[':qid'] = (int)$q;
}

/* ================= Count + List ================= */
$countSql = "SELECT COUNT(*) FROM ($unionSql) t $where";
$st = $pdo->prepare($countSql);
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($total/$perPage));

$listSql = "
  SELECT * FROM ($unionSql) t
  $where
  ORDER BY thoi_gian DESC
  LIMIT $perPage OFFSET $offset
";
$st = $pdo->prepare($listSql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= Summary (qty) ================= */
$sumIn = 0; $sumOut = 0; $sumNet = 0;
foreach($rows as $r){
  $sumIn += (int)($r['qty_in'] ?? 0);
  $sumOut += (int)($r['qty_out'] ?? 0);
  $sumNet += (int)($r['delta'] ?? 0);
}

/* ================= Render ================= */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

function urlWith(array $add=[]): string {
  $keep = $_GET;
  foreach($add as $k=>$v){
    if ($v === null) unset($keep[$k]);
    else $keep[$k] = $v;
  }
  return 'lichsu_kho.php' . ($keep ? ('?'.http_build_query($keep)) : '');
}
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between gap-3">
      <div>
        <div class="text-2xl font-extrabold">Lịch sử kho</div>
        <div class="text-sm text-muted font-bold mt-1">Nhập / Xuất / Bán (trừ kho theo đơn). Lọc theo thời gian và sản phẩm.</div>
      </div>
      <a href="baocao.php" class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">← Báo cáo</a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-line shadow-card p-4 md:p-5">
      <form class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end" method="get">
        <div class="md:col-span-3">
          <label class="text-sm font-extrabold">Từ ngày</label>
          <input type="date" name="from" value="<?= h($from) ?>" class="mt-1 w-full rounded-xl border-line bg-[#f3f6fb]">
        </div>
        <div class="md:col-span-3">
          <label class="text-sm font-extrabold">Đến ngày</label>
          <input type="date" name="to" value="<?= h($to) ?>" class="mt-1 w-full rounded-xl border-line bg-[#f3f6fb]">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-extrabold">Loại</label>
          <select name="t" class="mt-1 w-full rounded-xl border-line bg-white text-sm font-extrabold">
            <option value="all" <?= $t==='all'?'selected':'' ?>>Tất cả</option>
            <option value="NHAP" <?= $t==='NHAP'?'selected':'' ?>>Nhập</option>
            <option value="XUAT" <?= $t==='XUAT'?'selected':'' ?>>Xuất</option>
            <option value="BAN"  <?= $t==='BAN'?'selected':'' ?>>Bán</option>
          </select>
        </div>
        <div class="md:col-span-3">
          <label class="text-sm font-extrabold">Tìm (tên SP / ID / mã chứng từ)</label>
          <input name="q" value="<?= h($q) ?>" placeholder="VD: Classic / 12 / PN#5"
                 class="mt-1 w-full rounded-xl border-line bg-[#f3f6fb]">
        </div>
        <div class="md:col-span-1 flex gap-2">
          <button class="flex-1 px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">Lọc</button>
        </div>
      </form>
    </div>

    <!-- Quick stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-sm text-muted font-bold">Tổng bản ghi</div>
        <div class="mt-1 text-2xl font-extrabold"><?= number_format($total) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-sm text-muted font-bold">Trong trang (nhập)</div>
        <div class="mt-1 text-2xl font-extrabold text-green-600">+<?= number_format($sumIn) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-sm text-muted font-bold">Trong trang (xuất/bán)</div>
        <div class="mt-1 text-2xl font-extrabold text-red-600">-<?= number_format($sumOut) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-sm text-muted font-bold">Trong trang (net)</div>
        <div class="mt-1 text-2xl font-extrabold"><?= ($sumNet>=0?'+':'') . number_format($sumNet) ?></div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-line shadow-card overflow-hidden">
      <div class="p-4 md:p-5 flex items-center justify-between">
        <div class="text-sm font-extrabold">Timeline (mới nhất trước)</div>
        <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?></div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-[#f7faff] text-muted">
            <tr>
              <th class="text-left px-4 py-3 font-extrabold">Thời gian</th>
              <th class="text-left px-4 py-3 font-extrabold">Loại</th>
              <th class="text-left px-4 py-3 font-extrabold">Chứng từ</th>
              <th class="text-left px-4 py-3 font-extrabold">Sản phẩm</th>
              <th class="text-right px-4 py-3 font-extrabold">+Nhập</th>
              <th class="text-right px-4 py-3 font-extrabold">-Xuất</th>
              <th class="text-right px-4 py-3 font-extrabold">Net</th>
              <th class="text-right px-4 py-3 font-extrabold">Thành tiền</th>
              <th class="text-left px-4 py-3 font-extrabold">Ghi chú</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <?php if(!$rows): ?>
              <tr><td colspan="9" class="px-4 py-10 text-center text-muted font-bold">Không có dữ liệu.</td></tr>
            <?php endif; ?>

            <?php foreach($rows as $r):
              $loai = (string)($r['loai'] ?? '');
              $time = (string)($r['thoi_gian'] ?? '');
              $ctu  = (string)($r['chung_tu'] ?? '');
              $idsp = (int)($r['id_sp'] ?? 0);
              $ten  = (string)($r['ten_sp'] ?? ('#'.$idsp));
              $img  = (string)($r['img_sp'] ?? '');
              $imgUrl = $img ? img_src($img) : '';

              $in  = (int)($r['qty_in'] ?? 0);
              $out = (int)($r['qty_out'] ?? 0);
              $net = (int)($r['delta'] ?? 0);
              $money = $r['thanh_tien'];
              $note = (string)($r['ghi_chu'] ?? '');

              $badge = $loai==='NHAP' ? 'bg-green-50 text-green-700'
                     : ($loai==='XUAT' ? 'bg-amber-50 text-amber-700'
                     : 'bg-blue-50 text-primary');
            ?>
              <tr class="hover:bg-[#f7faff]">
                <td class="px-4 py-3">
                  <div class="text-xs text-muted font-bold"><?= h($time) ?></div>
                </td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $badge ?>"><?= h($loai) ?></span>
                </td>
                <td class="px-4 py-3">
                  <div class="font-extrabold"><?= h($ctu) ?></div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="size-11 rounded-xl border border-line bg-[#f1f5f9] overflow-hidden grid place-items-center">
                      <?php if($imgUrl): ?><img src="<?= h($imgUrl) ?>" class="w-full h-full object-cover" alt=""><?php
                      else: ?><span class="material-symbols-outlined text-slate-400">photo</span><?php endif; ?>
                    </div>
                    <div>
                      <div class="font-extrabold text-slate-900 line-clamp-1"><?= h($ten) ?></div>
                      <div class="text-xs text-muted font-bold">ID: <?= (int)$idsp ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-right font-extrabold text-green-700"><?= $in ? '+'.number_format($in) : '—' ?></td>
                <td class="px-4 py-3 text-right font-extrabold text-red-700"><?= $out ? '-'.number_format($out) : '—' ?></td>
                <td class="px-4 py-3 text-right font-extrabold"><?= ($net>=0?'+':'').number_format($net) ?></td>
                <td class="px-4 py-3 text-right font-extrabold"><?= ($money===null || $money==='') ? '—' : money_vnd($money) ?></td>
                <td class="px-4 py-3">
                  <div class="text-slate-700 font-bold"><?= h($note) ?></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 md:p-5 flex items-center justify-between">
        <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?></div>
        <div class="flex gap-2">
          <?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); ?>
          <a class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h(urlWith(['page'=>$prev])) ?>">Trước</a>
          <a class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h(urlWith(['page'=>$next])) ?>">Sau</a>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
