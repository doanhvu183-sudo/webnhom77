<?php
// admin/includes/thanhBen.php

if (session_status() === PHP_SESSION_NONE) session_start();

$ACTIVE   = $ACTIVE ?? 'tong_quan';
$APP_NAME = $APP_NAME ?? 'Crocs Admin';

$me = $_SESSION['admin'] ?? [];
$role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'NHANVIEN')));
$dept = strtoupper(trim((string)($me['bo_phan'] ?? $me['phong_ban'] ?? '')));

// ✅ CHỈ tạo h() nếu chưa tồn tại
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Ma trận quyền theo vai trò / bộ phận (thăng cấp dần).
 */
if (!function_exists('can_access')) {
  function can_access(string $key, string $role, string $dept=''): bool {
    $role = strtoupper(trim($role));
    $dept = strtoupper(trim($dept));

    if ($role === 'ADMIN') return true;

    $base = [
      'tong_quan'   => true,
      'sanpham'     => true,
      'theodoi_gia' => true,
      'donhang'     => true,
      'khachhang'   => true,
      'tonkho'      => true,
      'voucher'     => true,
      'baocao'      => false,
      'nhatky'      => false,
      'nhanvien'    => false,
      'thongbao'    => true,
      'caidat'      => false,
    ];

    if ($role === 'KETOAN' || $role === 'QUANLY') {
      $base['baocao'] = true;
      $base['nhatky'] = true;
    }

    if ($dept === 'KETOAN') {
      $base['baocao'] = true;
      $base['nhatky'] = true;
    }
    if ($dept === 'KHO') {
      $base['tonkho'] = true;
      $base['sanpham'] = true;
    }
    if ($dept === 'BANHANG') {
      $base['donhang'] = true;
      $base['khachhang'] = true;
    }
    if ($dept === 'CSKH') {
      $base['khachhang'] = true;
      $base['donhang'] = true;
    }

    return (bool)($base[$key] ?? false);
  }
}

if (!function_exists('nav_item')) {
  function nav_item(string $href, string $icon, string $label, string $key, string $ACTIVE): void {
    $is = ($key === $ACTIVE);
    $cls = $is
      ? "bg-primary text-white shadow-soft"
      : "text-slate-600 hover:bg-slate-100";
    $iconCls = $is ? "text-white" : "text-slate-500 group-hover:text-primary";

    echo '
    <a href="'.h($href).'" class="group flex items-center gap-3 px-3 py-3 rounded-2xl transition-all '.$cls.'">
      <span class="material-symbols-outlined '.$iconCls.'">'.$icon.'</span>
      <span class="text-sm font-extrabold hidden lg:block">'.$label.'</span>
    </a>';
  }
}

$isAdmin = ($role === 'ADMIN');
?>

<!-- SIDEBAR DESKTOP -->
<aside class="w-20 lg:w-64 bg-white border-r border-slate-200 hidden md:flex flex-col h-screen flex-shrink-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-slate-100">
    <div class="size-9 rounded-xl bg-primary flex items-center justify-center text-white font-extrabold text-xl">C</div>
    <span class="ml-3 font-extrabold text-lg hidden lg:block text-slate-900"><?= h($APP_NAME) ?></span>
  </div>

  <nav class="flex-1 overflow-y-auto py-5 px-3 flex flex-col gap-2">
    <?php if (can_access('tong_quan',$role,$dept)) nav_item('tong_quan.php',   'grid_view',     'Tổng quan',   'tong_quan',   $ACTIVE); ?>
    <?php if (can_access('sanpham',$role,$dept)) nav_item('sanpham.php',      'inventory_2',   'Sản phẩm',    'sanpham',     $ACTIVE); ?>
    <?php if (can_access('theodoi_gia',$role,$dept)) nav_item('theodoi_gia.php','monitoring',   'Theo dõi giá', 'theodoi_gia', $ACTIVE); ?>
    <?php if (can_access('donhang',$role,$dept)) nav_item('donhang.php',      'shopping_bag',  'Đơn hàng',    'donhang',     $ACTIVE); ?>
    <?php if (can_access('khachhang',$role,$dept)) nav_item('khachhang.php',  'groups',        'Khách hàng',  'khachhang',   $ACTIVE); ?>
    <?php if (can_access('tonkho',$role,$dept)) nav_item('tonkho.php',        'warehouse',     'Tồn kho',     'tonkho',      $ACTIVE); ?>
    <?php if (can_access('voucher',$role,$dept)) nav_item('voucher.php',      'sell',          'Voucher',     'voucher',     $ACTIVE); ?>
    <?php if (can_access('baocao',$role,$dept)) nav_item('baocao.php',        'bar_chart',     'Báo cáo',     'baocao',      $ACTIVE); ?>
    <?php if (can_access('nhatky',$role,$dept)) nav_item('nhatky.php',        'history',       'Nhật ký',     'nhatky',      $ACTIVE); ?>
    <?php if (can_access('nhanvien',$role,$dept)) nav_item('nhanvien.php',    'badge',         'Nhân viên',   'nhanvien',    $ACTIVE); ?>

    <div class="mt-auto pt-5 border-t border-slate-100 flex flex-col gap-2">
      <?php if (can_access('caidat',$role,$dept)) nav_item('caidat.php', 'settings', 'Cài đặt', 'caidat', $ACTIVE); ?>
      <a href="dang_xuat.php" class="group flex items-center gap-3 px-3 py-3 rounded-2xl text-slate-600 hover:bg-slate-100 transition-all">
        <span class="material-symbols-outlined text-slate-500 group-hover:text-primary">logout</span>
        <span class="text-sm font-extrabold hidden lg:block">Đăng xuất</span>
      </a>
      <div class="px-3 pb-2 text-[11px] text-slate-400 font-semibold hidden lg:block">
        Vai trò: <?= h($role) ?><?= $dept ? ' • Bộ phận: '.h($dept) : '' ?>
      </div>
    </div>
  </nav>
</aside>
