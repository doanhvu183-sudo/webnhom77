<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/thanhTren.php';

if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE = 'dashboard';
$title  = 'Tổng quan';

// KPI cơ bản (fallback nếu cột khác)
$dhCols = tableExists($pdo,'donhang') ? getCols($pdo,'donhang') : [];
$DH_TOTAL = pickCol($dhCols, ['tong_tien','total','tong']);
$DH_DATE  = pickCol($dhCols, ['ngay_dat','created_at','ngay_tao']);
$DH_STT   = pickCol($dhCols, ['trang_thai','status']);

$todayRevenue = 0;
$todayOrders  = 0;
if ($dhCols && $DH_TOTAL && $DH_DATE) {
  $todayRevenue = (int)$pdo->query("SELECT IFNULL(SUM($DH_TOTAL),0) FROM donhang WHERE DATE($DH_DATE)=CURDATE()")->fetchColumn();
  $todayOrders  = (int)$pdo->query("SELECT COUNT(*) FROM donhang WHERE DATE($DH_DATE)=CURDATE()")->fetchColumn();
}

$spCols = tableExists($pdo,'sanpham') ? getCols($pdo,'sanpham') : [];
$SP_QTY = pickCol($spCols, ['so_luong','ton_kho','qty']);
$spCount = $spCols ? (int)$pdo->query("SELECT COUNT(*) FROM sanpham")->fetchColumn() : 0;
$lowStock = 0;
if ($spCols && $SP_QTY) {
  $lowStock = (int)$pdo->query("SELECT COUNT(*) FROM sanpham WHERE $SP_QTY <= 5")->fetchColumn();
}

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
  <div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-5">
    <div class="text-xs text-slate-500 font-bold">Doanh thu hôm nay</div>
    <div class="text-2xl font-extrabold mt-2"><?= number_format($todayRevenue,0,',','.') ?>₫</div>
  </div>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-5">
    <div class="text-xs text-slate-500 font-bold">Đơn hôm nay</div>
    <div class="text-2xl font-extrabold mt-2"><?= number_format($todayOrders) ?></div>
  </div>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-5">
    <div class="text-xs text-slate-500 font-bold">Sản phẩm</div>
    <div class="text-2xl font-extrabold mt-2"><?= number_format($spCount) ?></div>
  </div>
  <div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-5">
    <div class="text-xs text-slate-500 font-bold">Sắp hết hàng (≤5)</div>
    <div class="text-2xl font-extrabold mt-2"><?= number_format($lowStock) ?></div>
  </div>
</div>

<div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-6">
  <div class="text-lg font-extrabold">Gợi ý thao tác</div>
  <ul class="mt-3 text-sm text-slate-600 space-y-2">
    <li>- Vào <b>Sản phẩm</b> để thêm/sửa và <b>Ẩn/Hiện</b> (có log + theo dõi giá).</li>
    <li>- Vào <b>Đơn hàng</b> để cập nhật trạng thái (có log thao tác).</li>
    <li>- Vào <b>Tồn kho</b> để chỉnh tồn trực tiếp (có log).</li>
  </ul>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
