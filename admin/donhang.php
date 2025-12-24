<?php
// admin/donhang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// fallback nếu dự án bạn dùng $conn
if (!isset($pdo) && isset($conn) && $conn instanceof PDO) $pdo = $conn;

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
$me = $_SESSION['admin'];
$vaiTro = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
$isAdmin = ($vaiTro === 'ADMIN');
$myId = (int)($me['id_admin'] ?? $me['id'] ?? 0);

/* ================= Helpers (guard, tránh redeclare) ================= */
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
  // fallback đơn giản: ADMIN vào hết; các role còn lại cho vào donhang
  function requirePermission(string $key): void {
    $me = $_SESSION['admin'] ?? [];
    $role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
    if ($role === 'ADMIN') return;
    $key = strtoupper($key);
    $allow = ['DONHANG','TONG_QUAN','NHATKY'];
    if (!in_array($key, $allow, true)) {
      header("Location: tong_quan.php?type=error&msg=" . urlencode("Bạn không có quyền truy cập."));
      exit;
    }
  }
}

/* ================= Nhật ký: nhatky_hoatdong (KHÔNG tạo bảng mới) ================= */
function nhatky_log(PDO $pdo, string $hanh_dong, string $mo_ta, string $bang = null, int $id_ban_ghi = null, $data = null): void {
  if (!tableExists($pdo,'nhatky_hoatdong')) return;

  $cols = getCols($pdo,'nhatky_hoatdong');
  $c_id_admin = pickCol($cols, ['id_admin','admin_id']);
  $c_role     = pickCol($cols, ['vai_tro','role']);
  $c_action   = pickCol($cols, ['hanh_dong','action']);
  $c_desc     = pickCol($cols, ['mo_ta','mo_ta_chi_tiet','description']);
  $c_table    = pickCol($cols, ['bang_lien_quan','bang','table_name']);
  $c_record   = pickCol($cols, ['id_ban_ghi','record_id']);
  $c_json     = pickCol($cols, ['du_lieu_json','json','data_json']);
  $c_ip       = pickCol($cols, ['ip']);
  $c_ua       = pickCol($cols, ['user_agent']);
  $c_time     = pickCol($cols, ['ngay_tao','created_at']);

  $fields=[]; $vals=[]; $bind=[];

  $me = $_SESSION['admin'] ?? [];
  $myId = (int)($me['id_admin'] ?? $me['id'] ?? 0);
  $role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));

  if ($c_id_admin) { $fields[]=$c_id_admin; $vals[]=':aid'; $bind[':aid']=$myId; }
  if ($c_role)     { $fields[]=$c_role;     $vals[]=':role'; $bind[':role']=$role; }
  if ($c_action)   { $fields[]=$c_action;   $vals[]=':ac'; $bind[':ac']=$hanh_dong; }
  if ($c_desc)     { $fields[]=$c_desc;     $vals[]=':ds'; $bind[':ds']=$mo_ta; }
  if ($c_table && $bang!==null)        { $fields[]=$c_table;  $vals[]=':tb'; $bind[':tb']=$bang; }
  if ($c_record && $id_ban_ghi!==null) { $fields[]=$c_record; $vals[]=':rid'; $bind[':rid']=(int)$id_ban_ghi; }
  if ($c_json && $data!==null)         { $fields[]=$c_json;   $vals[]=':js'; $bind[':js']=json_encode($data, JSON_UNESCAPED_UNICODE); }
  if ($c_ip)       { $fields[]=$c_ip;  $vals[]=':ip'; $bind[':ip']=$_SERVER['REMOTE_ADDR'] ?? ''; }
  if ($c_ua)       { $fields[]=$c_ua;  $vals[]=':ua'; $bind[':ua']=$_SERVER['HTTP_USER_AGENT'] ?? ''; }
  if ($c_time)     { $fields[]=$c_time; $vals[]='NOW()'; }

  if (!$fields) return;
  $sql = "INSERT INTO nhatky_hoatdong(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
  $pdo->prepare($sql)->execute($bind);
}

/* ================= Redirect helper (giữ filter) ================= */
function redirectWith(array $params = []): void {
  $keep = ['q','st','page','xem'];
  $qs = [];
  foreach($keep as $k){ if(isset($_GET[$k])) $qs[$k] = $_GET[$k]; }
  if (!isset($params['xem']) && isset($qs['xem'])) unset($qs['xem']);
  $qs = array_merge($qs, $params);

  $url = 'donhang.php';
  if ($qs) $url .= '?' . http_build_query($qs);
  header("Location: $url");
  exit;
}

/* ================= Page meta ================= */
$ACTIVE = 'donhang';
$PAGE_TITLE = 'Đơn hàng';
requirePermission('donhang');

/* ================= Validate DB ================= */
$fatalError = null;
if (!($pdo instanceof PDO)) $fatalError = "Kết nối CSDL không hợp lệ (thiếu \$pdo).";
if (!$fatalError && !tableExists($pdo,'donhang')) $fatalError = "Thiếu bảng <b>donhang</b>.";

/* ================= Map schema donhang ================= */
$DH_ID=$DH_CODE=$DH_TOTAL=$DH_STATUS=$DH_DATE=$DH_UPD=$DH_NOTE=$DH_GRAND=$DH_DISCOUNT=null;
$DH_NAME=$DH_PHONE=$DH_ADDR=$DH_PAY_METHOD=$DH_PAY_STATUS=null;

$CT_OK=false; $CT_IDDH=$CT_IDSP=$CT_NAME=$CT_SIZE=$CT_QTY=$CT_PRICE=$CT_TOTAL=null;
$SP_OK=false; $SP_ID=$SP_IMG=null;

if (!$fatalError) {
  $dhCols = getCols($pdo,'donhang');
  $DH_ID       = pickCol($dhCols, ['id_don_hang','donhang_id','id']);
  $DH_CODE     = pickCol($dhCols, ['ma_don_hang','ma_don','code']);
  $DH_STATUS   = pickCol($dhCols, ['trang_thai','status']);
  $DH_DATE     = pickCol($dhCols, ['ngay_dat','ngay_tao','created_at']);
  $DH_UPD      = pickCol($dhCols, ['ngay_cap_nhat','updated_at']);

  $DH_TOTAL    = pickCol($dhCols, ['tong_tien']);
  $DH_DISCOUNT = pickCol($dhCols, ['tien_giam','giam_gia','discount']);
  $DH_GRAND    = pickCol($dhCols, ['tong_thanh_toan','tong_tien']);

  $DH_NOTE     = pickCol($dhCols, ['ghi_chu','note']);
  $DH_PAY_METHOD = pickCol($dhCols, ['phuong_thuc_thanh_toan','phuong_thuc']);
  $DH_PAY_STATUS = pickCol($dhCols, ['trang_thai_thanh_toan','payment_status']);

  $DH_NAME     = pickCol($dhCols, ['ho_ten_nhan','ten_nguoi_nhan']);
  $DH_PHONE    = pickCol($dhCols, ['so_dien_thoai_nhan','sdt_nhan']);
  $DH_ADDR     = pickCol($dhCols, ['dia_chi_nhan','dia_chi']);

  if(!$DH_ID) $fatalError = "Bảng donhang thiếu cột ID (id_don_hang).";

  // chitiet_donhang
  $CT_OK = tableExists($pdo,'chitiet_donhang');
  if ($CT_OK) {
    $ctCols = getCols($pdo,'chitiet_donhang');
    $CT_IDDH  = pickCol($ctCols, ['id_don_hang']);
    $CT_IDSP  = pickCol($ctCols, ['id_san_pham']);
    $CT_NAME  = pickCol($ctCols, ['ten_san_pham','ten']);
    $CT_SIZE  = pickCol($ctCols, ['size','kich_co']);
    $CT_QTY   = pickCol($ctCols, ['so_luong','qty']);
    $CT_PRICE = pickCol($ctCols, ['don_gia','gia','price']);
    $CT_TOTAL = pickCol($ctCols, ['thanh_tien','total']);
  }

  // sanpham ảnh
  $SP_OK = tableExists($pdo,'sanpham');
  if ($SP_OK) {
    $spCols = getCols($pdo,'sanpham');
    $SP_ID  = pickCol($spCols, ['id_san_pham','id']);
    $SP_IMG = pickCol($spCols, ['hinh_anh','anh','image']);
  }
}

/* ================= POST actions (PHẢI trước render) ================= */
if (!$fatalError && $_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID đơn hàng.']);

  if ($action==='cap_nhat') {
    $newStatus = trim((string)($_POST['trang_thai'] ?? ''));
    $newNote   = trim((string)($_POST['ghi_chu'] ?? ''));

    $old = '';
    if ($DH_STATUS) {
      $st = $pdo->prepare("SELECT {$DH_STATUS} FROM donhang WHERE {$DH_ID}=? LIMIT 1");
      $st->execute([$id]);
      $old = (string)($st->fetchColumn() ?? '');
    }

    $set=[]; $bind=[':id'=>$id];
    if ($DH_STATUS && $newStatus!=='') { $set[]="{$DH_STATUS}=:st"; $bind[':st']=$newStatus; }
    if ($DH_NOTE) { $set[]="{$DH_NOTE}=:nt"; $bind[':nt']=$newNote; }
    if ($DH_UPD)  { $set[]="{$DH_UPD}=NOW()"; }
    if (!$set) redirectWith(['type'=>'error','msg'=>'Không có cột để cập nhật.']);

    $sql="UPDATE donhang SET ".implode(', ',$set)." WHERE {$DH_ID}=:id";
    $pdo->prepare($sql)->execute($bind);

    nhatky_log(
      $pdo,
      'CAP_NHAT_TRANG_THAI_DON',
      "Đổi trạng thái đơn #{$id}: {$old} → {$newStatus}".($newNote!=='' ? " | Ghi chú: {$newNote}" : ''),
      'donhang',
      $id,
      ['from'=>$old, 'to'=>$newStatus, 'ghi_chu'=>$newNote]
    );

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật đơn hàng.','xem'=>$id]);
  }

  if ($action==='xoa') {
    if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không được xoá đơn hàng.']);
    $pdo->prepare("DELETE FROM donhang WHERE {$DH_ID}=?")->execute([$id]);
    nhatky_log($pdo,'XOA_DON_HANG',"Xoá đơn #{$id}",'donhang',$id,['id_don_hang'=>$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã xoá đơn hàng.']);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= Filters / list ================= */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

$q    = trim((string)($_GET['q'] ?? ''));
$stf  = trim((string)($_GET['st'] ?? ''));
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$rows = [];
$total = 0;
$totalPages = 1;
$thumbByOrder = []; // [id_don_hang => filename]
$viewId = (int)($_GET['xem'] ?? 0);

$orderView = null;
$orderItems = [];

if (!$fatalError) {
  $where = " WHERE 1 ";
  $params = [];

  if ($q!=='') {
    $conds = [];
    if ($DH_CODE) { $conds[] = "d.{$DH_CODE} LIKE ?"; $params[] = "%$q%"; }
    $conds[] = "d.{$DH_ID} = ?"; $params[] = (int)$q;
    $where .= " AND (".implode(" OR ",$conds).") ";
  }
  if ($stf!=='') {
    if ($DH_STATUS) { $where .= " AND d.{$DH_STATUS} = ? "; $params[] = $stf; }
  }

  // count
  $stCount = $pdo->prepare("SELECT COUNT(*) FROM donhang d $where");
  $stCount->execute($params);
  $total = (int)$stCount->fetchColumn();
  $totalPages = max(1,(int)ceil($total/$perPage));

  // list
  $fields = [];
  $fields[] = "d.{$DH_ID} AS id";
  if ($DH_CODE) $fields[] = "d.{$DH_CODE} AS ma";
  if ($DH_STATUS) $fields[] = "d.{$DH_STATUS} AS trang_thai";
  if ($DH_DATE)   $fields[] = "d.{$DH_DATE} AS ngay";
  if ($DH_UPD)    $fields[] = "d.{$DH_UPD} AS cap_nhat";
  if ($DH_GRAND)  $fields[] = "d.{$DH_GRAND} AS tong";
  if ($DH_DISCOUNT) $fields[] = "d.{$DH_DISCOUNT} AS giam";
  if ($DH_NAME)   $fields[] = "d.{$DH_NAME} AS ten_nhan";
  if ($DH_PHONE)  $fields[] = "d.{$DH_PHONE} AS sdt_nhan";

  $orderBy = $DH_DATE ? "d.{$DH_DATE}" : "d.{$DH_ID}";
  $sql = "SELECT ".implode(', ',$fields)." FROM donhang d $where ORDER BY $orderBy DESC LIMIT $perPage OFFSET $offset";
  $stList = $pdo->prepare($sql);
  $stList->execute($params);
  $rows = $stList->fetchAll(PDO::FETCH_ASSOC);

  // thumb ảnh cho danh sách (lấy 1 ảnh bất kỳ trong đơn)
  if ($rows && $CT_OK && $CT_IDDH && $CT_IDSP && $SP_OK && $SP_ID && $SP_IMG) {
    $ids = array_map(fn($r)=>(int)$r['id'], $rows);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
      SELECT ct.{$CT_IDDH} AS id_don, MIN(sp.{$SP_IMG}) AS thumb
      FROM chitiet_donhang ct
      JOIN sanpham sp ON sp.{$SP_ID} = ct.{$CT_IDSP}
      WHERE ct.{$CT_IDDH} IN ($in)
      GROUP BY ct.{$CT_IDDH}
    ");
    $st->execute($ids);
    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $x){
      if (!empty($x['thumb'])) $thumbByOrder[(int)$x['id_don']] = (string)$x['thumb'];
    }
  }

  // view
  if ($viewId>0) {
    $st = $pdo->prepare("SELECT * FROM donhang WHERE {$DH_ID}=? LIMIT 1");
    $st->execute([$viewId]);
    $orderView = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($orderView && $CT_OK && $CT_IDDH) {
      $join = "";
      $selImg = "NULL AS sp_img";
      if ($SP_OK && $SP_ID && $SP_IMG && $CT_IDSP) {
        $join = " LEFT JOIN sanpham sp ON sp.{$SP_ID} = ct.{$CT_IDSP} ";
        $selImg = "sp.{$SP_IMG} AS sp_img";
      }
      $st = $pdo->prepare("
        SELECT ct.*, $selImg
        FROM chitiet_donhang ct
        $join
        WHERE ct.{$CT_IDDH} = ?
        ORDER BY 1 DESC
      ");
      $st->execute([$viewId]);
      $orderItems = $st->fetchAll(PDO::FETCH_ASSOC);
    }
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
  return 'donhang.php' . ($keep ? ('?'.http_build_query($keep)) : '');
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
      <div class="text-2xl font-extrabold">Đơn hàng</div>
      <div class="text-sm text-slate-500 font-semibold">Bên trái danh sách • Bên phải cập nhật • Ghi log vào <b>nhatky_hoatdong</b></div>
    </div>
    <div class="text-xs px-3 py-1 rounded-full bg-slate-100 text-slate-600 font-extrabold">
      <?= $isAdmin ? 'ADMIN' : h($vaiTro) ?>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-4 md:p-5">
    <form class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end" method="get">
      <div class="md:col-span-6">
        <label class="text-sm font-extrabold">Tìm kiếm</label>
        <input name="q" value="<?= h($q) ?>" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
               placeholder="Mã đơn hoặc ID..." />
      </div>

      <div class="md:col-span-4">
        <label class="text-sm font-extrabold">Trạng thái</label>
        <select name="st" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
          <option value="">Tất cả</option>
          <?php
            $stList = [];
            if ($DH_STATUS) {
              $stRows = $pdo->query("SELECT DISTINCT {$DH_STATUS} AS st FROM donhang WHERE {$DH_STATUS} IS NOT NULL ORDER BY 1 LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
              foreach($stRows as $x){ if(($x['st']??'')!=='') $stList[]=$x['st']; }
            }
            $stList = array_values(array_unique($stList));
          ?>
          <?php foreach($stList as $s): ?>
            <option value="<?= h($s) ?>" <?= $stf===$s?'selected':'' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2 flex gap-2">
        <button class="flex-1 px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">Lọc</button>
        <a href="donhang.php" class="px-4 py-3 rounded-2xl border border-gray-200 bg-white font-extrabold">Reset</a>
      </div>
    </form>
  </div>

  <!-- Main grid giống sanpham: trái danh sách, phải chỉnh sửa -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT: LIST -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-soft overflow-hidden">
      <div class="p-4 md:p-5 flex items-center justify-between">
        <div class="text-sm font-extrabold">Danh sách đơn</div>
        <div class="text-xs text-slate-500">Tổng: <b><?= number_format($total) ?></b></div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-slate-500">
            <tr>
              <th class="text-left px-4 py-3 font-extrabold">Ảnh</th>
              <th class="text-left px-4 py-3 font-extrabold">Mã đơn</th>
              <th class="text-left px-4 py-3 font-extrabold">Khách</th>
              <th class="text-left px-4 py-3 font-extrabold">Ngày</th>
              <th class="text-left px-4 py-3 font-extrabold">Tổng</th>
              <th class="text-left px-4 py-3 font-extrabold">Trạng thái</th>
              <th class="text-right px-4 py-3 font-extrabold">Thao tác</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100">
          <?php if(!$rows): ?>
            <tr>
              <td colspan="7" class="px-4 py-10 text-center text-slate-500 font-semibold">Không có đơn hàng.</td>
            </tr>
          <?php endif; ?>

          <?php foreach($rows as $r): ?>
            <?php
              $id = (int)$r['id'];
              $isSel = ($viewId === $id);
              $ma = (string)($r['ma'] ?? ("#".$id));
              $ten = (string)($r['ten_nhan'] ?? '-');
              $sdt = (string)($r['sdt_nhan'] ?? '');
              $ngay= (string)($r['ngay'] ?? '');
              $tong= (float)($r['tong'] ?? 0);
              $stt = (string)($r['trang_thai'] ?? '');
              $thumb = $thumbByOrder[$id] ?? '';
              $thumbUrl = $thumb ? "../assets/img/{$thumb}" : "";
            ?>
            <tr class="<?= $isSel?'bg-blue-50/50':'' ?> hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="size-11 rounded-xl bg-gray-100 border border-gray-200 overflow-hidden grid place-items-center">
                  <?php if($thumbUrl): ?>
                    <img src="<?= h($thumbUrl) ?>" class="w-full h-full object-cover" alt="">
                  <?php else: ?>
                    <span class="material-symbols-outlined text-slate-400">photo</span>
                  <?php endif; ?>
                </div>
              </td>

              <td class="px-4 py-3">
                <div class="font-extrabold text-slate-900"><?= h($ma) ?></div>
                <div class="text-xs text-slate-500">#<?= $id ?></div>
              </td>

              <td class="px-4 py-3">
                <div class="font-bold"><?= h($ten) ?></div>
                <div class="text-xs text-slate-500"><?= $sdt? h($sdt) : '' ?></div>
              </td>

              <td class="px-4 py-3">
                <div class="font-bold"><?= h($ngay) ?></div>
              </td>

              <td class="px-4 py-3">
                <span class="inline-flex px-3 py-1 rounded-full bg-blue-50 text-primary font-extrabold">
                  <?= money_vnd($tong) ?>
                </span>
              </td>

              <td class="px-4 py-3">
                <span class="inline-flex px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-extrabold">
                  <?= h($stt ?: '—') ?>
                </span>
              </td>

              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <a href="<?= h(urlWith(['xem'=>$id])) ?>"
                     class="px-3 py-2 rounded-xl border border-gray-200 bg-white font-extrabold hover:bg-gray-50">
                    Chọn
                  </a>
                  <?php if($isAdmin): ?>
                    <form method="post" onsubmit="return confirm('Xoá đơn #<?= $id ?>?');">
                      <input type="hidden" name="action" value="xoa">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="px-3 py-2 rounded-xl bg-red-600 text-white font-extrabold hover:bg-red-700">
                        Xoá
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

    <!-- RIGHT: EDIT/DETAIL -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-soft p-4 md:p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <div class="text-lg font-extrabold"><?= $orderView ? 'Cập nhật đơn hàng' : 'Chọn 1 đơn để xem' ?></div>
          <div class="text-xs text-slate-500 font-semibold">Cập nhật sẽ ghi log vào <b>nhatky_hoatdong</b></div>
        </div>
        <?php if($orderView): ?>
          <a class="text-sm font-extrabold text-primary hover:underline" href="<?= h(urlWith(['xem'=>null])) ?>">Bỏ chọn</a>
        <?php endif; ?>
      </div>

      <?php if(!$orderView): ?>
        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-slate-600 font-semibold">
          Bên trái bấm <b>Chọn</b> để xem chi tiết và cập nhật.
        </div>
      <?php else: ?>
        <?php
          $oid = (int)$orderView[$DH_ID];
          $ocode = $DH_CODE ? (string)($orderView[$DH_CODE] ?? '') : ("#".$oid);
          $ost = $DH_STATUS ? (string)($orderView[$DH_STATUS] ?? '') : '';
          $odate = $DH_DATE ? (string)($orderView[$DH_DATE] ?? '') : '';
          $onote = $DH_NOTE ? (string)($orderView[$DH_NOTE] ?? '') : '';
          $ototal = $DH_GRAND ? (float)($orderView[$DH_GRAND] ?? 0) : 0;
          $ogiam  = $DH_DISCOUNT ? (float)($orderView[$DH_DISCOUNT] ?? 0) : 0;
        ?>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
          <div class="font-extrabold text-slate-900"><?= h($ocode ?: ('#'.$oid)) ?></div>
          <div class="text-xs text-slate-500 font-bold mt-1">Ngày: <?= h($odate) ?></div>

          <div class="mt-3 flex flex-wrap gap-2">
            <span class="px-3 py-1 rounded-full bg-blue-50 text-primary font-extrabold text-xs">
              Tổng: <?= money_vnd($ototal) ?>
            </span>
            <?php if($DH_DISCOUNT): ?>
              <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-extrabold text-xs">
                Giảm: <?= money_vnd($ogiam) ?>
              </span>
            <?php endif; ?>
            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 font-extrabold text-xs">
              Trạng thái: <?= h($ost ?: '—') ?>
            </span>
          </div>
        </div>

        <form method="post" class="mt-4 space-y-3">
          <input type="hidden" name="action" value="cap_nhat">
          <input type="hidden" name="id" value="<?= $oid ?>">

          <?php if($DH_STATUS): ?>
            <div>
              <label class="text-sm font-bold">Trạng thái</label>
              <select name="trang_thai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                <?php
                  // lấy danh sách trạng thái từ DB (đã có ở filter), fallback nếu rỗng
                  $opts = $stList ?: ['CHO_DUYET','CHO_XU_LY','DANG_GIAO','HOAN_TAT','HUY'];
                  $cur = (string)$ost;
                  foreach($opts as $s){
                    $sel = ($s===$cur) ? 'selected' : '';
                    echo "<option value='".h($s)."' $sel>".h($s)."</option>";
                  }
                  if($cur && !in_array($cur,$opts,true)){
                    echo "<option value='".h($cur)."' selected>".h($cur)."</option>";
                  }
                ?>
              </select>
            </div>
          <?php endif; ?>

          <?php if($DH_NOTE): ?>
            <div>
              <label class="text-sm font-bold">Ghi chú</label>
              <textarea name="ghi_chu" rows="3" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"><?= h($onote) ?></textarea>
            </div>
          <?php endif; ?>

          <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
            Lưu cập nhật (có nhật ký)
          </button>

          <div class="text-[11px] text-slate-500 font-semibold">
            Log sẽ lưu: <b>id_admin</b>, <b>vai_tro</b>, <b>hanh_dong</b>, <b>mo_ta</b>, <b>bang_lien_quan</b>, <b>id_ban_ghi</b>, <b>json</b>, <b>ip</b>, <b>user_agent</b>, <b>ngay_tao</b>.
          </div>
        </form>

        <!-- Chi tiết sản phẩm + preview hover -->
        <div class="mt-5 grid grid-cols-1 gap-4">
          <div class="rounded-2xl border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-3">
              <div class="text-sm font-extrabold">Chi tiết đơn</div>
              <div class="text-xs text-slate-500 font-bold">Hover dòng để preview ảnh</div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
              <div class="lg:col-span-3 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200 rounded-xl overflow-hidden">
                  <thead class="bg-gray-50 text-slate-500">
                    <tr>
                      <th class="text-left px-3 py-2 font-extrabold">Ảnh</th>
                      <th class="text-left px-3 py-2 font-extrabold">Tên</th>
                      <th class="text-left px-3 py-2 font-extrabold">Size</th>
                      <th class="text-right px-3 py-2 font-extrabold">SL</th>
                      <th class="text-right px-3 py-2 font-extrabold">Giá</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 bg-white">
                    <?php if(!$orderItems): ?>
                      <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500 font-semibold">Chưa có chi tiết (hoặc thiếu bảng chitiet_donhang).</td></tr>
                    <?php endif; ?>

                    <?php foreach($orderItems as $it): ?>
                      <?php
                        $iname = $CT_NAME ? (string)($it[$CT_NAME] ?? '') : '';
                        $isize = $CT_SIZE ? (string)($it[$CT_SIZE] ?? '') : '';
                        $iqty  = $CT_QTY ? (int)($it[$CT_QTY] ?? 0) : 0;
                        $iprice= $CT_PRICE ? (float)($it[$CT_PRICE] ?? 0) : 0;

                        $img   = (string)($it['sp_img'] ?? '');
                        $imgUrl = $img ? "../assets/img/{$img}" : "";
                      ?>
                      <tr class="hover:bg-gray-50 order-item" data-preview="<?= h($imgUrl) ?>">
                        <td class="px-3 py-2">
                          <div class="size-10 rounded-xl bg-gray-100 border border-gray-200 overflow-hidden grid place-items-center">
                            <?php if($imgUrl): ?>
                              <img src="<?= h($imgUrl) ?>" class="w-full h-full object-cover" alt="">
                            <?php else: ?>
                              <span class="material-symbols-outlined text-slate-400">photo</span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td class="px-3 py-2 font-extrabold"><?= h($iname) ?></td>
                        <td class="px-3 py-2"><?= h($isize) ?></td>
                        <td class="px-3 py-2 text-right font-extrabold"><?= number_format($iqty) ?></td>
                        <td class="px-3 py-2 text-right"><?= money_vnd($iprice) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="lg:col-span-2">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                  <div class="text-sm font-extrabold mb-2">Preview ảnh</div>
                  <div class="aspect-square rounded-2xl border border-gray-200 bg-white overflow-hidden grid place-items-center">
                    <div class="text-slate-400 text-sm font-bold" id="previewEmpty">Di chuột lên sản phẩm để xem ảnh lớn.</div>
                    <img id="previewImg" src="" class="hidden w-full h-full object-cover" alt="">
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <script>
          (function(){
            const previewImg = document.getElementById('previewImg');
            const previewEmpty = document.getElementById('previewEmpty');
            const items = document.querySelectorAll('.order-item');

            function setPreview(url){
              if(!url){
                previewImg.classList.add('hidden');
                previewImg.src = '';
                previewEmpty.classList.remove('hidden');
                return;
              }
              previewImg.src = url;
              previewImg.classList.remove('hidden');
              previewEmpty.classList.add('hidden');
            }

            items.forEach(tr=>{
              tr.addEventListener('mouseenter', ()=>{
                const url = tr.getAttribute('data-preview') || '';
                setPreview(url);
              });
            });
          })();
        </script>

      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
