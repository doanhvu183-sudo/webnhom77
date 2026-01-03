<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';
/* ================== AUTH ADMIN ================== */
if (!isset($_SESSION['admin'])) {
    header("Location: dang_nhap.php");
    exit;
}

$success = '';
$error = '';

/* ================== HELPERS ================== */
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function getColumns(PDO $pdo, string $table): array {
    $rows = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($r) => $r['Field'], $rows);
}

function pickUserTable(PDO $pdo): array {
    // ưu tiên đúng theo dự án bạn (thường thấy: nguoidung)
    $candidates = ['nguoidung', 'nguoi_dung', 'users'];
    foreach ($candidates as $t) {
        if (tableExists($pdo, $t)) {
            $cols = getColumns($pdo, $t);
            // đoán cột id
            $idCol = null;
            foreach (['id_nguoi_dung','id','id_user','id_khach_hang'] as $k) {
                if (in_array($k, $cols, true)) { $idCol = $k; break; }
            }
            if (!$idCol) $idCol = $cols[0] ?? null;
            return [$t, $idCol, $cols];
        }
    }
    return [null, null, []];
}

/* ================== CHECK thong_bao COLUMNS ================== */
$tbCols = [];
try {
    $tbCols = getColumns($pdo, 'thong_bao');
} catch (Throwable $e) {
    die("Không đọc được cấu trúc bảng thong_bao. Kiểm tra DB.");
}

$hasLink = in_array('link', $tbCols, true);
$hasLoai = in_array('loai', $tbCols, true);

/* ================== USER TABLE (for broadcast) ================== */
[$userTable, $userIdCol, $userCols] = pickUserTable($pdo);

/* ================== PREPARE INSERT ================== */
$insertSql = "";
if ($hasLink && $hasLoai) {
    $insertSql = "INSERT INTO thong_bao (id_nguoi_dung, tieu_de, noi_dung, link, loai, da_doc, ngay_tao)
                  VALUES (?, ?, ?, ?, ?, 0, NOW())";
} elseif ($hasLink && !$hasLoai) {
    $insertSql = "INSERT INTO thong_bao (id_nguoi_dung, tieu_de, noi_dung, link, da_doc, ngay_tao)
                  VALUES (?, ?, ?, ?, 0, NOW())";
} else {
    // DB của bạn hiện tại thường rơi vào case này (không có link/loai)
    $insertSql = "INSERT INTO thong_bao (id_nguoi_dung, tieu_de, noi_dung, da_doc, ngay_tao)
                  VALUES (?, ?, ?, 0, NOW())";
}
$insertStmt = $pdo->prepare($insertSql);

/* ================== CREATE NOTIFICATION ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tao_thong_bao'])) {
    $id_nguoi_dung_raw = trim($_POST['id_nguoi_dung'] ?? '');
    $tieu_de = trim($_POST['tieu_de'] ?? '');
    $noi_dung = trim($_POST['noi_dung'] ?? '');

    $link = $hasLink ? trim($_POST['link'] ?? '') : null;
    $loai = $hasLoai ? trim($_POST['loai'] ?? 'he_thong') : null;

    if ($tieu_de === '') {
        $error = 'Vui lòng nhập tiêu đề thông báo.';
    } else {
        // CASE 1: gửi 1 user cụ thể
        if ($id_nguoi_dung_raw !== '' && ctype_digit($id_nguoi_dung_raw)) {
            $uid = (int)$id_nguoi_dung_raw;

            if ($hasLink && $hasLoai) {
                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null), ($link ?: null), ($loai ?: 'he_thong')]);
            } elseif ($hasLink && !$hasLoai) {
                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null), ($link ?: null)]);
            } else {
                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null)]);
            }

            $success = 'Đã tạo thông báo cho user ID: ' . $uid;
        }
        // CASE 2: gửi tất cả (KHÔNG INSERT NULL) => chèn cho từng user
        else {
            if (!$userTable || !$userIdCol) {
                $error = "Không tìm thấy bảng người dùng (nguoidung/nguoi_dung). Vui lòng nhập ID người dùng để gửi.";
            } else {
                // nếu có cột trạng_thai / is_active thì chỉ lấy user đang hoạt động
                $whereActive = "1=1";
                if (in_array('trang_thai', $userCols, true)) $whereActive = "trang_thai = 1";
                if (in_array('is_active', $userCols, true)) $whereActive = "is_active = 1";

                $uids = $pdo->query("SELECT `$userIdCol` FROM `$userTable` WHERE $whereActive")->fetchAll(PDO::FETCH_COLUMN);
                if (empty($uids)) {
                    $error = "Không có user nào để gửi (bảng $userTable trống hoặc không có user active).";
                } else {
                    $pdo->beginTransaction();
                    try {
                        foreach ($uids as $uid) {
                            $uid = (int)$uid;

                            if ($hasLink && $hasLoai) {
                                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null), ($link ?: null), ($loai ?: 'he_thong')]);
                            } elseif ($hasLink && !$hasLoai) {
                                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null), ($link ?: null)]);
                            } else {
                                $insertStmt->execute([$uid, $tieu_de, ($noi_dung ?: null)]);
                            }
                        }
                        $pdo->commit();
                        $success = "Đã tạo thông báo cho tất cả người dùng (" . count($uids) . " user).";
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = "Lỗi khi gửi tất cả: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

/* ================== DELETE ================== */
if (isset($_GET['xoa']) && ctype_digit($_GET['xoa'])) {
    $id_xoa = (int)$_GET['xoa'];
    $stmt = $pdo->prepare("DELETE FROM thong_bao WHERE id_thong_bao = ? LIMIT 1");
    $stmt->execute([$id_xoa]);
    header("Location: thongbao.php");
    exit;
}

/* ================== FILTER LIST ================== */
$q = trim($_GET['q'] ?? '');
$filter_user = trim($_GET['user'] ?? '');
$filter_loai = $hasLoai ? trim($_GET['loai'] ?? '') : '';
$filter_doc = trim($_GET['doc'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(tieu_de LIKE ? OR noi_dung LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($filter_user !== '' && ctype_digit($filter_user)) {
    $where[] = "id_nguoi_dung = ?";
    $params[] = (int)$filter_user;
}

if ($hasLoai && $filter_loai !== '') {
    $where[] = "loai = ?";
    $params[] = $filter_loai;
}

if ($filter_doc !== '' && ($filter_doc === '0' || $filter_doc === '1')) {
    $where[] = "da_doc = ?";
    $params[] = (int)$filter_doc;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT *
    FROM thong_bao
    $whereSql
    ORDER BY ngay_tao DESC, id_thong_bao DESC
    LIMIT 100
");
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Thông báo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

<script>
tailwind.config = {
  theme: {
    extend: {
      colors: { primary: "#137fec" },
      fontFamily: { display: ["Manrope","sans-serif"] }
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
<div class="max-w-7xl mx-auto p-4 md:p-8">

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-extrabold">Quản lý thông báo</h1>
      <p class="text-sm text-slate-500">
        Tạo & quản lý thông báo gửi cho khách hàng (hiện trên chuông ở header).
        <?php if ($userTable): ?>
          <span class="font-semibold text-slate-700">Bảng user:</span> <?= htmlspecialchars($userTable) ?> (ID: <?= htmlspecialchars($userIdCol) ?>)
        <?php endif; ?>
      </p>
    </div>
    <a href="index.php" class="px-4 py-2 rounded-lg bg-white border hover:bg-slate-50 font-bold">Về Dashboard</a>
  </div>

  <?php if ($success): ?>
    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm font-semibold">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-semibold">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
    <h2 class="text-lg font-extrabold mb-4">Tạo thông báo mới</h2>

    <form method="post" class="grid grid-cols-1 md:grid-cols-12 gap-4">
      <input type="hidden" name="tao_thong_bao" value="1">

      <div class="md:col-span-3">
        <label class="text-sm font-bold">ID người dùng (để trống = gửi tất cả)</label>
        <input name="id_nguoi_dung" placeholder="VD: 4"
               class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>

      <div class="md:col-span-5">
        <label class="text-sm font-bold">Tiêu đề</label>
        <input name="tieu_de" required
               class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>

      <?php if ($hasLoai): ?>
      <div class="md:col-span-2">
        <label class="text-sm font-bold">Loại</label>
        <select name="loai" class="mt-1 w-full border rounded-lg px-3 py-2">
          <option value="don_hang">Đơn hàng</option>
          <option value="voucher">Voucher</option>
          <option value="he_thong" selected>Hệ thống</option>
        </select>
      </div>
      <?php endif; ?>

      <?php if ($hasLink): ?>
      <div class="md:col-span-2">
        <label class="text-sm font-bold">Link (tuỳ chọn)</label>
        <input name="link" placeholder="../trang_nguoi_dung/don_hang.php"
               class="mt-1 w-full border rounded-lg px-3 py-2">
      </div>
      <?php endif; ?>

      <div class="md:col-span-12">
        <label class="text-sm font-bold">Nội dung (tuỳ chọn)</label>
        <textarea name="noi_dung" rows="3"
                  class="mt-1 w-full border rounded-lg px-3 py-2"></textarea>
      </div>

      <div class="md:col-span-12 flex justify-end">
        <button class="px-5 py-3 rounded-xl bg-primary text-white font-extrabold hover:opacity-95">
          Tạo thông báo
        </button>
      </div>
    </form>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border p-6">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
      <h2 class="text-lg font-extrabold">Danh sách thông báo (tối đa 100)</h2>

      <form class="flex gap-2 flex-wrap">
        <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Tìm tiêu đề/nội dung..."
               class="border rounded-lg px-3 py-2 text-sm">
        <input name="user" value="<?= htmlspecialchars($filter_user) ?>" placeholder="User ID"
               class="border rounded-lg px-3 py-2 text-sm w-28">

        <?php if ($hasLoai): ?>
        <select name="loai" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả loại</option>
          <option value="don_hang" <?= $filter_loai==='don_hang'?'selected':'' ?>>Đơn hàng</option>
          <option value="voucher" <?= $filter_loai==='voucher'?'selected':'' ?>>Voucher</option>
          <option value="he_thong" <?= $filter_loai==='he_thong'?'selected':'' ?>>Hệ thống</option>
        </select>
        <?php endif; ?>

        <select name="doc" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Đã đọc + Chưa đọc</option>
          <option value="0" <?= $filter_doc==='0'?'selected':'' ?>>Chưa đọc</option>
          <option value="1" <?= $filter_doc==='1'?'selected':'' ?>>Đã đọc</option>
        </select>

        <button class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold">Lọc</button>
        <a href="thongbao.php" class="px-4 py-2 rounded-lg bg-white border text-sm font-bold">Reset</a>
      </form>
    </div>

    <div class="overflow-x-auto border rounded-xl">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase font-extrabold">
          <tr>
            <th class="p-3 text-left">ID</th>
            <th class="p-3 text-left">User</th>
            <th class="p-3 text-left">Tiêu đề</th>
            <?php if ($hasLoai): ?><th class="p-3 text-left">Loại</th><?php endif; ?>
            <?php if ($hasLink): ?><th class="p-3 text-left">Link</th><?php endif; ?>
            <th class="p-3 text-left">Đọc</th>
            <th class="p-3 text-left">Ngày tạo</th>
            <th class="p-3 text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($list)): ?>
            <tr><td class="p-4 text-center text-slate-500" colspan="<?= 6 + ($hasLoai?1:0) + ($hasLink?1:0) ?>">Không có dữ liệu.</td></tr>
          <?php else: ?>
            <?php foreach ($list as $row): ?>
              <tr class="border-t">
                <td class="p-3 font-bold">#<?= (int)$row['id_thong_bao'] ?></td>
                <td class="p-3"><?= (int)$row['id_nguoi_dung'] ?></td>
                <td class="p-3">
                  <div class="font-bold"><?= htmlspecialchars($row['tieu_de']) ?></div>
                  <?php if (!empty($row['noi_dung'])): ?>
                    <div class="text-xs text-slate-500 line-clamp-1"><?= htmlspecialchars($row['noi_dung']) ?></div>
                  <?php endif; ?>
                </td>

                <?php if ($hasLoai): ?>
                  <td class="p-3">
                    <span class="px-2 py-1 rounded-full text-[10px] font-extrabold bg-slate-100">
                      <?= htmlspecialchars($row['loai'] ?? 'he_thong') ?>
                    </span>
                  </td>
                <?php endif; ?>

                <?php if ($hasLink): ?>
                  <td class="p-3 text-primary text-xs"><?= htmlspecialchars($row['link'] ?? '') ?></td>
                <?php endif; ?>

                <td class="p-3">
                  <?= ((int)$row['da_doc'] === 1)
                      ? '<span class="text-green-700 font-bold">Đã đọc</span>'
                      : '<span class="text-red-600 font-bold">Chưa đọc</span>' ?>
                </td>
                <td class="p-3 text-slate-600">
                  <?= !empty($row['ngay_tao']) ? date('d/m/Y H:i', strtotime($row['ngay_tao'])) : '' ?>
                </td>
                <td class="p-3 text-right">
                  <a class="text-red-600 font-bold hover:underline"
                     href="thongbao.php?xoa=<?= (int)$row['id_thong_bao'] ?>"
                     onclick="return confirm('Xoá thông báo này?')">
                    Xoá
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>