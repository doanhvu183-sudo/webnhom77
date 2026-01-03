<?php
// admin/lienhe.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

// Ưu tiên helpers hệ thống nếu có
if (file_exists(__DIR__ . '/includes/helpers.php')) require_once __DIR__ . '/includes/helpers.php';
if (file_exists(__DIR__ . '/includes/hamChung.php')) require_once __DIR__ . '/includes/hamChung.php';

/* ===== SAFE HELPERS (chống redeclare) ===== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('qn')) {
  function qn($s){ return '`'.str_replace('`','', (string)$s).'`'; }
}
if (!function_exists('tableExists')) {
  function tableExists(PDO $pdo, $name){
    $st=$pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$name]);
    return (bool)$st->fetchColumn();
  }
}
if (!function_exists('getCols')) {
  function getCols(PDO $pdo, $table){
    $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
  }
}
if (!function_exists('pickCol')) {
  function pickCol(array $cols, array $cands){
    foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; }
    return null;
  }
}
if (!function_exists('redirectWith')) {
  function redirectWith(array $flash, string $to=null){
    $_SESSION['_flash'] = $flash;
    header("Location: ".($to ?: ($_SERVER['HTTP_REFERER'] ?? 'lienhe.php')));
    exit;
  }
}
if (!function_exists('flash')) {
  function flash(){
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
  }
}

/* ===== AUTH (tùy hệ thống bạn) ===== */
if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }

$me = $_SESSION['admin'] ?? [];
$role = strtoupper(trim((string)($me['vai_tro'] ?? $me['role'] ?? 'NHANVIEN')));
$isAdmin = ($role === 'ADMIN');

if (function_exists('requirePermission')) {
  requirePermission('lienhe'); // nếu bạn có chucnang "lienhe" thì sẽ check
}

$ACTIVE = 'lienhe';
$PAGE_TITLE = 'Liên hệ';

/* ===== CHECK TABLE ===== */
if (!tableExists($pdo,'lienhe')) {
  // Không include UI ở đây để tránh header warning / output lẫn lộn
  die("Thiếu bảng lienhe. Hãy tạo bảng lienhe trước.");
}

$cols = getCols($pdo,'lienhe');
$C_ID    = pickCol($cols, ['id_lien_he','id_lienhe','id','id_contact']);
$C_NAME  = pickCol($cols, ['ho_ten','hoten','ten','full_name','name']);
$C_EMAIL = pickCol($cols, ['email']);
$C_SUBJ  = pickCol($cols, ['tieu_de','tieude','subject','chu_de']);
$C_MSG   = pickCol($cols, ['noi_dung','noidung','message','content']);
$C_TIME  = pickCol($cols, ['ngay_tao','created_at','thoi_gian','createdOn']);
$C_SEEN  = pickCol($cols, ['da_xem','is_read','trang_thai','status']);
$C_NOTE  = pickCol($cols, ['ghi_chu_admin','ghi_chu','note','admin_note']);

if (!$C_ID) die("Bảng lienhe thiếu cột ID (id/id_lien_he...).");

/* ===== Normalize read/unread in code =====
   - Nếu có da_xem / is_read: 1 = đã xem, 0 = chưa xem
   - Nếu chỉ có trang_thai/status: nhận 'READ/UNREAD' hoặc 'DA_XEM/CHUA_XEM'...
*/
function is_read_value($v): bool {
  $v = is_null($v) ? '' : (string)$v;
  $lv = strtolower(trim($v));
  if ($lv === '') return false;
  if (is_numeric($lv)) return ((int)$lv) === 1;
  return in_array($lv, ['read','da_xem','seen','done','1','true','yes'], true);
}
function make_read_store_value($colName, $toRead): array {
  // return [value_to_store, is_numeric]
  $toRead = (bool)$toRead;
  $lc = strtolower($colName);
  // nếu cột kiểu da_xem/is_read => số
  if (in_array($lc, ['da_xem','is_read'], true)) return [$toRead ? 1 : 0, true];
  // nếu status/trang_thai => chữ
  return [$toRead ? 'READ' : 'UNREAD', false];
}

/* ===== POST ACTIONS (MUST be before UI includes) ===== */
$idSelected = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? $idSelected);

  // Chỉ admin được xóa (bạn có thể nới nếu muốn)
  if ($action === 'delete' && !$isAdmin) {
    redirectWith(['type'=>'error','msg'=>'Bạn không có quyền xóa liên hệ.']);
  }

  // Helper redirect back
  $back = function($id=null){
    $to = 'lienhe.php';
    if ($id !== null && $id > 0) $to .= '?id='.(int)$id;
    return $to;
  };

  if ($action === 'mark_read' || $action === 'mark_unread') {
    if ($id <= 0) redirectWith(['type'=>'error','msg'=>'Thiếu ID liên hệ.']);
    if (!$C_SEEN) redirectWith(['type'=>'error','msg'=>'Bảng lienhe chưa có cột da_xem/is_read/trang_thai.']);

    $toRead = ($action === 'mark_read');
    [$val, $isNum] = make_read_store_value($C_SEEN, $toRead);

    $sql = "UPDATE lienhe SET ".qn($C_SEEN)." = ".($isNum ? "?" : "?")." WHERE ".qn($C_ID)." = ?";
    $pdo->prepare($sql)->execute([$val, $id]);

    redirectWith(['type'=>'ok','msg'=> $toRead ? 'Đã đánh dấu đã xem.' : 'Đã đánh dấu chưa xem.'], $back($id));
  }

  if ($action === 'save_note') {
    if ($id <= 0) redirectWith(['type'=>'error','msg'=>'Thiếu ID liên hệ.']);
    if (!$C_NOTE) redirectWith(['type'=>'error','msg'=>'Bảng lienhe chưa có cột ghi_chu_admin/note.']);

    $note = trim((string)($_POST['note'] ?? ''));
    $pdo->prepare("UPDATE lienhe SET ".qn($C_NOTE)."=? WHERE ".qn($C_ID)."=?")->execute([$note, $id]);

    redirectWith(['type'=>'ok','msg'=>'Đã lưu ghi chú.'], $back($id));
  }

  if ($action === 'delete') {
    if ($id <= 0) redirectWith(['type'=>'error','msg'=>'Thiếu ID liên hệ.']);
    $pdo->prepare("DELETE FROM lienhe WHERE ".qn($C_ID)."=?")->execute([$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã xóa liên hệ.'], 'lienhe.php');
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.'], $back($id));
}

/* ===== LIST QUERY ===== */
$q = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? '')); // unread/read/all

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$off = ($page-1)*$perPage;

$where = [];
$bind = [];

if ($q !== '') {
  $like = '%'.$q.'%';
  $parts = [];
  if ($C_NAME)  $parts[] = qn($C_NAME)." LIKE :q";
  if ($C_EMAIL) $parts[] = qn($C_EMAIL)." LIKE :q";
  if ($C_SUBJ)  $parts[] = qn($C_SUBJ)." LIKE :q";
  if ($parts) { $where[] = '('.implode(' OR ', $parts).')'; $bind[':q'] = $like; }
}

if ($C_SEEN && ($filter === 'unread' || $filter === 'read')) {
  // lọc theo giá trị lưu (numeric hoặc text)
  [$valRead, $isNum] = make_read_store_value($C_SEEN, true);
  [$valUnread, $isNum2] = make_read_store_value($C_SEEN, false);

  if ($filter === 'read') {
    $where[] = qn($C_SEEN)." = :seen";
    $bind[':seen'] = $valRead;
  } else {
    $where[] = qn($C_SEEN)." = :seen";
    $bind[':seen'] = $valUnread;
  }
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) FROM lienhe $whereSql";
$st = $pdo->prepare($countSql);
$st->execute($bind);
$totalRows = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows/$perPage));

$listCols = [qn($C_ID)." AS _id"];
if ($C_NAME)  $listCols[] = qn($C_NAME)." AS ho_ten";
if ($C_EMAIL) $listCols[] = qn($C_EMAIL)." AS email";
if ($C_SUBJ)  $listCols[] = qn($C_SUBJ)." AS tieu_de";
if ($C_MSG)   $listCols[] = qn($C_MSG)." AS noi_dung";
if ($C_TIME)  $listCols[] = qn($C_TIME)." AS created_at";
if ($C_SEEN)  $listCols[] = qn($C_SEEN)." AS da_xem";

$orderBy = $C_TIME ? qn($C_TIME)." DESC" : qn($C_ID)." DESC";

$sqlList = "SELECT ".implode(',', $listCols)." FROM lienhe $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $off";
$st = $pdo->prepare($sqlList);
$st->execute($bind);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($idSelected <= 0 && $rows) $idSelected = (int)$rows[0]['_id'];

/* ===== DETAIL ===== */
$detail = null;
if ($idSelected > 0) {
  $st = $pdo->prepare("SELECT * FROM lienhe WHERE ".qn($C_ID)."=? LIMIT 1");
  $st->execute([$idSelected]);
  $detail = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ===== AUTO MARK READ when open detail (optional) ===== */
if ($detail && $C_SEEN) {
  $curSeen = $detail[$C_SEEN] ?? null;
  $isRead = is_read_value($curSeen);
  if (!$isRead) {
    // mark read silently
    [$val, $isNum] = make_read_store_value($C_SEEN, true);
    $pdo->prepare("UPDATE lienhe SET ".qn($C_SEEN)."=? WHERE ".qn($C_ID)."=?")->execute([$val, $idSelected]);
    // update local
    $detail[$C_SEEN] = $val;
  }
}

/* ===== INCLUDES UI ===== */
$incDau  = file_exists(__DIR__ . '/includes/giaoDienDau.php') ? __DIR__ . '/includes/giaoDienDau.php' : null;
$incBen  = file_exists(__DIR__ . '/includes/thanhBen.php')    ? __DIR__ . '/includes/thanhBen.php'    : (file_exists(__DIR__ . '/includes/thanhben.php') ? __DIR__ . '/includes/thanhben.php' : null);
$incTren = file_exists(__DIR__ . '/includes/thanhTren.php')   ? __DIR__ . '/includes/thanhTren.php'   : null;
$incCuoi = file_exists(__DIR__ . '/includes/giaoDienCuoi.php')? __DIR__ . '/includes/giaoDienCuoi.php': null;

if ($incDau) require_once $incDau;
if ($incBen) require_once $incBen;
if ($incTren) require_once $incTren;

$f = flash();
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <?php if($f): ?>
      <div class="rounded-2xl border border-line bg-white shadow-card p-4">
        <div class="text-sm font-extrabold <?= ($f['type']??'')==='ok'?'text-green-600':'text-red-600' ?>">
          <?= h($f['msg'] ?? '') ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
      <div>
        <div class="text-xl font-extrabold">Liên hệ</div>
        <div class="text-xs text-muted font-bold">Xem và xử lý phản hồi khách hàng</div>
      </div>

      <div class="flex items-center gap-2">
        <?php if($C_SEEN): ?>
          <a href="lienhe.php?filter=unread" class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $filter==='unread'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 hover:bg-slate-50' ?>">Chưa xem</a>
          <a href="lienhe.php?filter=read" class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $filter==='read'?'bg-primary/10 text-primary border-primary/20':'bg-white text-slate-700 hover:bg-slate-50' ?>">Đã xem</a>
          <a href="lienhe.php" class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $filter===''?'bg-slate-900 text-white border-slate-900':'bg-white text-slate-700 hover:bg-slate-50' ?>">Tất cả</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- LEFT: list -->
      <div class="lg:col-span-7 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between gap-3">
          <div class="text-base font-extrabold">Danh sách liên hệ</div>
          <div class="text-xs text-muted font-bold">Tổng: <?= number_format($totalRows) ?></div>
        </div>

        <form class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3" method="get">
          <div class="md:col-span-10">
            <input name="q" value="<?= h($q) ?>" placeholder="Tìm họ tên / email / tiêu đề..." class="w-full px-4 py-2.5 rounded-xl border border-line bg-white text-sm font-bold outline-none focus:ring-2 focus:ring-primary/20">
          </div>
          <div class="md:col-span-2">
            <button class="w-full px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-extrabold">Tìm</button>
          </div>
          <?php if($filter!==''): ?>
            <input type="hidden" name="filter" value="<?= h($filter) ?>">
          <?php endif; ?>
        </form>

        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Khách</th>
                <th class="text-left py-3 pr-3">Tiêu đề</th>
                <th class="text-left py-3 pr-3">Thời gian</th>
                <th class="text-right py-3 pr-0">Xem</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach($rows as $r):
                $rid=(int)$r['_id'];
                $name=(string)($r['ho_ten'] ?? ('#'.$rid));
                $email=(string)($r['email'] ?? '');
                $subj=(string)($r['tieu_de'] ?? '');
                $time=(string)($r['created_at'] ?? '');
                $seenVal = $r['da_xem'] ?? null;
                $isRead = $C_SEEN ? is_read_value($seenVal) : true;

                $isSel = ($rid===$idSelected);
                $initial = mb_strtoupper(mb_substr(trim($name ?: $email ?: '#'),0,1,'UTF-8'),'UTF-8');
              ?>
                <tr class="<?= $isSel?'bg-primary/5':'' ?>">
                  <td class="py-3 pr-3">
                    <div class="flex items-center gap-3">
                      <div class="size-10 rounded-2xl <?= $isRead?'bg-slate-100':'bg-amber-50' ?> grid place-items-center font-extrabold">
                        <?= h($initial) ?>
                      </div>
                      <div class="min-w-0">
                        <div class="font-extrabold truncate">
                          <?= h($name) ?>
                          <?php if($C_SEEN && !$isRead): ?>
                            <span class="ml-2 px-2 py-0.5 rounded-full text-[11px] font-extrabold bg-amber-50 text-amber-700 align-middle">Mới</span>
                          <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted font-bold truncate"><?= h($email) ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 pr-3">
                    <div class="font-bold truncate max-w-[280px]"><?= h($subj ?: '-') ?></div>
                  </td>
                  <td class="py-3 pr-3">
                    <div class="text-xs text-muted font-bold"><?= h($time ?: '-') ?></div>
                  </td>
                  <td class="py-3 pr-0 text-right">
                    <a href="lienhe.php?id=<?= $rid ?><?= $q!==''?('&q='.urlencode($q)) : '' ?><?= $filter!==''?('&filter='.urlencode($filter)) : '' ?>"
                       class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-900 font-extrabold text-xs">Chi tiết</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$rows): ?>
                <tr><td colspan="4" class="py-8 text-center text-slate-500 font-bold">Không có dữ liệu</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-4 flex items-center justify-between">
          <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?></div>
          <div class="flex items-center gap-2">
            <?php
              $base = "lienhe.php?q=".urlencode($q)."&filter=".urlencode($filter);
            ?>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.max(1,$page-1)) ?>">Trước</a>
            <a class="px-3 py-2 rounded-xl border border-line text-xs font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
               href="<?= h($base.'&page='.min($totalPages,$page+1)) ?>">Sau</a>
          </div>
        </div>
      </div>

      <!-- RIGHT: detail -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="flex items-center justify-between">
            <div class="text-base font-extrabold">Chi tiết liên hệ</div>
            <div class="text-xs text-muted font-bold"><?= $detail?('ID: '.$idSelected):'Chọn 1 liên hệ' ?></div>
          </div>

          <?php if(!$detail): ?>
            <div class="mt-4 rounded-2xl border border-line bg-slate-50 p-4">
              <div class="text-sm font-extrabold">Chưa chọn liên hệ</div>
              <div class="text-xs text-muted font-bold mt-1">Chọn 1 dòng bên trái để xem nội dung.</div>
            </div>
          <?php else: ?>
            <?php
              $dName = (string)($C_NAME ? ($detail[$C_NAME] ?? '') : '');
              $dEmail= (string)($C_EMAIL? ($detail[$C_EMAIL]??'') : '');
              $dSubj = (string)($C_SUBJ ? ($detail[$C_SUBJ] ?? '') : '');
              $dMsg  = (string)($C_MSG  ? ($detail[$C_MSG]  ?? '') : '');
              $dTime = (string)($C_TIME ? ($detail[$C_TIME] ?? '') : '');
              $dSeen = $C_SEEN ? is_read_value($detail[$C_SEEN] ?? null) : true;
              $dNote = $C_NOTE ? (string)($detail[$C_NOTE] ?? '') : '';
            ?>

            <div class="mt-4">
              <div class="text-sm font-extrabold"><?= h($dSubj ?: 'Không tiêu đề') ?></div>
              <div class="text-xs text-muted font-bold mt-1">
                <?= h($dName ?: '-') ?> · <?= h($dEmail ?: '-') ?><?= $dTime?(' · '.h($dTime)) : '' ?>
              </div>

              <div class="mt-4 rounded-2xl border border-line bg-[#fbfdff] p-4">
                <div class="text-xs text-muted font-extrabold mb-2">Nội dung</div>
                <div class="text-sm text-slate-800 font-bold whitespace-pre-wrap"><?= h($dMsg ?: '-') ?></div>
              </div>

              <?php if($C_SEEN): ?>
                <div class="mt-4 grid grid-cols-2 gap-3">
                  <form method="post">
                    <input type="hidden" name="action" value="<?= $dSeen?'mark_unread':'mark_read' ?>">
                    <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                    <button class="w-full px-4 py-2.5 rounded-xl <?= $dSeen?'bg-slate-100 text-slate-800':'bg-amber-50 text-amber-700' ?> font-extrabold text-sm">
                      <?= $dSeen?'Đánh dấu chưa xem':'Đánh dấu đã xem' ?>
                    </button>
                  </form>

                  <form method="post" onsubmit="return confirm('Xóa liên hệ này?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                    <button class="w-full px-4 py-2.5 rounded-xl bg-red-50 text-red-600 font-extrabold text-sm <?= $isAdmin?'':'opacity-50 pointer-events-none' ?>">
                      Xóa
                    </button>
                    <?php if(!$isAdmin): ?>
                      <div class="text-[11px] text-red-600 font-extrabold mt-2">Chỉ ADMIN được xóa.</div>
                    <?php endif; ?>
                  </form>
                </div>
              <?php endif; ?>

              <div class="mt-5">
                <div class="text-sm font-extrabold">Ghi chú nội bộ</div>
                <?php if(!$C_NOTE): ?>
                  <div class="text-xs text-red-600 font-extrabold mt-2">Bảng lienhe chưa có cột ghi_chu_admin/note.</div>
                <?php else: ?>
                  <form method="post" class="mt-3 space-y-3">
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="id" value="<?= (int)$idSelected ?>">
                    <textarea name="note" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Ghi chú xử lý..."><?= h($dNote) ?></textarea>
                    <button class="w-full px-4 py-2.5 rounded-xl bg-slate-900 text-white font-extrabold text-sm">Lưu ghi chú</button>
                  </form>
                <?php endif; ?>
              </div>

            </div>
          <?php endif; ?>
        </div>

        <!-- Hint SQL -->
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="text-sm font-extrabold">Gợi ý nâng cấp bảng lienhe</div>
          <div class="text-xs text-muted font-bold mt-1">Nếu muốn dùng đầy đủ “đã xem” + “ghi chú admin”, thêm cột như dưới.</div>
          <details class="mt-3">
            <summary class="cursor-pointer text-xs font-extrabold text-slate-700">SQL bổ sung cột</summary>
            <pre class="mt-2 text-xs bg-slate-50 border border-line rounded-xl p-3 overflow-auto"><?php echo h(
"ALTER TABLE lienhe
  ADD COLUMN da_xem TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN ghi_chu_admin TEXT NULL,
  ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;"
); ?></pre>
          </details>
        </div>

      </div>
    </div>
  </div>
</div>

<?php if ($incCuoi) require_once $incCuoi; ?>
