<?php
require_once __DIR__ . '/_init.php';

$PAGE_TITLE = "Nhật ký hoạt động";
$ACTIVE = "nhatky";

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(actor_name LIKE ? OR actor_email LIKE ? OR chi_tiet LIKE ? OR hanh_dong LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($action !== '') {
  $where[] = "hanh_dong = ?";
  $params[] = $action;
}
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

$limit = 40;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$st = $pdo->prepare("SELECT * FROM nhat_ky_hoat_dong $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT COUNT(*) FROM nhat_ky_hoat_dong $whereSql");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($total/$limit));

$actions = $pdo->query("SELECT DISTINCT hanh_dong FROM nhat_ky_hoat_dong ORDER BY hanh_dong ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
?>

<div class="flex items-end justify-between gap-4 flex-wrap">
  <div>
    <h1 class="text-2xl font-extrabold">Nhật ký hoạt động</h1>
    <p class="text-sm text-slate-500 mt-1">Theo dõi thao tác của toàn bộ admin/nhân viên.</p>
  </div>
</div>

<div class="bg-white rounded-2xl shadow-soft border p-5">
  <form class="flex flex-wrap gap-2 items-center">
    <input name="q" value="<?= h($q) ?>" class="border rounded-xl px-3 py-2 text-sm w-72" placeholder="Tìm theo tên/email/hành động...">
    <select name="action" class="border rounded-xl px-3 py-2 text-sm">
      <option value="">-- Tất cả hành động --</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= h($a) ?>" <?= $action===$a?'selected':'' ?>><?= h($a) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold">Lọc</button>
    <a href="nhatky.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Reset</a>
  </form>
</div>

<div class="bg-white rounded-2xl shadow-soft border p-6 overflow-hidden">
  <div class="overflow-x-auto border rounded-xl">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase font-extrabold">
        <tr>
          <th class="p-3 text-left">Thời gian</th>
          <th class="p-3 text-left">Người thao tác</th>
          <th class="p-3 text-left">Hành động</th>
          <th class="p-3 text-left">Đối tượng</th>
          <th class="p-3 text-left">Chi tiết</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td class="p-4 text-center text-slate-500" colspan="5">Không có dữ liệu.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="p-3 text-xs text-slate-500 whitespace-nowrap"><?= h(date('d/m/Y H:i:s', strtotime($r['created_at']))) ?></td>
              <td class="p-3">
                <div class="font-extrabold"><?= h($r['actor_name'] ?? '-') ?></div>
                <div class="text-xs text-slate-500"><?= h($r['actor_email'] ?? '-') ?> • <?= h($r['actor_role'] ?? '-') ?></div>
              </td>
              <td class="p-3 font-extrabold text-primary"><?= h($r['hanh_dong']) ?></td>
              <td class="p-3">
                <?= h($r['doi_tuong'] ?? '-') ?>
                <?= $r['doi_tuong_id'] ? ' #'.(int)$r['doi_tuong_id'] : '' ?>
              </td>
              <td class="p-3 text-xs text-slate-600 max-w-[520px] break-words"><?= h($r['chi_tiet'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex items-center justify-between mt-4 text-sm">
    <div class="text-slate-500">Tổng: <b><?= number_format($total) ?></b></div>
    <div class="flex gap-2">
      <?php
        $base = "nhatky.php?q=".urlencode($q)."&action=".urlencode($action)."&page=";
      ?>
      <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>" href="<?= $base.($page-1) ?>">Trước</a>
      <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>" href="<?= $base.($page+1) ?>">Sau</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
