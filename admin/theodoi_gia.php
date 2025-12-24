<?php
require_once __DIR__ . '/_init.php';

$PAGE_TITLE = "Theo dõi giá";
$ACTIVE = "gia";

$q = trim($_GET['q'] ?? '');

$where = "";
$params = [];
if ($q !== '') {
  $where = "WHERE (sp.ten_san_pham LIKE ? OR tdg.id_san_pham = ?)";
  $params = ["%$q%", (int)$q];
}

$limit = 40;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;

$st = $pdo->prepare("
  SELECT tdg.*, sp.ten_san_pham, ad.ho_ten AS ten_admin
  FROM theo_doi_gia tdg
  LEFT JOIN sanpham sp ON sp.id_san_pham = tdg.id_san_pham
  LEFT JOIN admin ad ON ad.id_admin = tdg.id_admin
  $where
  ORDER BY tdg.created_at DESC
  LIMIT $limit OFFSET $offset
");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("
  SELECT COUNT(*)
  FROM theo_doi_gia tdg
  LEFT JOIN sanpham sp ON sp.id_san_pham = tdg.id_san_pham
  $where
");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($total/$limit));

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
?>

<div class="flex items-end justify-between gap-4 flex-wrap">
  <div>
    <h1 class="text-2xl font-extrabold">Theo dõi giá</h1>
    <p class="text-sm text-slate-500 mt-1">Lịch sử thay đổi giá sản phẩm (ghi log khi admin sửa sản phẩm).</p>
  </div>
</div>

<div class="bg-white rounded-2xl shadow-soft border p-5">
  <form class="flex flex-wrap gap-2 items-center">
    <input name="q" value="<?= h($q) ?>" class="border rounded-xl px-3 py-2 text-sm w-80" placeholder="Tìm tên sản phẩm hoặc ID...">
    <button class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold">Tìm</button>
    <a href="theodoi_gia.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Reset</a>
  </form>
</div>

<div class="bg-white rounded-2xl shadow-soft border p-6 overflow-hidden">
  <div class="overflow-x-auto border rounded-xl">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase font-extrabold">
        <tr>
          <th class="p-3 text-left">Thời gian</th>
          <th class="p-3 text-left">Sản phẩm</th>
          <th class="p-3 text-right">Giá cũ</th>
          <th class="p-3 text-right">Giá mới</th>
          <th class="p-3 text-right">Sale cũ</th>
          <th class="p-3 text-right">Sale mới</th>
          <th class="p-3 text-left">Người sửa</th>
          <th class="p-3 text-left">Ghi chú</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td class="p-4 text-center text-slate-500" colspan="8">Chưa có dữ liệu.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="p-3 text-xs text-slate-500 whitespace-nowrap"><?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
              <td class="p-3">
                <div class="font-extrabold"><?= h($r['ten_san_pham'] ?? ('SP #'.$r['id_san_pham'])) ?></div>
                <div class="text-xs text-slate-500">ID: <?= (int)$r['id_san_pham'] ?></div>
              </td>
              <td class="p-3 text-right font-extrabold"><?= number_format((int)($r['gia_cu'] ?? 0)) ?></td>
              <td class="p-3 text-right font-extrabold text-primary"><?= number_format((int)($r['gia_moi'] ?? 0)) ?></td>
              <td class="p-3 text-right font-extrabold"><?= number_format((int)($r['gia_sale_cu'] ?? 0)) ?></td>
              <td class="p-3 text-right font-extrabold text-primary"><?= number_format((int)($r['gia_sale_moi'] ?? 0)) ?></td>
              <td class="p-3"><?= h($r['ten_admin'] ?? ('#'.$r['id_admin'])) ?></td>
              <td class="p-3 text-xs text-slate-600 max-w-[320px] break-words"><?= h($r['ghi_chu'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex items-center justify-between mt-4 text-sm">
    <div class="text-slate-500">Tổng: <b><?= number_format($total) ?></b></div>
    <div class="flex gap-2">
      <?php $base = "theodoi_gia.php?q=".urlencode($q)."&page="; ?>
      <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>" href="<?= $base.($page-1) ?>">Trước</a>
      <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>" href="<?= $base.($page+1) ?>">Sau</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
