<?php
// admin/includes/thanhBen.php

if (session_status() === PHP_SESSION_NONE) session_start();

$ACTIVE   = $ACTIVE ?? 'tong_quan';
$APP_NAME = $APP_NAME ?? 'Crocs™';

$me   = $_SESSION['admin'] ?? [];
$role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'NHANVIEN')));
$dept = strtoupper(trim((string)($me['bo_phan'] ?? $me['phong_ban'] ?? '')));

// ✅ CHỈ tạo h() nếu chưa tồn tại
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Quyền truy cập menu (UI level). Nếu bạn đã dùng requirePermission() ở từng trang,
 * thì menu này chỉ là "hiển thị/ẩn" cho tiện quản lý.
 */
if (!function_exists('can_access')) {
  function can_access(string $key, string $role, string $dept=''): bool {
    $role = strtoupper(trim($role));
    $dept = strtoupper(trim($dept));

    if ($role === 'ADMIN') return true;

    // mặc định cho nhân viên xem các màn cơ bản
    $base = [
      'tong_quan'      => true,
      'sanpham'        => true,
      'danhmuc'        => true,
      'theodoi_gia'    => true,
      'donhang'        => true,
      'khachhang'      => true,
      'tonkho'         => true,
      'phieunhap'      => true,
      'phieuxuat'      => true,
      'lichsu_kho'     => true,
      'tonkho_nhatky'  => true,
      'voucher'        => true,
      'baocao'         => false,
      'timkiem'        => true,
      'thongbao'       => true,
      'nhatky'         => false,
      'nhanvien'       => false,
      'caidat'         => false,
      'dang_nhap'      => false,
      'dang_xuat'      => true,
    ];

    // kế toán/quản lý
    if (in_array($role, ['KETOAN','QUANLY'], true)) {
      $base['baocao'] = true;
      $base['nhatky'] = true;
    }

    // theo phòng ban
    if ($dept === 'KETOAN') {
      $base['baocao'] = true;
      $base['nhatky'] = true;
    }
    if ($dept === 'KHO') {
      $base['tonkho'] = true;
      $base['phieunhap'] = true;
      $base['phieuxuat'] = true;
      $base['lichsu_kho'] = true;
      $base['tonkho_nhatky'] = true;
      $base['sanpham'] = true;
    }
    if (in_array($dept, ['BANHANG','CSKH'], true)) {
      $base['donhang'] = true;
      $base['khachhang'] = true;
    }

    return (bool)($base[$key] ?? false);
  }
}

if (!function_exists('nav_item')) {
  function nav_item(string $href, string $icon, string $label, string $key, string $ACTIVE): void {
    $is = ($key === $ACTIVE);

    // hiệu ứng: active có "thanh" bên trái + hover translate nhẹ
    $wrapCls = $is
      ? "bg-primary/10 text-primary border-primary/20"
      : "text-slate-700 hover:bg-slate-50 border-transparent";

    $iconCls = $is ? "text-primary" : "text-slate-500 group-hover:text-primary";

    echo '
    <a href="'.h($href).'"
       title="'.h($label).'"
       class="group relative flex items-center gap-3 px-3 py-3 rounded-2xl border transition-all duration-200 '.$wrapCls.' hover:-translate-y-[1px]">
      <span class="absolute left-0 top-1/2 -translate-y-1/2 h-8 w-1 rounded-full '.($is?'bg-primary':'bg-transparent').'"></span>
      <span class="material-symbols-outlined '.$iconCls.'">'.$icon.'</span>
      <span class="text-sm font-extrabold hidden lg:block">'.h($label).'</span>
    </a>';
  }
}

$isAdmin = ($role === 'ADMIN');

// Danh sách file bạn có (theo ảnh)
$MENU = [
  ['group'=>'Tổng quan', 'items'=>[
    ['tong_quan.php',      'grid_view',     'Tổng quan',        'tong_quan'],
    ['timkiem.php',        'search',        'Tìm kiếm',         'timkiem'],
    ['thongbao.php',       'notifications', 'Thông báo',        'thongbao'],
  ]],
  ['group'=>'Bán hàng', 'items'=>[
    ['donhang.php',        'shopping_bag',  'Đơn hàng',         'donhang'],
    ['khachhang.php',      'groups',        'Khách hàng',       'khachhang'],
    ['voucher.php',        'sell',          'Voucher',          'voucher'],
  ]],
  ['group'=>'Sản phẩm', 'items'=>[
    ['sanpham.php',        'inventory_2',   'Sản phẩm',         'sanpham'],
    ['danhmuc.php',        'category',      'Danh mục',         'danhmuc'],
    ['theodoi_gia.php',    'monitoring',    'Theo dõi giá',     'theodoi_gia'],
  ]],
  ['group'=>'Kho', 'items'=>[
    ['tonkho.php',         'warehouse',     'Tồn kho',          'tonkho'],
    ['phieunhap.php',      'inbox',         'Phiếu nhập',       'phieunhap'],
    ['phieuxuat.php',      'outbox',        'Phiếu xuất',       'phieuxuat'],
    ['lichsu_kho.php',     'swap_horiz',    'Lịch sử kho',      'lichsu_kho'],
    ['tonkho_nhatky.php',  'history',       'Tồn kho nhật ký',  'tonkho_nhatky'],
  ]],
  ['group'=>'Quản trị', 'items'=>[
    ['baocao.php',         'bar_chart',     'Báo cáo',          'baocao'],
    ['nhatky.php',         'history_edu',   'Nhật ký',          'nhatky'],
    ['nhanvien.php',       'badge',         'Nhân viên',        'nhanvien'],
    ['caidat.php',         'settings',      'Cài đặt',          'caidat'],
  ]],
];
?>

<!-- SIDEBAR DESKTOP -->
<aside class="w-20 lg:w-72 bg-white border-r border-slate-200 hidden md:flex flex-col h-screen flex-shrink-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-slate-100">
    <div class="size-9 rounded-xl bg-primary flex items-center justify-center text-white font-extrabold text-xl">C</div>
    <span class="ml-3 font-extrabold text-lg hidden lg:block text-slate-900"><?= h($APP_NAME) ?></span>
  </div>

  <nav class="flex-1 overflow-y-auto py-5 px-3 flex flex-col gap-4">
    <?php foreach($MENU as $g): ?>
      <div>
        <div class="px-2 pb-2 text-[11px] uppercase tracking-wide text-slate-400 font-extrabold hidden lg:block">
          <?= h($g['group']) ?>
        </div>
        <div class="flex flex-col gap-2">
          <?php foreach($g['items'] as $it):
            [$href,$icon,$label,$key] = $it;

            // chỉ hiện nếu được phép (trừ vài trang hệ thống)
            if ($key !== 'dang_nhap' && !can_access($key,$role,$dept)) continue;

            nav_item($href, $icon, $label, $key, $ACTIVE);
          endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="mt-auto pt-4 border-t border-slate-100 flex flex-col gap-2">
      <a href="dang_xuat.php"
         class="group relative flex items-center gap-3 px-3 py-3 rounded-2xl border border-transparent text-slate-700 hover:bg-slate-50 transition-all duration-200 hover:-translate-y-[1px]">
        <span class="material-symbols-outlined text-slate-500 group-hover:text-primary">logout</span>
        <span class="text-sm font-extrabold hidden lg:block">Đăng xuất</span>
      </a>

      <div class="px-3 pb-2 text-[11px] text-slate-400 font-semibold hidden lg:block">
        Vai trò: <?= h($role) ?><?= $dept ? ' • Bộ phận: '.h($dept) : '' ?>
      </div>
    </div>
  </nav>
</aside>
