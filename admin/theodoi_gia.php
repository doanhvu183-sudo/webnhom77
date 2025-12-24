<?php
// admin/theodoi_gia.php  (VIEW ONLY)
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// fallback nếu dự án dùng $conn
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) $pdo = $conn;

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
$me = $_SESSION['admin'];
$vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Guards: tránh redeclare ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n): string {
    $n = (float)($n ?? 0);
    return number_format($n, 0, ',', '.') . ' ₫';
  }
}
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, string $name): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('getCols')) {
  function getCols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
  }
}
if (!function_exists('pickCol')) {
  function pickCol(array $cols, array $cands): ?string {
    foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; }
    return null;
  }
}
if (!function_exists('requirePermission')) {
  // fallback tối thiểu để không lỗi nếu project bạn chưa có hàm
  function requirePermission(string $key): void { return; }
}

/* ================= Page meta ================= */
$ACTIVE = 'theodoi_gia';
$PAGE_TITLE = 'Theo dõi giá';
requirePermission('theodoi_gia');

/* ================= Validate ================= */
$fatalError = null;
if (!($pdo instanceof PDO)) $fatalError = "Kết nối CSDL không hợp lệ (thiếu \$pdo).";

if (!$fatalError && !tableExists($pdo,'sanpham')) $fatalError = "Thiếu bảng <b>sanpham</b>.";
if (!$fatalError && !tableExists($pdo,'theo_doi_gia')) $fatalError = "Thiếu bảng <b>theo_doi_gia</b>.";

/* ================= Map schema ================= */
$SP_ID=$SP_NAME=$SP_IMG=$SP_COST=$SP_PRICE=null;
$HIS_ID=$HIS_SP=$HIS_TIME=$HIS_OLD=$HIS_NEW=$HIS_NOTE=null;

if (!$fatalError) {
  // sanpham
  $spCols = getCols($pdo,'sanpham');
  $SP_ID   = pickCol($spCols, ['id_san_pham','id']);
  $SP_NAME = pickCol($spCols, ['ten_san_pham','ten','name']);
  $SP_IMG  = pickCol($spCols, ['hinh_anh','anh','image']);
  $SP_COST = pickCol($spCols, ['gia_nhap','gia_von','cost']);
  $SP_PRICE= pickCol($spCols, ['gia','gia_ban','price']);

  if(!$SP_ID)   $fatalError = "Bảng sanpham thiếu cột ID (id_san_pham).";
  if(!$SP_NAME) $fatalError = $fatalError ?: "Bảng sanpham thiếu cột tên (ten_san_pham).";
  if(!$SP_PRICE)$fatalError = $fatalError ?: "Bảng sanpham thiếu cột giá bán (gia/gia_ban).";

  // theo_doi_gia
  $hisCols = getCols($pdo,'theo_doi_gia');
  $HIS_ID   = pickCol($hisCols, ['id','id_theo_doi','id_log']);
  $HIS_SP   = pickCol($hisCols, ['id_san_pham','sanpham_id']);
  $HIS_TIME = pickCol($hisCols, ['thoi_gian','ngay_tao','created_at','updated_at']);
  $HIS_OLD  = pickCol($hisCols, ['gia_cu','old_price','gia_truoc']);
  $HIS_NEW  = pickCol($hisCols, ['gia_moi','new_price','gia_sau']);
  $HIS_NOTE = pickCol($hisCols, ['ghi_chu','note','mo_ta']);

  if(!$HIS_SP)  $fatalError = $fatalError ?: "Bảng theo_doi_gia thiếu cột liên kết sản phẩm (id_san_pham).";
  if(!$HIS_OLD) $fatalError = $fatalError ?: "Bảng theo_doi_gia thiếu cột giá cũ (gia_cu).";
  if(!$HIS_NEW) $fatalError = $fatalError ?: "Bảng theo_doi_gia thiếu cột giá mới (gia_moi).";
}

/* ================= Filters / Pagination ================= */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

$rows = [];
$total = 0;
$totalPages = 1;

if (!$fatalError) {
  $where = " WHERE 1 ";
  $params = [];

  if ($q !== '') {
    // tìm theo tên / id sản phẩm
    $where .= " AND (sp.{$SP_NAME} LIKE ? OR sp.{$SP_ID} = ?) ";
    $params[] = "%$q%";
    $params[] = (int)$q;
  }

  // count
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM theo_doi_gia h
    JOIN sanpham sp ON sp.{$SP_ID} = h.{$HIS_SP}
    $where
  ");
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $totalPages = max(1, (int)ceil($total/$perPage));

  // list
  $timeOrder = $HIS_TIME ? "h.{$HIS_TIME}" : ($HIS_ID ? "h.{$HIS_ID}" : "1");
  $sql = "
    SELECT
      sp.{$SP_ID}   AS sp_id,
      sp.{$SP_NAME} AS sp_ten
      ".($SP_IMG ? ", sp.{$SP_IMG} AS sp_img" : ", NULL AS sp_img")."
      ".($SP_COST ? ", sp.{$SP_COST} AS sp_cost" : ", NULL AS sp_cost")."
      , sp.{$SP_PRICE} AS sp_price
      ".($HIS_TIME ? ", h.{$HIS_TIME} AS his_time" : ", NULL AS his_time")."
      , h.{$HIS_OLD}  AS his_old
      , h.{$HIS_NEW}  AS his_new
      ".($HIS_NOTE ? ", h.{$HIS_NOTE} AS his_note" : ", NULL AS his_note")."
    FROM theo_doi_gia h
    JOIN sanpham sp ON sp.{$SP_ID} = h.{$HIS_SP}
    $where
    ORDER BY $timeOrder DESC
    LIMIT $perPage OFFSET $offset
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= Render ================= */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

function urlWith(array $add=[]): string {
  $keep = $_GET;
  unset($keep['type'],$keep['msg']);
  foreach($add as $k=>$v){
    if ($v === null) unset($keep[$k]);
    else $keep[$k] = $v;
  }
  return 'theodoi_gia.php' . ($keep ? ('?'.http_build_query($keep)) : '');
}
?>

<?php if($fatalError): ?>
  <div class="max-w-7xl mx-auto">
    <div class="p-6 bg-white rounded-2xl border border-gray-200 shadow-soft">
      <div class="text-lg font-extrabold mb-2">Không thể tải trang</div>
      <div class="text-sm text-slate-600"><?= $fatalError ?></div>
    </div>
  </div>
  <?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; exit; ?>
<?php endif; ?>

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

  <div class="flex items-center justify-between">
    <div>
      <div class="text-2xl font-extrabold">Lịch sử thay đổi giá</div>
      <div class="text-sm text-slate-500 font-semibold">
        Hiển thị: Ảnh • Tên • Giá nhập • Giá bán • Thời gian • Giá cũ • Giá mới • Ghi chú
      </div>
    </div>
    <div class="text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-extrabold">
      <?= $isAdmin ? 'ADMIN' : h($vaiTro) ?>
    </div>
  </div>

  <!-- Filter -->
  <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-4 md:p-5">
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end" method="get">
      <div class="md:col-span-10">
        <label class="text-sm font-extrabold">Tìm theo tên / ID sản phẩm</label>
        <input name="q" value="<?= h($q) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
               placeholder="VD: Dép sục / 12 ..." />
      </div>
      <div class="md:col-span-2 flex gap-2">
        <button class="flex-1 px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">Lọc</button>
        <a href="theodoi_gia.php" class="px-4 py-3 rounded-2xl border border-gray-200 bg-white font-extrabold">Reset</a>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-gray-200 shadow-soft overflow-hidden">
    <div class="p-4 md:p-5 flex items-center justify-between">
      <div class="text-sm font-extrabold">Nhật ký giá (mới nhất trước)</div>
      <div class="text-xs text-slate-500">Tổng: <b><?= number_format($total) ?></b></div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-slate-500">
          <tr>
            <th class="text-left px-4 py-3 font-extrabold">Ảnh</th>
            <th class="text-left px-4 py-3 font-extrabold">Sản phẩm</th>
            <th class="text-right px-4 py-3 font-extrabold">Giá nhập</th>
            <th class="text-right px-4 py-3 font-extrabold">Giá bán</th>
            <th class="text-left px-4 py-3 font-extrabold">Thời gian</th>
            <th class="text-right px-4 py-3 font-extrabold">Giá cũ</th>
            <th class="text-right px-4 py-3 font-extrabold">Giá mới</th>
            <th class="text-left px-4 py-3 font-extrabold">Ghi chú</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
        <?php if(!$rows): ?>
          <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500 font-semibold">Chưa có lịch sử giá.</td></tr>
        <?php endif; ?>

        <?php foreach($rows as $r): ?>
          <?php
            $img = (string)($r['sp_img'] ?? '');
            $imgUrl = $img ? "../assets/img/{$img}" : '';
            $spId = (int)($r['sp_id'] ?? 0);
            $spTen = (string)($r['sp_ten'] ?? '');
            $cost = $r['sp_cost'];
            $price = (float)($r['sp_price'] ?? 0);
            $time = (string)($r['his_time'] ?? '');
            $oldP = (float)($r['his_old'] ?? 0);
            $newP = (float)($r['his_new'] ?? 0);
            $note = (string)($r['his_note'] ?? '');
          ?>
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="size-11 rounded-xl bg-gray-100 border border-gray-200 overflow-hidden grid place-items-center">
                <?php if($imgUrl): ?>
                  <img src="<?= h($imgUrl) ?>" class="w-full h-full object-cover" alt="">
                <?php else: ?>
                  <span class="material-symbols-outlined text-slate-400">photo</span>
                <?php endif; ?>
              </div>
            </td>

            <td class="px-4 py-3">
              <div class="font-extrabold text-slate-900 line-clamp-1"><?= h($spTen) ?></div>
              <div class="text-xs text-slate-500">#<?= $spId ?></div>
            </td>

            <td class="px-4 py-3 text-right">
              <span class="inline-flex px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-extrabold">
                <?= ($cost===null || $cost==='') ? '—' : money_vnd($cost) ?>
              </span>
            </td>

            <td class="px-4 py-3 text-right">
              <span class="inline-flex px-3 py-1 rounded-full bg-blue-50 text-primary font-extrabold">
                <?= money_vnd($price) ?>
              </span>
            </td>

            <td class="px-4 py-3">
              <div class="text-xs text-slate-500 font-bold"><?= h($time) ?></div>
            </td>

            <td class="px-4 py-3 text-right font-extrabold"><?= money_vnd($oldP) ?></td>
            <td class="px-4 py-3 text-right font-extrabold"><?= money_vnd($newP) ?></td>

            <td class="px-4 py-3">
              <div class="text-slate-700 font-semibold"><?= h($note) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="p-4 md:p-5 flex items-center justify-between">
      <div class="text-xs text-slate-500">Trang <?= $page ?>/<?= $totalPages ?></div>
      <div class="flex gap-2">
        <?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); ?>
        <a class="px-3 py-2 rounded-xl border bg-white text-sm font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
           href="<?= h(urlWith(['page'=>$prev])) ?>">Trước</a>
        <a class="px-3 py-2 rounded-xl border bg-white text-sm font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
           href="<?= h(urlWith(['page'=>$next])) ?>">Sau</a>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
