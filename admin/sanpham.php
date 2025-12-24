<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== AUTH ================== */
if (!isset($_SESSION['admin'])) {
  header("Location: dangnhap.php");
  exit;
}

$roleRaw = $_SESSION['admin']['vai_tro'] ?? 'ADMIN';
$ROLE = strtoupper((string)$roleRaw);
$isAdmin = (strpos($ROLE, 'ADMIN') !== false) || (strpos($ROLE, 'QUAN') !== false) || in_array($ROLE, ['ROOT','SUPERADMIN','SUPER_ADMIN'], true);

/* ================== HELPERS: schema detect ================== */
function tableExists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("SHOW TABLES LIKE ?");
  $st->execute([$table]);
  return (bool)$st->fetchColumn();
}

function describeTable(PDO $pdo, string $table): array {
  $rows = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
  $map = [];
  foreach ($rows as $r) $map[$r['Field']] = $r;
  return $map; // field => row
}

function pickCol(array $descMap, array $candidates): ?string {
  foreach ($candidates as $c) {
    if (isset($descMap[$c])) return $c;
  }
  return null;
}

function firstIdCol(array $descMap): ?string {
  foreach ($descMap as $field => $meta) {
    if (stripos($field, 'id_') === 0 || $field === 'id') return $field;
  }
  return array_key_first($descMap) ?: null;
}

function isAutoIncrement(array $descRow): bool {
  return isset($descRow['Extra']) && stripos($descRow['Extra'], 'auto_increment') !== false;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ================== TABLES ================== */
if (!tableExists($pdo, 'sanpham')) die("Thiếu bảng sanpham trong DB.");
if (!tableExists($pdo, 'danhmuc')) die("Thiếu bảng danhmuc trong DB.");

$descSP = describeTable($pdo, 'sanpham');
$descDM = describeTable($pdo, 'danhmuc');

$SP_ID   = pickCol($descSP, ['id_san_pham','id_sanpham','id_sp','id']) ?? firstIdCol($descSP);
$SP_NAME = pickCol($descSP, ['ten_san_pham','ten','ten_sp','tieu_de']);
$SP_PRICE= pickCol($descSP, ['gia','gia_ban','don_gia','price']);
$SP_SALE = pickCol($descSP, ['gia_khuyen_mai','gia_sale','sale_price','giam_gia']);
$SP_IMG  = pickCol($descSP, ['hinh_anh','image','anh','thumb']);
$SP_DESC = pickCol($descSP, ['mo_ta','noi_dung','description']);
$SP_STT  = pickCol($descSP, ['trang_thai','is_active','hien_thi']);
$SP_CAT  = pickCol($descSP, ['id_danh_muc','id_danhmuc','id_dm','ma_danh_muc']);

$SP_CREATED = pickCol($descSP, ['ngay_tao','created_at','created']);
$SP_UPDATED = pickCol($descSP, ['ngay_cap_nhat','updated_at','updated']);

$DM_ID   = pickCol($descDM, ['id_danh_muc','id_danhmuc','id_dm','id']) ?? firstIdCol($descDM);
$DM_NAME = pickCol($descDM, ['ten_danh_muc','ten','ten_dm','tieu_de']);
$DM_STT  = pickCol($descDM, ['trang_thai','is_active','hien_thi']);
$DM_DESC = pickCol($descDM, ['mo_ta','ghi_chu','noi_dung']);
$DM_CREATED = pickCol($descDM, ['ngay_tao','created_at','created']);

$hasTonkho = tableExists($pdo, 'tonkho');
$descTK = $hasTonkho ? describeTable($pdo, 'tonkho') : [];
$TK_SP = $hasTonkho ? (pickCol($descTK, ['id_san_pham','id_sanpham','id_sp']) ?? null) : null;
$TK_QTY= $hasTonkho ? (pickCol($descTK, ['so_luong','ton','qty','quantity']) ?? null) : null;

/* ================== UI tab ================== */
$tab = $_GET['tab'] ?? 'sanpham';
if (!in_array($tab, ['sanpham','danhmuc'], true)) $tab = 'sanpham';

$success = '';
$error = '';

/* ================== ACTION: CATEGORY ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  /* ADD/UPDATE category */
  if (isset($_POST['save_danhmuc'])) {
    $id = trim($_POST['dm_id'] ?? '');
    $ten = trim($_POST['dm_ten'] ?? '');
    $mota = trim($_POST['dm_mota'] ?? '');

    if ($DM_NAME === null) {
      $error = "Bảng danhmuc thiếu cột tên (ten_danh_muc/ten).";
    } elseif ($ten === '') {
      $error = "Vui lòng nhập tên danh mục.";
    } else {
      try {
        if ($id === '') {
          // insert
          $fields = [$DM_NAME];
          $vals = [$ten];
          if ($DM_DESC) { $fields[] = $DM_DESC; $vals[] = ($mota === '' ? null : $mota); }
          if ($DM_STT)  { $fields[] = $DM_STT;  $vals[] = 1; }
          if ($DM_CREATED) { /* let default handle if exists */ }

          // handle non-autoinc id
          $needId = $DM_ID && isset($descDM[$DM_ID]) && !isAutoIncrement($descDM[$DM_ID]) && ($descDM[$DM_ID]['Null'] === 'NO') && ($descDM[$DM_ID]['Default'] === null);
          if ($needId) {
            $nextId = (int)$pdo->query("SELECT IFNULL(MAX(`$DM_ID`),0)+1 FROM danhmuc")->fetchColumn();
            array_unshift($fields, $DM_ID);
            array_unshift($vals, $nextId);
          }

          $sql = "INSERT INTO danhmuc (`" . implode("`,`", $fields) . "`) VALUES (" . rtrim(str_repeat("?,", count($fields)), ",") . ")";
          $st = $pdo->prepare($sql);
          $st->execute($vals);
          $success = "Đã thêm danh mục.";
          $tab = 'danhmuc';
        } else {
          // update
          $sets = ["`$DM_NAME`=?"];
          $vals = [$ten];
          if ($DM_DESC) { $sets[] = "`$DM_DESC`=?"; $vals[] = ($mota === '' ? null : $mota); }
          $vals[] = (int)$id;

          $sql = "UPDATE danhmuc SET ".implode(",", $sets)." WHERE `$DM_ID`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);
          $success = "Đã cập nhật danh mục #$id.";
          $tab = 'danhmuc';
        }
      } catch (Throwable $e) {
        $error = "Lỗi danh mục: ".$e->getMessage();
      }
    }
  }

  /* TOGGLE category */
  if (isset($_POST['toggle_danhmuc'])) {
    $id = (int)($_POST['dm_id'] ?? 0);
    if (!$DM_STT) {
      $error = "Bảng danhmuc chưa có cột trạng thái để ẩn/hiện (trang_thai/is_active/hien_thi).";
    } else {
      try {
        $cur = $pdo->prepare("SELECT `$DM_STT` FROM danhmuc WHERE `$DM_ID`=? LIMIT 1");
        $cur->execute([$id]);
        $v = (int)$cur->fetchColumn();
        $new = $v ? 0 : 1;

        $st = $pdo->prepare("UPDATE danhmuc SET `$DM_STT`=? WHERE `$DM_ID`=? LIMIT 1");
        $st->execute([$new, $id]);
        $success = "Đã ".($new? "HIỆN":"ẨN")." danh mục #$id.";
        $tab = 'danhmuc';
      } catch (Throwable $e) {
        $error = "Lỗi ẩn/hiện danh mục: ".$e->getMessage();
      }
    }
  }
}

/* ================== ACTION: PRODUCT ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['save_sanpham'])) {
    $id = trim($_POST['sp_id'] ?? '');
    $ten = trim($_POST['sp_ten'] ?? '');
    $gia = trim($_POST['sp_gia'] ?? '');
    $sale= trim($_POST['sp_sale'] ?? '');
    $dm  = trim($_POST['sp_dm'] ?? '');
    $mota= trim($_POST['sp_mota'] ?? '');
    $stt = isset($_POST['sp_stt']) ? (int)$_POST['sp_stt'] : 1;

    // image: allow upload OR manual filename
    $imgName = trim($_POST['sp_hinh'] ?? '');
    if (!empty($_FILES['sp_file']['name']) && is_uploaded_file($_FILES['sp_file']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['sp_file']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
        $newName = 'sp_' . time() . '_' . rand(100,999) . '.' . $ext;
        $destDir = realpath(__DIR__ . '/../assets/img');
        if ($destDir && is_dir($destDir)) {
          $dest = $destDir . DIRECTORY_SEPARATOR . $newName;
          if (move_uploaded_file($_FILES['sp_file']['tmp_name'], $dest)) {
            $imgName = $newName;
          }
        }
      }
    }

    if ($SP_NAME === null) {
      $error = "Bảng sanpham thiếu cột tên (ten_san_pham/ten).";
    } elseif ($ten === '') {
      $error = "Vui lòng nhập tên sản phẩm.";
    } else {
      try {
        if ($id === '') {
          // INSERT (không set id nếu auto_increment)
          $fields = [];
          $vals = [];

          // handle non-autoinc id
          $needId = $SP_ID && isset($descSP[$SP_ID]) && !isAutoIncrement($descSP[$SP_ID]) && ($descSP[$SP_ID]['Null'] === 'NO') && ($descSP[$SP_ID]['Default'] === null);
          if ($needId) {
            $nextId = (int)$pdo->query("SELECT IFNULL(MAX(`$SP_ID`),0)+1 FROM sanpham")->fetchColumn();
            $fields[] = $SP_ID;
            $vals[] = $nextId;
          }

          $fields[] = $SP_NAME; $vals[] = $ten;

          if ($SP_CAT && $dm !== '') { $fields[] = $SP_CAT; $vals[] = (int)$dm; }
          if ($SP_PRICE && $gia !== '') { $fields[] = $SP_PRICE; $vals[] = (int)preg_replace('/\D+/', '', $gia); }
          if ($SP_SALE && $sale !== '') { $fields[] = $SP_SALE; $vals[] = (int)preg_replace('/\D+/', '', $sale); }
          if ($SP_DESC) { $fields[] = $SP_DESC; $vals[] = ($mota === '' ? null : $mota); }
          if ($SP_IMG && $imgName !== '') { $fields[] = $SP_IMG; $vals[] = $imgName; }
          if ($SP_STT) { $fields[] = $SP_STT; $vals[] = $stt; }

          $sql = "INSERT INTO sanpham (`".implode("`,`",$fields)."`) VALUES (".rtrim(str_repeat("?,", count($fields)), ",").")";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          // tạo tồn kho mặc định nếu có bảng tonkho
          if ($hasTonkho && $TK_SP && $TK_QTY) {
            $newId = $needId ? $nextId : (int)$pdo->lastInsertId();
            $chk = $pdo->prepare("SELECT COUNT(*) FROM tonkho WHERE `$TK_SP`=?");
            $chk->execute([$newId]);
            if ((int)$chk->fetchColumn() === 0) {
              $pdo->prepare("INSERT INTO tonkho (`$TK_SP`,`$TK_QTY`) VALUES (?,0)")->execute([$newId]);
            }
          }

          $success = "Đã thêm sản phẩm.";
          $tab = 'sanpham';
        } else {
          // UPDATE
          $sets = ["`$SP_NAME`=?"];
          $vals = [$ten];

          if ($SP_CAT) { $sets[] = "`$SP_CAT`=?"; $vals[] = ($dm === '' ? null : (int)$dm); }
          if ($SP_PRICE) { $sets[] = "`$SP_PRICE`=?"; $vals[] = ($gia === '' ? null : (int)preg_replace('/\D+/', '', $gia)); }
          if ($SP_SALE) { $sets[] = "`$SP_SALE`=?"; $vals[] = ($sale === '' ? null : (int)preg_replace('/\D+/', '', $sale)); }
          if ($SP_DESC) { $sets[] = "`$SP_DESC`=?"; $vals[] = ($mota === '' ? null : $mota); }
          if ($SP_IMG && $imgName !== '') { $sets[] = "`$SP_IMG`=?"; $vals[] = $imgName; }
          if ($SP_STT) { $sets[] = "`$SP_STT`=?"; $vals[] = $stt; }

          if ($SP_UPDATED) { $sets[] = "`$SP_UPDATED`=NOW()"; }

          $vals[] = (int)$id;
          $sql = "UPDATE sanpham SET ".implode(",", $sets)." WHERE `$SP_ID`=? LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute($vals);

          $success = "Đã cập nhật sản phẩm #$id.";
          $tab = 'sanpham';
        }
      } catch (Throwable $e) {
        $error = "Lỗi sản phẩm: ".$e->getMessage();
      }
    }
  }

  if (isset($_POST['toggle_sanpham'])) {
    $id = (int)($_POST['sp_id'] ?? 0);
    if (!$SP_STT) {
      $error = "Bảng sanpham chưa có cột trạng thái để ẩn/hiện (trang_thai/is_active/hien_thi).";
    } else {
      try {
        $cur = $pdo->prepare("SELECT `$SP_STT` FROM sanpham WHERE `$SP_ID`=? LIMIT 1");
        $cur->execute([$id]);
        $v = (int)$cur->fetchColumn();
        $new = $v ? 0 : 1;
        $pdo->prepare("UPDATE sanpham SET `$SP_STT`=? WHERE `$SP_ID`=? LIMIT 1")->execute([$new,$id]);
        $success = "Đã ".($new? "HIỆN":"ẨN")." sản phẩm #$id.";
        $tab = 'sanpham';
      } catch (Throwable $e) {
        $error = "Lỗi ẩn/hiện sản phẩm: ".$e->getMessage();
      }
    }
  }

  if (isset($_POST['delete_sanpham'])) {
    $id = (int)($_POST['sp_id'] ?? 0);
    if (!$isAdmin) {
      $error = "Nhân viên không được xóa sản phẩm (chỉ được ẩn/hiện).";
    } else {
      try {
        $pdo->prepare("DELETE FROM sanpham WHERE `$SP_ID`=? LIMIT 1")->execute([$id]);
        $success = "Đã xóa sản phẩm #$id.";
        $tab = 'sanpham';
      } catch (Throwable $e) {
        $error = "Không xóa được (có ràng buộc dữ liệu). Hãy dùng ẨN thay vì XÓA. Chi tiết: ".$e->getMessage();
      }
    }
  }
}

/* ================== LOAD DATA ================== */
$dmList = [];
try {
  if ($DM_ID && $DM_NAME) {
    $sql = "SELECT * FROM danhmuc ORDER BY ".($DM_CREATED ? "`$DM_CREATED` DESC" : "`$DM_ID` DESC");
    $dmList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) { /* ignore */ }

// map category name by id
$dmMap = [];
foreach ($dmList as $dm) $dmMap[(string)$dm[$DM_ID]] = $dm[$DM_NAME] ?? ('#'.$dm[$DM_ID]);

// editing product/category
$editSP = null;
$editDM = null;

if ($tab === 'sanpham' && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM sanpham WHERE `$SP_ID`=? LIMIT 1");
  $st->execute([$id]);
  $editSP = $st->fetch(PDO::FETCH_ASSOC);
}

if ($tab === 'danhmuc' && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $pdo->prepare("SELECT * FROM danhmuc WHERE `$DM_ID`=? LIMIT 1");
  $st->execute([$id]);
  $editDM = $st->fetch(PDO::FETCH_ASSOC);
}

// search products
$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
if ($q !== '' && $SP_NAME) {
  $where[] = "`$SP_NAME` LIKE ?";
  $params[] = "%$q%";
}
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// product list with stock join
$limit = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;

$orderCol = $SP_CREATED ?: $SP_ID;

$sqlList = "SELECT sp.*";
if ($hasTonkho && $TK_SP && $TK_QTY) $sqlList .= ", tk.`$TK_QTY` AS ton";
$sqlList .= " FROM sanpham sp";
if ($hasTonkho && $TK_SP && $TK_QTY) $sqlList .= " LEFT JOIN tonkho tk ON tk.`$TK_SP` = sp.`$SP_ID`";
$sqlList .= " $whereSql ORDER BY sp.`$orderCol` DESC LIMIT $limit OFFSET $offset";

$st = $pdo->prepare($sqlList);
$st->execute($params);
$spList = $st->fetchAll(PDO::FETCH_ASSOC);

// count
$st = $pdo->prepare("SELECT COUNT(*) FROM sanpham ".($whereSql ? $whereSql : ""));
$st->execute($params);
$totalRows = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows/$limit));

/* stats */
$totalSP = (int)$pdo->query("SELECT COUNT(*) FROM sanpham")->fetchColumn();
$activeSP = $SP_STT ? (int)$pdo->query("SELECT COUNT(*) FROM sanpham WHERE `$SP_STT`=1")->fetchColumn() : null;
$hiddenSP = $SP_STT ? (int)$pdo->query("SELECT COUNT(*) FROM sanpham WHERE `$SP_STT`=0")->fetchColumn() : null;

$outOfStock = null;
if ($hasTonkho && $TK_SP && $TK_QTY) {
  $outOfStock = (int)$pdo->query("SELECT COUNT(*) FROM tonkho WHERE `$TK_QTY`<=0")->fetchColumn();
}

$totalDM = (int)$pdo->query("SELECT COUNT(*) FROM danhmuc")->fetchColumn();
$activeDM = $DM_STT ? (int)$pdo->query("SELECT COUNT(*) FROM danhmuc WHERE `$DM_STT`=1")->fetchColumn() : null;
$hiddenDM = $DM_STT ? (int)$pdo->query("SELECT COUNT(*) FROM danhmuc WHERE `$DM_STT`=0")->fetchColumn() : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Sản phẩm & Danh mục</title>
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

<body class="font-display bg-slate-100 text-slate-800 min-h-screen overflow-hidden flex">
<!-- SIDEBAR -->
<aside class="w-20 lg:w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-screen">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-gray-100">
    <div class="size-8 rounded bg-primary flex items-center justify-center text-white font-extrabold text-xl">C</div>
    <span class="ml-3 font-extrabold text-lg hidden lg:block">Crocs Admin</span>
  </div>

  <nav class="flex-1 overflow-y-auto py-6 px-3 flex flex-col gap-2">
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="index.php">
      <span class="material-symbols-outlined">grid_view</span>
      <span class="text-sm font-bold hidden lg:block">Tổng quan</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft transition-all"
       href="sanpham.php">
      <span class="material-symbols-outlined">inventory_2</span>
      <span class="text-sm font-bold hidden lg:block">Sản phẩm</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="donhang.php">
      <span class="material-symbols-outlined">shopping_bag</span>
      <span class="text-sm font-bold hidden lg:block">Đơn hàng</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="tonkho.php">
      <span class="material-symbols-outlined">warehouse</span>
      <span class="text-sm font-bold hidden lg:block">Kho / Tồn</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="thongbao.php">
      <span class="material-symbols-outlined">notifications</span>
      <span class="text-sm font-bold hidden lg:block">Thông báo</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="baocao.php">
      <span class="material-symbols-outlined">bar_chart</span>
      <span class="text-sm font-bold hidden lg:block">Báo cáo</span>
    </a>

    <?php if ($isAdmin): ?>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
       href="nhanvien.php">
      <span class="material-symbols-outlined">groups</span>
      <span class="text-sm font-bold hidden lg:block">Nhân viên</span>
    </a>
    <?php endif; ?>

    <div class="mt-auto pt-6 border-t border-gray-100">
      <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 transition-all"
         href="dangxuat.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-bold hidden lg:block">Đăng xuất</span>
      </a>
    </div>
  </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col h-screen overflow-hidden">
  <!-- TOP BAR -->
  <header class="bg-white/80 backdrop-blur border-b border-gray-200 h-16 flex items-center justify-between px-4 md:px-6 sticky top-0 z-10">
    <div class="flex items-center gap-3">
      <h2 class="text-xl font-extrabold hidden sm:block">Quản lý Sản phẩm</h2>
      <span class="text-xs font-bold px-2 py-1 rounded-full bg-slate-100 border">
        <?= h($ROLE) ?>
      </span>
    </div>

    <div class="flex items-center gap-3">
      <form method="get" class="relative hidden sm:block">
        <input type="hidden" name="tab" value="sanpham">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q" value="<?= h($q) ?>"
               class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-72 focus:ring-2 focus:ring-primary/40"
               placeholder="Tìm nhanh sản phẩm..." />
      </form>

      <a href="thongbao.php" class="relative p-2 rounded-full hover:bg-gray-100 text-gray-600">
        <span class="material-symbols-outlined">notifications</span>
      </a>

      <div class="size-9 rounded-full bg-gray-200 border-2 border-white shadow-sm flex items-center justify-center font-extrabold">
        <?= h(mb_substr($_SESSION['admin']['ho_ten'] ?? 'A', 0, 1)) ?>
      </div>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto space-y-6">

      <?php if ($success): ?>
        <div class="p-3 rounded-xl bg-green-50 border border-green-200 text-green-700 font-bold text-sm"><?= h($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 font-bold text-sm"><?= h($error) ?></div>
      <?php endif; ?>

      <!-- TABS -->
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex gap-2">
          <a href="sanpham.php?tab=sanpham"
             class="px-4 py-2 rounded-xl font-extrabold border <?= $tab==='sanpham'?'bg-primary text-white border-primary':'bg-white hover:bg-slate-50' ?>">
             Sản phẩm
          </a>
          <a href="sanpham.php?tab=danhmuc"
             class="px-4 py-2 rounded-xl font-extrabold border <?= $tab==='danhmuc'?'bg-primary text-white border-primary':'bg-white hover:bg-slate-50' ?>">
             Danh mục
          </a>
        </div>

        <div class="text-sm text-slate-600">
          <?php if ($tab==='sanpham'): ?>
            Tổng SP: <b><?= (int)$totalSP ?></b>
            <?php if ($activeSP!==null): ?> | Hiện: <b><?= (int)$activeSP ?></b><?php endif; ?>
            <?php if ($hiddenSP!==null): ?> | Ẩn: <b><?= (int)$hiddenSP ?></b><?php endif; ?>
            <?php if ($outOfStock!==null): ?> | Hết hàng: <b><?= (int)$outOfStock ?></b><?php endif; ?>
          <?php else: ?>
            Tổng DM: <b><?= (int)$totalDM ?></b>
            <?php if ($activeDM!==null): ?> | Hiện: <b><?= (int)$activeDM ?></b><?php endif; ?>
            <?php if ($hiddenDM!==null): ?> | Ẩn: <b><?= (int)$hiddenDM ?></b><?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($tab === 'danhmuc'): ?>
        <!-- ================== DANH MUC ================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="bg-white rounded-2xl shadow-soft border p-6">
            <h3 class="text-lg font-extrabold mb-4"><?= $editDM ? 'Sửa danh mục' : 'Thêm danh mục' ?></h3>

            <form method="post" class="space-y-4">
              <input type="hidden" name="save_danhmuc" value="1">
              <input type="hidden" name="dm_id" value="<?= $editDM ? (int)$editDM[$DM_ID] : '' ?>">

              <div>
                <label class="text-sm font-bold">Tên danh mục</label>
                <input name="dm_ten" required
                       value="<?= $editDM ? h($editDM[$DM_NAME] ?? '') : '' ?>"
                       class="mt-1 w-full border rounded-xl px-3 py-2">
              </div>

              <?php if ($DM_DESC): ?>
              <div>
                <label class="text-sm font-bold">Mô tả</label>
                <textarea name="dm_mota" rows="3"
                          class="mt-1 w-full border rounded-xl px-3 py-2"><?= $editDM ? h($editDM[$DM_DESC] ?? '') : '' ?></textarea>
              </div>
              <?php endif; ?>

              <button class="w-full py-3 rounded-xl bg-primary text-white font-extrabold hover:opacity-95">
                <?= $editDM ? 'Cập nhật danh mục' : 'Thêm danh mục' ?>
              </button>

              <?php if ($editDM): ?>
                <a href="sanpham.php?tab=danhmuc"
                   class="block text-center py-3 rounded-xl border font-extrabold hover:bg-slate-50">
                  Hủy sửa
                </a>
              <?php endif; ?>

              <?php if (!$DM_STT): ?>
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                  Lưu ý: bảng <b>danhmuc</b> chưa có cột trạng thái (trang_thai/is_active/hien_thi) nên tính năng ẨN/HIỆN sẽ không hoạt động.
                </p>
              <?php endif; ?>
            </form>
          </div>

          <div class="lg:col-span-2 bg-white rounded-2xl shadow-soft border p-6 overflow-hidden">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-extrabold">Danh sách danh mục</h3>
              <span class="text-xs text-slate-500">Tổng: <?= (int)$totalDM ?></span>
            </div>

            <div class="overflow-x-auto border rounded-xl">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold">
                  <tr>
                    <th class="p-3 text-left">ID</th>
                    <th class="p-3 text-left">Tên</th>
                    <?php if ($DM_STT): ?><th class="p-3 text-left">Trạng thái</th><?php endif; ?>
                    <th class="p-3 text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($dmList)): ?>
                    <tr><td class="p-4 text-center text-slate-500" colspan="<?= $DM_STT?4:3 ?>">Chưa có danh mục.</td></tr>
                  <?php else: ?>
                    <?php foreach ($dmList as $dm): ?>
                      <?php
                        $id = (int)$dm[$DM_ID];
                        $stt = $DM_STT ? (int)($dm[$DM_STT] ?? 1) : 1;
                      ?>
                      <tr class="border-t">
                        <td class="p-3 font-extrabold">#<?= $id ?></td>
                        <td class="p-3">
                          <div class="font-bold"><?= h($dm[$DM_NAME] ?? '') ?></div>
                          <?php if ($DM_DESC && !empty($dm[$DM_DESC])): ?>
                            <div class="text-xs text-slate-500 line-clamp-1"><?= h($dm[$DM_DESC]) ?></div>
                          <?php endif; ?>
                        </td>
                        <?php if ($DM_STT): ?>
                        <td class="p-3">
                          <?php if ($stt===1): ?>
                            <span class="px-2 py-1 rounded-full bg-green-50 text-green-700 text-[10px] font-extrabold">HIỆN</span>
                          <?php else: ?>
                            <span class="px-2 py-1 rounded-full bg-red-50 text-red-700 text-[10px] font-extrabold">ẨN</span>
                          <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="p-3 text-right whitespace-nowrap">
                          <a class="font-extrabold text-primary hover:underline" href="sanpham.php?tab=danhmuc&edit=<?= $id ?>">Sửa</a>
                          <?php if ($DM_STT): ?>
                            <form method="post" class="inline">
                              <input type="hidden" name="toggle_danhmuc" value="1">
                              <input type="hidden" name="dm_id" value="<?= $id ?>">
                              <button class="ml-3 font-extrabold <?= $stt? 'text-red-600':'text-green-600' ?> hover:underline" type="submit">
                                <?= $stt ? 'Ẩn' : 'Hiện' ?>
                              </button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

      <?php else: ?>
        <!-- ================== SAN PHAM ================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="bg-white rounded-2xl shadow-soft border p-6">
            <h3 class="text-lg font-extrabold mb-4"><?= $editSP ? 'Sửa sản phẩm' : 'Thêm sản phẩm' ?></h3>

            <form method="post" enctype="multipart/form-data" class="space-y-4">
              <input type="hidden" name="save_sanpham" value="1">
              <input type="hidden" name="sp_id" value="<?= $editSP ? (int)$editSP[$SP_ID] : '' ?>">

              <div>
                <label class="text-sm font-bold">Tên sản phẩm</label>
                <input name="sp_ten" required
                       value="<?= $editSP ? h($editSP[$SP_NAME] ?? '') : '' ?>"
                       class="mt-1 w-full border rounded-xl px-3 py-2">
              </div>

              <?php if ($SP_CAT && $DM_ID && $DM_NAME): ?>
              <div>
                <label class="text-sm font-bold">Danh mục</label>
                <select name="sp_dm" class="mt-1 w-full border rounded-xl px-3 py-2">
                  <option value="">-- Chọn danh mục --</option>
                  <?php foreach ($dmList as $dm): ?>
                    <?php $id = (int)$dm[$DM_ID]; ?>
                    <option value="<?= $id ?>"
                      <?= $editSP && isset($editSP[$SP_CAT]) && (int)$editSP[$SP_CAT]===$id ? 'selected' : '' ?>>
                      #<?= $id ?> - <?= h($dm[$DM_NAME]) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>

              <?php if ($SP_PRICE): ?>
              <div>
                <label class="text-sm font-bold">Giá</label>
                <input name="sp_gia" inputmode="numeric"
                       value="<?= $editSP ? h($editSP[$SP_PRICE] ?? '') : '' ?>"
                       class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="VD: 399000">
              </div>
              <?php endif; ?>

              <?php if ($SP_SALE): ?>
              <div>
                <label class="text-sm font-bold">Giá sale (tuỳ chọn)</label>
                <input name="sp_sale" inputmode="numeric"
                       value="<?= $editSP ? h($editSP[$SP_SALE] ?? '') : '' ?>"
                       class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="VD: 299000">
              </div>
              <?php endif; ?>

              <?php if ($SP_IMG): ?>
              <div>
                <label class="text-sm font-bold">Hình ảnh (tên file trong /assets/img)</label>
                <input name="sp_hinh"
                       value="<?= $editSP ? h($editSP[$SP_IMG] ?? '') : '' ?>"
                       class="mt-1 w-full border rounded-xl px-3 py-2" placeholder="VD: sp1.png">
                <div class="mt-2">
                  <label class="text-xs font-bold text-slate-600">Hoặc upload ảnh</label>
                  <input type="file" name="sp_file" accept=".png,.jpg,.jpeg,.webp,.gif"
                         class="mt-1 w-full text-sm">
                </div>
              </div>
              <?php endif; ?>

              <?php if ($SP_DESC): ?>
              <div>
                <label class="text-sm font-bold">Mô tả</label>
                <textarea name="sp_mota" rows="3"
                          class="mt-1 w-full border rounded-xl px-3 py-2"><?= $editSP ? h($editSP[$SP_DESC] ?? '') : '' ?></textarea>
              </div>
              <?php endif; ?>

              <?php if ($SP_STT): ?>
              <div>
                <label class="text-sm font-bold">Trạng thái</label>
                <?php $curStt = $editSP ? (int)($editSP[$SP_STT] ?? 1) : 1; ?>
                <select name="sp_stt" class="mt-1 w-full border rounded-xl px-3 py-2">
                  <option value="1" <?= $curStt===1?'selected':'' ?>>Hiện</option>
                  <option value="0" <?= $curStt===0?'selected':'' ?>>Ẩn</option>
                </select>
              </div>
              <?php endif; ?>

              <button class="w-full py-3 rounded-xl bg-primary text-white font-extrabold hover:opacity-95">
                <?= $editSP ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm' ?>
              </button>

              <?php if ($editSP): ?>
                <a href="sanpham.php?tab=sanpham"
                   class="block text-center py-3 rounded-xl border font-extrabold hover:bg-slate-50">
                  Hủy sửa
                </a>
              <?php endif; ?>

              <?php if (!$SP_STT): ?>
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">
                  Lưu ý: bảng <b>sanpham</b> chưa có cột trạng thái (trang_thai/is_active/hien_thi) nên tính năng ẨN/HIỆN sẽ không hoạt động.
                </p>
              <?php endif; ?>
            </form>
          </div>

          <div class="lg:col-span-2 bg-white rounded-2xl shadow-soft border p-6 overflow-hidden">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
              <h3 class="text-lg font-extrabold">Danh sách sản phẩm</h3>
              <a href="tonkho.php" class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50 font-extrabold">Đi tới Tồn kho</a>
            </div>

            <div class="overflow-x-auto border rounded-xl">
              <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold">
                  <tr>
                    <th class="p-3 text-left">ID</th>
                    <th class="p-3 text-left">Sản phẩm</th>
                    <th class="p-3 text-left">Danh mục</th>
                    <th class="p-3 text-right">Giá</th>
                    <?php if ($hasTonkho && $TK_QTY): ?><th class="p-3 text-right">Tồn</th><?php endif; ?>
                    <?php if ($SP_STT): ?><th class="p-3 text-left">TT</th><?php endif; ?>
                    <th class="p-3 text-right">Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($spList)): ?>
                    <tr><td class="p-4 text-center text-slate-500" colspan="<?= 6 + ($hasTonkho?1:0) + ($SP_STT?1:0) ?>">Không có dữ liệu.</td></tr>
                  <?php else: ?>
                    <?php foreach ($spList as $sp): ?>
                      <?php
                        $id = (int)$sp[$SP_ID];
                        $stt = $SP_STT ? (int)($sp[$SP_STT] ?? 1) : 1;
                        $catName = '-';
                        if ($SP_CAT && isset($sp[$SP_CAT]) && $sp[$SP_CAT] !== null) {
                          $catName = $dmMap[(string)$sp[$SP_CAT]] ?? ('#'.$sp[$SP_CAT]);
                        }
                        $ton = $hasTonkho ? (int)($sp['ton'] ?? 0) : null;
                      ?>
                      <tr class="border-t">
                        <td class="p-3 font-extrabold">#<?= $id ?></td>

                        <td class="p-3">
                          <div class="flex items-center gap-3">
                            <?php if ($SP_IMG && !empty($sp[$SP_IMG])): ?>
                              <img src="../assets/img/<?= h($sp[$SP_IMG]) ?>" class="w-10 h-10 object-contain border rounded-lg bg-white" onerror="this.style.display='none'">
                            <?php endif; ?>
                            <div class="min-w-0">
                              <div class="font-bold truncate"><?= h($sp[$SP_NAME] ?? '') ?></div>
                              <div class="text-xs text-slate-500">
                                <?= $SP_CREATED && !empty($sp[$SP_CREATED]) ? date('d/m/Y H:i', strtotime($sp[$SP_CREATED])) : '' ?>
                              </div>
                            </div>
                          </div>
                        </td>

                        <td class="p-3"><?= h($catName) ?></td>

                        <td class="p-3 text-right font-extrabold">
                          <?php if ($SP_PRICE && isset($sp[$SP_PRICE])): ?>
                            <?= number_format((int)$sp[$SP_PRICE]) ?>₫
                          <?php else: ?>
                            -
                          <?php endif; ?>
                        </td>

                        <?php if ($hasTonkho && $TK_QTY): ?>
                        <td class="p-3 text-right font-extrabold <?= $ton<=0?'text-red-600':'' ?>">
                          <?= number_format($ton) ?>
                        </td>
                        <?php endif; ?>

                        <?php if ($SP_STT): ?>
                        <td class="p-3">
                          <?php if ($stt===1): ?>
                            <span class="px-2 py-1 rounded-full bg-green-50 text-green-700 text-[10px] font-extrabold">HIỆN</span>
                          <?php else: ?>
                            <span class="px-2 py-1 rounded-full bg-red-50 text-red-700 text-[10px] font-extrabold">ẨN</span>
                          <?php endif; ?>
                        </td>
                        <?php endif; ?>

                        <td class="p-3 text-right whitespace-nowrap">
                          <a class="font-extrabold text-primary hover:underline" href="sanpham.php?tab=sanpham&edit=<?= $id ?>">Sửa</a>

                          <?php if ($SP_STT): ?>
                          <form method="post" class="inline">
                            <input type="hidden" name="toggle_sanpham" value="1">
                            <input type="hidden" name="sp_id" value="<?= $id ?>">
                            <button class="ml-3 font-extrabold <?= $stt? 'text-red-600':'text-green-600' ?> hover:underline" type="submit">
                              <?= $stt ? 'Ẩn' : 'Hiện' ?>
                            </button>
                          </form>
                          <?php endif; ?>

                          <form method="post" class="inline" onsubmit="return confirm('Xóa sản phẩm này? Nếu lỗi ràng buộc, hãy dùng ẨN.');">
                            <input type="hidden" name="delete_sanpham" value="1">
                            <input type="hidden" name="sp_id" value="<?= $id ?>">
                            <button class="ml-3 font-extrabold text-slate-400 hover:text-red-600 hover:underline" type="submit">
                              Xóa
                            </button>
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
                Trang <b><?= $page ?></b> / <b><?= $totalPages ?></b> — Tổng <b><?= $totalRows ?></b> sản phẩm
              </div>
              <div class="flex gap-2">
                <?php
                  $base = "sanpham.php?tab=sanpham&q=".urlencode($q)."&page=";
                ?>
                <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page<=1?'opacity-50 pointer-events-none':'' ?>"
                   href="<?= $base.($page-1) ?>">Trước</a>
                <a class="px-3 py-2 rounded-lg border bg-white hover:bg-slate-50 font-extrabold <?= $page>=$totalPages?'opacity-50 pointer-events-none':'' ?>"
                   href="<?= $base.($page+1) ?>">Sau</a>
              </div>
            </div>

          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</main>
</body>
</html>
