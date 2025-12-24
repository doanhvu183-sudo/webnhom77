<?php
// admin/thanhben.php
$ACTIVE = $ACTIVE ?? 'dashboard';

function nav_item($href, $icon, $label, $key, $ACTIVE, ) {
  $is = ($key === $ACTIVE);
  $cls = $is
    ? "bg-primary text-white shadow-soft"
    : "text-slate-600 hover:bg-slate-100";
  $iconCls = $is ? "text-white" : "text-slate-500 group-hover:text-primary";
  echo '
  <a href="'.$href.'" class="group flex items-center gap-3 px-3 py-3 rounded-xl transition-all '.$cls.'">
    <span class="material-symbols-outlined '.$iconCls.'">'.$icon.'</span>
    <span class="text-sm font-extrabold hidden lg:block">'.$label.'</span>
  </a>';
}

$shopName = get_setting($pdo, 'shop_name', 'Crocs Admin');
$lowStock = (int)get_setting($pdo, 'low_stock_threshold', 5);
?>
<!-- SIDEBAR -->
<aside class="w-20 lg:w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-screen sticky top-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-gray-100">
    <div class="size-8 rounded bg-primary flex items-center justify-center text-white font-extrabold text-xl">C</div>
    <span class="ml-3 font-extrabold text-lg hidden lg:block"><?= h($shopName) ?></span>
  </div>

  <nav class="flex-1 overflow-y-auto py-6 px-3 flex flex-col gap-2">
    <?php
      nav_item("index.php", "grid_view", "Tổng quan", "dashboard", $ACTIVE);
      nav_item("sanpham.php", "inventory_2", "Sản phẩm", "sanpham", $ACTIVE);
      nav_item("donhang.php", "shopping_bag", "Đơn hàng", "donhang", $ACTIVE);
      nav_item("tonkho.php", "warehouse", "Kho / Tồn", "tonkho", $ACTIVE);
      nav_item("voucher.php", "shopping_bag", "Voucher", "voucher", $ACTIVE);
      nav_item("thongbao.php", "notifications", "Thông báo", "thongbao", $ACTIVE);
      nav_item("theodoi_gia.php", "sell", "Theo dõi giá", "gia", $ACTIVE);
      nav_item("nhatky.php", "history", "Nhật ký hoạt động", "nhatky", $ACTIVE);
      nav_item("baocao.php", "bar_chart", "Báo cáo", "baocao", $ACTIVE);
      if (!empty($GLOBALS['IS_ADMIN'])) nav_item("nhanvien.php", "groups", "Nhân viên", "nhanvien", $ACTIVE);
      nav_item("caidat.php", "settings", "Cài đặt", "caidat", $ACTIVE);
    ?>
    <div class="mt-auto pt-6 border-t border-gray-100">
      <a href="dangxuat.php" class="group flex items-center gap-3 px-3 py-3 rounded-xl text-slate-600 hover:bg-slate-100 transition-all">
        <span class="material-symbols-outlined text-slate-500 group-hover:text-primary">logout</span>
        <span class="text-sm font-extrabold hidden lg:block">Đăng xuất</span>
      </a>
    </div>
  </nav>
</aside>

<!-- CONTENT -->
<div class="flex-1 flex flex-col min-w-0">
  <!-- TOPBAR -->
  <header class="bg-white/80 backdrop-blur border-b border-gray-200 h-16 flex items-center justify-between px-4 md:px-6 sticky top-0 z-10">
    <div class="flex items-center gap-3 min-w-0">
      <div class="font-extrabold text-lg truncate"><?= h($PAGE_TITLE ?? 'Admin') ?></div>
      <span class="hidden sm:inline text-xs font-extrabold px-2 py-1 rounded-full bg-slate-100 border">
        <?= h($ROLE ?? 'ADMIN') ?>
      </span>
      <span class="hidden sm:inline text-xs font-bold text-slate-500">
        Low stock: <= <?= (int)$lowStock ?>
      </span>
    </div>

    <div class="flex items-center gap-3">
      <form method="get" action="" class="relative hidden sm:block">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q"
               value="<?= h($_GET['q'] ?? '') ?>"
               class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-72 focus:ring-2 focus:ring-primary/40"
               placeholder="Tìm nhanh..." />
      </form>

      <a href="thongbao.php" class="p-2 rounded-full hover:bg-gray-100 text-slate-600">
        <span class="material-symbols-outlined">notifications</span>
      </a>

      <div class="size-9 rounded-full bg-slate-200 border-2 border-white shadow-sm flex items-center justify-center font-extrabold">
        <?= h(mb_substr($ADMIN['ho_ten'] ?? 'A', 0, 1)) ?>
      </div>
    </div>
  </header>

  <main class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto space-y-6">
