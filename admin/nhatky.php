<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';

if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE='nhatky';
$title='Nhật ký hoạt động';

$TABLE = 'nhatky_hoatdong';

if(!tableExists($pdo,$TABLE)){
  $rows=[];
  $total=0;
} else {

  // ===== Filters (thêm) =====
  $q = trim((string)($_GET['q'] ?? ''));
  $hanh_dong = trim((string)($_GET['hanh_dong'] ?? ''));
  $bang = trim((string)($_GET['bang'] ?? ''));
  $id_admin_f = trim((string)($_GET['id_admin'] ?? ''));
  $from = trim((string)($_GET['from'] ?? ''));
  $to   = trim((string)($_GET['to'] ?? ''));

  $page = max(1,(int)($_GET['page'] ?? 1));
  $perPage = 25;
  $offset = ($page-1)*$perPage;

  $where = " WHERE 1 ";
  $params = [];

  if ($q !== '') {
    $where .= " AND (hanh_dong LIKE ? OR mo_ta LIKE ? OR bang_lien_quan LIKE ? OR du_lieu_json LIKE ? OR user_agent LIKE ?) ";
    $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%";
  }
  if ($hanh_dong !== '') { $where .= " AND hanh_dong = ? "; $params[] = $hanh_dong; }
  if ($bang !== '')      { $where .= " AND bang_lien_quan = ? "; $params[] = $bang; }
  if ($id_admin_f !== '' && ctype_digit($id_admin_f)) { $where .= " AND id_admin = ? "; $params[] = (int)$id_admin_f; }

  if ($from !== '') { $where .= " AND DATE(ngay_tao) >= ? "; $params[] = $from; }
  if ($to   !== '') { $where .= " AND DATE(ngay_tao) <= ? "; $params[] = $to; }

  // Count
  $st = $pdo->prepare("SELECT COUNT(*) FROM {$TABLE} {$where}");
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  // Rows
  $st = $pdo->prepare("
    SELECT *
    FROM {$TABLE}
    {$where}
    ORDER BY id_log DESC
    LIMIT {$perPage} OFFSET {$offset}
  ");
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $totalPages = max(1,(int)ceil($total/$perPage));

  // Distinct (để filter)
  $hanhList = $pdo->query("SELECT DISTINCT hanh_dong FROM {$TABLE} WHERE hanh_dong IS NOT NULL AND hanh_dong<>'' ORDER BY hanh_dong ASC")->fetchAll(PDO::FETCH_COLUMN);
  $bangList = $pdo->query("SELECT DISTINCT bang_lien_quan FROM {$TABLE} WHERE bang_lien_quan IS NOT NULL AND bang_lien_quan<>'' ORDER BY bang_lien_quan ASC")->fetchAll(PDO::FETCH_COLUMN);
}

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- LEFT: TABLE -->
  <div class="lg:col-span-2 bg-white border border-slate-100 rounded-2xl shadow-soft p-4 md:p-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="text-lg font-extrabold">Nhật ký hoạt động</div>
        <div class="text-xs text-slate-500 font-bold mt-1">Hiển thị đầy đủ cột theo bảng <b><?= h($TABLE) ?></b></div>
      </div>
      <div class="text-xs text-slate-500 font-bold">Tổng: <b><?= number_format($total ?? 0) ?></b></div>
    </div>

    <?php if(!tableExists($pdo,$TABLE)): ?>
      <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-600">
        Thiếu bảng <b><?= h($TABLE) ?></b>.
      </div>
    <?php else: ?>

      <!-- FILTERS -->
      <form method="get" class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-2">
        <input name="q" value="<?= h($q ?? '') ?>" class="md:col-span-2 rounded-xl border-slate-200 bg-slate-50" placeholder="Tìm nhanh...">

        <select name="hanh_dong" class="rounded-xl border-slate-200 bg-slate-50">
          <option value="">Tất cả hành động</option>
          <?php foreach(($hanhList ?? []) as $x): ?>
            <option value="<?= h($x) ?>" <?= (($hanh_dong ?? '')===$x?'selected':'') ?>><?= h($x) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="bang" class="rounded-xl border-slate-200 bg-slate-50">
          <option value="">Tất cả bảng</option>
          <?php foreach(($bangList ?? []) as $x): ?>
            <option value="<?= h($x) ?>" <?= (($bang ?? '')===$x?'selected':'') ?>><?= h($x) ?></option>
          <?php endforeach; ?>
        </select>

        <input name="id_admin" value="<?= h($id_admin_f ?? '') ?>" class="rounded-xl border-slate-200 bg-slate-50" placeholder="ID admin">

        <input type="date" name="from" value="<?= h($from ?? '') ?>" class="rounded-xl border-slate-200 bg-slate-50">
        <input type="date" name="to" value="<?= h($to ?? '') ?>" class="rounded-xl border-slate-200 bg-slate-50">

        <div class="md:col-span-5 flex gap-2 justify-end">
          <a href="nhatky.php" class="px-4 py-2 rounded-xl border bg-white font-extrabold text-sm">Xóa lọc</a>
          <button class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold text-sm">Lọc</button>
        </div>
      </form>

      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-slate-500">
              <th class="text-left py-3 pr-3">Thời gian</th>
              <th class="text-left py-3 pr-3">Ai thao tác</th>
              <th class="text-left py-3 pr-3">Hành động</th>
              <th class="text-left py-3 pr-3">Bảng</th>
              <th class="text-left py-3 pr-3">ID bản ghi</th>
              <th class="text-left py-3 pr-3">Mô tả</th>
              <th class="text-left py-3 pr-3">IP</th>
              <th class="text-right py-3">Xem</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100">
            <?php foreach($rows as $r):
              $id_log = (int)($r['id_log'] ?? 0);
              $aid = $r['id_admin'] ?? '';
              $vt  = $r['vai_tro'] ?? '';
              $hd  = $r['hanh_dong'] ?? '';
              $mo  = $r['mo_ta'] ?? '';
              $b   = $r['bang_lien_quan'] ?? '';
              $idb = $r['id_ban_ghi'] ?? '';
              $js  = $r['du_lieu_json'] ?? '';
              $ip  = $r['ip'] ?? '';
              $ua  = $r['user_agent'] ?? '';
              $t   = $r['ngay_tao'] ?? '';
              $mo_short = mb_strimwidth((string)$mo,0,60,'...');
            ?>
              <tr class="hover:bg-slate-50">
                <td class="py-3 pr-3 text-xs text-slate-500"><?= h($t) ?></td>
                <td class="py-3 pr-3 font-extrabold"><?= h(($aid!=='' ? ('#'.$aid) : 'NULL')) ?> <span class="text-xs text-slate-500 font-bold">(<?= h($vt ?: '-') ?>)</span></td>
                <td class="py-3 pr-3 font-extrabold"><?= h($hd) ?></td>
                <td class="py-3 pr-3"><?= h($b) ?></td>
                <td class="py-3 pr-3"><?= h($idb) ?></td>
                <td class="py-3 pr-3"><?= h($mo_short) ?></td>
                <td class="py-3 pr-3 text-xs text-slate-500"><?= h($ip) ?></td>
                <td class="py-3 text-right">
                  <button type="button"
                    class="px-3 py-2 rounded-xl border bg-white font-extrabold text-sm hover:bg-slate-50"
                    onclick="previewLog(this)"
                    data-id="<?= h($id_log) ?>"
                    data-time="<?= h($t) ?>"
                    data-admin="<?= h(($aid!==''?('#'.$aid):'NULL')) ?>"
                    data-role="<?= h($vt) ?>"
                    data-action="<?= h($hd) ?>"
                    data-table="<?= h($b) ?>"
                    data-record="<?= h($idb) ?>"
                    data-desc="<?= h($mo) ?>"
                    data-json="<?= h($js) ?>"
                    data-ip="<?= h($ip) ?>"
                    data-ua="<?= h($ua) ?>"
                  >Chi tiết</button>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if(!$rows): ?>
              <tr><td colspan="8" class="py-8 text-center text-slate-500 font-bold">Chưa có log</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if(($total ?? 0) > 0): ?>
      <div class="mt-4 flex items-center justify-between">
        <div class="text-xs text-slate-500 font-bold">
          Trang <?= (int)($page ?? 1) ?>/<?= (int)($totalPages ?? 1) ?>
        </div>
        <div class="flex gap-2">
          <?php
            $qs = $_GET;
            $mk = function($p) use($qs){
              $qs['page']=$p;
              return 'nhatky.php?'.http_build_query($qs);
            };
            $cur = (int)($page ?? 1);
            $maxp = (int)($totalPages ?? 1);
          ?>
          <a class="px-3 py-2 rounded-xl border bg-white text-sm font-extrabold <?= $cur<=1?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h($mk(max(1,$cur-1))) ?>">Trước</a>
          <a class="px-3 py-2 rounded-xl border bg-white text-sm font-extrabold <?= $cur>=$maxp?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h($mk(min($maxp,$cur+1))) ?>">Sau</a>
        </div>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <!-- RIGHT: PREVIEW -->
  <div class="bg-white border border-slate-100 rounded-2xl shadow-soft p-4 md:p-6">
    <div class="text-sm font-extrabold">Xem nhanh</div>
    <div class="text-xs text-slate-500 font-bold mt-1">Bấm “Chi tiết” để xem đầy đủ.</div>

    <div class="mt-4 space-y-3">
      <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
        <div class="text-xs text-slate-500 font-extrabold">Thời gian</div>
        <div id="pv_time" class="font-extrabold mt-1">—</div>
      </div>

      <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
        <div class="text-xs text-slate-500 font-extrabold">Ai thao tác</div>
        <div id="pv_actor" class="font-extrabold mt-1">—</div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
          <div class="text-xs text-slate-500 font-extrabold">Hành động</div>
          <div id="pv_action" class="font-extrabold mt-1">—</div>
        </div>
        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
          <div class="text-xs text-slate-500 font-extrabold">Bảng</div>
          <div id="pv_table" class="font-extrabold mt-1">—</div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
          <div class="text-xs text-slate-500 font-extrabold">ID bản ghi</div>
          <div id="pv_record" class="font-extrabold mt-1">—</div>
        </div>
        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
          <div class="text-xs text-slate-500 font-extrabold">IP</div>
          <div id="pv_ip" class="font-extrabold mt-1">—</div>
        </div>
      </div>

      <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
        <div class="text-xs text-slate-500 font-extrabold">Mô tả</div>
        <div id="pv_desc" class="font-bold mt-2 whitespace-pre-wrap text-slate-800">—</div>
      </div>

      <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
        <div class="text-xs text-slate-500 font-extrabold">Dữ liệu JSON</div>
        <pre id="pv_json" class="mt-2 text-xs font-bold text-slate-700 whitespace-pre-wrap">—</pre>
      </div>

      <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
        <div class="text-xs text-slate-500 font-extrabold">User-Agent</div>
        <div id="pv_ua" class="mt-2 text-xs font-bold text-slate-700 break-words">—</div>
      </div>
    </div>
  </div>

</div>

<script>
function previewLog(btn){
  const admin = (btn.dataset.admin || '—') + (btn.dataset.role ? (' ('+btn.dataset.role+')') : '');
  document.getElementById('pv_time').textContent   = btn.dataset.time || '—';
  document.getElementById('pv_actor').textContent  = admin;
  document.getElementById('pv_action').textContent = btn.dataset.action || '—';
  document.getElementById('pv_table').textContent  = btn.dataset.table || '—';
  document.getElementById('pv_record').textContent = btn.dataset.record || '—';
  document.getElementById('pv_ip').textContent     = btn.dataset.ip || '—';
  document.getElementById('pv_desc').textContent   = btn.dataset.desc || '—';
  document.getElementById('pv_json').textContent   = (btn.dataset.json && btn.dataset.json.trim()) ? btn.dataset.json : '—';
  document.getElementById('pv_ua').textContent     = btn.dataset.ua || '—';
}
</script>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
