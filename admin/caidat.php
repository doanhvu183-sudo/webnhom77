<?php
// admin/caidat.php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

require_login_admin();
if (function_exists('requirePermission')) requirePermission('caidat', $pdo);

$ACTIVE = 'caidat';
$PAGE_TITLE = 'Cài đặt';

function qn(string $s): string { return '`'.str_replace('`','',$s).'`'; }

// Ensure settings table exists?
$hasSetting = tableExists($pdo,'cai_dat');
$cols = $hasSetting ? getCols($pdo,'cai_dat') : [];
$K = $hasSetting ? pickCol($cols, ['khoa','key','ten']) : null;
$V = $hasSetting ? pickCol($cols, ['gia_tri','value','noi_dung']) : null;
$UPD = $hasSetting ? pickCol($cols, ['ngay_cap_nhat','updated_at']) : null;

if (!function_exists('get_setting')) {
  function get_setting(PDO $pdo, string $key, $default=null) {
    if (!function_exists('tableExists') || !tableExists($pdo,'cai_dat')) return $default;
    $cols = getCols($pdo,'cai_dat');
    $K = pickCol($cols, ['khoa','key','ten']);
    $V = pickCol($cols, ['gia_tri','value','noi_dung']);
    if(!$K || !$V) return $default;
    $st=$pdo->prepare("SELECT $V FROM cai_dat WHERE $K=? LIMIT 1");
    $st->execute([$key]);
    $val=$st->fetchColumn();
    return ($val===false || $val===null || $val==='') ? $default : $val;
  }
}
if (!function_exists('set_setting')) {
  function set_setting(PDO $pdo, string $key, string $value): bool {
    if (!tableExists($pdo,'cai_dat')) return false;
    $cols=getCols($pdo,'cai_dat');
    $K=pickCol($cols,['khoa','key','ten']);
    $V=pickCol($cols,['gia_tri','value','noi_dung']);
    $UPD=pickCol($cols,['ngay_cap_nhat','updated_at']);
    if(!$K || !$V) return false;

    $st=$pdo->prepare("SELECT COUNT(*) FROM cai_dat WHERE $K=?");
    $st->execute([$key]);
    $exists=((int)$st->fetchColumn()>0);

    if ($exists) {
      $sql="UPDATE cai_dat SET $V=?".($UPD?(", $UPD=NOW()"):'')." WHERE $K=?";
      return $pdo->prepare($sql)->execute([$value,$key]);
    }
    $sql="INSERT INTO cai_dat($K,$V".($UPD?(", $UPD"):'').") VALUES(?,?".($UPD?",NOW()":'').")";
    return $pdo->prepare($sql)->execute([$key,$value]);
  }
}

// ===== POST save before output =====
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!$hasSetting || !$K || !$V) {
    flash_set(['type'=>'error','msg'=>'Thiếu bảng cai_dat hoặc thiếu cột khoa/gia_tri.']);
    header("Location: caidat.php"); exit;
  }

  $shopName = trim((string)($_POST['shop_name'] ?? ''));
  $lowStock = (int)($_POST['low_stock_threshold'] ?? 5);

  set_setting($pdo,'shop_name',$shopName !== '' ? $shopName : 'Crocs Admin');
  set_setting($pdo,'low_stock_threshold',(string)max(0,$lowStock));

  if (function_exists('nhatky_log')) nhatky_log($pdo,'CAP_NHAT_CAI_DAT',"Cập nhật cài đặt hệ thống",'cai_dat',null,['shop_name'=>$shopName,'low_stock_threshold'=>$lowStock]);
  flash_set(['type'=>'ok','msg'=>'Đã lưu cài đặt.']);
  header("Location: caidat.php"); exit;
}

// ===== load values =====
$shopName = (string)get_setting($pdo,'shop_name','Crocs Admin');
$lowStock = (int)get_setting($pdo,'low_stock_threshold','5');

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

$f = flash_get();
?>
<div class="p-4 md:p-8">
  <div class="max-w-5xl mx-auto space-y-6">
    <div>
      <div class="text-xl font-extrabold">Cài đặt</div>
      <div class="text-xs text-muted font-bold">Thiết lập thông số hệ thống</div>
    </div>

    <?php if($f): ?>
      <div class="rounded-2xl border border-line bg-white shadow-card p-4">
        <div class="text-sm font-extrabold <?= ($f['type']??'')==='ok'?'text-green-600':'text-red-600' ?>">
          <?= h($f['msg'] ?? '') ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-line shadow-card p-6">
      <?php if(!$hasSetting): ?>
        <div class="font-extrabold text-red-600">Bạn chưa có bảng <b>cai_dat</b>.</div>
        <div class="mt-2 text-sm text-muted font-bold">Tạo bảng theo SQL mẫu bên dưới.</div>
        <pre class="mt-4 text-xs bg-slate-50 border border-line rounded-xl p-4 overflow-auto"><?=
h("CREATE TABLE IF NOT EXISTS cai_dat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  khoa VARCHAR(100) NOT NULL UNIQUE,
  gia_tri TEXT NULL,
  ngay_cap_nhat DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")
?></pre>
      <?php else: ?>
        <form method="post" class="space-y-4">
          <div>
            <div class="text-xs font-extrabold text-slate-600 mb-1">Tên hệ thống</div>
            <input name="shop_name" value="<?= h($shopName) ?>"
                   class="w-full px-4 py-3 rounded-xl border border-line text-sm font-bold">
          </div>

          <div>
            <div class="text-xs font-extrabold text-slate-600 mb-1">Ngưỡng tồn kho thấp</div>
            <input name="low_stock_threshold" type="number" min="0" value="<?= (int)$lowStock ?>"
                   class="w-full px-4 py-3 rounded-xl border border-line text-sm font-bold">
            <div class="text-[11px] text-muted font-bold mt-1">
              Dashboard sẽ tính “sắp hết” dựa trên <b>sanpham.so_luong</b> hoặc <b>tonkho.so_luong</b> nếu có.
            </div>
          </div>

          <button class="w-full px-4 py-3 rounded-xl bg-primary text-white font-extrabold">Lưu cài đặt</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
