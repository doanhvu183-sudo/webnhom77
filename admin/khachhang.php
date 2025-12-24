<?php
// admin/khachhang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// fallback nếu dự án dùng $conn
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) $pdo = $conn;

/* ================= AUTH ================= */
if (empty($_SESSION['admin']) || !($pdo instanceof PDO)) { header("Location: dang_nhap.php"); exit; }
$me = $_SESSION['admin'];
$vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Helpers (guard) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money_vnd')) {
  function money_vnd($n): string { return number_format((float)($n ?? 0), 0, ',', '.') . ' ₫'; }
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
  function requirePermission(string $k): void { return; }
}
function redirectWith(array $params=[]){
  header("Location: khachhang.php".($params?('?'.http_build_query($params)):''));
  exit;
}

/* ================= Permission ================= */
$ACTIVE = 'khachhang';
$PAGE_TITLE = 'Quản lý Khách hàng';
requirePermission('khachhang');

/* ================= Detect schema ================= */
$fatalError = null;
if (!tableExists($pdo,'nguoidung')) $fatalError = "Thiếu bảng <b>nguoidung</b>.";
if (!$fatalError && !tableExists($pdo,'donhang')) { /* donhang có thể thiếu trong demo */ }

$ND_ID=$ND_NAME=$ND_EMAIL=$ND_PHONE=$ND_ACTIVE=$ND_CREATED=$ND_ROLE=null;
$DH_ID=$DH_UID=$DH_CODE=$DH_TOTAL=$DH_STATUS=$DH_DATE=null;

if (!$fatalError) {
  $ndCols = getCols($pdo,'nguoidung');
  $ND_ID     = pickCol($ndCols, ['id_nguoi_dung','id_user','id']);
  $ND_NAME   = pickCol($ndCols, ['ho_ten','ten','full_name','name','username']);
  $ND_EMAIL  = pickCol($ndCols, ['email']);
  $ND_PHONE  = pickCol($ndCols, ['so_dien_thoai','sdt','phone']);
  $ND_ACTIVE = pickCol($ndCols, ['is_active','trang_thai','active','hien_thi','status']);
  $ND_CREATED= pickCol($ndCols, ['ngay_tao','created_at','ngay_dang_ky']);
  $ND_ROLE   = pickCol($ndCols, ['vai_tro','role','quyen']);

  if(!$ND_ID)   $fatalError = "Bảng nguoidung thiếu cột ID.";
  if(!$ND_NAME) $ND_NAME = $ND_ID; // fallback
}

$donhangOk = (!$fatalError && tableExists($pdo,'donhang'));
if ($donhangOk) {
  $dhCols = getCols($pdo,'donhang');
  $DH_ID     = pickCol($dhCols, ['id_don_hang']);
  $DH_UID    = pickCol($dhCols, ['id_nguoi_dung','id_user']);
  $DH_CODE   = pickCol($dhCols, ['ma_don_hang']);
  $DH_TOTAL  = pickCol($dhCols, ['tong_thanh_toan','tong_tien','tong_cong']);
  $DH_STATUS = pickCol($dhCols, ['trang_thai']);
  $DH_DATE   = pickCol($dhCols, ['ngay_dat','created_at','ngay_tao']);
}

/* ================= Log to nhatky_hoatdong ================= */
function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, ?string $bang=null, ?int $id_ban_ghi=null, array $du_lieu=[]): void {
  $cands = ['nhatky_hoatdong','nhatky_hoat_dong','nhat_ky_hoat_dong','nhat_ky_hoatdong'];
  $logTable = null;
  foreach($cands as $t){ if (tableExists($pdo,$t)) { $logTable = $t; break; } }
  if(!$logTable) return;

  $cols = getCols($pdo,$logTable);
  $C_IDADMIN = pickCol($cols, ['id_admin','actor_id','admin_id']);
  $C_ROLE    = pickCol($cols, ['vai_tro','actor_role','role']);
  $C_ACTION  = pickCol($cols, ['hanh_dong','action']);
  $C_DESC    = pickCol($cols, ['mo_ta','chi_tiet','description']);
  $C_TABLE   = pickCol($cols, ['bang_lien_quan','doi_tuong','object','table_name']);
  $C_ROWID   = pickCol($cols, ['id_ban_ghi','doi_tuong_id','object_id','row_id']);
  $C_JSON    = pickCol($cols, ['du_lieu_json','chi_tiet_json','data_json','json']);
  $C_IP      = pickCol($cols, ['ip']);
  $C_UA      = pickCol($cols, ['user_agent']);
  $C_TIME    = pickCol($cols, ['ngay_tao','created_at']);

  $fields=[]; $vals=[]; $bind=[];
  if($C_IDADMIN){ $fields[]=$C_IDADMIN; $vals[]=':aid'; $bind[':aid']=(int)($_SESSION['admin']['id_admin'] ?? $_SESSION['admin']['id'] ?? 0); }
  if($C_ROLE){    $fields[]=$C_ROLE;    $vals[]=':role';$bind[':role']=strtolower((string)($_SESSION['admin']['vai_tro'] ?? $_SESSION['admin']['role'] ?? 'admin')); }
  if($C_ACTION){  $fields[]=$C_ACTION;  $vals[]=':ac';  $bind[':ac']=$hanh_dong; }
  if($C_DESC){    $fields[]=$C_DESC;    $vals[]=':ds';  $bind[':ds']=$mo_ta; }
  if($C_TABLE && $bang!==null){ $fields[]=$C_TABLE; $vals[]=':tb'; $bind[':tb']=$bang; }
  if($C_ROWID && $id_ban_ghi!==null){ $fields[]=$C_ROWID; $vals[]=':rid'; $bind[':rid']=$id_ban_ghi; }
  if($C_JSON && !empty($du_lieu)){ $fields[]=$C_JSON; $vals[]=':js'; $bind[':js']=json_encode($du_lieu, JSON_UNESCAPED_UNICODE); }
  if($C_IP){ $fields[]=$C_IP; $vals[]=':ip'; $bind[':ip']=$_SERVER['REMOTE_ADDR'] ?? ''; }
  if($C_UA){ $fields[]=$C_UA; $vals[]=':ua'; $bind[':ua']=$_SERVER['HTTP_USER_AGENT'] ?? ''; }
  if($C_TIME){ $fields[]=$C_TIME; $vals[]='NOW()'; }

  if(!$fields) return;
  $sql="INSERT INTO {$logTable}(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
  try { $pdo->prepare($sql)->execute($bind); } catch(Exception $e){ /* no-op */ }
}

/* ================= POST actions (MUST be before render) ================= */
if (!$fatalError && $_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID khách hàng.']);

  if ($action==='cap_nhat_trang_thai') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Chỉ ADMIN được khóa/mở khóa khách hàng.']);
    if (!$ND_ACTIVE) redirectWith(['type'=>'error','msg'=>'Bảng nguoidung thiếu cột trạng thái (is_active/trang_thai).']);

    $new = (int)($_POST['is_active'] ?? 1);

    // old
    $old = null;
    $st = $pdo->prepare("SELECT {$ND_ACTIVE} FROM nguoidung WHERE {$ND_ID}=? LIMIT 1");
    $st->execute([$id]);
    $old = (int)($st->fetchColumn() ?? 0);

    $pdo->prepare("UPDATE nguoidung SET {$ND_ACTIVE}=? WHERE {$ND_ID}=?")->execute([$new,$id]);

    nhatky_log(
      $pdo,
      'CAP_NHAT_TRANG_THAI_KHACH',
      "Đổi trạng thái khách #{$id}: ".($old? 'Hoạt động':'Khóa')." → ".($new? 'Hoạt động':'Khóa'),
      'nguoidung',
      $id,
      ['from'=>$old,'to'=>$new]
    );

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái khách hàng.','xem'=>$id]);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= Query filters ================= */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page-1)*$perPage;

$where = " WHERE 1 ";
$params = [];

// nếu có cột role thì loại ADMIN/NHANVIEN ra khỏi danh sách khách (nếu DB dùng chung)
if ($ND_ROLE) {
  $where .= " AND ( {$ND_ROLE} IS NULL OR UPPER({$ND_ROLE}) NOT IN ('ADMIN','NHANVIEN','STAFF') ) ";
}

if ($q!=='') {
  $conds=[];
  $conds[]="u.{$ND_NAME} LIKE ?"; $params[]="%$q%";
  if ($ND_EMAIL){ $conds[]="u.{$ND_EMAIL} LIKE ?"; $params[]="%$q%"; }
  if ($ND_PHONE){ $conds[]="u.{$ND_PHONE} LIKE ?"; $params[]="%$q%"; }
  $conds[]="u.{$ND_ID}=?"; $params[]=(int)$q;
  $where .= " AND (".implode(" OR ",$conds).") ";
}

/* ================= Summary cards ================= */
$totalCust = 0; $activeCust = 0; $new7 = 0;
$totalOrdersAll = 0; $totalRevenueAll = 0;

if (!$fatalError) {
  // total
  $st = $pdo->prepare("SELECT COUNT(*) FROM nguoidung u $where");
  $st->execute($params);
  $totalCust = (int)$st->fetchColumn();

  // active
  if ($ND_ACTIVE) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM nguoidung u $where AND u.{$ND_ACTIVE}=1");
    $st->execute($params);
    $activeCust = (int)$st->fetchColumn();
  }

  // new 7 days
  if ($ND_CREATED) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM nguoidung u $where AND u.{$ND_CREATED} >= (NOW() - INTERVAL 7 DAY)");
    $st->execute($params);
    $new7 = (int)$st->fetchColumn();
  }

  // orders/revenue overall
  if ($donhangOk && $DH_UID && $DH_TOTAL) {
    // tính theo tất cả đơn, không filter q (để giống dashboard). Nếu muốn filter theo q thì báo mình.
    $st = $pdo->query("SELECT COUNT(*) c, IFNULL(SUM({$DH_TOTAL}),0) s FROM donhang");
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $totalOrdersAll = (int)($r['c'] ?? 0);
    $totalRevenueAll= (float)($r['s'] ?? 0);
  }
}

/* ================= List customers + order stats per customer ================= */
$rows = [];
$totalPages = 1;

if (!$fatalError) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM nguoidung u $where");
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $totalPages = max(1,(int)ceil($total/$perPage));

  // subquery order stats
  $joinStats = "";
  if ($donhangOk && $DH_UID && $DH_TOTAL && $DH_ID) {
    $joinStats = "
      LEFT JOIN (
        SELECT {$DH_UID} AS uid, COUNT(*) AS so_don, IFNULL(SUM({$DH_TOTAL}),0) AS tong_chi
        FROM donhang
        GROUP BY {$DH_UID}
      ) od ON od.uid = u.{$ND_ID}
    ";
  } else {
    $joinStats = "LEFT JOIN (SELECT NULL uid, 0 so_don, 0 tong_chi) od ON 1=0";
  }

  $fields = [
    "u.{$ND_ID} AS id",
    "u.{$ND_NAME} AS ten"
  ];
  $fields[] = $ND_EMAIL ? "u.{$ND_EMAIL} AS email" : "NULL AS email";
  $fields[] = $ND_PHONE ? "u.{$ND_PHONE} AS sdt" : "NULL AS sdt";
  $fields[] = $ND_CREATED ? "u.{$ND_CREATED} AS created_at" : "NULL AS created_at";
  $fields[] = $ND_ACTIVE ? "u.{$ND_ACTIVE} AS is_active" : "1 AS is_active";
  $fields[] = "IFNULL(od.so_don,0) AS so_don";
  $fields[] = "IFNULL(od.tong_chi,0) AS tong_chi";

  $orderBy = $ND_CREATED ? "u.{$ND_CREATED}" : "u.{$ND_ID}";
  $sql = "
    SELECT ".implode(", ",$fields)."
    FROM nguoidung u
    $joinStats
    $where
    ORDER BY $orderBy DESC
    LIMIT $perPage OFFSET $offset
  ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= Detail selected customer ================= */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
$orders = [];

if (!$fatalError && $viewId > 0) {
  $st = $pdo->prepare("SELECT * FROM nguoidung WHERE {$ND_ID}=? LIMIT 1");
  $st->execute([$viewId]);
  $view = $st->fetch(PDO::FETCH_ASSOC);

  if ($view && $donhangOk && $DH_UID && $DH_ID) {
    $fields = ["{$DH_ID} AS id"];
    $fields[] = $DH_CODE ? "{$DH_CODE} AS ma" : "CONCAT('#',{$DH_ID}) AS ma";
    $fields[] = $DH_TOTAL ? "{$DH_TOTAL} AS tong" : "0 AS tong";
    $fields[] = $DH_STATUS ? "{$DH_STATUS} AS trang_thai" : "'' AS trang_thai";
    $fields[] = $DH_DATE ? "{$DH_DATE} AS ngay" : "NULL AS ngay";

    $sql = "SELECT ".implode(", ",$fields)." FROM donhang WHERE {$DH_UID}=? ORDER BY ".($DH_DATE?$DH_DATE:$DH_ID)." DESC LIMIT 20";
    $st = $pdo->prepare($sql);
    $st->execute([$viewId]);
    $orders = $st->fetchAll(PDO::FETCH_ASSOC);
  }
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
  return 'khachhang.php'.($keep?('?'.http_build_query($keep)):'');
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

  <!-- Top cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-5">
      <div class="flex items-start justify-between">
        <div class="size-12 rounded-2xl bg-blue-50 grid place-items-center">
          <span class="material-symbols-outlined text-primary">groups</span>
        </div>
      </div>
      <div class="mt-4 text-sm text-slate-500 font-bold">Tổng khách</div>
      <div class="mt-1 text-2xl font-extrabold"><?= number_format($totalCust) ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-5">
      <div class="flex items-start justify-between">
        <div class="size-12 rounded-2xl bg-green-50 grid place-items-center">
          <span class="material-symbols-outlined text-green-600">verified_user</span>
        </div>
      </div>
      <div class="mt-4 text-sm text-slate-500 font-bold">Hoạt động</div>
      <div class="mt-1 text-2xl font-extrabold"><?= number_format($activeCust) ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-5">
      <div class="flex items-start justify-between">
        <div class="size-12 rounded-2xl bg-amber-50 grid place-items-center">
          <span class="material-symbols-outlined text-amber-600">person_add</span>
        </div>
      </div>
      <div class="mt-4 text-sm text-slate-500 font-bold">Mới 7 ngày</div>
      <div class="mt-1 text-2xl font-extrabold"><?= number_format($new7) ?></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-5">
      <div class="flex items-start justify-between">
        <div class="size-12 rounded-2xl bg-purple-50 grid place-items-center">
          <span class="material-symbols-outlined text-purple-600">receipt_long</span>
        </div>
      </div>
      <div class="mt-4 text-sm text-slate-500 font-bold">Tổng đơn / Doanh thu</div>
      <div class="mt-1 text-lg font-extrabold">
        <?= number_format($totalOrdersAll) ?> • <?= money_vnd($totalRevenueAll) ?>
      </div>
    </div>
  </div>

  <!-- Main grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LIST -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-soft overflow-hidden">
      <div class="p-4 md:p-5 flex items-center justify-between">
        <div>
          <div class="text-sm font-extrabold">Danh sách khách hàng</div>
          <div class="text-xs text-slate-500">Bảng: <b>nguoidung</b></div>
        </div>

        <form method="get" class="hidden md:block relative w-[340px]">
          <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
          <input name="q" value="<?= h($q) ?>"
            class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-xl text-sm w-full focus:ring-2 focus:ring-primary/50"
            placeholder="Tìm tên / email / SĐT..." />
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-slate-500">
            <tr>
              <th class="text-left px-4 py-3 font-extrabold">Khách</th>
              <th class="text-left px-4 py-3 font-extrabold">Liên hệ</th>
              <th class="text-left px-4 py-3 font-extrabold">Đơn / Chi</th>
              <th class="text-left px-4 py-3 font-extrabold">Trạng thái</th>
              <th class="text-right px-4 py-3 font-extrabold">Xem</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
            <?php if(!$rows): ?>
              <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500 font-semibold">Chưa có khách hàng.</td></tr>
            <?php endif; ?>

            <?php foreach($rows as $r): ?>
              <?php
                $id = (int)$r['id'];
                $ten = (string)($r['ten'] ?? '');
                $email = (string)($r['email'] ?? '');
                $sdt = (string)($r['sdt'] ?? '');
                $created = (string)($r['created_at'] ?? '');
                $so_don = (int)($r['so_don'] ?? 0);
                $tong_chi = (float)($r['tong_chi'] ?? 0);
                $active = (int)($r['is_active'] ?? 1);
              ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <div class="font-extrabold text-slate-900 line-clamp-1"><?= h($ten) ?></div>
                  <div class="text-xs text-slate-500">ID: <b><?= $id ?></b></div>
                  <?php if($created): ?>
                    <div class="text-[11px] text-slate-400 font-semibold">Tạo: <?= h($created) ?></div>
                  <?php endif; ?>
                </td>

                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-800"><?= h($email) ?></div>
                  <div class="text-xs text-slate-500"><?= h($sdt) ?></div>
                </td>

                <td class="px-4 py-3">
                  <div class="font-extrabold"><?= number_format($so_don) ?> đơn</div>
                  <div class="text-xs text-slate-500">Chi: <?= money_vnd($tong_chi) ?></div>
                </td>

                <td class="px-4 py-3">
                  <span class="inline-flex px-3 py-1 rounded-full text-xs font-extrabold <?= $active? 'bg-green-50 text-green-700':'bg-slate-100 text-slate-600' ?>">
                    <?= $active? 'Hoạt động':'Đã khóa' ?>
                  </span>
                </td>

                <td class="px-4 py-3 text-right">
                  <a href="<?= h(urlWith(['xem'=>$id])) ?>"
                     class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-blue-50 text-primary font-extrabold hover:opacity-90">
                    Chi tiết <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- pagination -->
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

    <!-- DETAIL -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft overflow-hidden">
      <div class="p-4 md:p-5">
        <div class="text-lg font-extrabold">Chi tiết khách</div>
        <div class="text-xs text-slate-500 font-semibold"><?= $view ? 'Đang xem khách #'.(int)$viewId : 'Chọn 1 khách để xem' ?></div>
      </div>

      <?php if(!$view): ?>
        <div class="px-4 md:px-5 pb-5">
          <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-slate-600 font-semibold">
            Chưa chọn khách hàng.
          </div>
        </div>
      <?php else: ?>
        <?php
          $vName = (string)($view[$ND_NAME] ?? '');
          $vEmail= $ND_EMAIL ? (string)($view[$ND_EMAIL] ?? '') : '';
          $vPhone= $ND_PHONE ? (string)($view[$ND_PHONE] ?? '') : '';
          $vActive= $ND_ACTIVE ? (int)($view[$ND_ACTIVE] ?? 1) : 1;
          $vCreated= $ND_CREATED ? (string)($view[$ND_CREATED] ?? '') : '';
          $sumOrders = 0; $sumSpend = 0;
          if ($donhangOk && $DH_UID && $DH_TOTAL) {
            $st = $pdo->prepare("SELECT COUNT(*) c, IFNULL(SUM({$DH_TOTAL}),0) s FROM donhang WHERE {$DH_UID}=?");
            $st->execute([(int)$viewId]);
            $rr = $st->fetch(PDO::FETCH_ASSOC);
            $sumOrders = (int)($rr['c'] ?? 0);
            $sumSpend  = (float)($rr['s'] ?? 0);
          }
        ?>

        <div class="px-4 md:px-5 pb-5 space-y-4">
          <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-extrabold text-slate-900 text-lg truncate"><?= h($vName) ?></div>
                <div class="text-xs text-slate-500">ID: <b><?= (int)$viewId ?></b></div>
              </div>
              <span class="inline-flex px-3 py-1 rounded-full text-xs font-extrabold <?= $vActive? 'bg-green-50 text-green-700':'bg-slate-100 text-slate-600' ?>">
                <?= $vActive? 'Hoạt động':'Đã khóa' ?>
              </span>
            </div>

            <div class="mt-3 text-sm text-slate-700 font-semibold space-y-1">
              <?php if($vEmail): ?><div>Email: <span class="font-bold"><?= h($vEmail) ?></span></div><?php endif; ?>
              <?php if($vPhone): ?><div>SĐT: <span class="font-bold"><?= h($vPhone) ?></span></div><?php endif; ?>
              <?php if($vCreated): ?><div class="text-xs text-slate-500">Tạo: <?= h($vCreated) ?></div><?php endif; ?>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
              <div class="p-3 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="text-xs text-slate-500 font-bold">Tổng đơn</div>
                <div class="text-lg font-extrabold"><?= number_format($sumOrders) ?></div>
              </div>
              <div class="p-3 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="text-xs text-slate-500 font-bold">Tổng chi</div>
                <div class="text-lg font-extrabold"><?= money_vnd($sumSpend) ?></div>
              </div>
            </div>

            <?php if($isAdmin && $ND_ACTIVE): ?>
              <form method="post" class="mt-4" onsubmit="return confirm('Xác nhận cập nhật trạng thái khách hàng?');">
                <input type="hidden" name="action" value="cap_nhat_trang_thai">
                <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                <input type="hidden" name="is_active" value="<?= $vActive?0:1 ?>">
                <button class="w-full px-4 py-3 rounded-2xl font-extrabold <?= $vActive?'bg-slate-900 text-white hover:opacity-90':'bg-primary text-white hover:opacity-90' ?>">
                  <?= $vActive? 'Khóa khách hàng':'Mở khóa khách hàng' ?>
                </button>
                <div class="mt-2 text-[11px] text-slate-500 font-semibold">
                  Không có chỉnh công nợ ở màn này.
                </div>
              </form>
            <?php endif; ?>
          </div>

          <div class="p-4 rounded-2xl border border-gray-200 bg-white">
            <div class="text-sm font-extrabold mb-3">Đơn hàng gần đây</div>

            <?php if(!$donhangOk || !$orders): ?>
              <div class="text-sm text-slate-500 font-semibold">
                <?= !$donhangOk ? 'Chưa có bảng donhang hoặc thiếu cột liên kết.' : 'Khách chưa có đơn.' ?>
              </div>
            <?php else: ?>
              <div class="space-y-2">
                <?php foreach($orders as $od): ?>
                  <a class="block p-3 rounded-2xl bg-gray-50 border border-gray-200 hover:bg-white transition"
                     href="donhang.php?xem=<?= (int)$od['id'] ?>">
                    <div class="flex items-start justify-between gap-2">
                      <div class="min-w-0">
                        <div class="font-extrabold truncate"><?= h($od['ma'] ?? ('#'.$od['id'])) ?></div>
                        <div class="text-xs text-slate-500 font-semibold"><?= h((string)($od['ngay'] ?? '')) ?></div>
                      </div>
                      <div class="text-right">
                        <div class="font-extrabold"><?= money_vnd($od['tong'] ?? 0) ?></div>
                        <div class="text-xs text-slate-500 font-semibold"><?= h((string)($od['trang_thai'] ?? '')) ?></div>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
