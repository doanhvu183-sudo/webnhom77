<?php
// admin/danhmuc.php
ob_start();
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Lỗi kết nối DB: $pdo không tồn tại.');

function call_permission($key, $pdo){
  if (!function_exists('requirePermission')) return;
  try {
    $rf = new ReflectionFunction('requirePermission');
    if ($rf->getNumberOfParameters() >= 2) requirePermission($key, $pdo);
    else requirePermission($key);
  } catch(Throwable $e) { requirePermission($key); }
}
if (function_exists('require_login_admin')) require_login_admin();
call_permission('danhmuc', $pdo); // nếu bạn chưa khai permission này thì đổi sang 'sanpham'

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
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
function redirect_to($params=[]){
  $base = 'danhmuc.php';
  header("Location: ".$base.($params?('?'.http_build_query($params)):'')); exit;
}

$ACTIVE = 'danhmuc';
$PAGE_TITLE = 'Danh mục';

if (!tableExists($pdo,'danhmuc')) {
  // vẫn render UI để bạn nhìn thấy, nhưng báo lỗi rõ
  require_once __DIR__ . '/includes/giaoDienDau.php';
  require_once __DIR__ . '/includes/thanhBen.php';
  require_once __DIR__ . '/includes/thanhTren.php';
  echo "<div class='bg-white rounded-2xl border border-line shadow-card p-6'>
          <div class='font-extrabold text-red-600'>Thiếu bảng danhmuc</div>
          <div class='text-sm text-muted font-bold mt-2'>Bạn cần tạo bảng <b>danhmuc</b> trước.</div>
        </div>";
  require_once __DIR__ . '/includes/giaoDienCuoi.php';
  ob_end_flush();
  exit;
}

$cols = getCols($pdo,'danhmuc');
$DM_ID = pickCol($cols, ['id_danh_muc','id','danhmuc_id']);
$DM_NAME = pickCol($cols, ['ten_danh_muc','ten','name']);
$DM_SLUG = pickCol($cols, ['slug']);
$DM_ACTIVE = pickCol($cols, ['trang_thai','is_active','active','status','hien_thi']);
$DM_CREATED = pickCol($cols, ['ngay_tao','created_at']);
$DM_UPDATED = pickCol($cols, ['ngay_cap_nhat','updated_at']);

if(!$DM_ID || !$DM_NAME) die("Bảng danhmuc thiếu cột id/ten.");

$me = $_SESSION['admin'] ?? $_SESSION['nguoi_dung'] ?? [];
$vaiTro = strtoupper(trim($me['vai_tro'] ?? $me['role'] ?? 'ADMIN'));
$isAdmin = ($vaiTro === 'ADMIN');

function slugify($s){
  $s = trim(mb_strtolower($s,'UTF-8'));
  $s = preg_replace('~[^\pL0-9]+~u', '-', $s);
  $s = trim($s,'-');
  return $s ?: 'danh-muc';
}

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  if (!$isAdmin && in_array($action, ['add','update','delete','toggle'], true)) {
    redirect_to(['type'=>'error','msg'=>'Chỉ ADMIN được thao tác danh mục.']);
  }

  if ($action==='add') {
    $ten = trim($_POST['ten'] ?? '');
    if($ten==='') redirect_to(['type'=>'error','msg'=>'Tên danh mục không được trống.']);

    $fields = [$DM_NAME];
    $vals = [':ten'];
    $bind = [':ten'=>$ten];

    if ($DM_SLUG) { $fields[]=$DM_SLUG; $vals[]=':slug'; $bind[':slug']=slugify($ten); }
    if ($DM_ACTIVE) { $fields[]=$DM_ACTIVE; $vals[]=':st'; $bind[':st']=(int)($_POST['st'] ?? 1); }
    if ($DM_CREATED) { $fields[]=$DM_CREATED; $vals[]='NOW()'; }
    if ($DM_UPDATED) { $fields[]=$DM_UPDATED; $vals[]='NOW()'; }

    $sql="INSERT INTO danhmuc(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    redirect_to(['type'=>'ok','msg'=>'Đã thêm danh mục.']);
  }

  if ($action==='update') {
    $id = (int)($_POST['id'] ?? 0);
    if($id<=0) redirect_to(['type'=>'error','msg'=>'Thiếu ID danh mục.']);
    $ten = trim($_POST['ten'] ?? '');
    if($ten==='') redirect_to(['type'=>'error','msg'=>'Tên danh mục không được trống.']);

    $set = ["$DM_NAME=:ten"];
    $bind = [':ten'=>$ten, ':id'=>$id];

    if ($DM_SLUG) { $set[]="$DM_SLUG=:slug"; $bind[':slug']=slugify($ten); }
    if ($DM_ACTIVE) { $set[]="$DM_ACTIVE=:st"; $bind[':st']=(int)($_POST['st'] ?? 1); }
    if ($DM_UPDATED) $set[]="$DM_UPDATED=NOW()";

    $pdo->prepare("UPDATE danhmuc SET ".implode(', ',$set)." WHERE $DM_ID=:id")->execute($bind);
    redirect_to(['type'=>'ok','msg'=>'Đã cập nhật danh mục.']);
  }

  if ($action==='toggle') {
    if (!$DM_ACTIVE) redirect_to(['type'=>'error','msg'=>'Bảng danhmuc chưa có cột trạng thái để ẩn/hiện.']);
    $id = (int)($_POST['id'] ?? 0);
    if($id<=0) redirect_to(['type'=>'error','msg'=>'Thiếu ID danh mục.']);

    $st = $pdo->prepare("SELECT $DM_ACTIVE FROM danhmuc WHERE $DM_ID=? LIMIT 1");
    $st->execute([$id]);
    $cur = (string)($st->fetchColumn() ?? '1');
    $new = ($cur==='1') ? 0 : 1;

    $pdo->prepare("UPDATE danhmuc SET $DM_ACTIVE=? ".($DM_UPDATED?(", $DM_UPDATED=NOW()"):"")." WHERE $DM_ID=?")->execute([$new,$id]);
    redirect_to(['type'=>'ok','msg'=>'Đã đổi trạng thái danh mục.']);
  }

  if ($action==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    if($id<=0) redirect_to(['type'=>'error','msg'=>'Thiếu ID danh mục.']);

    // chặn nếu còn sản phẩm đang dùng (nếu có bảng sanpham & cột id_danh_muc)
    if (tableExists($pdo,'sanpham')) {
      $spCols = getCols($pdo,'sanpham');
      $SP_CAT_ID = pickCol($spCols, ['id_danh_muc','danh_muc_id','category_id']);
      if ($SP_CAT_ID) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM sanpham WHERE $SP_CAT_ID=?");
        $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) {
          redirect_to(['type'=>'error','msg'=>'Không thể xoá: còn sản phẩm đang thuộc danh mục này.']);
        }
      }
    }

    $pdo->prepare("DELETE FROM danhmuc WHERE $DM_ID=?")->execute([$id]);
    redirect_to(['type'=>'ok','msg'=>'Đã xoá danh mục.']);
  }

  redirect_to(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ===== LIST ===== */
$q = trim((string)($_GET['q'] ?? ''));
$where = "WHERE 1";
$bind = [];
if ($q!=='') { $where.=" AND $DM_NAME LIKE :q"; $bind[':q']="%$q%"; }

$sql = "SELECT * FROM danhmuc $where ORDER BY ".($DM_ACTIVE?$DM_ACTIVE:"$DM_ID")." DESC, $DM_NAME ASC";
$st = $pdo->prepare($sql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>

<div class="bg-white rounded-2xl border border-line shadow-card p-5">
  <div class="flex items-center justify-between gap-3">
    <div>
      <div class="text-lg font-extrabold">Danh mục</div>
      <div class="text-sm text-muted font-bold mt-1">Quản lý thêm/sửa/ẩn/hiện/xóa</div>
    </div>

    <form class="flex items-center gap-2" method="get">
      <input name="q" value="<?= h($q) ?>" class="border border-line rounded-xl px-3 py-2 text-sm font-bold w-[260px]" placeholder="Tìm danh mục...">
      <button class="px-4 py-2 rounded-xl bg-[var(--primary)] text-white font-extrabold text-sm">Tìm</button>
    </form>
  </div>

  <?php if($msg): ?>
    <div class="mt-4 p-4 rounded-2xl border shadow-card bg-white <?= $type==='ok'?'border-green-200':($type==='error'?'border-red-200':'border-line') ?>">
      <div class="text-sm font-extrabold"><?= h($msg) ?></div>
    </div>
  <?php endif; ?>

  <div class="mt-5 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-muted">
          <th class="py-3 pr-3 font-extrabold">ID</th>
          <th class="py-3 pr-3 font-extrabold">Tên</th>
          <?php if($DM_SLUG): ?><th class="py-3 pr-3 font-extrabold">Slug</th><?php endif; ?>
          <?php if($DM_ACTIVE): ?><th class="py-3 pr-3 font-extrabold">Trạng thái</th><?php endif; ?>
          <th class="py-3 pr-0 font-extrabold text-right">Thao tác</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line">
        <?php if(!$rows): ?>
          <tr><td colspan="5" class="py-6 text-muted font-bold">Chưa có danh mục.</td></tr>
        <?php endif; ?>

        <?php foreach($rows as $r):
          $id = (int)$r[$DM_ID];
          $ten = (string)$r[$DM_NAME];
          $stVal = $DM_ACTIVE ? (string)($r[$DM_ACTIVE] ?? '1') : '1';
          $stText = ($DM_ACTIVE && $stVal==='0') ? 'Ẩn' : 'Hiển thị';
        ?>
          <tr>
            <td class="py-3 pr-3 font-extrabold"><?= $id ?></td>
            <td class="py-3 pr-3">
              <div class="font-extrabold"><?= h($ten) ?></div>
            </td>
            <?php if($DM_SLUG): ?>
              <td class="py-3 pr-3 text-xs text-muted font-bold"><?= h($r[$DM_SLUG] ?? '') ?></td>
            <?php endif; ?>
            <?php if($DM_ACTIVE): ?>
              <td class="py-3 pr-3">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $stVal==='0'?'bg-slate-100 text-slate-700':'bg-green-50 text-green-700' ?>">
                  <?= h($stText) ?>
                </span>
              </td>
            <?php endif; ?>
            <td class="py-3 pr-0">
              <div class="flex justify-end gap-2">
                <?php if($isAdmin): ?>
                  <form method="post" class="flex items-center gap-2">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input name="ten" value="<?= h($ten) ?>" class="border border-line rounded-xl px-3 py-2 text-sm font-bold w-[240px]">
                    <?php if($DM_ACTIVE): ?>
                      <select name="st" class="border border-line rounded-xl px-3 py-2 text-sm font-extrabold">
                        <option value="1" <?= $stVal==='1'?'selected':'' ?>>Hiển thị</option>
                        <option value="0" <?= $stVal==='0'?'selected':'' ?>>Ẩn</option>
                      </select>
                    <?php endif; ?>
                    <button class="px-3 py-2 rounded-xl border border-line text-sm font-extrabold hover:bg-slate-50">Lưu</button>
                  </form>

                  <?php if($DM_ACTIVE): ?>
                    <form method="post">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <button class="px-3 py-2 rounded-xl bg-[var(--primary)] text-white text-sm font-extrabold">
                        <?= $stVal==='0'?'Hiện':'Ẩn' ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post" onsubmit="return confirm('Xóa danh mục này?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="px-3 py-2 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-extrabold">Xóa</button>
                  </form>
                <?php else: ?>
                  <span class="text-xs text-muted font-bold">Chỉ ADMIN được sửa</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if($isAdmin): ?>
    <div class="mt-6 p-4 rounded-2xl border border-line">
      <div class="font-extrabold">Thêm danh mục</div>
      <form method="post" class="mt-3 flex flex-col md:flex-row gap-2 items-stretch md:items-center">
        <input type="hidden" name="action" value="add">
        <input name="ten" required class="border border-line rounded-xl px-3 py-2 text-sm font-bold flex-1" placeholder="Tên danh mục...">
        <?php if($DM_ACTIVE): ?>
          <select name="st" class="border border-line rounded-xl px-3 py-2 text-sm font-extrabold w-[160px]">
            <option value="1" selected>Hiển thị</option>
            <option value="0">Ẩn</option>
          </select>
        <?php endif; ?>
        <button class="px-4 py-2 rounded-xl bg-slate-900 text-white font-extrabold">Thêm</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ob_end_flush(); ?>
