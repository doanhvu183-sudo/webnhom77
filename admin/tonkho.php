<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['admin'])) {
  header("Location: dangnhap.php");
  exit;
}

function tableExists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE ?");
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}
function describeTable(PDO $pdo, string $table): array {
  $rows = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) $map[$r['Field']] = $r;
  return $map;
}
function pickCol(array $descMap, array $candidates): ?string {
  foreach ($candidates as $c) if (isset($descMap[$c])) return $c;
  return null;
}
function firstIdCol(array $descMap): ?string {
  foreach ($descMap as $field => $meta) {
    if (stripos($field, 'id_') === 0 || $field === 'id') return $field;
  }
  return array_key_first($descMap) ?: null;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!tableExists($pdo, 'sanpham')) die("Thiếu bảng sanpham.");
if (!tableExists($pdo, 'tonkho')) die("Thiếu bảng tonkho.");

$descSP = describeTable($pdo, 'sanpham');
$descTK = describeTable($pdo, 'tonkho');
$descDM = tableExists($pdo, 'danhmuc') ? describeTable($pdo, 'danhmuc') : [];

$SP_ID   = pickCol($descSP, ['id_san_pham','id_sanpham','id_sp','id']) ?? firstIdCol($descSP);
$SP_NAME = pickCol($descSP, ['ten_san_pham','ten','ten_sp','tieu_de']);
$SP_CAT  = pickCol($descSP, ['id_danh_muc','id_danhmuc','id_dm','ma_danh_muc']);
$SP_IMG  = pickCol($descSP, ['hinh_anh','image','anh','thumb']);
$SP_PRICE= pickCol($descSP, ['gia','gia_ban','don_gia','price']);

$DM_ID   = $descDM ? (pickCol($descDM, ['id_danh_muc','id_danhmuc','id_dm','id']) ?? firstIdCol($descDM)) : null;
$DM_NAME = $descDM ? pickCol($descDM, ['ten_danh_muc','ten','ten_dm','tieu_de']) : null;

$TK_ID = pickCol($descTK, ['id_tonkho','id']) ?? firstIdCol($descTK);
$TK_SP = pickCol($descTK, ['id_san_pham','id_sanpham','id_sp']);
$TK_QTY= pickCol($descTK, ['so_luong','ton','qty','quantity']);
$TK_UPD= pickCol($descTK, ['ngay_cap_nhat','updated_at','updated']);

if (!$TK_SP || !$TK_QTY) die("Bảng tonkho thiếu cột id_san_pham/so_luong.");

/* load categories map */
$dmMap = [];
if ($descDM && $DM_ID && $DM_NAME) {
  $rows = $pdo->query("SELECT `$DM_ID`,`$DM_NAME` FROM danhmuc")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) $dmMap[(string)$r[$DM_ID]] = $r[$DM_NAME];
}

$success = '';
$error = '';

/* ACTION: set stock / adjust */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['capnhat_ton'])) {
  $spId = (int)($_POST['sp_id'] ?? 0);
  $mode = $_POST['mode'] ?? 'set';
  $val  = (int)($_POST['value'] ?? 0);

  if ($spId <= 0) {
    $error = "Sản phẩm không hợp lệ.";
  } else {
    try {
      // check existing row
      $st = $pdo->prepare("SELECT `$TK_ID`, `$TK_QTY` FROM tonkho WHERE `$TK_SP`=? LIMIT 1");
      $st->execute([$spId]);
      $row = $st->fetch(PDO::FETCH_ASSOC);

      if (!$row) {
        // insert row
        $qty = ($mode === 'add') ? max(0, $val) : (($mode === 'sub') ? max(0, -$val) : max(0, $val));
        // if sub on empty => 0
        if ($mode === 'sub') $qty = 0;

        $sql = "INSERT INTO tonkho (`$TK_SP`,`$TK_QTY`".($TK_UPD? ",`$TK_UPD`":"").") VALUES (?,?,".($TK_UPD? "NOW()":"").")";
        if (!$TK_UPD) $sql = "INSERT INTO tonkho (`$TK_SP`,`$TK_QTY`) VALUES (?,?)";
        $pdo->prepare($sql)->execute([$spId, $qty]);

        $success = "Đã tạo tồn kho cho SP #$spId (tồn: $qty).";
      } else {
        $curQty = (int)$row[$TK_QTY];
        if ($mode === 'add') $newQty = max(0, $curQty + $val);
        elseif ($mode === 'sub') $newQty = max(0, $curQty - $val);
        else $newQty = max(0, $val);

        $sql = "UPDATE tonkho SET `$TK_QTY`=?".($TK_UPD? ", `$TK_UPD`=NOW()":"")." WHERE `$TK_SP`=? LIMIT 1";
        $pdo->prepare($sql)->execute([$newQty, $spId]);

        $success = "Đã cập nhật tồn kho SP #$spId: $curQty → $newQty.";
      }
    } catch (Throwable $e) {
      $error = "Lỗi cập nhật tồn kho: ".$e->getMessage();
    }
  }
}

/* filters */
$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$low = max(1, (int)($_GET['low'] ?? 5));

$where = [];
$params = [];
if ($q !== '' && $SP_NAME) {
  $where[] = "sp.`$SP_NAME` LIKE ?";
  $params[] = "%$q%";
}
if ($filter === 'out') {
  $where[] = "IFNULL(tk.`$TK_QTY`,0) <= 0";
}
if ($filter === 'low') {
  $where[] = "IFNULL(tk.`$TK_QTY`,0) > 0 AND IFNULL(tk.`$TK_QTY`,0) <= ?";
  $params[] = $low;
}
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

/* list */
$limit = 40;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;

$sql = "SELECT sp.`$SP_ID` AS sp_id, sp.`$SP_NAME` AS sp_ten"
     . ($SP_CAT ? ", sp.`$SP_CAT` AS sp_dm" : "")
     . ($SP_IMG ? ", sp.`$SP_IMG` AS sp_img" : "")
     . ($SP_PRICE ? ", sp.`$SP_PRICE` AS sp_gia" : "")
     . ", IFNULL(tk.`$TK_QTY`,0) AS ton"
     . " FROM sanpham sp"
     . " LEFT JOIN tonkho tk ON tk.`$TK_SP` = sp.`$SP_ID`"
     . " $whereSql"
     . " ORDER BY ton ASC, sp.`$SP_ID` DESC"
     . " LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$list = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT COUNT(*) FROM sanpham sp LEFT JOIN tonkho tk ON tk.`$TK_SP`=sp.`$SP_ID` $whereSql");
$st->execute($params);
$totalRows = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows/$limit));

/* stats */
$totalSP = (int)$pdo->query("SELECT COUNT(*) FROM sanpham")->fetchColumn();
$totalTon = (int)$pdo->query("SELECT IFNULL(SUM(`$TK_QTY`),0) FROM tonkho")->fetchColumn();
$outCount = (int)$pdo->query("SELECT COUNT(*) FROM sanpham sp LEFT JOIN tonkho tk ON tk.`$TK_SP`=sp.`$SP_ID` WHERE IFNULL(tk.`$TK_QTY`,0)<=0")->fetchColumn();
$lowCount = (int)$pdo->prepare("SELECT COUNT(*) FROM sanpham sp LEFT JOIN tonkho tk ON tk.`$TK_SP`=sp.`$SP_ID` WHERE IFNULL(tk.`$TK_QTY`,0)>0 AND IFNULL(tk.`$TK_QTY`,0)<=?")->execute([$low]) ? (int)$pdo->query("SELECT 0")->fetchColumn() : 0;
// workaround because execute() returns bool; do a separate query:
$st = $pdo->prepare("SELECT COUNT(*) FROM sanpham sp LEFT JOIN tonkho tk ON tk.`$TK_SP`=sp.`$SP_ID` WHERE IFNULL(tk.`$TK_QTY`,0)>0 AND IFNULL(tk.`$TK_QTY`,0)<=?");
$st->execute([$low]);
$lowCount = (int)$st->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Tồn kho</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: { primary:"#137fec", success:"#10b981", warning:"#f59e0b", danger:"#ef4444" },
      fontFamily: { display:["Manrope","sans-serif"] },
      boxShadow: { soft:"0 4px 20px -2px rgba(0,0,0,0.06)" }
    }
  }
}
</script>
<style>
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#94a3b8}
</style>
</head>

<body class="font-display bg-slate-100 text-slate-800 min-h-screen">
<div class="max-w-7xl mx-auto p-4 md:p-8 space-y-6">

  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <h1 class="text-2xl font-extrabold">Kho / Tồn kho</h1>
      <p class="text-sm text-slate-500">Quản lý tồn theo sản phẩm (set tồn, +/- nhanh, lọc hết hàng/low stock).</p>
    </div>
    <div class="flex gap-2">
      <a href="sanpham.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Sản phẩm</a>
      <a href="index.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Dashboard</a>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 font-bold text-sm"><?= h($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 font-bold text-sm"><?= h($error) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl shadow-soft border p-5">
      <div class="text-sm text-slate-500 font-bold">Tổng sản phẩm</div>
      <div class="text-2xl font-extrabold mt-1"><?= number_format($totalSP) ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow-soft border p-5">
      <div class="text-sm text-slate-500 font-bold">Tổng tồn</div>
      <div class="text-2xl font-extrabold mt-1"><?= number_format($totalTon) ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow-soft border p-5">
      <div class="text-sm text-slate-500 font-bold">Hết hàng</div>
      <div class="text-2xl font-extrabold mt-1 text-danger"><?= number_format($outCount) ?></div>
    </div>
    <div class="bg-white rounded-2xl shadow-soft border p-5">
      <div class="text-sm text-slate-500 font-bold">Low stock (<= <?= (int)$low ?>)</div>
      <div class="text-2xl font-extrabold mt-1 text-warning"><?= number_format($lowCount) ?></div>
    </div>
  </div>

  <!-- FILTER -->
  <div class="bg-white rounded-2xl shadow-soft border p-5">
    <form class="flex flex-wrap gap-2 items-center">
      <span class="material-symbols-outlined text-slate-400">search</span>
      <input name="q" value="<?= h($q) ?>" class="border rounded-xl px-3 py-2 text-sm w-72" placeholder="Tìm tên sản phẩm...">

      <select name="filter" class="border rounded-xl px-3 py-2 text-sm">
        <option value="" <?= $filter===''?'selected':'' ?>>Tất cả</option>
        <option value="out" <?= $filter==='out'?'selected':'' ?>>Hết hàng</option>
        <option value="low" <?= $filter==='low'?'selected':'' ?>>Low stock</option>
      </select>

      <input name="low" value="<?= (int)$low ?>" class="border rounded-xl px-3 py-2 text-sm w-28" inputmode="numeric" placeholder="Ngưỡng">
      <button class="px-4 py-2 rounded-xl bg-primary text-white font-extrabold">Lọc</button>
      <a href="tonkho.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Reset</a>
    </form>
  </div>

  <!-- LIST -->
  <div class="bg-white rounded-2xl shadow-soft border p-6 overflow-hidden">
    <div class="overflow-x-auto border rounded-xl">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase font-extrabold">
          <tr>
            <th class="p-3 text-left">SP</th>
            <th class="p-3 text-left">Danh mục</th>
            <th class="p-3 text-right">Giá</th>
            <th class="p-3 text-right">Tồn</th>
            <th class="p-3 text-right">Điều chỉnh</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
            <tr><td class="p-4 text-center text-slate-500" colspan="5">Không có dữ liệu.</td></tr>
          <?php else: ?>
            <?php foreach ($list as $r): ?>
              <?php
                $id = (int)$r['sp_id'];
                $ton = (int)$r['ton'];
                $dmName = '-';
                if ($SP_CAT && $DM_ID && $DM_NAME && isset($r['sp_dm']) && $r['sp_dm'] !== null) {
                  $dmName = $dmMap[(string)$r['sp_dm']] ?? ('#'.$r['sp_dm']);
                }
              ?>
              <tr class="border-t">
                <td class="p-3">
                  <div class="flex items-center gap-3">
                    <?php if ($SP_IMG && !empty($r['sp_img'])): ?>
                      <img src="../assets/img/<?= h($r['sp_img']) ?>" class="w-10 h-10 object-contain border rounded-lg bg-white" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="min-w-0">
                      <div class="font-extrabold">#<?= $id ?> - <?= h($r['sp_ten'] ?? '') ?></div>
                      <div class="text-xs text-slate-500">ID sản phẩm: <?= $id ?></div>
                    </div>
                  </div>
                </td>

                <td class="p-3"><?= h($dmName) ?></td>

                <td class="p-3 text-right font-extrabold">
                  <?= isset($r['sp_gia']) && $r['sp_gia'] !== null ? number_format((int)$r['sp_gia']).'₫' : '-' ?>
                </td>

                <td class="p-3 text-right font-extrabold <?= $ton<=0?'text-danger':'' ?>">
                  <?= number_format($ton) ?>
                </td>

                <td class="p-3 text-right whitespace-nowrap">
                  <!-- SET -->
                  <form method="post" class="inline-flex items-center gap-2">
                    <input type="hidden" name="capnhat_ton" value="1">
                    <input type="hidden" name="sp_id" value="<?= $id ?>">
                    <input type="hidden" name="mode" value="set">
                    <input name="value" value="<?= $ton ?>" class="w-24 border rounded-xl px-3 py-2 text-sm text-right" inputmode="numeric">
                    <button class="px-3 py-2 rounded-xl bg-primary text-white font-extrabold">Set</button>
                  </form>

                  <!-- + / - quick -->
                  <form method="post" class="inline-flex items-center gap-2 ml-2">
                    <input type="hidden" name="capnhat_ton" value="1">
                    <input type="hidden" name="sp_id" value="<?= $id ?>">
                    <input type="hidden" name="mode" value="add">
                    <input type="hidden" name="value" value="1">
                    <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">+1</button>
                  </form>

                  <form method="post" class="inline-flex items-center gap-2 ml-1">
                    <input type="hidden" name="capnhat_ton" value="1">
                    <input type="hidden" name="sp_id" value="<?= $id ?>">
                    <input type="hidden" name="mode" value="sub">
                    <input type="hidden" name="value" value="1">
                    <button class="px-3 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">-1</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <div class="flex items-center justify-between mt-4 text-sm">
      <div class="text-slate-500">
        Trang <b><?= $page ?></b> / <b><?= $totalPages ?></b> — Tổng <b><?= $totalRows ?></b>
      </div>
      <div class="flex gap-2">
        <?php
          $base = "tonkho.php?q=".urlencode($q)."&filter=".urlencode($filter)."&low=".(int)$low."&page=";
        ?>
        <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>"
           href="<?= $base.($page-1) ?>">Trước</a>
        <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>"
           href="<?= $base.($page+1) ?>">Sau</a>
      </div>
    </div>
  </div>

</div>
</body>
</html>
