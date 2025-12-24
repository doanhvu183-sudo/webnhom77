<?php
// admin/timkiem.php
$ACTIVE='tongquan';
$PAGE_TITLE='Tìm kiếm';
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';

$q = trim($_GET['q'] ?? '');
$sp=[]; $dh=[];

if ($q !== '') {
  try{
    $st = $pdo->prepare("SELECT id_san_pham, ten_san_pham, gia, hinh_anh FROM sanpham WHERE ten_san_pham LIKE ? OR id_san_pham LIKE ? ORDER BY id_san_pham DESC LIMIT 10");
    $st->execute(["%$q%","%$q%"]);
    $sp = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch(Throwable $e){}

  try{
    $st = $pdo->prepare("
      SELECT dh.id_don_hang, dh.ma_don_hang, dh.trang_thai, dh.tong_thanh_toan, dh.ngay_dat, nd.ho_ten
      FROM donhang dh
      LEFT JOIN nguoidung nd ON nd.id_nguoi_dung = dh.id_nguoi_dung
      WHERE dh.id_don_hang LIKE ? OR dh.ma_don_hang LIKE ? OR nd.ho_ten LIKE ?
      ORDER BY dh.id_don_hang DESC LIMIT 10
    ");
    $st->execute(["%$q%","%$q%","%$q%"]);
    $dh = $st->fetchAll(PDO::FETCH_ASSOC);
  } catch(Throwable $e){}
}
?>
<div>
  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Kết quả tìm kiếm</h1>
  <p class="text-sm text-slate-500">Từ thanh tìm kiếm trên cùng.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl shadow-soft border border-gray-100 dark:border-gray-800">
    <h3 class="text-lg font-extrabold mb-4">Sản phẩm</h3>
    <?php if ($q==='' || empty($sp)): ?>
      <div class="text-sm text-slate-500">Không có kết quả.</div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach($sp as $r): ?>
          <?php $img = !empty($r['hinh_anh']) ? "../assets/img/".$r['hinh_anh'] : "../assets/img/no-image.png"; ?>
          <a href="sanpham.php?edit=<?= (int)$r['id_san_pham'] ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/40">
            <img src="<?= h($img) ?>" class="w-10 h-10 object-contain rounded-lg border bg-white"
                 data-preview-src="<?= h($img) ?>" data-preview-name="<?= h($r['ten_san_pham']) ?>">
            <div class="flex-1">
              <div class="text-sm font-extrabold">#<?= (int)$r['id_san_pham'] ?> • <?= h($r['ten_san_pham']) ?></div>
              <div class="text-xs text-slate-500"><?= number_format((int)$r['gia']) ?> ₫</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-white dark:bg-surface-dark p-6 rounded-2xl shadow-soft border border-gray-100 dark:border-gray-800">
    <h3 class="text-lg font-extrabold mb-4">Đơn hàng</h3>
    <?php if ($q==='' || empty($dh)): ?>
      <div class="text-sm text-slate-500">Không có kết quả.</div>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach($dh as $r): ?>
          <a href="donhang.php?q=<?= urlencode((string)$r['id_don_hang']) ?>" class="block p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800/40">
            <div class="text-sm font-extrabold">#<?= (int)$r['id_don_hang'] ?> <?= !empty($r['ma_don_hang']) ? '• '.h($r['ma_don_hang']) : '' ?></div>
            <div class="text-xs text-slate-500"><?= h($r['ho_ten'] ?? '—') ?> • <?= h($r['trang_thai'] ?? '') ?> • <?= number_format((int)($r['tong_thanh_toan'] ?? 0)) ?> ₫</div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
