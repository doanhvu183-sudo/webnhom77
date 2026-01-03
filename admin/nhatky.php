<?php
// admin/nhatky.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/helpers.php';

require_login_admin();
requirePermission('nhatky', $pdo);

$ACTIVE = 'nhatky';
$PAGE_TITLE = 'Nhật ký hoạt động';

if (!tableExists($pdo,'nhatky_hoatdong')) {
  die("Thiếu bảng nhatky_hoatdong.");
}

$q = trim((string)($_GET['q'] ?? ''));
$hanh_dong = trim((string)($_GET['hanh_dong'] ?? ''));
$bang = trim((string)($_GET['bang'] ?? ''));

$page = max(1,(int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page-1)*$limit;

$where = [];
$bind = [];

if ($q !== '') {
  $where[] = "(mo_ta LIKE :q OR ip LIKE :q OR user_agent LIKE :q OR CAST(id_admin AS CHAR) LIKE :q)";
  $bind[':q'] = "%$q%";
}
if ($hanh_dong !== '') {
  $where[] = "hanh_dong = :hd";
  $bind[':hd'] = $hanh_dong;
}
if ($bang !== '') {
  $where[] = "bang_lien_quan = :b";
  $bind[':b'] = $bang;
}

$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// filter lists
$actions = $pdo->query("SELECT DISTINCT hanh_dong FROM nhatky_hoatdong ORDER BY hanh_dong ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$tables  = $pdo->query("SELECT DISTINCT bang_lien_quan FROM nhatky_hoatdong WHERE bang_lien_quan IS NOT NULL AND bang_lien_quan<>'' ORDER BY bang_lien_quan ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

// count
$st = $pdo->prepare("SELECT COUNT(*) FROM nhatky_hoatdong $whereSql");
$st->execute($bind);
$total = (int)$st->fetchColumn();
$totalPages = max(1,(int)ceil($total/$limit));

// rows
$sql = "SELECT * FROM nhatky_hoatdong $whereSql ORDER BY ngay_tao DESC, id_log DESC LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>

<div class="bg-white rounded-2xl border border-line shadow-card p-5">
  <div class="flex items-center justify-between gap-3">
    <div>
      <div class="text-lg font-extrabold">Nhật ký hoạt động</div>
      <div class="text-sm text-muted font-bold mt-1">Hiển thị đầy đủ: ai thao tác, hành động, bảng liên quan, JSON, IP, user-agent.</div>
    </div>

    <form class="flex items-center gap-2" method="get">
      <input name="q" value="<?= h($q) ?>" class="border border-line rounded-xl px-3 py-2 text-sm font-bold w-[260px]" placeholder="Tìm mô tả / IP / id_admin...">

      <select name="hanh_dong" class="border border-line rounded-xl px-3 py-2 text-sm font-bold">
        <option value="">Tất cả hành động</option>
        <?php foreach($actions as $a): ?>
          <option value="<?= h($a) ?>" <?= $a===$hanh_dong?'selected':'' ?>><?= h($a) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="bang" class="border border-line rounded-xl px-3 py-2 text-sm font-bold">
        <option value="">Tất cả bảng</option>
        <?php foreach($tables as $t): ?>
          <option value="<?= h($t) ?>" <?= $t===$bang?'selected':'' ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="px-4 py-2 rounded-xl bg-[var(--primary)] text-white font-extrabold text-sm">Lọc</button>
    </form>
  </div>

  <div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="text-slate-500">
          <th class="text-left py-3 pr-3">Thời gian</th>
          <th class="text-left py-3 pr-3">Người thao tác</th>
          <th class="text-left py-3 pr-3">Hành động</th>
          <th class="text-left py-3 pr-3">Mô tả</th>
          <th class="text-left py-3 pr-3">Bảng</th>
          <th class="text-left py-3 pr-3">ID bản ghi</th>
          <th class="text-left py-3 pr-3">IP</th>
          <th class="text-left py-3 pr-3">JSON</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-slate-100">
        <?php foreach($rows as $r): ?>
          <?php
            $role = (string)($r['vai_tro'] ?? 'admin');
            $aid  = (int)($r['id_admin'] ?? 0);
            $actor = ($role === 'nhanvien' ? 'NHÂN VIÊN' : 'ADMIN') . ($aid ? " #$aid" : '');
            $json = (string)($r['du_lieu_json'] ?? '');
          ?>
          <tr>
            <td class="py-3 pr-3 text-xs text-muted font-bold"><?= h($r['ngay_tao'] ?? '') ?></td>
            <td class="py-3 pr-3 font-extrabold"><?= h($actor) ?></td>
            <td class="py-3 pr-3">
              <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-slate-100 text-slate-700">
                <?= h($r['hanh_dong'] ?? '') ?>
              </span>
            </td>
            <td class="py-3 pr-3 text-muted font-bold"><?= h($r['mo_ta'] ?? '') ?></td>
            <td class="py-3 pr-3 font-extrabold"><?= h($r['bang_lien_quan'] ?? '') ?></td>
            <td class="py-3 pr-3 font-extrabold"><?= h($r['id_ban_ghi'] ?? '') ?></td>
            <td class="py-3 pr-3 text-xs text-muted font-bold"><?= h($r['ip'] ?? '') ?></td>
            <td class="py-3 pr-3">
              <?php if($json !== ''): ?>
                <button
                  class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold hover:bg-slate-50"
                  onclick="showJson(<?= (int)$r['id_log'] ?>, <?= json_encode($json, JSON_UNESCAPED_UNICODE) ?>)">
                  Xem
                </button>
              <?php else: ?>
                <span class="text-xs text-muted font-bold">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if(!$rows): ?>
          <tr><td colspan="8" class="py-8 text-center text-muted font-bold">Chưa có log.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-4 flex items-center justify-between">
    <div class="text-sm text-muted font-bold">Tổng: <?= number_format($total) ?> log</div>
    <div class="flex items-center gap-2">
      <?php $prev=max(1,$page-1); $next=min($totalPages,$page+1); ?>
      <a class="px-3 py-2 rounded-xl border border-line text-sm font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>"
         href="?q=<?= urlencode($q) ?>&hanh_dong=<?= urlencode($hanh_dong) ?>&bang=<?= urlencode($bang) ?>&page=<?= $prev ?>">Trước</a>
      <div class="px-3 py-2 text-sm font-extrabold">Trang <?= $page ?>/<?= $totalPages ?></div>
      <a class="px-3 py-2 rounded-xl border border-line text-sm font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>"
         href="?q=<?= urlencode($q) ?>&hanh_dong=<?= urlencode($hanh_dong) ?>&bang=<?= urlencode($bang) ?>&page=<?= $next ?>">Sau</a>
    </div>
  </div>
</div>

<!-- Modal JSON -->
<div id="jsonModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-6 z-50">
  <div class="bg-white rounded-2xl border border-line shadow-card w-full max-w-3xl">
    <div class="p-4 border-b border-line flex items-center justify-between">
      <div class="font-extrabold">Chi tiết JSON</div>
      <button class="size-10 rounded-xl border border-line hover:bg-slate-50 grid place-items-center"
              onclick="hideJson()">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div class="p-4">
      <pre id="jsonPre" class="text-xs bg-slate-50 border border-line rounded-xl p-4 overflow-auto max-h-[60vh]"></pre>
    </div>
  </div>
</div>

<script>
  function showJson(id, raw){
    const modal = document.getElementById('jsonModal');
    const pre = document.getElementById('jsonPre');
    try {
      const obj = JSON.parse(raw);
      pre.textContent = JSON.stringify(obj, null, 2);
    } catch(e){
      pre.textContent = raw;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
  function hideJson(){
    const modal = document.getElementById('jsonModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
</script>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
