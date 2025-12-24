<?php
require_once __DIR__ . '/_init.php';

$PAGE_TITLE = "Cài đặt";
$ACTIVE = "caidat";

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $shop_name = trim($_POST['shop_name'] ?? '');
    $support_email = trim($_POST['support_email'] ?? '');
    $support_phone = trim($_POST['support_phone'] ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 5);

    if ($shop_name === '') $shop_name = 'Crocs Admin';
    if ($low_stock_threshold < 1) $low_stock_threshold = 1;

    set_setting($pdo, 'shop_name', $shop_name, 'Tên shop hiển thị trên admin');
    set_setting($pdo, 'support_email', $support_email, 'Email hỗ trợ');
    set_setting($pdo, 'support_phone', $support_phone, 'SĐT hỗ trợ');
    set_setting($pdo, 'shop_address', $shop_address, 'Địa chỉ shop');
    set_setting($pdo, 'low_stock_threshold', $low_stock_threshold, 'Ngưỡng low stock');

    log_activity($pdo, $ADMIN, 'CAIDAT_UPDATE', 'cai_dat', null, [
      'shop_name'=>$shop_name,
      'support_email'=>$support_email,
      'support_phone'=>$support_phone,
      'low_stock_threshold'=>$low_stock_threshold
    ]);

    $success = "Đã lưu cài đặt.";
  } catch (Throwable $e) {
    $error = "Lỗi lưu cài đặt: ".$e->getMessage();
  }
}

$shop_name = get_setting($pdo, 'shop_name', 'Crocs Admin');
$support_email = get_setting($pdo, 'support_email', '');
$support_phone = get_setting($pdo, 'support_phone', '');
$shop_address = get_setting($pdo, 'shop_address', '');
$low_stock_threshold = (int)get_setting($pdo, 'low_stock_threshold', 5);

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
?>

<?php if ($success): ?>
  <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 font-extrabold text-sm"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 font-extrabold text-sm"><?= h($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-soft border p-6">
    <div class="text-xl font-extrabold mb-1">Cài đặt chung</div>
    <div class="text-sm text-slate-500 mb-6">Các thông số dùng chung cho toàn bộ admin.</div>

    <form method="post" class="space-y-4">
      <div>
        <label class="text-sm font-extrabold">Tên shop</label>
        <input name="shop_name" value="<?= h($shop_name) ?>" class="mt-1 w-full border rounded-xl px-3 py-2">
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-extrabold">Email hỗ trợ</label>
          <input name="support_email" value="<?= h($support_email) ?>" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="help@domain.com">
        </div>
        <div>
          <label class="text-sm font-extrabold">SĐT hỗ trợ</label>
          <input name="support_phone" value="<?= h($support_phone) ?>" class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="090...">
        </div>
      </div>

      <div>
        <label class="text-sm font-extrabold">Địa chỉ shop</label>
        <input name="shop_address" value="<?= h($shop_address) ?>" class="mt-1 w-full border rounded-xl px-3 py-2">
      </div>

      <div>
        <label class="text-sm font-extrabold">Ngưỡng Low stock</label>
        <input name="low_stock_threshold" value="<?= (int)$low_stock_threshold ?>" inputmode="numeric"
               class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="VD: 5">
        <div class="text-xs text-slate-500 mt-1">Dùng để cảnh báo “Sắp hết hàng”.</div>
      </div>

      <button class="px-5 py-3 rounded-xl bg-primary text-white font-extrabold hover:opacity-95">
        Lưu cài đặt
      </button>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow-soft border p-6">
    <div class="text-xl font-extrabold mb-4">Thông tin hệ thống</div>

    <div class="space-y-3 text-sm">
      <div class="flex justify-between gap-3">
        <div class="text-slate-500">Tài khoản</div>
        <div class="font-extrabold"><?= h($ADMIN['email'] ?? '-') ?></div>
      </div>
      <div class="flex justify-between gap-3">
        <div class="text-slate-500">Vai trò</div>
        <div class="font-extrabold"><?= h($ADMIN['vai_tro'] ?? '-') ?></div>
      </div>
      <div class="flex justify-between gap-3">
        <div class="text-slate-500">DB</div>
        <div class="font-extrabold">MySQL/MariaDB</div>
      </div>
      <div class="flex justify-between gap-3">
        <div class="text-slate-500">Timezone</div>
        <div class="font-extrabold">Asia/Ho_Chi_Minh</div>
      </div>
    </div>

    <div class="mt-6 p-3 rounded-xl border bg-slate-50 text-sm text-slate-600">
      Gợi ý: bật theo dõi giá trong <b>sanpham.php</b> (chèn đoạn log giá) để trang “Theo dõi giá” có dữ liệu.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
