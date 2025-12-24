<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Safe session ===== */
$adminName   = $_SESSION['admin']['ho_ten']  ?? ($_SESSION['admin']['username'] ?? 'Admin');
$adminRole   = $_SESSION['admin']['vai_tro'] ?? 'admin';
$avatarRaw   = $_SESSION['admin']['avatar']  ?? ''; // có thể là tên file hoặc URL

/* ===== Helpers: chống lỗi PDO/bảng/cột ===== */
function _tableExists($pdo, $table) {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $st->execute([$table]);
    return (int)$st->fetchColumn() > 0;
  } catch (Exception $e) {
    return false;
  }
}
function _colExists($pdo, $table, $col) {
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $st->execute([$table, $col]);
    return (int)$st->fetchColumn() > 0;
  } catch (Exception $e) {
    return false;
  }
}
function _qCount($pdo, $sql, $params = []) {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return (int)($v ?? 0);
  } catch (Exception $e) {
    return 0;
  }
}

/* ===== Badge thông báo (tự thích nghi schema) ===== */
$badgeThongBao = 0;
if (isset($pdo) && $pdo instanceof PDO) {
  if (_tableExists($pdo, 'thong_bao')) {
    // ưu tiên da_doc=0, nếu không có thì trang_thai='chua_doc', nếu không có thì lấy tổng 7 ngày gần nhất
    if (_colExists($pdo, 'thong_bao', 'da_doc')) {
      $badgeThongBao = _qCount($pdo, "SELECT COUNT(*) FROM thong_bao WHERE da_doc = 0");
    } elseif (_colExists($pdo, 'thong_bao', 'trang_thai')) {
      $badgeThongBao = _qCount($pdo, "SELECT COUNT(*) FROM thong_bao WHERE trang_thai IN ('chua_doc','Chưa đọc','CHUA_DOC')");
    } elseif (_colExists($pdo, 'thong_bao', 'ngay_tao')) {
      $badgeThongBao = _qCount($pdo, "SELECT COUNT(*) FROM thong_bao WHERE ngay_tao >= NOW() - INTERVAL 7 DAY");
    } else {
      $badgeThongBao = _qCount($pdo, "SELECT COUNT(*) FROM thong_bao");
    }
  }
}

/* ===== Avatar URL =====
   - nếu avatar là URL http(s) thì dùng luôn
   - nếu là tên file thì lấy trong ../assets/img/
*/
$avatarUrl = '';
if (!empty($avatarRaw)) {
  if (preg_match('/^https?:\/\//i', $avatarRaw)) {
    $avatarUrl = $avatarRaw;
  } else {
    $avatarUrl = '../assets/img/' . ltrim($avatarRaw, '/');
  }
}

/* ===== Initial chữ cái ===== */
$initial = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<style>
  /* giữ topbar gọn và icon thẳng hàng */
  .topicon { display:inline-flex; align-items:center; justify-content:center; }
  /* “cầu nối hover” để menu không rớt khi rê chuột xuống */
  .hover-bridge { height: 10px; }
</style>

<header class="bg-white/80 dark:bg-surface-dark/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-800 h-16 flex items-center justify-between px-6 z-20 sticky top-0">
  <!-- Left -->
  <div class="flex items-center gap-3 min-w-0">
    <h2 class="text-xl font-bold text-slate-800 dark:text-white hidden sm:block truncate">
      Bảng điều khiển
    </h2>
  </div>

  <!-- Right -->
  <div class="flex items-center gap-3 sm:gap-4">
    <!-- Search -->
    <form action="#" method="get" class="relative hidden sm:block">
      <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
      <input
        name="q"
        class="pl-10 pr-4 py-2 bg-gray-100 dark:bg-gray-800 border-none rounded-lg text-sm w-64 focus:ring-2 focus:ring-primary/50 text-slate-700 dark:text-gray-200"
        placeholder="Tìm kiếm nhanh..."
        type="text"
        autocomplete="off"
      />
    </form>

    <!-- Notifications -->
    <a href="thongbao.php"
       class="relative topicon w-10 h-10 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors"
       aria-label="Thông báo">
      <span class="material-symbols-outlined">notifications</span>

      <?php if ($badgeThongBao > 0): ?>
        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-danger text-white text-[11px] font-bold rounded-full flex items-center justify-center border-2 border-white dark:border-surface-dark">
          <?= $badgeThongBao > 99 ? '99+' : (int)$badgeThongBao ?>
        </span>
      <?php else: ?>
        <!-- chấm đỏ nhẹ (tuỳ thích) -->
        <!-- <span class="absolute top-2 right-2 size-2 bg-danger rounded-full border border-white dark:border-surface-dark"></span> -->
      <?php endif; ?>
    </a>

    <!-- Account dropdown (không rớt hover) -->
    <div class="relative group">
      <button type="button"
              class="topicon w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 border-2 border-white dark:border-gray-600 shadow-sm overflow-hidden">
        <?php if (!empty($avatarUrl)): ?>
          <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" class="w-full h-full object-cover">
        <?php else: ?>
          <span class="text-slate-800 dark:text-white font-extrabold"><?= htmlspecialchars($initial) ?></span>
        <?php endif; ?>
      </button>

      <!-- vùng cầu nối -->
      <div class="absolute right-0 top-full w-56 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150">
        <div class="hover-bridge"></div>

        <div class="bg-white dark:bg-surface-dark border border-gray-200 dark:border-gray-800 rounded-2xl shadow-soft overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800">
            <p class="text-sm font-extrabold text-slate-900 dark:text-white truncate">
              <?= htmlspecialchars($adminName) ?>
            </p>
            <p class="text-xs text-slate-500">
              <?= htmlspecialchars($adminRole) ?>
            </p>
          </div>

          <div class="p-2">
            <a href="caidat.php"
               class="block px-3 py-2 rounded-xl text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
              Cài đặt
            </a>
            <a href="dang_xuat.php"
               class="block px-3 py-2 rounded-xl text-sm text-danger hover:bg-red-50 dark:hover:bg-red-900/10">
              Đăng xuất
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</header>
