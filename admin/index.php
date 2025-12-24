<?php
require_once __DIR__ . '/_init.php';

$PAGE_TITLE = "Bảng điều khiển";
$ACTIVE = "dashboard";

/* ===== KPI ===== */
$todayRevenue = 0;
$yesterdayRevenue = 0;
$ordersToday = 0;
$processing = 0;
$completed = 0;
$cancelled = 0;

try {
  if ($DH_DATE && $DH_TOTAL) {
    $todayRevenue = (int)$pdo->query("SELECT IFNULL(SUM(`$DH_TOTAL`),0) FROM donhang WHERE DATE(`$DH_DATE`)=CURDATE()")->fetchColumn();
    $yesterdayRevenue = (int)$pdo->query("SELECT IFNULL(SUM(`$DH_TOTAL`),0) FROM donhang WHERE DATE(`$DH_DATE`)=CURDATE() - INTERVAL 1 DAY")->fetchColumn();
  }
  if ($DH_DATE) {
    $ordersToday = (int)$pdo->query("SELECT COUNT(*) FROM donhang WHERE DATE(`$DH_DATE`)=CURDATE()")->fetchColumn();
  }
  if ($DH_STT) {
    // map mềm: chấp nhận cả dạng mã và dạng chữ
    $processing = (int)$pdo->query("
      SELECT COUNT(*) FROM donhang
      WHERE `$DH_STT` IN ('CHO_XU_LY','DANG_GIAO','Chờ xử lý','Đang giao','Chờ duyệt','Đang xử lý')
    ")->fetchColumn();
    $completed = (int)$pdo->query("
      SELECT COUNT(*) FROM donhang
      WHERE `$DH_STT` IN ('HOAN_TAT','DA_GIAO','Hoàn tất','Đã giao','Hoàn thành')
    ")->fetchColumn();
    $cancelled = (int)$pdo->query("
      SELECT COUNT(*) FROM donhang
      WHERE `$DH_STT` IN ('DA_HUY','HUY','Đã hủy','Hủy')
    ")->fetchColumn();
  }
} catch (Throwable $e) {}

$stockTotal = 0;
$outCount = 0;
$lowCount = 0;
$lowThreshold = (int)get_setting($pdo, 'low_stock_threshold', 5);

try {
  if (table_exists($pdo, 'tonkho') && $TK_QTY) {
    $stockTotal = (int)$pdo->query("SELECT IFNULL(SUM(`$TK_QTY`),0) FROM tonkho")->fetchColumn();
    $outCount = (int)$pdo->query("SELECT COUNT(*) FROM tonkho WHERE IFNULL(`$TK_QTY`,0) <= 0")->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM tonkho WHERE IFNULL(`$TK_QTY`,0) > 0 AND IFNULL(`$TK_QTY`,0) <= ?");
    $st->execute([$lowThreshold]);
    $lowCount = (int)$st->fetchColumn();
  }
} catch (Throwable $e) {}

/* ===== recent activity ===== */
$logs = [];
try {
  $logs = $pdo->query("SELECT * FROM nhat_ky_hoat_dong ORDER BY created_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$priceLogs = [];
try {
  $priceLogs = $pdo->query("
    SELECT tdg.*, sp.ten_san_pham
    FROM theo_doi_gia tdg
    LEFT JOIN sanpham sp ON sp.id_san_pham = tdg.id_san_pham
    ORDER BY tdg.created_at DESC
    LIMIT 10
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

/* ===== optional: chart data last 14 days ===== */
$chart = [];
try {
  if ($DH_DATE && $DH_TOTAL) {
    $chart = $pdo->query("
      SELECT DATE(`$DH_DATE`) AS d, IFNULL(SUM(`$DH_TOTAL`),0) AS total
      FROM donhang
      WHERE `$DH_DATE` >= (CURDATE() - INTERVAL 13 DAY)
      GROUP BY DATE(`$DH_DATE`)
      ORDER BY d ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {}

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
?>

<div class="flex items-end justify-between gap-4 flex-wrap">
  <div>
    <h1 class="text-2xl md:text-3xl font-extrabold">Chào, <?= h($ADMIN['ho_ten'] ?? 'Quản trị') ?></h1>
    <p class="text-slate-500 text-sm mt-1">Tổng quan hoạt động hôm nay + nhật ký thao tác + theo dõi giá.</p>
  </div>
  <div class="flex gap-2">
    <a href="nhatky.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Xem nhật ký</a>
    <a href="theodoi_gia.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Xem theo dõi giá</a>
  </div>
</div>

<!-- KPI -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
  <div class="bg-white p-6 rounded-2xl shadow-soft border">
    <div class="flex items-start justify-between">
      <div class="p-3 rounded-xl bg-blue-50 text-primary">
        <span class="material-symbols-outlined">attach_money</span>
      </div>
      <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100">Hôm nay</span>
    </div>
    <div class="mt-3 text-sm text-slate-500 font-bold">Doanh thu</div>
    <div class="text-2xl font-extrabold mt-1"><?= number_format($todayRevenue) ?> ₫</div>
    <div class="text-xs text-slate-500 mt-2">Hôm qua: <b><?= number_format($yesterdayRevenue) ?> ₫</b></div>
  </div>

  <div class="bg-white p-6 rounded-2xl shadow-soft border">
    <div class="flex items-start justify-between">
      <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
        <span class="material-symbols-outlined">shopping_cart</span>
      </div>
      <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100">Today</span>
    </div>
    <div class="mt-3 text-sm text-slate-500 font-bold">Đơn hôm nay</div>
    <div class="text-2xl font-extrabold mt-1"><?= number_format($ordersToday) ?></div>
    <div class="text-xs text-slate-500 mt-2">Đang xử lý: <b><?= number_format($processing) ?></b></div>
  </div>

  <div class="bg-white p-6 rounded-2xl shadow-soft border">
    <div class="flex items-start justify-between">
      <div class="p-3 rounded-xl bg-green-50 text-success">
        <span class="material-symbols-outlined">task_alt</span>
      </div>
      <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100">Tổng</span>
    </div>
    <div class="mt-3 text-sm text-slate-500 font-bold">Hoàn tất / Hủy</div>
    <div class="text-2xl font-extrabold mt-1"><?= number_format($completed) ?> <span class="text-slate-400 text-base">/</span> <?= number_format($cancelled) ?></div>
    <div class="text-xs text-slate-500 mt-2">Theo trạng thái đơn hàng</div>
  </div>

  <div class="bg-white p-6 rounded-2xl shadow-soft border">
    <div class="flex items-start justify-between">
      <div class="p-3 rounded-xl bg-cyan-50 text-cyan-700">
        <span class="material-symbols-outlined">warehouse</span>
      </div>
      <span class="text-xs font-extrabold px-2 py-1 rounded-lg bg-slate-100">Kho</span>
    </div>
    <div class="mt-3 text-sm text-slate-500 font-bold">Tổng tồn</div>
    <div class="text-2xl font-extrabold mt-1"><?= number_format($stockTotal) ?></div>
    <div class="text-xs text-slate-500 mt-2">Hết hàng: <b class="text-danger"><?= number_format($outCount) ?></b> | Low: <b class="text-warning"><?= number_format($lowCount) ?></b></div>
  </div>
</div>

<!-- CHART + SIDE PANELS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-soft border p-6">
    <div class="flex items-center justify-between">
      <div>
        <div class="text-lg font-extrabold">Doanh thu 14 ngày</div>
        <div class="text-sm text-slate-500">Tổng theo ngày (từ bảng donhang)</div>
      </div>
      <a href="baocao.php" class="text-primary font-extrabold text-sm hover:underline">Xem báo cáo</a>
    </div>

    <?php if (empty($chart)): ?>
      <div class="p-6 text-center text-slate-500">Chưa đủ dữ liệu để vẽ biểu đồ.</div>
    <?php else: ?>
      <?php
        $max = 0;
        foreach ($chart as $r) $max = max($max, (int)$r['total']);
        $max = max(1, $max);
      ?>
      <div class="mt-6 space-y-2">
        <?php foreach ($chart as $r): ?>
          <?php $w = (int)round(((int)$r['total'] / $max) * 100); ?>
          <div class="flex items-center gap-3">
            <div class="w-20 text-xs text-slate-500 font-bold"><?= date('d/m', strtotime($r['d'])) ?></div>
            <div class="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden">
              <div class="h-3 bg-primary" style="width: <?= $w ?>%"></div>
            </div>
            <div class="w-28 text-right text-xs font-extrabold"><?= number_format((int)$r['total']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-2xl shadow-soft border p-6">
    <div class="flex items-center justify-between">
      <div class="text-lg font-extrabold">Cảnh báo nhanh</div>
      <a href="tonkho.php?filter=low&low=<?= (int)$lowThreshold ?>" class="text-primary font-extrabold text-sm hover:underline">Kho</a>
    </div>
    <div class="mt-4 space-y-3">
      <div class="p-3 rounded-xl border bg-red-50 border-red-200">
        <div class="font-extrabold text-danger">Hết hàng</div>
        <div class="text-sm text-slate-700">Có <b><?= number_format($outCount) ?></b> mã tồn <= 0</div>
      </div>
      <div class="p-3 rounded-xl border bg-yellow-50 border-yellow-200">
        <div class="font-extrabold text-warning">Low stock</div>
        <div class="text-sm text-slate-700">Có <b><?= number_format($lowCount) ?></b> mã tồn <= <?= (int)$lowThreshold ?></div>
      </div>
      <div class="p-3 rounded-xl border bg-slate-50">
        <div class="font-extrabold text-slate-700">Đơn đang xử lý</div>
        <div class="text-sm text-slate-700"><b><?= number_format($processing) ?></b> đơn</div>
      </div>
    </div>
  </div>
</div>

<!-- LOGS -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white rounded-2xl shadow-soft border p-6">
    <div class="flex items-center justify-between">
      <div class="text-lg font-extrabold">Nhật ký hoạt động (mới nhất)</div>
      <a href="nhatky.php" class="text-primary font-extrabold text-sm hover:underline">Xem tất cả</a>
    </div>

    <?php if (empty($logs)): ?>
      <div class="p-6 text-center text-slate-500">Chưa có nhật ký.</div>
    <?php else: ?>
      <div class="mt-4 space-y-3">
        <?php foreach ($logs as $l): ?>
          <div class="p-3 rounded-xl border hover:bg-slate-50 transition">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <div class="font-extrabold truncate">
                  <?= h($l['actor_name'] ?? 'Unknown') ?> —
                  <span class="text-primary"><?= h($l['hanh_dong']) ?></span>
                </div>
                <div class="text-xs text-slate-500">
                  <?= h($l['doi_tuong'] ?? '-') ?><?= $l['doi_tuong_id'] ? ' #'.(int)$l['doi_tuong_id'] : '' ?>
                  • <?= h($l['actor_role'] ?? '') ?>
                </div>
              </div>
              <div class="text-xs text-slate-500 whitespace-nowrap">
                <?= h(date('d/m H:i', strtotime($l['created_at']))) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-2xl shadow-soft border p-6">
    <div class="flex items-center justify-between">
      <div class="text-lg font-extrabold">Theo dõi giá (mới nhất)</div>
      <a href="theodoi_gia.php" class="text-primary font-extrabold text-sm hover:underline">Xem tất cả</a>
    </div>

    <?php if (empty($priceLogs)): ?>
      <div class="p-6 text-center text-slate-500">Chưa có dữ liệu theo dõi giá (cần ghi log khi sửa sản phẩm).</div>
    <?php else: ?>
      <div class="mt-4 space-y-3">
        <?php foreach ($priceLogs as $p): ?>
          <div class="p-3 rounded-xl border hover:bg-slate-50 transition">
            <div class="font-extrabold"><?= h($p['ten_san_pham'] ?? ('SP #'.$p['id_san_pham'])) ?></div>
            <div class="text-sm text-slate-700">
              Giá: <b><?= number_format((int)($p['gia_cu'] ?? 0)) ?></b> → <b class="text-primary"><?= number_format((int)($p['gia_moi'] ?? 0)) ?></b>
              <?php if ($p['gia_sale_moi'] !== null || $p['gia_sale_cu'] !== null): ?>
                • Sale: <b><?= number_format((int)($p['gia_sale_cu'] ?? 0)) ?></b> → <b class="text-primary"><?= number_format((int)($p['gia_sale_moi'] ?? 0)) ?></b>
              <?php endif; ?>
            </div>
            <div class="text-xs text-slate-500">
              <?= h(date('d/m/Y H:i', strtotime($p['created_at']))) ?>
              <?= !empty($p['ghi_chu']) ? ' • '.h($p['ghi_chu']) : '' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
