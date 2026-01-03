<?php
// admin/tonkho_nhatky.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (file_exists(__DIR__ . '/includes/helpers.php')) require_once __DIR__ . '/includes/helpers.php';
elseif (file_exists(__DIR__ . '/includes/hamChung.php')) require_once __DIR__ . '/includes/hamChung.php';

if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('money_vnd')) {
  function money_vnd($n){
    $n=(float)($n??0);
    return number_format($n,0,',','.') . ' ₫';
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
    $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
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

$ACTIVE = 'tonkho_nhatky';
$PAGE_TITLE = 'Lịch sử tồn kho';
if (function_exists('requirePermission')) requirePermission('tonkho_nhatky');

/* Validate */
if (!($pdo instanceof PDO)) die("Thiếu kết nối DB (\$pdo).");
if (!tableExists($pdo,'tonkho_nhatky')) die("Thiếu bảng <b>tonkho_nhatky</b>.");

/* Map columns (hỗ trợ nếu bạn đặt tên hơi khác) */
$cols = getCols($pdo,'tonkho_nhatky');
$C_ID   = pickCol($cols,['id_nk','id','id_log']);
$C_TIME = pickCol($cols,['thoi_gian','ngay_tao','created_at']);
$C_TYPE = pickCol($cols,['loai','type']);
$C_SRC  = pickCol($cols,['nguon_bang','nguon','source']);
$C_DOC  = pickCol($cols,['id_chung_tu','id_ref','ref_id']);
$C_DOCNO= pickCol($cols,['ma_chung_tu','ma_phieu','ref_code']);
$C_SP   = pickCol($cols,['id_san_pham','sanpham_id','id_sp']);
$C_KHO  = pickCol($cols,['id_kho','kho_id']);
$C_BEFORE = pickCol($cols,['so_luong_truoc','ton_truoc','before_qty']);
$C_DELTA  = pickCol($cols,['thay_doi','delta','change_qty']);
$C_AFTER  = pickCol($cols,['so_luong_sau','ton_sau','after_qty']);
$C_PRICE  = pickCol($cols,['don_gia','gia','price']);
$C_NOTE   = pickCol($cols,['ghi_chu','note','mo_ta']);

if (!$C_SP || !$C_DELTA) die("Bảng tonkho_nhatky thiếu cột bắt buộc: <b>id_san_pham</b> và <b>thay_doi</b>.");

/* Join product name if possible */
$spOk = tableExists($pdo,'sanpham');
$SP_ID=$SP_NAME=null;
if ($spOk){
  $spCols=getCols($pdo,'sanpham');
  $SP_ID=pickCol($spCols,['id_san_pham','id']);
  $SP_NAME=pickCol($spCols,['ten_san_pham','ten','name']);
}

/* Filters */
$q = trim((string)($_GET['q'] ?? ''));           // name or id
$type = trim((string)($_GET['type'] ?? 'all'));  // all|NHAP|XUAT|BAN|...
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page-1)*$perPage;

$where=" WHERE 1 ";
$params=[];

if ($type !== 'all' && $C_TYPE){
  $where.=" AND h.$C_TYPE = ? ";
  $params[]=$type;
}
if ($from !== '' && $C_TIME){
  $where.=" AND h.$C_TIME >= ? ";
  $params[]=$from.' 00:00:00';
}
if ($to !== '' && $C_TIME){
  $where.=" AND h.$C_TIME <= ? ";
  $params[]=$to.' 23:59:59';
}
if ($q !== ''){
  if ($spOk && $SP_ID && $SP_NAME){
    $where.=" AND (sp.$SP_NAME LIKE ? OR sp.$SP_ID = ? OR h.$C_SP = ?) ";
    $params[]="%$q%";
    $params[]=(int)$q;
    $params[]=(int)$q;
  } else {
    $where.=" AND (h.$C_SP = ?) ";
    $params[]=(int)$q;
  }
}

/* Count */
$join = ($spOk && $SP_ID) ? " LEFT JOIN sanpham sp ON sp.$SP_ID = h.$C_SP " : "";
$st=$pdo->prepare("SELECT COUNT(*) FROM tonkho_nhatky h $join $where");
$st->execute($params);
$total=(int)$st->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));

/* Rows */
$sel = [
  ($C_ID ? "h.$C_ID AS id" : "NULL AS id"),
  ($C_TIME ? "h.$C_TIME AS t" : "NULL AS t"),
  ($C_TYPE ? "h.$C_TYPE AS loai" : "NULL AS loai"),
  ($C_SRC ? "h.$C_SRC AS src" : "NULL AS src"),
  ($C_DOC ? "h.$C_DOC AS doc_id" : "NULL AS doc_id"),
  ($C_DOCNO ? "h.$C_DOCNO AS doc_no" : "NULL AS doc_no"),
  "h.$C_SP AS sp_id",
  ($C_KHO ? "h.$C_KHO AS kho" : "1 AS kho"),
  ($C_BEFORE ? "h.$C_BEFORE AS before_qty" : "NULL AS before_qty"),
  "h.$C_DELTA AS delta_qty",
  ($C_AFTER ? "h.$C_AFTER AS after_qty" : "NULL AS after_qty"),
  ($C_PRICE ? "h.$C_PRICE AS price" : "NULL AS price"),
  ($C_NOTE ? "h.$C_NOTE AS note" : "NULL AS note"),
];
if ($spOk && $SP_ID && $SP_NAME) $sel[] = "sp.$SP_NAME AS sp_ten";
else $sel[]="NULL AS sp_ten";

$order = $C_TIME ? "h.$C_TIME DESC" : ($C_ID ? "h.$C_ID DESC" : "1");

$sql="SELECT ".implode(', ',$sel)." FROM tonkho_nhatky h $join $where ORDER BY $order LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Render */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

function urlWith(array $add=[]): string {
  $keep=$_GET;
  foreach($add as $k=>$v){
    if ($v===null) unset($keep[$k]);
    else $keep[$k]=$v;
  }
  return 'tonkho_nhatky.php'.($keep?('?'.http_build_query($keep)):'');
}
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-2xl font-extrabold">Lịch sử tồn kho</div>
        <div class="text-sm text-muted font-bold mt-1">Tổng: <?= number_format($total) ?> dòng lịch sử</div>
      </div>
      <a href="baocao.php" class="px-4 py-2 rounded-xl border border-line bg-white text-sm font-extrabold">Báo cáo</a>
    </div>

    <div class="bg-white rounded-2xl border border-line shadow-card p-4 md:p-5">
      <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-4">
          <label class="text-sm font-extrabold">Tìm (tên / ID sản phẩm)</label>
          <input name="q" value="<?= h($q) ?>" class="mt-1 w-full rounded-xl border border-line bg-[#f3f6fb]">
        </div>
        <div class="md:col-span-3">
          <label class="text-sm font-extrabold">Loại</label>
          <select name="type" class="mt-1 w-full rounded-xl border border-line bg-white text-sm font-extrabold">
            <option value="all" <?= $type==='all'?'selected':'' ?>>Tất cả</option>
            <option value="NHAP" <?= $type==='NHAP'?'selected':'' ?>>NHẬP</option>
            <option value="XUAT" <?= $type==='XUAT'?'selected':'' ?>>XUẤT</option>
            <option value="BAN"  <?= $type==='BAN'?'selected':'' ?>>BÁN</option>
            <option value="HOAN" <?= $type==='HOAN'?'selected':'' ?>>HOÀN</option>
            <option value="DIEU_CHINH" <?= $type==='DIEU_CHINH'?'selected':'' ?>>ĐIỀU CHỈNH</option>
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-extrabold">Từ ngày</label>
          <input type="date" name="from" value="<?= h($from) ?>" class="mt-1 w-full rounded-xl border border-line bg-white">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-extrabold">Đến ngày</label>
          <input type="date" name="to" value="<?= h($to) ?>" class="mt-1 w-full rounded-xl border border-line bg-white">
        </div>
        <div class="md:col-span-1 flex gap-2">
          <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">Lọc</button>
        </div>
      </form>
    </div>

    <div class="bg-white rounded-2xl border border-line shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-[#f3f6fb] text-muted">
            <tr>
              <th class="px-4 py-3 text-left font-extrabold">Thời gian</th>
              <th class="px-4 py-3 text-left font-extrabold">Loại</th>
              <th class="px-4 py-3 text-left font-extrabold">Sản phẩm</th>
              <th class="px-4 py-3 text-right font-extrabold">Trước</th>
              <th class="px-4 py-3 text-right font-extrabold">+/−</th>
              <th class="px-4 py-3 text-right font-extrabold">Sau</th>
              <th class="px-4 py-3 text-left font-extrabold">Chứng từ</th>
              <th class="px-4 py-3 text-left font-extrabold">Ghi chú</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <?php if(!$rows): ?>
              <tr><td colspan="8" class="px-4 py-10 text-center text-muted font-bold">Chưa có lịch sử.</td></tr>
            <?php endif; ?>

            <?php foreach($rows as $r):
              $loai = (string)($r['loai'] ?? '');
              $delta = (int)($r['delta_qty'] ?? 0);
              $pillCls = ($delta>=0) ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700';
              $spTen = (string)($r['sp_ten'] ?? '');
              $spId = (int)($r['sp_id'] ?? 0);

              $docNo = (string)($r['doc_no'] ?? '');
              $src = (string)($r['src'] ?? '');
              $docId = (int)($r['doc_id'] ?? 0);

              // link chứng từ (tuỳ bạn có trang nào)
              $docLink = '';
              if ($src==='phieunhap') $docLink = 'phieunhap.php?xem='.$docId;
              elseif ($src==='phieuxuat') $docLink = 'phieuxuat.php?xem='.$docId;
              elseif ($src==='donhang') $docLink = 'donhang.php?xem='.$docId;
            ?>
              <tr class="hover:bg-[#f7faff]">
                <td class="px-4 py-3 text-xs text-muted font-bold"><?= h((string)($r['t'] ?? '')) ?></td>
                <td class="px-4 py-3">
                  <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700"><?= h($loai) ?></span>
                </td>
                <td class="px-4 py-3">
                  <div class="font-extrabold text-slate-900"><?= h($spTen ?: ('SP #'.$spId)) ?></div>
                  <div class="text-xs text-muted font-bold">ID: <?= $spId ?></div>
                </td>
                <td class="px-4 py-3 text-right font-extrabold"><?= ($r['before_qty']===null?'—':number_format((int)$r['before_qty'])) ?></td>
                <td class="px-4 py-3 text-right">
                  <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $pillCls ?>">
                    <?= $delta>=0?'+':'' ?><?= number_format($delta) ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-extrabold"><?= ($r['after_qty']===null?'—':number_format((int)$r['after_qty'])) ?></td>
                <td class="px-4 py-3">
                  <?php if($docLink): ?>
                    <a class="text-primary font-extrabold hover:underline" href="<?= h($docLink) ?>">
                      <?= h($docNo ?: ($src.'#'.$docId)) ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted font-bold"><?= h($docNo ?: ($src ? ($src.'#'.$docId) : '—')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-muted font-bold"><?= h((string)($r['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

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
