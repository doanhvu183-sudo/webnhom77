<?php
// admin/caidat.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// helpers.php của bạn đang có tableExists/getCols/pickCol/redirectWith... (nếu có)
if (file_exists(__DIR__ . '/includes/helpers.php')) {
  require_once __DIR__ . '/includes/helpers.php';
}

/* ================= SAFE HELPERS (không redeclare) ================= */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('auth_me')) {
  function auth_me(){
    $me = $_SESSION['admin'] ?? [];
    $id = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_nguoi_dung'] ?? 0);
    $role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
    $isAdmin = ($role === 'ADMIN');
    return [$me,$id,$role,$isAdmin];
  }
}
if (!function_exists('requirePermission')) {
  function requirePermission($key){
    // Fallback tối giản: mặc định chỉ ADMIN được vào cài đặt
    $me = $_SESSION['admin'] ?? [];
    $role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'ADMIN')));
    if ($key === 'cai_dat' && $role !== 'ADMIN') {
      http_response_code(403);
      echo "<div style='padding:16px;font-family:Arial'>Bạn không có quyền truy cập mục này.</div>";
      exit;
    }
  }
}

/* ===== fallback tableExists/getCols/pickCol nếu helpers.php không có ===== */
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, $name){
    $st=$pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
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

/* ================= ACTIVITY LOG (nhat_ky_hoat_dong) ================= */
if (!function_exists('admin_log')) {
  function admin_log(PDO $pdo, string $hanhDong, string $moTa, ?string $bang=null, ?int $idBanGhi=null, $data=null) {
    // Ưu tiên bảng nhat_ky_hoat_dong
    $table = null;
    if (tableExists($pdo,'nhat_ky_hoat_dong')) $table = 'nhat_ky_hoat_dong';
    else if (tableExists($pdo,'nhatky_hoatdong')) $table = 'nhatky_hoatdong';
    else return;

    $cols = getCols($pdo,$table);

    $C_IDADMIN   = pickCol($cols, ['id_admin','actor_id','admin_id','user_id']);
    $C_ROLE      = pickCol($cols, ['vai_tro','actor_role','role']);
    $C_ACTION    = pickCol($cols, ['hanh_dong','action','su_kien']);
    $C_DESC      = pickCol($cols, ['mo_ta','chi_tiet','description','noi_dung']);
    $C_TABLE     = pickCol($cols, ['bang_lien_quan','doi_tuong','table_name','bang']);
    $C_ROWID     = pickCol($cols, ['id_ban_ghi','doi_tuong_id','row_id','id_lien_quan']);
    $C_JSON      = pickCol($cols, ['du_lieu_json','chi_tiet_json','data_json','json']);
    $C_IP        = pickCol($cols, ['ip']);
    $C_UA        = pickCol($cols, ['user_agent']);
    $C_CREATED   = pickCol($cols, ['ngay_tao','created_at']);

    $me = $_SESSION['admin'] ?? [];
    $idAdmin = (int)($me['id_admin'] ?? $me['id'] ?? $me['id_nguoi_dung'] ?? 0);
    $role = (string)($me['vai_tro'] ?? $me['role'] ?? 'admin');

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $json = ($data===null || $data==='') ? null : (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));

    $fields = [];
    $vals   = [];
    $bind   = [];

    $add = function($col, $ph, $val) use (&$fields,&$vals,&$bind){
      if(!$col) return;
      $fields[] = $col;
      $vals[]   = $ph;
      if($ph !== 'NOW()') $bind[$ph] = $val;
    };

    $add($C_IDADMIN, ':aid', $idAdmin ?: null);
    $add($C_ROLE,    ':role', $role ?: null);
    $add($C_ACTION,  ':act',  $hanhDong);
    $add($C_DESC,    ':desc', $moTa);
    $add($C_TABLE,   ':tbl',  $bang);
    $add($C_ROWID,   ':rid',  $idBanGhi);
    $add($C_JSON,    ':js',   $json);
    $add($C_IP,      ':ip',   $ip);
    $add($C_UA,      ':ua',   $ua);
    if ($C_CREATED) { $fields[] = $C_CREATED; $vals[] = 'NOW()'; }

    if (!$fields) return;
    $sql = "INSERT INTO {$table} (".implode(',',$fields).") VALUES (".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
  }
}

/* ================= AUTH ================= */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

/* ================= PAGE META ================= */
$ACTIVE = 'cai_dat';
$PAGE_TITLE = 'Cài đặt hệ thống';

/* ================= INCLUDE LAYOUT ================= */
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
require_once __DIR__ . '/includes/thanhTren.php';

requirePermission('cai_dat');

/* ================= SCHEMA ================= */
$fatal = false;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $fatal = true;
}

if (!$fatal && !tableExists($pdo,'cai_dat')) {
  $fatal = true;
  $fatalMsg = "Thiếu bảng <b>cai_dat</b>. Vui lòng tạo bảng hoặc gửi cấu trúc để map.";
}

$cols = (!$fatal) ? getCols($pdo,'cai_dat') : [];

$CD_ID    = !$fatal ? pickCol($cols, ['id','id_cai_dat']) : null;
$CD_KEY   = !$fatal ? pickCol($cols, ['khoa','key','ten']) : null;
$CD_VAL   = !$fatal ? pickCol($cols, ['gia_tri','value','noi_dung']) : null;
$CD_NOTE  = !$fatal ? pickCol($cols, ['mo_ta','ghi_chu','note','mo_ta_ngan']) : null;
$CD_UPD   = !$fatal ? pickCol($cols, ['ngay_cap_nhat','updated_at']) : null;
$CD_CREATED = !$fatal ? pickCol($cols, ['ngay_tao','created_at']) : null;

if (!$fatal && !$CD_KEY) { $fatal = true; $fatalMsg = "Bảng cai_dat thiếu cột <b>khoa/key/ten</b>."; }
if (!$fatal && !$CD_VAL) { $fatal = true; $fatalMsg = "Bảng cai_dat thiếu cột <b>gia_tri/value/noi_dung</b>."; }

/* ================= FILTERS ================= */
$q = trim($_GET['q'] ?? '');
$viewId = (int)($_GET['xem'] ?? 0);

/* ================= POST (MUST be before render) ================= */
if (!$fatal && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Cài đặt chỉ cho ADMIN
  if (!$isAdmin) {
    $_SESSION['_flash'] = ['type'=>'error','msg'=>'Chỉ ADMIN được chỉnh sửa cài đặt.'];
    header("Location: caidat.php");
    exit;
  }

  $key  = trim((string)($_POST['khoa'] ?? $_POST['key'] ?? ''));
  $val  = trim((string)($_POST['gia_tri'] ?? $_POST['value'] ?? ''));
  $note = trim((string)($_POST['mo_ta'] ?? $_POST['ghi_chu'] ?? ''));

  $id = (int)($_POST['id'] ?? 0);

  $go = function(array $flash, array $extra=[]) {
    $qs = $extra ? ('?'.http_build_query($extra)) : '';
    $_SESSION['_flash'] = $flash;
    header("Location: caidat.php".$qs);
    exit;
  };

  if ($action === 'them') {
    if ($key === '') $go(['type'=>'error','msg'=>'Thiếu khóa (key).']);
    // kiểm tra trùng key
    $st = $pdo->prepare("SELECT ".($CD_ID ?: $CD_KEY)." FROM cai_dat WHERE {$CD_KEY}=? LIMIT 1");
    $st->execute([$key]);
    $exists = $st->fetchColumn();

    if ($exists) {
      // Upsert => update theo key
      $set = ["{$CD_VAL}=:v"];
      $bind = [':v'=>$val, ':k'=>$key];

      if ($CD_NOTE) { $set[]="{$CD_NOTE}=:n"; $bind[':n']=$note; }
      if ($CD_UPD)  { $set[]="{$CD_UPD}=NOW()"; }

      $sql="UPDATE cai_dat SET ".implode(', ',$set)." WHERE {$CD_KEY}=:k";
      $pdo->prepare($sql)->execute($bind);

      admin_log($pdo,'CAP_NHAT_CAI_DAT',"Cập nhật cài đặt {$key}",'cai_dat', (int)$exists, ['key'=>$key,'value'=>$val,'note'=>$note]);
      $go(['type'=>'ok','msg'=>"Đã cập nhật: {$key}"], ['q'=>$q, 'xem'=>(int)$exists]);
    } else {
      $fields = [$CD_KEY, $CD_VAL];
      $valsQ  = [':k', ':v'];
      $bind   = [':k'=>$key, ':v'=>$val];

      if ($CD_NOTE) { $fields[]=$CD_NOTE; $valsQ[]=':n'; $bind[':n']=$note; }
      if ($CD_CREATED) { $fields[]=$CD_CREATED; $valsQ[]='NOW()'; }
      if ($CD_UPD)     { $fields[]=$CD_UPD;     $valsQ[]='NOW()'; }

      $sql = "INSERT INTO cai_dat(".implode(',',$fields).") VALUES(".implode(',',$valsQ).")";
      $pdo->prepare($sql)->execute($bind);

      $newId = (int)$pdo->lastInsertId();
      admin_log($pdo,'THEM_CAI_DAT',"Thêm cài đặt {$key}",'cai_dat',$newId, ['key'=>$key,'value'=>$val,'note'=>$note]);
      $go(['type'=>'ok','msg'=>"Đã thêm: {$key}"], ['xem'=>$newId]);
    }
  }

  if ($action === 'sua') {
    if ($id <= 0) $go(['type'=>'error','msg'=>'Thiếu ID.']);
    if ($key === '') $go(['type'=>'error','msg'=>'Thiếu khóa (key).']);

    // lấy cũ để log
    $oldRow = $pdo->prepare("SELECT {$CD_KEY} AS k, {$CD_VAL} AS v".($CD_NOTE?(", {$CD_NOTE} AS n"):"")." FROM cai_dat WHERE ".($CD_ID?:$CD_KEY)."=? LIMIT 1");
    $oldRow->execute([$CD_ID ? $id : $key]);
    $old = $oldRow->fetch(PDO::FETCH_ASSOC) ?: [];

    $set = ["{$CD_KEY}=:k","{$CD_VAL}=:v"];
    $bind = [':k'=>$key, ':v'=>$val, ':id'=>$id];
    if ($CD_NOTE) { $set[]="{$CD_NOTE}=:n"; $bind[':n']=$note; }
    if ($CD_UPD)  { $set[]="{$CD_UPD}=NOW()"; }

    $whereCol = $CD_ID ?: $CD_KEY;
    $sql = "UPDATE cai_dat SET ".implode(', ',$set)." WHERE {$whereCol}=:id";
    $pdo->prepare($sql)->execute($bind);

    admin_log($pdo,'CAP_NHAT_CAI_DAT',"Sửa cài đặt #{$id} ({$key})",'cai_dat',$id, ['from'=>$old,'to'=>['k'=>$key,'v'=>$val,'n'=>$note]]);
    $go(['type'=>'ok','msg'=>'Đã lưu cài đặt.'], ['xem'=>$id]);
  }

  if ($action === 'xoa') {
    if ($id <= 0) $go(['type'=>'error','msg'=>'Thiếu ID.']);
    if (!$CD_ID) $go(['type'=>'error','msg'=>'Bảng cai_dat không có cột ID để xoá an toàn.']);

    // lấy key để log
    $st = $pdo->prepare("SELECT {$CD_KEY} FROM cai_dat WHERE {$CD_ID}=? LIMIT 1");
    $st->execute([$id]);
    $k = (string)($st->fetchColumn() ?? '');

    $pdo->prepare("DELETE FROM cai_dat WHERE {$CD_ID}=?")->execute([$id]);
    admin_log($pdo,'XOA_CAI_DAT',"Xoá cài đặt #{$id} ({$k})",'cai_dat',$id, ['key'=>$k]);
    $go(['type'=>'ok','msg'=>'Đã xoá cài đặt.']);
  }

  $go(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= LIST ================= */
$rows = [];
$total = 0;

if (!$fatal) {
  $where = " WHERE 1 ";
  $params = [];

  if ($q !== '') {
    $where .= " AND ({$CD_KEY} LIKE ? OR {$CD_VAL} LIKE ?".($CD_NOTE ? " OR {$CD_NOTE} LIKE ?" : "").")";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    if ($CD_NOTE) $params[] = "%{$q}%";
  }

  $st = $pdo->prepare("SELECT COUNT(*) FROM cai_dat {$where}");
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  $orderBy = $CD_UPD ? $CD_UPD : ($CD_CREATED ? $CD_CREATED : ($CD_ID ?: $CD_KEY));
  $select = [];
  $select[] = ($CD_ID ? "{$CD_ID} AS id" : "NULL AS id");
  $select[] = "{$CD_KEY} AS k";
  $select[] = "{$CD_VAL} AS v";
  if ($CD_NOTE) $select[] = "{$CD_NOTE} AS n";
  if ($CD_UPD)  $select[] = "{$CD_UPD} AS upd";
  if ($CD_CREATED) $select[] = "{$CD_CREATED} AS crt";

  $sql = "SELECT ".implode(', ',$select)." FROM cai_dat {$where} ORDER BY {$orderBy} DESC LIMIT 200";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= VIEW ================= */
$view = null;
if (!$fatal && $viewId > 0) {
  if ($CD_ID) {
    $st = $pdo->prepare("SELECT * FROM cai_dat WHERE {$CD_ID}=? LIMIT 1");
    $st->execute([$viewId]);
    $view = $st->fetch(PDO::FETCH_ASSOC);
  }
}

/* ================= FLASH ================= */
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between gap-3">
      <div>
        <div class="text-2xl font-extrabold">Cài đặt hệ thống</div>
        <div class="text-sm text-muted font-bold">Quản lý các cấu hình trong bảng <b>cai_dat</b> (hiển thị tối đa 200 dòng).</div>
      </div>
      <div class="flex items-center gap-2">
        <a href="<?= h($_SERVER['HTTP_REFERER'] ?? 'tong_quan.php') ?>"
           class="px-4 py-2 rounded-xl border border-line bg-white font-extrabold hover:bg-slate-50">Quay lại</a>
      </div>
    </div>

    <?php if($flash): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-4">
        <div class="flex items-start gap-2">
          <span class="material-symbols-outlined <?= ($flash['type']??'')==='ok'?'text-green-600':(($flash['type']??'')==='error'?'text-red-600':'text-slate-600') ?>">
            <?= ($flash['type']??'')==='ok'?'check_circle':(($flash['type']??'')==='error'?'error':'info') ?>
          </span>
          <div class="font-bold"><?= h($flash['msg'] ?? '') ?></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if($fatal): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-6">
        <div class="text-sm font-extrabold text-red-600">Không thể tải trang</div>
        <div class="mt-2 text-sm text-muted font-bold"><?= $fatalMsg ?? 'Lỗi cấu hình.' ?></div>
      </div>
    <?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- LIST -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-line shadow-card p-4 md:p-6">
        <div class="flex items-center justify-between gap-3 mb-4">
          <div>
            <div class="text-sm font-extrabold">Danh sách cài đặt</div>
            <div class="text-xs text-muted font-bold">Tổng: <?= number_format($total) ?></div>
          </div>
          <form method="get" class="relative w-full max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-2.5 text-muted text-[20px]">search</span>
            <input name="q" value="<?= h($q) ?>"
                   class="w-full pl-10 pr-3 py-2 rounded-xl bg-slate-50 border border-line focus:ring-2 focus:ring-primary/30"
                   placeholder="Tìm theo key / giá trị / mô tả...">
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-muted">
                <th class="text-left py-3 pr-3">Key</th>
                <th class="text-left py-3 pr-3">Giá trị</th>
                <?php if($CD_NOTE): ?><th class="text-left py-3 pr-3">Mô tả</th><?php endif; ?>
                <th class="text-left py-3 pr-3">Cập nhật</th>
                <th class="text-right py-3">Chọn</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <?php if(!$rows): ?>
                <tr><td colspan="<?= $CD_NOTE?5:4 ?>" class="py-8 text-center text-muted font-bold">Chưa có dữ liệu.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r): ?>
                <?php
                  $rid = (int)($r['id'] ?? 0);
                  $link = 'caidat.php?'.http_build_query(array_filter(['q'=>$q,'xem'=>$rid?:null]));
                ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-3 pr-3 font-extrabold text-slate-900"><?= h($r['k'] ?? '') ?></td>
                  <td class="py-3 pr-3 text-slate-700">
                    <span class="line-clamp-1 break-all"><?= h($r['v'] ?? '') ?></span>
                  </td>
                  <?php if($CD_NOTE): ?>
                    <td class="py-3 pr-3 text-slate-600">
                      <span class="line-clamp-1"><?= h($r['n'] ?? '') ?></span>
                    </td>
                  <?php endif; ?>
                  <td class="py-3 pr-3 text-xs text-muted font-bold"><?= h($r['upd'] ?? $r['crt'] ?? '') ?></td>
                  <td class="py-3 text-right">
                    <?php if($rid>0): ?>
                      <a href="<?= h($link) ?>" class="px-3 py-2 rounded-xl bg-primary/10 text-primary font-extrabold hover:bg-primary/15">Chỉnh</a>
                    <?php else: ?>
                      <span class="text-xs text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-4 text-xs text-muted font-bold">
          Gợi ý key hay dùng: <code>shop_name</code>, <code>low_stock_threshold</code>, <code>currency</code>...
        </div>
      </div>

      <!-- FORM -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-4 md:p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
          <div>
            <div class="text-lg font-extrabold"><?= $view ? 'Sửa cài đặt' : 'Thêm cài đặt' ?></div>
            <div class="text-xs text-muted font-bold"><?= $isAdmin ? 'ADMIN có quyền chỉnh sửa.' : 'Bạn chỉ được xem.' ?></div>
          </div>
          <?php if($view): ?>
            <a href="caidat.php<?= $q!==''?('?'.http_build_query(['q'=>$q])):'' ?>"
               class="text-sm font-extrabold text-primary hover:underline">Bỏ chọn</a>
          <?php endif; ?>
        </div>

        <form method="post" class="space-y-3">
          <input type="hidden" name="action" value="<?= $view ? 'sua' : 'them' ?>">
          <?php if($view && $CD_ID): ?>
            <input type="hidden" name="id" value="<?= (int)$viewId ?>">
          <?php endif; ?>

          <div>
            <label class="text-sm font-bold">Key (khoá)</label>
            <input name="khoa" required
              value="<?= $view ? h($view[$CD_KEY] ?? '') : '' ?>"
              class="mt-1 w-full rounded-xl border border-line bg-slate-50"
              placeholder="VD: shop_name">
          </div>

          <div>
            <label class="text-sm font-bold">Giá trị</label>
            <textarea name="gia_tri" rows="3"
              class="mt-1 w-full rounded-xl border border-line bg-slate-50"
              placeholder="VD: Crocs Admin"><?= $view ? h($view[$CD_VAL] ?? '') : '' ?></textarea>
          </div>

          <?php if($CD_NOTE): ?>
          <div>
            <label class="text-sm font-bold">Mô tả / ghi chú</label>
            <input name="mo_ta"
              value="<?= $view ? h($view[$CD_NOTE] ?? '') : '' ?>"
              class="mt-1 w-full rounded-xl border border-line bg-slate-50"
              placeholder="VD: Tên hiển thị sidebar">
          </div>
          <?php endif; ?>

          <button type="submit"
            class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90
                   <?= $isAdmin?'':'opacity-40 pointer-events-none' ?>">
            <?= $view ? 'Lưu cập nhật' : 'Thêm / Cập nhật theo key' ?>
          </button>

          <?php if(!$isAdmin): ?>
            <div class="text-xs text-muted font-bold">Bạn không có quyền chỉnh sửa cài đặt.</div>
          <?php endif; ?>
        </form>

        <?php if($view && $CD_ID): ?>
          <form method="post" class="mt-3"
                onsubmit="return confirm('Xoá cài đặt này?');">
            <input type="hidden" name="action" value="xoa">
            <input type="hidden" name="id" value="<?= (int)$viewId ?>">
            <button class="w-full px-4 py-3 rounded-2xl bg-red-600 text-white font-extrabold hover:bg-red-700
                           <?= $isAdmin?'':'opacity-40 pointer-events-none' ?>">
              Xoá
            </button>
          </form>
        <?php endif; ?>

        <div class="mt-4 text-xs text-muted font-bold">
          Lưu ý: Trang này **không tạo bảng mới**, chỉ thao tác trên bảng <b>cai_dat</b> và ghi log vào <b>nhat_ky_hoat_dong</b>.
        </div>
      </div>

    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
