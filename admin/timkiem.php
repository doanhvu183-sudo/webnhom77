<?php
// admin/timkiem.php
// Tìm kiếm tổng hợp (sanpham / donhang / khachhang-nguoidung / phieunhap / phieuxuat)
// - Tự dò cột theo schema hiện có (pickCol/getCols/tableExists)
// - Mỗi nhóm có phân trang riêng
// - Không redeclare h()

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

if (function_exists('require_login_admin')) require_login_admin();
if (function_exists('requirePermission')) {
  // nếu bạn có key "timkiem" thì dùng; không có thì vẫn OK (admin thường luôn được)
  try { requirePermission('timkiem', $pdo); } catch (Throwable $e) { /* ignore */ }
}

$ACTIVE = 'timkiem';
$PAGE_TITLE = 'Tìm kiếm';

$incDau  = __DIR__ . '/includes/giaoDienDau.php';
$incBen  = file_exists(__DIR__ . '/includes/thanhBen.php') ? __DIR__ . '/includes/thanhBen.php' : __DIR__ . '/includes/thanhben.php';
$incTren = __DIR__ . '/includes/thanhTren.php';
$incCuoi = __DIR__ . '/includes/giaoDienCuoi.php';

require_once $incDau;
require_once $incBen;
require_once $incTren;

$q = trim((string)($_GET['q'] ?? ''));
$qNorm = mb_strtolower($q, 'UTF-8');

// phân trang riêng từng nhóm
$perPage = 10;
$p_sp = max(1, (int)($_GET['p_sp'] ?? 1));
$p_dh = max(1, (int)($_GET['p_dh'] ?? 1));
$p_kh = max(1, (int)($_GET['p_kh'] ?? 1));
$p_pn = max(1, (int)($_GET['p_pn'] ?? 1));
$p_px = max(1, (int)($_GET['p_px'] ?? 1));

function qn_safe(string $s): string { return '`'.str_replace('`','',$s).'`'; }
function likeParam(string $q): string { return '%'.$q.'%'; }

function buildPagerUrl(array $overrides = []): string {
  $base = $_GET;
  foreach ($overrides as $k=>$v) $base[$k] = $v;
  // giữ q + các page khác
  return 'timkiem.php?'.http_build_query($base);
}

$hasQuery = ($q !== '');

// ======================= SANPHAM =======================
$spRows = []; $spTotal = 0; $spPages = 1;
if (tableExists($pdo,'sanpham') && $hasQuery) {
  $cols = getCols($pdo,'sanpham');
  $ID   = pickCol($cols,['id_san_pham','id']);
  $TEN  = pickCol($cols,['ten_san_pham','ten']);
  $MA   = pickCol($cols,['ma_san_pham','sku','ma']);
  $SLUG = pickCol($cols,['slug']);
  $GIA  = pickCol($cols,['gia_ban','gia']);
  $HINH = pickCol($cols,['hinh_anh','anh']);
  $TON  = pickCol($cols,['so_luong','ton_kho']);
  $HIEU = pickCol($cols,['hieu','thuong_hieu','brand']);
  $CRE  = pickCol($cols,['ngay_tao','created_at']);
  if ($ID) {
    $where = [];
    $bind = [':q'=> likeParam($q)];
    if ($TEN)  $where[] = qn_safe($TEN)." LIKE :q";
    if ($MA)   $where[] = qn_safe($MA)." LIKE :q";
    if ($SLUG) $where[] = qn_safe($SLUG)." LIKE :q";
    if ($HIEU) $where[] = qn_safe($HIEU)." LIKE :q";
    $whereSql = $where ? ('WHERE ('.implode(' OR ',$where).')') : '';
    if ($whereSql) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM sanpham $whereSql");
      $st->execute($bind);
      $spTotal = (int)$st->fetchColumn();
      $spPages = max(1, (int)ceil($spTotal/$perPage));
      $off = ($p_sp-1)*$perPage;

      $sel = [];
      $sel[] = qn_safe($ID)." AS id";
      if ($TEN)  $sel[] = qn_safe($TEN)." AS ten";
      if ($MA)   $sel[] = qn_safe($MA)." AS ma";
      if ($GIA)  $sel[] = qn_safe($GIA)." AS gia";
      if ($TON)  $sel[] = qn_safe($TON)." AS ton";
      if ($HIEU) $sel[] = qn_safe($HIEU)." AS hieu";
      if ($HINH) $sel[] = qn_safe($HINH)." AS hinh";
      if ($CRE)  $sel[] = qn_safe($CRE)." AS created_at";

      $order = $CRE ? qn_safe($CRE)." DESC" : qn_safe($ID)." DESC";
      $sql = "SELECT ".implode(',',$sel)." FROM sanpham $whereSql ORDER BY $order LIMIT $perPage OFFSET $off";
      $st = $pdo->prepare($sql);
      $st->execute($bind);
      $spRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

// ======================= DONHANG =======================
$dhRows = []; $dhTotal = 0; $dhPages = 1;
if (tableExists($pdo,'donhang') && $hasQuery) {
  $cols = getCols($pdo,'donhang');
  $ID   = pickCol($cols,['id_don_hang']);
  $MA   = pickCol($cols,['ma_don_hang','ma']);
  $TT   = pickCol($cols,['tong_thanh_toan','tong_tien']);
  $STT  = pickCol($cols,['trang_thai']);
  $TEN  = pickCol($cols,['ten_nguoi_nhan','ho_ten','ten_khach_hang']);
  $SDT  = pickCol($cols,['sdt','so_dien_thoai','phone']);
  $DC   = pickCol($cols,['dia_chi','dia_chi_giao','address']);
  $CRE  = pickCol($cols,['ngay_dat','ngay_tao','created_at']);
  if ($ID) {
    $where = [];
    $bind = [':q'=> likeParam($q)];
    if ($MA)  $where[] = qn_safe($MA)." LIKE :q";
    if ($TEN) $where[] = qn_safe($TEN)." LIKE :q";
    if ($SDT) $where[] = qn_safe($SDT)." LIKE :q";
    if ($DC)  $where[] = qn_safe($DC)." LIKE :q";
    if ($STT) $where[] = qn_safe($STT)." LIKE :q";

    $whereSql = $where ? ('WHERE ('.implode(' OR ',$where).')') : '';
    if ($whereSql) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM donhang $whereSql");
      $st->execute($bind);
      $dhTotal = (int)$st->fetchColumn();
      $dhPages = max(1, (int)ceil($dhTotal/$perPage));
      $off = ($p_dh-1)*$perPage;

      $sel = [qn_safe($ID)." AS id"];
      if ($MA)  $sel[] = qn_safe($MA)." AS ma";
      if ($TEN) $sel[] = qn_safe($TEN)." AS ten";
      if ($SDT) $sel[] = qn_safe($SDT)." AS sdt";
      if ($TT)  $sel[] = qn_safe($TT)." AS tong";
      if ($STT) $sel[] = qn_safe($STT)." AS trang_thai";
      if ($CRE) $sel[] = qn_safe($CRE)." AS created_at";

      $order = $CRE ? qn_safe($CRE)." DESC" : qn_safe($ID)." DESC";
      $sql = "SELECT ".implode(',',$sel)." FROM donhang $whereSql ORDER BY $order LIMIT $perPage OFFSET $off";
      $st = $pdo->prepare($sql);
      $st->execute($bind);
      $dhRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

// ======================= KHACHHANG / NGUOIDUNG =======================
$khRows = []; $khTotal = 0; $khPages = 1;
$khTable = null;
if (tableExists($pdo,'khachhang')) $khTable = 'khachhang';
elseif (tableExists($pdo,'nguoidung')) $khTable = 'nguoidung';

if ($khTable && $hasQuery) {
  $cols = getCols($pdo,$khTable);
  $ID   = pickCol($cols, ['id_khach_hang','id_nguoi_dung','id']);
  $TEN  = pickCol($cols, ['ho_ten','ten','ten_nguoi_dung','full_name']);
  $EMAIL= pickCol($cols, ['email']);
  $SDT  = pickCol($cols, ['sdt','so_dien_thoai','phone']);
  $USER = pickCol($cols, ['username','ten_dang_nhap','tai_khoan']);
  $DC   = pickCol($cols, ['dia_chi','address']);
  $CRE  = pickCol($cols, ['ngay_tao','created_at']);
  if ($ID) {
    $where = [];
    $bind = [':q'=> likeParam($q)];
    if ($TEN)   $where[] = qn_safe($TEN)." LIKE :q";
    if ($EMAIL) $where[] = qn_safe($EMAIL)." LIKE :q";
    if ($SDT)   $where[] = qn_safe($SDT)." LIKE :q";
    if ($USER)  $where[] = qn_safe($USER)." LIKE :q";
    if ($DC)    $where[] = qn_safe($DC)." LIKE :q";
    $whereSql = $where ? ('WHERE ('.implode(' OR ',$where).')') : '';
    if ($whereSql) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM ".qn_safe($khTable)." $whereSql");
      $st->execute($bind);
      $khTotal = (int)$st->fetchColumn();
      $khPages = max(1, (int)ceil($khTotal/$perPage));
      $off = ($p_kh-1)*$perPage;

      $sel = [qn_safe($ID)." AS id"];
      if ($TEN)   $sel[] = qn_safe($TEN)." AS ten";
      if ($EMAIL) $sel[] = qn_safe($EMAIL)." AS email";
      if ($SDT)   $sel[] = qn_safe($SDT)." AS sdt";
      if ($USER)  $sel[] = qn_safe($USER)." AS username";
      if ($DC)    $sel[] = qn_safe($DC)." AS dia_chi";
      if ($CRE)   $sel[] = qn_safe($CRE)." AS created_at";

      $order = $CRE ? qn_safe($CRE)." DESC" : qn_safe($ID)." DESC";
      $sql = "SELECT ".implode(',',$sel)." FROM ".qn_safe($khTable)." $whereSql ORDER BY $order LIMIT $perPage OFFSET $off";
      $st = $pdo->prepare($sql);
      $st->execute($bind);
      $khRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

// ======================= PHIEUNHAP =======================
$pnRows = []; $pnTotal = 0; $pnPages = 1;
if (tableExists($pdo,'phieunhap') && $hasQuery) {
  $cols = getCols($pdo,'phieunhap');
  $ID   = pickCol($cols,['id_phieu_nhap','id']);
  $MA   = pickCol($cols,['ma_phieu_nhap','ma']);
  $NCC  = pickCol($cols,['ten_ncc','nha_cung_cap','ncc']);
  $TT   = pickCol($cols,['tong_tien','tong_gia_tri']);
  $STT  = pickCol($cols,['trang_thai']);
  $CRE  = pickCol($cols,['ngay_nhap','ngay_tao','created_at']);
  if ($ID) {
    $where = [];
    $bind = [':q'=> likeParam($q)];
    if ($MA)  $where[] = qn_safe($MA)." LIKE :q";
    if ($NCC) $where[] = qn_safe($NCC)." LIKE :q";
    if ($STT) $where[] = qn_safe($STT)." LIKE :q";
    $whereSql = $where ? ('WHERE ('.implode(' OR ',$where).')') : '';
    if ($whereSql) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM phieunhap $whereSql");
      $st->execute($bind);
      $pnTotal = (int)$st->fetchColumn();
      $pnPages = max(1, (int)ceil($pnTotal/$perPage));
      $off = ($p_pn-1)*$perPage;

      $sel = [qn_safe($ID)." AS id"];
      if ($MA)  $sel[] = qn_safe($MA)." AS ma";
      if ($NCC) $sel[] = qn_safe($NCC)." AS ncc";
      if ($TT)  $sel[] = qn_safe($TT)." AS tong";
      if ($STT) $sel[] = qn_safe($STT)." AS trang_thai";
      if ($CRE) $sel[] = qn_safe($CRE)." AS created_at";

      $order = $CRE ? qn_safe($CRE)." DESC" : qn_safe($ID)." DESC";
      $sql = "SELECT ".implode(',',$sel)." FROM phieunhap $whereSql ORDER BY $order LIMIT $perPage OFFSET $off";
      $st = $pdo->prepare($sql);
      $st->execute($bind);
      $pnRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

// ======================= PHIEUXUAT =======================
$pxRows = []; $pxTotal = 0; $pxPages = 1;
if (tableExists($pdo,'phieuxuat') && $hasQuery) {
  $cols = getCols($pdo,'phieuxuat');
  $ID   = pickCol($cols,['id_phieu_xuat','id']);
  $MA   = pickCol($cols,['ma_phieu_xuat','ma']);
  $LYDO = pickCol($cols,['ly_do','ghi_chu','note']);
  $TT   = pickCol($cols,['tong_tien','tong_gia_tri']);
  $STT  = pickCol($cols,['trang_thai']);
  $CRE  = pickCol($cols,['ngay_xuat','ngay_tao','created_at']);
  if ($ID) {
    $where = [];
    $bind = [':q'=> likeParam($q)];
    if ($MA)  $where[] = qn_safe($MA)." LIKE :q";
    if ($LYDO)$where[] = qn_safe($LYDO)." LIKE :q";
    if ($STT) $where[] = qn_safe($STT)." LIKE :q";
    $whereSql = $where ? ('WHERE ('.implode(' OR ',$where).')') : '';
    if ($whereSql) {
      $st = $pdo->prepare("SELECT COUNT(*) FROM phieuxuat $whereSql");
      $st->execute($bind);
      $pxTotal = (int)$st->fetchColumn();
      $pxPages = max(1, (int)ceil($pxTotal/$perPage));
      $off = ($p_px-1)*$perPage;

      $sel = [qn_safe($ID)." AS id"];
      if ($MA)  $sel[] = qn_safe($MA)." AS ma";
      if ($LYDO)$sel[] = qn_safe($LYDO)." AS ly_do";
      if ($TT)  $sel[] = qn_safe($TT)." AS tong";
      if ($STT) $sel[] = qn_safe($STT)." AS trang_thai";
      if ($CRE) $sel[] = qn_safe($CRE)." AS created_at";

      $order = $CRE ? qn_safe($CRE)." DESC" : qn_safe($ID)." DESC";
      $sql = "SELECT ".implode(',',$sel)." FROM phieuxuat $whereSql ORDER BY $order LIMIT $perPage OFFSET $off";
      $st = $pdo->prepare($sql);
      $st->execute($bind);
      $pxRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
  }
}

// ======================= UI =======================
$totalAll = $spTotal + $dhTotal + $khTotal + $pnTotal + $pxTotal;
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="text-xl font-extrabold">Tìm kiếm</div>
        <div class="text-xs text-muted font-bold mt-1">
          Nhập từ khóa để tìm nhanh trên Sản phẩm / Đơn hàng / Khách hàng / Phiếu nhập / Phiếu xuất
        </div>
      </div>
      <div class="hidden md:block text-xs text-muted font-bold">
        <?= $hasQuery ? ("Tổng kết quả: <b>".number_format($totalAll)."</b>") : "Chưa nhập từ khóa" ?>
      </div>
    </div>

    <form method="get" class="bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
        <div class="md:col-span-10">
          <input
            name="q"
            value="<?= h($q) ?>"
            placeholder="VD: mã đơn / tên sản phẩm / email / sđt / mã phiếu..."
            class="w-full px-4 py-3 rounded-xl border border-line bg-white text-sm font-bold outline-none focus:ring-2 focus:ring-primary/20"
            autocomplete="off"
          >
        </div>
        <div class="md:col-span-2">
          <button class="w-full px-4 py-3 rounded-xl bg-primary text-white font-extrabold">Tìm</button>
        </div>
      </div>

      <?php if($hasQuery): ?>
        <div class="mt-3 text-xs text-muted font-bold">
          Từ khóa: <b><?= h($q) ?></b> · Tổng: <b><?= number_format($totalAll) ?></b>
        </div>
      <?php endif; ?>
    </form>

    <?php if(!$hasQuery): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-6">
        <div class="text-sm font-extrabold">Gợi ý</div>
        <div class="text-xs text-muted font-bold mt-1">
          Bạn có thể tìm theo: mã đơn, tên người nhận, SĐT, email, SKU/mã sản phẩm, tên sản phẩm, mã phiếu nhập/xuất...
        </div>
      </div>
    <?php else: ?>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-xs text-muted font-bold">Sản phẩm</div>
        <div class="text-2xl font-extrabold mt-1"><?= number_format($spTotal) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-xs text-muted font-bold">Đơn hàng</div>
        <div class="text-2xl font-extrabold mt-1"><?= number_format($dhTotal) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-xs text-muted font-bold"><?= h($khTable ?: 'Khách hàng') ?></div>
        <div class="text-2xl font-extrabold mt-1"><?= number_format($khTotal) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-xs text-muted font-bold">Phiếu nhập</div>
        <div class="text-2xl font-extrabold mt-1"><?= number_format($pnTotal) ?></div>
      </div>
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-xs text-muted font-bold">Phiếu xuất</div>
        <div class="text-2xl font-extrabold mt-1"><?= number_format($pxTotal) ?></div>
      </div>
    </div>

    <!-- Results -->
    <div class="grid grid-cols-1 gap-6">

      <!-- SANPHAM -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Sản phẩm</div>
          <div class="text-xs text-muted font-bold"><?= number_format($spTotal) ?> kết quả</div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Sản phẩm</th>
                <th class="text-left py-3 pr-3">Mã</th>
                <th class="text-left py-3 pr-3">Hiệu</th>
                <th class="text-right py-3 pr-3">Tồn</th>
                <th class="text-right py-3 pr-3">Giá</th>
                <th class="text-right py-3 pr-0">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($spRows as $r): ?>
                <tr>
                  <td class="py-3 pr-3">
                    <div class="font-extrabold"><?= h($r['ten'] ?? ('#'.$r['id'])) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['created_at'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['ma'] ?? '-') ?></td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['hieu'] ?? '-') ?></td>
                  <td class="py-3 pr-3 text-right font-extrabold"><?= (int)($r['ton'] ?? 0) ?></td>
                  <td class="py-3 pr-3 text-right font-extrabold"><?= isset($r['gia']) ? money_vnd($r['gia']) : '-' ?></td>
                  <td class="py-3 pr-0 text-right">
                    <a href="sanpham.php?id=<?= (int)$r['id'] ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$spRows): ?>
                <tr><td colspan="6" class="py-6 text-center text-slate-500 font-bold">Không có kết quả.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($spPages>1): ?>
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $p_sp ?>/<?= $spPages ?></div>
          <div class="flex items-center gap-2">
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_sp<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_sp'=>max(1,$p_sp-1)])) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_sp>=$spPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_sp'=>min($spPages,$p_sp+1)])) ?>">Sau</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- DONHANG -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Đơn hàng</div>
          <div class="text-xs text-muted font-bold"><?= number_format($dhTotal) ?> kết quả</div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Mã / ID</th>
                <th class="text-left py-3 pr-3">Khách</th>
                <th class="text-left py-3 pr-3">Trạng thái</th>
                <th class="text-right py-3 pr-3">Tổng</th>
                <th class="text-right py-3 pr-0">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($dhRows as $r): ?>
                <tr>
                  <td class="py-3 pr-3">
                    <div class="font-extrabold"><?= h($r['ma'] ?? ('#'.$r['id'])) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['created_at'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3">
                    <div class="font-bold"><?= h($r['ten'] ?? '-') ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['sdt'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                      <?= h($r['trang_thai'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="py-3 pr-3 text-right font-extrabold"><?= isset($r['tong']) ? money_vnd($r['tong']) : '-' ?></td>
                  <td class="py-3 pr-0 text-right">
                    <a href="donhang.php?id=<?= (int)$r['id'] ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$dhRows): ?>
                <tr><td colspan="5" class="py-6 text-center text-slate-500 font-bold">Không có kết quả.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($dhPages>1): ?>
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $p_dh ?>/<?= $dhPages ?></div>
          <div class="flex items-center gap-2">
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_dh<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_dh'=>max(1,$p_dh-1)])) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_dh>=$dhPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_dh'=>min($dhPages,$p_dh+1)])) ?>">Sau</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- KHACHHANG/NGUOIDUNG -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold"><?= h(mb_strtoupper($khTable ?: 'khachhang', 'UTF-8')) ?></div>
          <div class="text-xs text-muted font-bold"><?= number_format($khTotal) ?> kết quả</div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Khách</th>
                <th class="text-left py-3 pr-3">Email</th>
                <th class="text-left py-3 pr-3">SĐT</th>
                <th class="text-left py-3 pr-3">Username</th>
                <th class="text-right py-3 pr-0">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($khRows as $r): ?>
                <tr>
                  <td class="py-3 pr-3">
                    <div class="font-extrabold"><?= h($r['ten'] ?? ('#'.$r['id'])) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['dia_chi'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['email'] ?? '-') ?></td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['sdt'] ?? '-') ?></td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['username'] ?? '-') ?></td>
                  <td class="py-3 pr-0 text-right">
                    <a href="khachhang.php?id=<?= (int)$r['id'] ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$khRows): ?>
                <tr><td colspan="5" class="py-6 text-center text-slate-500 font-bold">Không có kết quả.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($khPages>1): ?>
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $p_kh ?>/<?= $khPages ?></div>
          <div class="flex items-center gap-2">
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_kh<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_kh'=>max(1,$p_kh-1)])) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_kh>=$khPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_kh'=>min($khPages,$p_kh+1)])) ?>">Sau</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- PHIEUNHAP -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Phiếu nhập</div>
          <div class="text-xs text-muted font-bold"><?= number_format($pnTotal) ?> kết quả</div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Mã / ID</th>
                <th class="text-left py-3 pr-3">NCC</th>
                <th class="text-left py-3 pr-3">Trạng thái</th>
                <th class="text-right py-3 pr-3">Tổng</th>
                <th class="text-right py-3 pr-0">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($pnRows as $r): ?>
                <tr>
                  <td class="py-3 pr-3">
                    <div class="font-extrabold"><?= h($r['ma'] ?? ('#'.$r['id'])) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['created_at'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['ncc'] ?? '-') ?></td>
                  <td class="py-3 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                      <?= h($r['trang_thai'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="py-3 pr-3 text-right font-extrabold"><?= isset($r['tong']) ? money_vnd($r['tong']) : '-' ?></td>
                  <td class="py-3 pr-0 text-right">
                    <a href="nhaphang.php?id=<?= (int)$r['id'] ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$pnRows): ?>
                <tr><td colspan="5" class="py-6 text-center text-slate-500 font-bold">Không có kết quả.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($pnPages>1): ?>
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $p_pn ?>/<?= $pnPages ?></div>
          <div class="flex items-center gap-2">
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_pn<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_pn'=>max(1,$p_pn-1)])) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_pn>=$pnPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_pn'=>min($pnPages,$p_pn+1)])) ?>">Sau</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- PHIEUXUAT -->
      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between">
          <div class="text-base font-extrabold">Phiếu xuất</div>
          <div class="text-xs text-muted font-bold"><?= number_format($pxTotal) ?> kết quả</div>
        </div>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Mã / ID</th>
                <th class="text-left py-3 pr-3">Lý do</th>
                <th class="text-left py-3 pr-3">Trạng thái</th>
                <th class="text-right py-3 pr-3">Tổng</th>
                <th class="text-right py-3 pr-0">Mở</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($pxRows as $r): ?>
                <tr>
                  <td class="py-3 pr-3">
                    <div class="font-extrabold"><?= h($r['ma'] ?? ('#'.$r['id'])) ?></div>
                    <div class="text-xs text-muted font-bold"><?= h($r['created_at'] ?? '') ?></div>
                  </td>
                  <td class="py-3 pr-3 font-bold"><?= h($r['ly_do'] ?? '-') ?></td>
                  <td class="py-3 pr-3">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                      <?= h($r['trang_thai'] ?? '-') ?>
                    </span>
                  </td>
                  <td class="py-3 pr-3 text-right font-extrabold"><?= isset($r['tong']) ? money_vnd($r['tong']) : '-' ?></td>
                  <td class="py-3 pr-0 text-right">
                    <a href="xuatkho.php?id=<?= (int)$r['id'] ?>" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$pxRows): ?>
                <tr><td colspan="5" class="py-6 text-center text-slate-500 font-bold">Không có kết quả.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if($pxPages>1): ?>
        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $p_px ?>/<?= $pxPages ?></div>
          <div class="flex items-center gap-2">
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_px<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_px'=>max(1,$p_px-1)])) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $p_px>=$pxPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h(buildPagerUrl(['p_px'=>min($pxPages,$p_px+1)])) ?>">Sau</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <?php endif; // hasQuery ?>

  </div>
</div>

<?php require_once $incCuoi; ?>
