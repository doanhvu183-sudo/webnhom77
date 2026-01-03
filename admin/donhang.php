<?php
// admin/donhang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/helpers.php';

require_login_admin();
requirePermission('donhang', $pdo);
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE = 'donhang';
$PAGE_TITLE = 'Quản lý Đơn hàng';

if (!tableExists($pdo,'donhang')) die("Thiếu bảng donhang.");
if (!tableExists($pdo,'chitiet_donhang')) die("Thiếu bảng chitiet_donhang.");

/* ========= map columns ========= */
$dhCols = getCols($pdo,'donhang');
$DH_ID      = pickCol($dhCols, ['id_don_hang']);
$DH_UID     = pickCol($dhCols, ['id_nguoi_dung','id_user']);
$DH_CODE    = pickCol($dhCols, ['ma_don_hang']);
$DH_TOTAL   = pickCol($dhCols, ['tong_thanh_toan','tong_tien']);
$DH_SUB     = pickCol($dhCols, ['tong_tien']);             // subtotal
$DH_DISC    = pickCol($dhCols, ['tien_giam','giam_gia']);
$DH_STATUS  = pickCol($dhCols, ['trang_thai']);
$DH_PAYST   = pickCol($dhCols, ['trang_thai_thanh_toan']);
$DH_METHOD  = pickCol($dhCols, ['phuong_thuc','phuong_thuc_thanh_toan']);
$DH_NOTE    = pickCol($dhCols, ['ghi_chu']);
$DH_DATE    = pickCol($dhCols, ['ngay_dat','created_at']);
$DH_UPD     = pickCol($dhCols, ['ngay_cap_nhat','updated_at']);
$DH_NAME    = pickCol($dhCols, ['ten_nhan']);
$DH_PHONE   = pickCol($dhCols, ['so_dien_thoai_nhan']);
$DH_ADDR    = pickCol($dhCols, ['dia_chi_nhan']);
$DH_VOUCHER = pickCol($dhCols, ['ma_voucher']);
$DH_DATRU   = pickCol($dhCols, ['da_tru_ton']); // khuyến nghị có

if(!$DH_ID) die("Bảng donhang thiếu cột id_don_hang.");

/* chitiet_donhang */
$ctCols = getCols($pdo,'chitiet_donhang');
$CT_OD    = pickCol($ctCols, ['id_don_hang']);
$CT_PID   = pickCol($ctCols, ['id_san_pham']);
$CT_NAME  = pickCol($ctCols, ['ten_san_pham']);
$CT_SIZE  = pickCol($ctCols, ['size']);
$CT_QTY   = pickCol($ctCols, ['so_luong']);
$CT_PRICE = pickCol($ctCols, ['don_gia']);
$CT_TOTAL = pickCol($ctCols, ['thanh_tien']);
if(!$CT_OD || !$CT_PID || !$CT_QTY) die("Bảng chitiet_donhang thiếu cột id_don_hang/id_san_pham/so_luong.");

/* sanpham (để lấy ảnh/tên + cập nhật tồn nếu cần) */
$spOk = tableExists($pdo,'sanpham');
$SP_ID = $SP_NAME = $SP_IMG = $SP_STOCK = null;
if ($spOk) {
  $spCols = getCols($pdo,'sanpham');
  $SP_ID    = pickCol($spCols, ['id_san_pham','id']);
  $SP_NAME  = pickCol($spCols, ['ten_san_pham','ten']);
  $SP_IMG   = pickCol($spCols, ['hinh_anh','anh']);
  $SP_STOCK = pickCol($spCols, ['so_luong']);
}

/* tonkho */
$tkOk = tableExists($pdo,'tonkho');

/* ========= helpers in-page ========= */
function norm_status(string $s): string {
  $x = trim($s);
  if ($x === '') return $x;
  if (preg_match('~^[A-Z0-9_]+$~', $x)) return $x;
  $m = [
    'Chờ xử lý' => 'CHO_XU_LY',
    'Chờ duyệt' => 'CHO_DUYET',
    'Đang giao' => 'DANG_GIAO',
    'Hoàn tất'  => 'HOAN_TAT',
    'Hoàn thành'=> 'HOAN_TAT',
    'Hủy'       => 'HUY',
    'Huỷ'       => 'HUY',
  ];
  return $m[$x] ?? $x;
}
function is_done_status(string $s): bool {
  $x = norm_status($s);
  return in_array($x, ['HOAN_TAT','HOAN_THANH','DONE','COMPLETED'], true);
}

function order_deducted(PDO $pdo, int $orderId, ?string $DH_DATRU, string $DH_ID): bool {
  if ($DH_DATRU) {
    $st = $pdo->prepare("SELECT {$DH_DATRU} FROM donhang WHERE {$DH_ID}=? LIMIT 1");
    $st->execute([$orderId]);
    return ((int)($st->fetchColumn() ?? 0)) === 1;
  }
  if (tableExists($pdo,'nhatky_hoatdong')) {
    $st = $pdo->prepare("SELECT 1 FROM nhatky_hoatdong WHERE hanh_dong='TRU_TON_DON' AND bang_lien_quan='donhang' AND id_ban_ghi=? LIMIT 1");
    $st->execute([$orderId]);
    return (bool)$st->fetchColumn();
  }
  return false;
}
function set_order_deducted(PDO $pdo, int $orderId, bool $flag, ?string $DH_DATRU, string $DH_ID): void {
  if ($DH_DATRU) {
    $pdo->prepare("UPDATE donhang SET {$DH_DATRU}=? WHERE {$DH_ID}=?")->execute([$flag ? 1 : 0, $orderId]);
  }
}

/**
 * Apply stock delta for an order.
 * $dir = -1 (trừ tồn), +1 (hoàn tồn)
 */
function apply_stock_for_order(PDO $pdo, int $orderId, int $dir, array $colMap): array {
  $CT_OD   = $colMap['CT_OD'];
  $CT_PID  = $colMap['CT_PID'];
  $CT_QTY  = $colMap['CT_QTY'];
  $CT_NAME = $colMap['CT_NAME'];
  $CT_SIZE = $colMap['CT_SIZE'];

  $spOk    = $colMap['spOk'];
  $SP_ID   = $colMap['SP_ID'];
  $SP_STOCK= $colMap['SP_STOCK'];

  $tkOk    = $colMap['tkOk'];

  $st = $pdo->prepare(
    "SELECT {$CT_PID} AS pid, {$CT_QTY} AS qty,
            ".($CT_NAME ? "{$CT_NAME} AS name" : "NULL AS name").",
            ".($CT_SIZE ? "{$CT_SIZE} AS size" : "NULL AS size")."
     FROM chitiet_donhang
     WHERE {$CT_OD}=?"
  );
  $st->execute([$orderId]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $sum = [];
  foreach ($items as $it) {
    $pid = (int)$it['pid'];
    $qty = (int)$it['qty'];
    if ($pid<=0 || $qty<=0) continue;
    $sum[$pid] = ($sum[$pid] ?? 0) + $qty;
  }
  if (!$sum) return ['items'=>[], 'changed'=>0];

  $changed = 0;
  foreach ($sum as $pid => $qty) {
    $delta = $dir * $qty;

    if ($tkOk && tableExists($pdo,'tonkho')) {
      $pdo->prepare("INSERT INTO tonkho (id_san_pham, so_luong, ngay_cap_nhat)
                     VALUES (?, 0, NOW())
                     ON DUPLICATE KEY UPDATE ngay_cap_nhat=ngay_cap_nhat")->execute([$pid]);
      $pdo->prepare("UPDATE tonkho SET so_luong = GREATEST(0, so_luong + ?), ngay_cap_nhat=NOW() WHERE id_san_pham=?")
          ->execute([$delta, $pid]);
      $changed++;
    }

    if ($spOk && $SP_ID && $SP_STOCK && tableExists($pdo,'sanpham')) {
      $pdo->prepare("UPDATE sanpham SET {$SP_STOCK} = GREATEST(0, {$SP_STOCK} + ?) WHERE {$SP_ID}=?")
          ->execute([$delta, $pid]);
      $changed++;
    }
  }

  return ['items'=>$items, 'changed'=>$changed];
}

function img_src_assets($img): string {
  $img = trim((string)$img);
  if ($img === '') return '';
  if (preg_match('~^https?://~i', $img)) return $img;
  if ($img[0] === '/') return $img;
  return "../assets/img/" . rawurlencode(basename($img));
}

/* ================= POST actions (before render) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID đơn hàng.']);

  if ($action === 'cap_nhat') {
    $old = '';
    if ($DH_STATUS) {
      $st = $pdo->prepare("SELECT {$DH_STATUS} FROM donhang WHERE {$DH_ID}=? LIMIT 1");
      $st->execute([$id]);
      $old = (string)($st->fetchColumn() ?? '');
    }

    $newStatus = trim((string)($_POST['trang_thai'] ?? ''));
    $newPay    = trim((string)($_POST['trang_thai_thanh_toan'] ?? ''));
    $newNote   = trim((string)($_POST['ghi_chu'] ?? ''));

    $set = [];
    $bind = [':id'=>$id];

    if ($DH_STATUS && $newStatus!=='') { $set[] = "{$DH_STATUS}=:st"; $bind[':st']=$newStatus; }
    if ($DH_PAYST && $newPay!=='')    { $set[] = "{$DH_PAYST}=:pay"; $bind[':pay']=$newPay; }
    if ($DH_NOTE)                      { $set[] = "{$DH_NOTE}=:nt"; $bind[':nt']=$newNote; }
    if ($DH_UPD)                       { $set[] = "{$DH_UPD}=NOW()"; }

    if (!$set) redirectWith(['type'=>'error','msg'=>'Không có cột để cập nhật.']);

    $pdo->prepare("UPDATE donhang SET ".implode(', ',$set)." WHERE {$DH_ID}=:id")->execute($bind);

    nhatky_log(
      $pdo,
      'CAP_NHAT_TRANG_THAI_DON',
      "Đổi trạng thái đơn #{$id}: {$old} → {$newStatus}".($newNote!=='' ? " | Ghi chú: {$newNote}" : ''),
      'donhang',
      $id,
      ['from'=>$old,'to'=>$newStatus,'note'=>$newNote,'pay'=>$newPay]
    );

    $wasDone = is_done_status($old);
    $isDone  = is_done_status($newStatus);
    $deducted = order_deducted($pdo, $id, $DH_DATRU, $DH_ID);

    $colMap = [
      'CT_OD'=>$CT_OD, 'CT_PID'=>$CT_PID, 'CT_QTY'=>$CT_QTY, 'CT_NAME'=>$CT_NAME, 'CT_SIZE'=>$CT_SIZE,
      'spOk'=>$spOk, 'SP_ID'=>$SP_ID, 'SP_STOCK'=>$SP_STOCK,
      'tkOk'=>$tkOk,
    ];

    if ($isDone && !$deducted) {
      $pdo->beginTransaction();
      try {
        $res = apply_stock_for_order($pdo, $id, -1, $colMap);
        set_order_deducted($pdo, $id, true, $DH_DATRU, $DH_ID);

        nhatky_log($pdo,'TRU_TON_DON',"Trừ tồn kho theo đơn #{$id}",'donhang',$id,['direction'=>'-','items'=>$res['items']]);
        $pdo->commit();
      } catch(Exception $e) {
        $pdo->rollBack();
        redirectWith(['type'=>'error','msg'=>'Trừ tồn thất bại: '.$e->getMessage()], "donhang.php?id={$id}");
      }
    }

    if (!$isDone && $deducted) {
      $pdo->beginTransaction();
      try {
        $res = apply_stock_for_order($pdo, $id, +1, $colMap);
        set_order_deducted($pdo, $id, false, $DH_DATRU, $DH_ID);

        nhatky_log($pdo,'HOAN_TON_DON',"Hoàn tồn kho do đổi trạng thái đơn #{$id}",'donhang',$id,['direction'=>'+','items'=>$res['items']]);
        $pdo->commit();
      } catch(Exception $e) {
        $pdo->rollBack();
        redirectWith(['type'=>'error','msg'=>'Hoàn tồn thất bại: '.$e->getMessage()], "donhang.php?id={$id}");
      }
    }

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật đơn hàng.'], "donhang.php?id={$id}");
  }

  if ($action === 'xoa') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không được xoá đơn hàng.'], "donhang.php?id={$id}");

    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM chitiet_donhang WHERE {$CT_OD}=?")->execute([$id]);
      $pdo->prepare("DELETE FROM donhang WHERE {$DH_ID}=?")->execute([$id]);

      nhatky_log($pdo,'XOA_DON_HANG',"Xoá đơn #{$id}",'donhang',$id,null);
      $pdo->commit();
    } catch(Exception $e) {
      $pdo->rollBack();
      redirectWith(['type'=>'error','msg'=>'Xoá thất bại: '.$e->getMessage()], "donhang.php?id={$id}");
    }
    redirectWith(['type'=>'ok','msg'=>'Đã xoá đơn hàng.'], "donhang.php");
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.'], "donhang.php?id={$id}");
}

/* ================= list + detail data ================= */
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page-1)*$limit;

$where = [];
$bind = [];
if ($q !== '') {
  $where[] = "(".
    ($DH_CODE ? "{$DH_CODE} LIKE :q" : "1=0")
    ." OR ".($DH_NAME ? "{$DH_NAME} LIKE :q" : "1=0")
    ." OR ".($DH_PHONE ? "{$DH_PHONE} LIKE :q" : "1=0")
    ." OR CAST({$DH_ID} AS CHAR) LIKE :q
  )";
  $bind[':q'] = "%$q%";
}
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

$st = $pdo->prepare("SELECT COUNT(*) FROM donhang $whereSql");
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPages = max(1,(int)ceil($total/$limit));

$listSql = "SELECT *
            FROM donhang
            $whereSql
            ORDER BY ".($DH_DATE?:$DH_ID)." DESC, {$DH_ID} DESC
            LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($listSql);
$st->execute($bind);
$list = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedId = (int)($_GET['id'] ?? 0);
if ($selectedId<=0 && $list) $selectedId = (int)($list[0][$DH_ID] ?? 0);

$order = null;
$items = [];
if ($selectedId>0) {
  $st = $pdo->prepare("SELECT * FROM donhang WHERE {$DH_ID}=? LIMIT 1");
  $st->execute([$selectedId]);
  $order = $st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($order) {
    if ($spOk && $SP_ID) {
      $sql = "SELECT ct.*,
              ".($SP_IMG ? "sp.$SP_IMG" : "NULL")." AS sp_img,
              ".($SP_NAME ? "sp.$SP_NAME" : "NULL")." AS sp_name
              FROM chitiet_donhang ct
              LEFT JOIN sanpham sp ON sp.$SP_ID = ct.$CT_PID
              WHERE ct.$CT_OD=?
              ORDER BY ct.$CT_PID ASC";
      $st = $pdo->prepare($sql);
      $st->execute([$selectedId]);
      $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $st = $pdo->prepare("SELECT * FROM chitiet_donhang WHERE {$CT_OD}=?");
      $st->execute([$selectedId]);
      $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

$statuses = [];
if ($DH_STATUS) {
  $statuses = $pdo->query("SELECT DISTINCT {$DH_STATUS} FROM donhang ORDER BY {$DH_STATUS} ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
$defaults = ['CHO_DUYET','CHO_XU_LY','DANG_GIAO','HOAN_TAT','HUY'];
foreach ($defaults as $d) if (!in_array($d,$statuses,true)) $statuses[] = $d;

$payStatuses = [];
if ($DH_PAYST) {
  $payStatuses = $pdo->query("SELECT DISTINCT {$DH_PAYST} FROM donhang ORDER BY {$DH_PAYST} ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
$payDefaults = ['Chưa thanh toán','Đã thanh toán','Hoàn tiền'];
foreach ($payDefaults as $d) if (!in_array($d,$payStatuses,true)) $payStatuses[] = $d;

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>

<div class="grid grid-cols-12 gap-6">
  <!-- LEFT: LIST -->
  <div class="col-span-12 lg:col-span-7">
    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between gap-3">
        <div class="font-extrabold">Danh sách đơn hàng</div>
        <form class="flex items-center gap-2" method="get">
          <input name="q" value="<?= h($q) ?>" class="border border-line rounded-xl px-3 py-2 text-sm font-bold w-[280px]"
                 placeholder="Mã đơn / tên nhận / sđt / ID...">
          <button class="px-4 py-2 rounded-xl bg-[var(--primary)] text-white font-extrabold text-sm">Tìm</button>
        </form>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-slate-500">
              <th class="text-left py-3 pr-3">Đơn</th>
              <th class="text-left py-3 pr-3">Khách nhận</th>
              <th class="text-left py-3 pr-3">Tổng</th>
              <th class="text-left py-3 pr-3">Trạng thái</th>
              <th class="text-left py-3 pr-3">Ngày</th>
              <th class="text-right py-3">Xem</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
          <?php foreach($list as $r): ?>
            <?php
              $id = (int)$r[$DH_ID];
              $isSel = ($id === $selectedId);
              $stt = (string)($DH_STATUS ? $r[$DH_STATUS] : '');
              $done = is_done_status($stt);
              $chip = $done ? "bg-green-50 text-green-700" : "bg-slate-100 text-slate-700";
              $code = (string)($DH_CODE ? $r[$DH_CODE] : '#'.$id);
              $name = (string)($DH_NAME ? $r[$DH_NAME] : '');
              $phone= (string)($DH_PHONE ? $r[$DH_PHONE] : '');
              $totalV= (int)($DH_TOTAL ? $r[$DH_TOTAL] : ($DH_SUB ? ($r[$DH_SUB] ?? 0) : 0));
              $date = (string)($DH_DATE ? $r[$DH_DATE] : '');
            ?>
            <tr class="<?= $isSel ? 'bg-slate-50' : '' ?>">
              <td class="py-3 pr-3">
                <div class="font-extrabold"><?= h($code) ?></div>
                <div class="text-xs text-muted font-bold">ID: <?= $id ?></div>
              </td>
              <td class="py-3 pr-3">
                <div class="font-extrabold"><?= h($name) ?></div>
                <div class="text-xs text-muted font-bold"><?= h($phone) ?></div>
              </td>
              <td class="py-3 pr-3 font-extrabold"><?= money_vnd($totalV) ?></td>
              <td class="py-3 pr-3">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $chip ?>"><?= h($stt) ?></span>
              </td>
              <td class="py-3 pr-3 text-xs text-muted font-bold"><?= h($date) ?></td>
              <td class="py-3 text-right">
                <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold hover:bg-slate-50"
                   href="donhang.php?id=<?= $id ?>&q=<?= urlencode($q) ?>&page=<?= $page ?>">Chi tiết</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$list): ?>
            <tr><td colspan="6" class="py-8 text-center text-muted font-bold">Chưa có đơn hàng.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex items-center justify-between">
        <div class="text-sm text-muted font-bold">Tổng: <?= number_format($total) ?> đơn</div>
        <div class="flex items-center gap-2">
          <?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); ?>
          <a class="px-3 py-2 rounded-xl border border-line text-sm font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>"
             href="?q=<?= urlencode($q) ?>&page=<?= $prev ?>">Trước</a>
          <div class="px-3 py-2 text-sm font-extrabold">Trang <?= $page ?>/<?= $totalPages ?></div>
          <a class="px-3 py-2 rounded-xl border border-line text-sm font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>"
             href="?q=<?= urlencode($q) ?>&page=<?= $next ?>">Sau</a>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: DETAIL + EDIT -->
  <div class="col-span-12 lg:col-span-5">
    <div class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between">
        <div class="font-extrabold">Chi tiết đơn</div>
        <button class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold hover:bg-slate-50"
                onclick="history.back()">Quay lại</button>
      </div>

      <?php if(!$order): ?>
        <div class="mt-4 text-sm text-muted font-bold">Chọn 1 đơn ở danh sách để xem.</div>
      <?php else: ?>
        <?php
          $oid = (int)$order[$DH_ID];
          $code = (string)($DH_CODE ? $order[$DH_CODE] : '#'.$oid);
          $stt  = (string)($DH_STATUS ? $order[$DH_STATUS] : '');
          $pay  = (string)($DH_PAYST ? $order[$DH_PAYST] : '');
          $totalV= (int)($DH_TOTAL ? $order[$DH_TOTAL] : ($DH_SUB ? ($order[$DH_SUB] ?? 0) : 0));
          $disc = (int)($DH_DISC ? $order[$DH_DISC] : 0);
          $sub  = (int)($DH_SUB ? $order[$DH_SUB] : 0);
          $name = (string)($DH_NAME ? $order[$DH_NAME] : '');
          $phone= (string)($DH_PHONE ? $order[$DH_PHONE] : '');
          $addr = (string)($DH_ADDR ? $order[$DH_ADDR] : '');
          $note = (string)($DH_NOTE ? $order[$DH_NOTE] : '');
          $date = (string)($DH_DATE ? $order[$DH_DATE] : '');
          $ded  = order_deducted($pdo, $oid, $DH_DATRU, $DH_ID);
        ?>

        <div class="mt-4 space-y-2">
          <div class="text-sm font-extrabold"><?= h($code) ?></div>
          <div class="text-xs text-muted font-bold">Ngày đặt: <?= h($date) ?></div>
          <div class="text-xs text-muted font-bold">Trừ tồn: <b><?= $ded ? 'Đã trừ' : 'Chưa trừ' ?></b></div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div class="p-3 rounded-2xl border border-line">
            <div class="text-xs text-muted font-bold">Tạm tính</div>
            <div class="font-extrabold"><?= money_vnd($sub) ?></div>
          </div>
          <div class="p-3 rounded-2xl border border-line">
            <div class="text-xs text-muted font-bold">Giảm</div>
            <div class="font-extrabold"><?= money_vnd($disc) ?></div>
          </div>
          <div class="p-3 rounded-2xl border border-line col-span-2">
            <div class="text-xs text-muted font-bold">Thanh toán</div>
            <div class="text-lg font-extrabold"><?= money_vnd($totalV) ?></div>
          </div>
        </div>

        <div class="mt-4 p-4 rounded-2xl border border-line">
          <div class="font-extrabold mb-2">Thông tin nhận</div>
          <div class="text-sm text-muted font-bold"><?= h($name) ?> • <?= h($phone) ?></div>
          <div class="text-sm text-muted font-bold mt-1"><?= h($addr) ?></div>
        </div>

        <div class="mt-4">
          <div class="font-extrabold mb-2">Sản phẩm</div>
          <div class="space-y-3">
            <?php foreach($items as $it): ?>
              <?php
                $pid = (int)($it[$CT_PID] ?? 0);
                $qty = (int)($it[$CT_QTY] ?? 0);
                $nameIt = (string)($it['sp_name'] ?? ($CT_NAME ? ($it[$CT_NAME] ?? '') : ''));
                $sizeIt = (string)($CT_SIZE ? ($it[$CT_SIZE] ?? '') : '');
                $img = (string)($it['sp_img'] ?? '');
                $imgSrc = $img ? img_src_assets($img) : "";
                $price = (int)($CT_PRICE ? ($it[$CT_PRICE] ?? 0) : 0);
                $line  = (int)($CT_TOTAL ? ($it[$CT_TOTAL] ?? ($price*$qty)) : ($price*$qty));
              ?>
              <div class="flex items-center gap-3 p-3 rounded-2xl border border-line">
                <div class="size-12 rounded-xl bg-slate-100 overflow-hidden grid place-items-center">
                  <?php if($imgSrc): ?>
                    <img src="<?= h($imgSrc) ?>" class="w-full h-full object-cover" alt="">
                  <?php else: ?>
                    <span class="material-symbols-outlined text-slate-400">image</span>
                  <?php endif; ?>
                </div>
                <div class="flex-1">
                  <div class="font-extrabold"><?= h($nameIt) ?></div>
                  <div class="text-xs text-muted font-bold">
                    ID: <?= $pid ?><?= $sizeIt!=='' ? ' • Size: '.h($sizeIt) : '' ?> • SL: <?= $qty ?>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-xs text-muted font-bold"><?= money_vnd($price) ?></div>
                  <div class="font-extrabold"><?= money_vnd($line) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if(!$items): ?>
              <div class="text-sm text-muted font-bold">Chưa có chi tiết đơn.</div>
            <?php endif; ?>
          </div>
        </div>

        <form class="mt-5 space-y-3" method="post">
          <input type="hidden" name="action" value="cap_nhat">
          <input type="hidden" name="id" value="<?= (int)$oid ?>">

          <div>
            <label class="text-xs text-muted font-bold">Trạng thái</label>
            <select name="trang_thai" class="mt-1 w-full border border-line rounded-xl px-3 py-2 text-sm font-extrabold">
              <?php foreach($statuses as $s): ?>
                <option value="<?= h($s) ?>" <?= $s===$stt?'selected':'' ?>><?= h($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if($DH_PAYST): ?>
          <div>
            <label class="text-xs text-muted font-bold">Thanh toán</label>
            <select name="trang_thai_thanh_toan" class="mt-1 w-full border border-line rounded-xl px-3 py-2 text-sm font-extrabold">
              <option value="">—</option>
              <?php foreach($payStatuses as $s): ?>
                <option value="<?= h($s) ?>" <?= $s===$pay?'selected':'' ?>><?= h($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div>
            <label class="text-xs text-muted font-bold">Ghi chú</label>
            <textarea name="ghi_chu" rows="3" class="mt-1 w-full border border-line rounded-xl px-3 py-2 text-sm font-bold"><?= h($note) ?></textarea>
          </div>

          <button class="w-full px-4 py-3 rounded-xl bg-[var(--primary)] text-white font-extrabold">Cập nhật</button>
        </form>

        <?php if($isAdmin): ?>
        <form class="mt-3" method="post" onsubmit="return confirm('Xoá đơn này?');">
          <input type="hidden" name="action" value="xoa">
          <input type="hidden" name="id" value="<?= (int)$oid ?>">
          <button class="w-full px-4 py-3 rounded-xl border border-red-200 bg-red-50 text-red-700 font-extrabold">Xoá đơn</button>
        </form>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
