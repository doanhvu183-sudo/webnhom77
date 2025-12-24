<?php
// admin/donhang.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================= AUTH (chịu được 2 kiểu session: id hoặc id_admin) ================= */
if (empty($_SESSION['admin']) || (!isset($_SESSION['admin']['id']) && !isset($_SESSION['admin']['id_admin']))) {
    header("Location: dang_nhap.php");
    exit;
}
$me = $_SESSION['admin'];
$adminId = (int)($me['id_admin'] ?? $me['id'] ?? 0);
$vaiTro = strtoupper(trim($me['vai_tro'] ?? 'ADMIN'));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Helpers ================= */
function h($str){ return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function redirectWith($params = []) {
    $base = 'donhang.php';
    header("Location: ".$base.($params ? ('?'.http_build_query($params)) : ''));
    exit;
}
function tableExists(PDO $pdo, $name){
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
}
function getCols(PDO $pdo, $table){
    $st = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $st->execute([$table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}
function pickCol(array $cols, array $cands){
    foreach($cands as $c){
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

/* ================= TABLE / SCHEMA DETECT ================= */
if (!tableExists($pdo, 'donhang')) {
    die("Không tìm thấy bảng <b>donhang</b> trong DB hiện tại.");
}
$dhCols = getCols($pdo, 'donhang');

$DH_PK       = pickCol($dhCols, ['id_don_hang','id','donhang_id']);
$DH_USERID   = pickCol($dhCols, ['id_nguoi_dung','id_khach_hang','user_id','khachhang_id']);
$DH_CODE     = pickCol($dhCols, ['ma_don_hang','ma_dh','order_code']);
$DH_TOTAL    = pickCol($dhCols, ['tong_tien','total','tong']);
$DH_STATUS   = pickCol($dhCols, ['trang_thai','status']);
$DH_PAY      = pickCol($dhCols, ['phuong_thuc','phuong_thuc_thanh_toan','payment_method']);
$DH_NOTE     = pickCol($dhCols, ['ghi_chu','note']);
$DH_REASON   = pickCol($dhCols, ['ly_do_huy','lydo_huy','reason_cancel']);
$DH_CREATED  = pickCol($dhCols, ['ngay_dat','ngay_tao','created_at']);
$DH_UPDATED  = pickCol($dhCols, ['ngay_cap_nhat','updated_at']);

if (!$DH_PK || !$DH_STATUS) {
    die("Bảng donhang thiếu cột bắt buộc: <b>id</b> hoặc <b>trang_thai</b>.");
}

/* ===== Detect bảng chi tiết đơn ===== */
$detailTable = null;
foreach (['chi_tiet_donhang','chitiet_donhang','chi_tiet_don_hang','ct_donhang','ct_don_hang','chi_tiet_don_hang'] as $t) {
    if (tableExists($pdo, $t)) { $detailTable = $t; break; }
}
$ctCols = $detailTable ? getCols($pdo, $detailTable) : [];
$CT_ORDERID = $detailTable ? pickCol($ctCols, ['id_don_hang','donhang_id','order_id']) : null;
$CT_SPID    = $detailTable ? pickCol($ctCols, ['id_san_pham','sanpham_id','id_sp']) : null;
$CT_QTY     = $detailTable ? pickCol($ctCols, ['so_luong','qty','so_luong_mua']) : null;
$CT_PRICE   = $detailTable ? pickCol($ctCols, ['don_gia','gia','gia_mua']) : null;
$CT_SUB     = $detailTable ? pickCol($ctCols, ['thanh_tien','subtotal','tong']) : null;

/* ===== Detect bảng sản phẩm để join tên/ảnh ===== */
$spTable = tableExists($pdo, 'sanpham') ? 'sanpham' : null;
$spCols = $spTable ? getCols($pdo, $spTable) : [];
$SP_PK   = $spTable ? pickCol($spCols, ['id_san_pham','id','sanpham_id']) : null;
$SP_NAME = $spTable ? pickCol($spCols, ['ten_san_pham','ten','name']) : null;
$SP_IMG  = $spTable ? pickCol($spCols, ['hinh_anh','anh','image']) : null;

/* ===== Detect bảng user để hiện khách ===== */
$userTable = null;
foreach (['nguoidung','nguoi_dung','users','khachhang'] as $t) {
    if (tableExists($pdo, $t)) { $userTable = $t; break; }
}
$userCols = $userTable ? getCols($pdo, $userTable) : [];
$U_PK   = $userTable ? pickCol($userCols, ['id_nguoi_dung','id_khach_hang','id','user_id','khachhang_id']) : null;
$U_NAME = $userTable ? pickCol($userCols, ['ho_ten','ten','full_name','name']) : null;
$U_EMAIL= $userTable ? pickCol($userCols, ['email']) : null;
$U_PHONE= $userTable ? pickCol($userCols, ['so_dien_thoai','sdt','phone']) : null;
$U_ADDR = $userTable ? pickCol($userCols, ['dia_chi','address']) : null;

/* ===== Detect bảng thông báo (optional) ===== */
$tbTable = tableExists($pdo, 'thong_bao') ? 'thong_bao' : null;
$tbCols  = $tbTable ? getCols($pdo, $tbTable) : [];
$TB_USERID = $tbTable ? pickCol($tbCols, ['id_nguoi_dung','id_khach_hang','user_id','khachhang_id']) : null;
$TB_TITLE  = $tbTable ? pickCol($tbCols, ['tieu_de','title']) : null;
$TB_BODY   = $tbTable ? pickCol($tbCols, ['noi_dung','noiDung','content','body']) : null;
$TB_STATUS = $tbTable ? pickCol($tbCols, ['trang_thai','status']) : null;
$TB_TYPE   = $tbTable ? pickCol($tbCols, ['loai','type']) : null;
$TB_DATE   = $tbTable ? pickCol($tbCols, ['ngay_tao','created_at']) : null;
$canNotify = $tbTable && $TB_USERID && $TB_TITLE && $TB_BODY;

/* ================= STATUS CANONICAL ================= */
// Bạn có thể đổi lại theo DB bạn đang dùng
$STATUS_PENDING   = 'Chờ duyệt';
$STATUS_PROCESS   = 'Đang xử lý';
$STATUS_SHIPPING  = 'Đang giao';
$STATUS_DONE      = 'Hoàn tất';
$STATUS_CANCELED  = 'Đã hủy';

$STATUS_ALL = [$STATUS_PENDING,$STATUS_PROCESS,$STATUS_SHIPPING,$STATUS_DONE,$STATUS_CANCELED];

/* ================= HANDLE POST: cập nhật / huỷ ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) redirectWith(['type'=>'error','msg'=>'Thiếu ID đơn hàng.']);

    // Lấy đơn hiện tại
    $stOld = $pdo->prepare("SELECT * FROM donhang WHERE {$DH_PK} = ? LIMIT 1");
    $stOld->execute([$id]);
    $old = $stOld->fetch(PDO::FETCH_ASSOC);
    if (!$old) redirectWith(['type'=>'error','msg'=>'Không tìm thấy đơn hàng.']);

    if ($action === 'capnhat') {
        $newStatus = trim($_POST['trang_thai'] ?? '');
        if ($newStatus === '') redirectWith(['type'=>'error','msg'=>'Thiếu trạng thái.']);

        // Cho chọn trong list chuẩn, nhưng vẫn cho phép custom nếu DB đang dùng khác
        // (nếu bạn muốn khóa cứng, chỉ cho trong $STATUS_ALL)
        $note = trim($_POST['ghi_chu'] ?? '');

        $set = [];
        $bind = [];

        $set[] = "{$DH_STATUS} = :st";
        $bind[':st'] = $newStatus;

        if ($DH_NOTE) {
            $set[] = "{$DH_NOTE} = :note";
            $bind[':note'] = ($note !== '' ? $note : ($old[$DH_NOTE] ?? null));
        }

        if ($DH_UPDATED) {
            $set[] = "{$DH_UPDATED} = :up";
            $bind[':up'] = date('Y-m-d H:i:s');
        }

        $bind[':id'] = $id;

        $sql = "UPDATE donhang SET ".implode(', ', $set)." WHERE {$DH_PK} = :id";
        $pdo->prepare($sql)->execute($bind);

        // Thông báo cho khách (nếu có)
        if ($canNotify && $DH_USERID && !empty($old[$DH_USERID])) {
            $uid = (int)$old[$DH_USERID];
            $code = $DH_CODE ? ($old[$DH_CODE] ?? ('#'.$id)) : ('#'.$id);

            $title = "Cập nhật đơn hàng ".$code;
            $body  = "Trạng thái đơn hàng đã cập nhật: ".$newStatus.".";

            // message riêng cho một vài trạng thái
            if (mb_stripos($newStatus, 'hủy') !== false || mb_stripos($newStatus, 'huỷ') !== false) {
                $body = "Đơn hàng ".$code." đã bị hủy. Vui lòng liên hệ nếu cần hỗ trợ.";
            }
            if (mb_stripos($newStatus, 'giao') !== false) {
                $body = "Đơn hàng ".$code." đang được giao. Bạn chú ý điện thoại để nhận hàng.";
            }
            if (mb_stripos($newStatus, 'hoàn') !== false) {
                $body = "Đơn hàng ".$code." đã hoàn tất. Cảm ơn bạn đã mua hàng.";
            }

            $fields = [$TB_USERID, $TB_TITLE, $TB_BODY];
            $vals   = [':uid', ':t', ':b'];
            $b = [':uid'=>$uid, ':t'=>$title, ':b'=>$body];

            if ($TB_STATUS) { $fields[] = $TB_STATUS; $vals[]=':s'; $b[':s']=0; } // 0 = chưa đọc (tuỳ DB bạn)
            if ($TB_TYPE)   { $fields[] = $TB_TYPE;   $vals[]=':tp'; $b[':tp']='donhang'; }
            if ($TB_DATE)   { $fields[] = $TB_DATE;   $vals[]='NOW()'; }

            $sqlTB = "INSERT INTO {$tbTable}(".implode(',', $fields).") VALUES(".implode(',', $vals).")";
            $pdo->prepare($sqlTB)->execute($b);
        }

        redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái đơn hàng.','xem'=>$id]);
    }

    if ($action === 'huy') {
        if (!$isAdmin) {
            redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền hủy đơn.']);
        }
        $reason = trim($_POST['ly_do'] ?? '');
        if ($reason === '') $reason = 'Không có lý do';

        $set = [];
        $bind = [];

        $set[] = "{$DH_STATUS} = :st";
        $bind[':st'] = $STATUS_CANCELED;

        if ($DH_REASON) {
            $set[] = "{$DH_REASON} = :rs";
            $bind[':rs'] = $reason;
        } elseif ($DH_NOTE) {
            // fallback đẩy lý do vào ghi chú nếu không có ly_do_huy
            $set[] = "{$DH_NOTE} = :note";
            $bind[':note'] = "Lý do hủy: ".$reason;
        }

        if ($DH_UPDATED) {
            $set[] = "{$DH_UPDATED} = :up";
            $bind[':up'] = date('Y-m-d H:i:s');
        }

        $bind[':id'] = $id;

        $sql = "UPDATE donhang SET ".implode(', ', $set)." WHERE {$DH_PK} = :id";
        $pdo->prepare($sql)->execute($bind);

        // Thông báo cho khách (nếu có)
        if ($canNotify && $DH_USERID && !empty($old[$DH_USERID])) {
            $uid = (int)$old[$DH_USERID];
            $code = $DH_CODE ? ($old[$DH_CODE] ?? ('#'.$id)) : ('#'.$id);

            $title = "Đơn hàng ".$code." bị hủy";
            $body  = "Đơn hàng ".$code." đã bị hủy. Lý do: ".$reason;

            $fields = [$TB_USERID, $TB_TITLE, $TB_BODY];
            $vals   = [':uid', ':t', ':b'];
            $b = [':uid'=>$uid, ':t'=>$title, ':b'=>$body];

            if ($TB_STATUS) { $fields[] = $TB_STATUS; $vals[]=':s'; $b[':s']=0; }
            if ($TB_TYPE)   { $fields[] = $TB_TYPE;   $vals[]=':tp'; $b[':tp']='donhang'; }
            if ($TB_DATE)   { $fields[] = $TB_DATE;   $vals[]='NOW()'; }

            $sqlTB = "INSERT INTO {$tbTable}(".implode(',', $fields).") VALUES(".implode(',', $vals).")";
            $pdo->prepare($sqlTB)->execute($b);
        }

        redirectWith(['type'=>'ok','msg'=>'Đã hủy đơn hàng.','xem'=>$id]);
    }

    redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= FILTERS / SEARCH / PAGINATION ================= */
$q = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['st'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

// distinct statuses
$stList = $pdo->query("SELECT {$DH_STATUS} AS st, COUNT(*) c FROM donhang GROUP BY {$DH_STATUS} ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);

// counts by heuristic
$counts = [
    'total' => 0,
    'pending' => 0,
    'process' => 0,
    'shipping' => 0,
    'done' => 0,
    'cancel' => 0
];
foreach ($stList as $row) {
    $counts['total'] += (int)$row['c'];
    $s = mb_strtolower((string)$row['st']);
    if (mb_strpos($s, 'chờ') !== false || mb_strpos($s, 'duyệt') !== false) $counts['pending'] += (int)$row['c'];
    else if (mb_strpos($s, 'xử') !== false) $counts['process'] += (int)$row['c'];
    else if (mb_strpos($s, 'giao') !== false || mb_strpos($s, 'vận') !== false) $counts['shipping'] += (int)$row['c'];
    else if (mb_strpos($s, 'hoàn') !== false || mb_strpos($s, 'thành') !== false) $counts['done'] += (int)$row['c'];
    else if (mb_strpos($s, 'hủy') !== false || mb_strpos($s, 'huỷ') !== false || mb_strpos($s, 'huy') !== false) $counts['cancel'] += (int)$row['c'];
}

// Build list query
$where = " WHERE 1 ";
$params = [];

if ($filterStatus !== '') {
    $where .= " AND dh.{$DH_STATUS} = ? ";
    $params[] = $filterStatus;
}

$joinUser = "";
$selectUser = "";
if ($userTable && $DH_USERID && $U_PK && $DH_USERID) {
    $joinUser = " LEFT JOIN {$userTable} u ON u.{$U_PK} = dh.{$DH_USERID} ";
    if ($U_NAME)  $selectUser .= ", u.{$U_NAME} AS u_name";
    if ($U_EMAIL) $selectUser .= ", u.{$U_EMAIL} AS u_email";
    if ($U_PHONE) $selectUser .= ", u.{$U_PHONE} AS u_phone";
    if ($U_ADDR)  $selectUser .= ", u.{$U_ADDR} AS u_addr";
}

if ($q !== '') {
    $likeParts = [];
    if ($DH_CODE) { $likeParts[] = "dh.{$DH_CODE} LIKE ?"; $params[]="%$q%"; }
    if ($DH_PAY)  { $likeParts[] = "dh.{$DH_PAY} LIKE ?";  $params[]="%$q%"; }
    if ($userTable) {
        if ($U_NAME)  { $likeParts[] = "u.{$U_NAME} LIKE ?";  $params[]="%$q%"; }
        if ($U_EMAIL) { $likeParts[] = "u.{$U_EMAIL} LIKE ?"; $params[]="%$q%"; }
        if ($U_PHONE) { $likeParts[] = "u.{$U_PHONE} LIKE ?"; $params[]="%$q%"; }
    }
    if ($likeParts) $where .= " AND (".implode(" OR ", $likeParts).") ";
}

$orderBy = $DH_CREATED ? "dh.{$DH_CREATED}" : "dh.{$DH_PK}";
$sqlCount = "SELECT COUNT(*) FROM donhang dh {$joinUser} {$where}";
$stCount = $pdo->prepare($sqlCount);
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($total/$perPage));

$fields = [
    "dh.{$DH_PK} AS id",
    "dh.{$DH_STATUS} AS trang_thai"
];
if ($DH_CODE)    $fields[] = "dh.{$DH_CODE} AS ma";
if ($DH_TOTAL)   $fields[] = "dh.{$DH_TOTAL} AS tong_tien";
if ($DH_PAY)     $fields[] = "dh.{$DH_PAY} AS phuong_thuc";
if ($DH_CREATED) $fields[] = "dh.{$DH_CREATED} AS ngay_tao";
if ($DH_NOTE)    $fields[] = "dh.{$DH_NOTE} AS ghi_chu";
if ($DH_REASON)  $fields[] = "dh.{$DH_REASON} AS ly_do_huy";

$sqlList = "SELECT ".implode(", ", $fields)." {$selectUser}
            FROM donhang dh
            {$joinUser}
            {$where}
            ORDER BY {$orderBy} DESC
            LIMIT {$perPage} OFFSET {$offset}";
$stListQ = $pdo->prepare($sqlList);
$stListQ->execute($params);
$orders = $stListQ->fetchAll(PDO::FETCH_ASSOC);

/* ================= DETAIL VIEW ================= */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
$items = [];
if ($viewId > 0) {
    $st = $pdo->prepare("SELECT * FROM donhang WHERE {$DH_PK} = ? LIMIT 1");
    $st->execute([$viewId]);
    $view = $st->fetch(PDO::FETCH_ASSOC);

    if ($view && $detailTable && $CT_ORDERID) {
        // Join sản phẩm nếu có
        if ($spTable && $CT_SPID && $SP_PK && $SP_NAME) {
            $sqlItems = "SELECT ct.*,
                            sp.{$SP_NAME} AS sp_ten"
                        . ($SP_IMG ? ", sp.{$SP_IMG} AS sp_anh" : "") . "
                         FROM {$detailTable} ct
                         LEFT JOIN {$spTable} sp ON sp.{$SP_PK} = ct.{$CT_SPID}
                         WHERE ct.{$CT_ORDERID} = ?
                         ORDER BY ct.{$CT_SPID} ASC";
            $sti = $pdo->prepare($sqlItems);
            $sti->execute([$viewId]);
            $items = $sti->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sqlItems = "SELECT * FROM {$detailTable} WHERE {$CT_ORDERID} = ? ORDER BY 1 DESC";
            $sti = $pdo->prepare($sqlItems);
            $sti->execute([$viewId]);
            $items = $sti->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

/* ================= FLASH ================= */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

/* ================= STATUS BADGE ================= */
function statusBadge($st){
    $s = mb_strtolower((string)$st);
    if (mb_strpos($s,'hủy')!==false || mb_strpos($s,'huỷ')!==false) return ['bg-red-50 text-red-700','cancel'];
    if (mb_strpos($s,'hoàn')!==false) return ['bg-green-50 text-green-700','done'];
    if (mb_strpos($s,'giao')!==false || mb_strpos($s,'vận')!==false) return ['bg-blue-50 text-blue-700','shipping'];
    if (mb_strpos($s,'xử')!==false) return ['bg-yellow-50 text-yellow-700','process'];
    if (mb_strpos($s,'chờ')!==false || mb_strpos($s,'duyệt')!==false) return ['bg-slate-100 text-slate-700','pending'];
    return ['bg-gray-100 text-gray-700','other'];
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin - Đơn hàng</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: "#137fec",
        secondary: "#5e6c84",
        "background-light": "#f8f9fa",
        success: "#10b981",
        warning: "#f59e0b",
        danger: "#ef4444",
      },
      fontFamily: { display: ["Manrope", "sans-serif"] },
      boxShadow: { soft: "0 4px 20px -2px rgba(0,0,0,.05)" }
    }
  }
}
</script>

<style>
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
</style>
</head>

<body class="font-display bg-background-light text-slate-800 h-screen overflow-hidden flex">

<!-- ===== SIDEBAR ===== -->
<aside class="w-20 lg:w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full flex-shrink-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-gray-100">
    <div class="size-8 rounded bg-primary flex items-center justify-center text-white font-bold text-xl">C</div>
    <span class="ml-3 font-bold text-lg hidden lg:block text-slate-900">Crocs Admin</span>
  </div>

  <nav class="flex-1 overflow-y-auto py-6 px-3 flex flex-col gap-2">
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="index.php">
      <span class="material-symbols-outlined group-hover:text-primary">grid_view</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Tổng quan</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="sanpham.php">
      <span class="material-symbols-outlined group-hover:text-primary">inventory_2</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Sản phẩm</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft transition-all"
       href="donhang.php">
      <span class="material-symbols-outlined">shopping_bag</span>
      <span class="text-sm font-bold hidden lg:block">Đơn hàng</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="khachhang.php">
      <span class="material-symbols-outlined group-hover:text-primary">groups</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Khách hàng</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="voucher.php">
      <span class="material-symbols-outlined group-hover:text-primary">sell</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Voucher</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="baocao.php">
      <span class="material-symbols-outlined group-hover:text-primary">bar_chart</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Báo cáo</span>
    </a>

    <?php if ($isAdmin): ?>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
       href="nhanvien.php">
      <span class="material-symbols-outlined group-hover:text-primary">badge</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Nhân viên</span>
    </a>
    <?php endif; ?>

    <div class="mt-auto pt-6 border-t border-gray-100">
      <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-secondary transition-all group"
         href="dang_xuat.php">
        <span class="material-symbols-outlined group-hover:text-primary">logout</span>
        <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Đăng xuất</span>
      </a>
    </div>
  </nav>
</aside>

<!-- ===== MAIN ===== -->
<main class="flex-1 flex flex-col h-full overflow-hidden">

  <!-- TOPBAR -->
  <header class="bg-white/80 backdrop-blur-md border-b border-gray-200 h-16 flex items-center justify-between px-6 sticky top-0 z-20">
    <div class="flex items-center gap-3">
      <button class="md:hidden text-gray-500">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <h2 class="text-xl font-bold hidden sm:block">Quản lý Đơn hàng</h2>
    </div>

    <div class="flex items-center gap-3">
      <form class="hidden sm:block relative" method="get" action="donhang.php">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q" value="<?= h($q) ?>"
          class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-80 focus:ring-2 focus:ring-primary/50"
          placeholder="Tìm mã đơn / tên / email / SĐT..." />
      </form>

      <div class="text-xs px-3 py-1 rounded-full bg-gray-100 text-slate-600 font-bold">
        <?= $isAdmin ? 'ADMIN' : 'NHÂN VIÊN' ?>
      </div>

      <div class="size-9 rounded-full bg-gray-200 bg-center bg-cover border-2 border-white shadow-sm"
           style="background-image:url('<?= !empty($me['avatar']) ? '../assets/img/'.h($me['avatar']) : 'https://via.placeholder.com/80' ?>')">
      </div>
    </div>
  </header>

  <!-- CONTENT -->
  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

      <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border shadow-soft bg-white
                    <?= $type==='ok' ? 'border-green-200' : ($type==='error' ? 'border-red-200' : 'border-gray-200') ?>">
          <div class="flex items-start gap-2">
            <span class="material-symbols-outlined <?= $type==='ok' ? 'text-green-600' : ($type==='error' ? 'text-red-600' : 'text-slate-600') ?>">
              <?= $type==='ok' ? 'check_circle' : ($type==='error' ? 'error' : 'info') ?>
            </span>
            <div class="text-sm font-semibold"><?= h($msg) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- SUMMARY CARDS -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex justify-between items-start">
            <div class="p-3 bg-blue-50 rounded-xl text-primary">
              <span class="material-symbols-outlined">receipt_long</span>
            </div>
          </div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Tổng đơn</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($counts['total']) ?></div>
        </div>

        <a href="donhang.php?st=<?= urlencode($STATUS_PENDING) ?>" class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 hover:-translate-y-0.5 transition">
          <div class="p-3 bg-slate-100 rounded-xl text-slate-700 w-fit">
            <span class="material-symbols-outlined">hourglass_top</span>
          </div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Chờ duyệt</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($counts['pending']) ?></div>
        </a>

        <a href="donhang.php?st=<?= urlencode($STATUS_PROCESS) ?>" class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 hover:-translate-y-0.5 transition">
          <div class="p-3 bg-yellow-50 rounded-xl text-yellow-700 w-fit">
            <span class="material-symbols-outlined">sync</span>
          </div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đang xử lý</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($counts['process']) ?></div>
        </a>

        <a href="donhang.php?st=<?= urlencode($STATUS_SHIPPING) ?>" class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 hover:-translate-y-0.5 transition">
          <div class="p-3 bg-blue-50 rounded-xl text-blue-700 w-fit">
            <span class="material-symbols-outlined">local_shipping</span>
          </div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đang giao</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($counts['shipping']) ?></div>
        </a>

        <a href="donhang.php?st=<?= urlencode($STATUS_CANCELED) ?>" class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 hover:-translate-y-0.5 transition">
          <div class="p-3 bg-red-50 rounded-xl text-red-700 w-fit">
            <span class="material-symbols-outlined">block</span>
          </div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đã hủy</div>
          <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($counts['cancel']) ?></div>
        </a>
      </div>

      <!-- FILTER BAR -->
      <div class="bg-white p-4 rounded-2xl shadow-soft border border-gray-100 flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2 items-center">
          <a href="donhang.php" class="px-4 py-2 rounded-xl border bg-white text-sm font-bold hover:bg-gray-50">
            Tất cả
          </a>

          <form method="get" class="flex gap-2 items-center">
            <input type="hidden" name="q" value="<?= h($q) ?>">
            <select name="st" class="rounded-xl border-gray-200 bg-gray-50 text-sm font-bold">
              <option value="">Lọc theo trạng thái</option>
              <?php foreach ($stList as $row): ?>
                <?php $st = (string)$row['st']; ?>
                <option value="<?= h($st) ?>" <?= $filterStatus===$st ? 'selected':'' ?>>
                  <?= h($st) ?> (<?= (int)$row['c'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <button class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-extrabold hover:opacity-90">
              Lọc
            </button>
          </form>
        </div>

        <div class="text-xs text-slate-500">
          Trang <?= $page ?>/<?= $totalPages ?> • Tổng <?= number_format($total) ?> đơn
        </div>
      </div>

      <!-- MAIN GRID -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LIST -->
        <div class="lg:col-span-2 bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-extrabold text-slate-800">Danh sách đơn</div>
            <div class="text-xs text-slate-500"><?= $detailTable ? "Có chi tiết đơn" : "Chưa có bảng chi tiết đơn" ?></div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-slate-500 border-b">
                  <th class="py-3 pr-3">Mã đơn</th>
                  <th class="py-3 pr-3">Khách</th>
                  <th class="py-3 pr-3">Tổng</th>
                  <th class="py-3 pr-3">Trạng thái</th>
                  <th class="py-3 pr-3">Ngày</th>
                  <th class="py-3 text-right">Xem</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$orders): ?>
                  <tr><td colspan="10" class="py-8 text-center text-slate-500">Không có đơn hàng.</td></tr>
                <?php endif; ?>

                <?php foreach ($orders as $o): ?>
                  <?php
                    [$badgeClass] = statusBadge($o['trang_thai'] ?? '');
                    $ma = $DH_CODE ? ($o['ma'] ?? ('#'.$o['id'])) : ('#'.$o['id']);
                    $kh = trim(($o['u_name'] ?? '').'');
                    $kh2 = trim(($o['u_phone'] ?? ($o['u_email'] ?? '')) . '');
                    $tong = isset($o['tong_tien']) ? (int)$o['tong_tien'] : 0;
                    $ngay = $o['ngay_tao'] ?? '';
                    $isActive = ($viewId > 0 && (int)$o['id'] === $viewId);
                  ?>
                  <tr class="border-b last:border-0 hover:bg-gray-50 <?= $isActive ? 'bg-blue-50/40' : '' ?>">
                    <td class="py-3 pr-3">
                      <div class="font-extrabold text-slate-800"><?= h($ma) ?></div>
                      <div class="text-xs text-slate-500">ID: <?= (int)$o['id'] ?></div>
                      <?php if (!empty($o['phuong_thuc'])): ?>
                        <div class="text-xs text-slate-500">PTTT: <b><?= h($o['phuong_thuc']) ?></b></div>
                      <?php endif; ?>
                    </td>

                    <td class="py-3 pr-3">
                      <div class="font-bold text-slate-800"><?= $kh !== '' ? h($kh) : '—' ?></div>
                      <div class="text-xs text-slate-500"><?= $kh2 !== '' ? h($kh2) : '' ?></div>
                    </td>

                    <td class="py-3 pr-3 font-extrabold text-slate-900">
                      <?= number_format($tong) ?> ₫
                    </td>

                    <td class="py-3 pr-3">
                      <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $badgeClass ?>">
                        <?= h($o['trang_thai'] ?? '') ?>
                      </span>
                      <?php if (!empty($o['ly_do_huy'])): ?>
                        <div class="text-xs text-red-600 mt-1 truncate max-w-[180px]" title="<?= h($o['ly_do_huy']) ?>">
                          <?= h($o['ly_do_huy']) ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td class="py-3 pr-3 text-slate-600">
                      <?= $ngay ? h($ngay) : '—' ?>
                    </td>

                    <td class="py-3 text-right">
                      <a href="donhang.php?<?= h(http_build_query(array_merge($_GET, ['xem'=>$o['id']])) ) ?>"
                         class="px-3 py-2 rounded-xl bg-blue-50 text-primary font-extrabold hover:bg-blue-100 text-xs">
                        Chi tiết
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <div class="flex items-center justify-between mt-4">
            <div class="text-xs text-slate-500">
              Hiển thị <?= count($orders) ?> / <?= number_format($total) ?>
            </div>

            <div class="flex gap-2">
              <?php
                $qs = $_GET;
                $mkLink = function($p) use ($qs) {
                    $qs['page'] = $p;
                    return 'donhang.php?'.http_build_query($qs);
                };
              ?>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mkLink(max(1,$page-1))) ?>">Trước</a>

              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mkLink(min($totalPages,$page+1))) ?>">Sau</a>
            </div>
          </div>
        </div>

        <!-- DETAIL -->
        <div class="bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-extrabold text-slate-900">Chi tiết đơn</div>
              <div class="text-xs text-slate-500">Chọn 1 đơn để xem & xử lý</div>
            </div>
            <?php if ($viewId): ?>
              <a href="donhang.php" class="text-sm font-extrabold text-primary hover:underline">Bỏ chọn</a>
            <?php endif; ?>
          </div>

          <?php if (!$view): ?>
            <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 text-slate-600 text-sm">
              Chưa chọn đơn hàng.
            </div>
          <?php else: ?>
            <?php
              $code = $DH_CODE ? ($view[$DH_CODE] ?? ('#'.$viewId)) : ('#'.$viewId);
              $stNow = (string)($view[$DH_STATUS] ?? '');
              [$badgeClass] = statusBadge($stNow);
              $totalMoney = $DH_TOTAL ? (int)($view[$DH_TOTAL] ?? 0) : 0;

              // Lấy thông tin khách nếu join được
              $cust = null;
              if ($userTable && $DH_USERID && !empty($view[$DH_USERID]) && $U_PK) {
                  $uid = (int)$view[$DH_USERID];
                  $stc = $pdo->prepare("SELECT * FROM {$userTable} WHERE {$U_PK} = ? LIMIT 1");
                  $stc->execute([$uid]);
                  $cust = $stc->fetch(PDO::FETCH_ASSOC);
              }
            ?>

            <div class="space-y-3">
              <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="text-sm text-slate-500">Mã đơn</div>
                    <div class="text-xl font-extrabold text-slate-900"><?= h($code) ?></div>
                    <div class="text-xs text-slate-500 mt-1">ID: <?= (int)$viewId ?></div>
                  </div>
                  <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $badgeClass ?>">
                    <?= h($stNow) ?>
                  </span>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                  <div>
                    <div class="text-slate-500">Tổng tiền</div>
                    <div class="font-extrabold text-slate-900"><?= number_format($totalMoney) ?> ₫</div>
                  </div>
                  <div>
                    <div class="text-slate-500">Thanh toán</div>
                    <div class="font-bold text-slate-800">
                      <?= $DH_PAY ? h($view[$DH_PAY] ?? '—') : '—' ?>
                    </div>
                  </div>

                  <div>
                    <div class="text-slate-500">Ngày tạo</div>
                    <div class="font-bold text-slate-800"><?= $DH_CREATED ? h($view[$DH_CREATED] ?? '—') : '—' ?></div>
                  </div>
                  <div>
                    <div class="text-slate-500">Cập nhật</div>
                    <div class="font-bold text-slate-800"><?= $DH_UPDATED ? h($view[$DH_UPDATED] ?? '—') : '—' ?></div>
                  </div>
                </div>

                <?php if ($DH_REASON && !empty($view[$DH_REASON])): ?>
                  <div class="mt-3 text-xs text-red-700 font-bold">
                    Lý do hủy: <?= h($view[$DH_REASON]) ?>
                  </div>
                <?php endif; ?>

                <?php if ($DH_NOTE && !empty($view[$DH_NOTE])): ?>
                  <div class="mt-3 text-xs text-slate-600">
                    Ghi chú: <b><?= h($view[$DH_NOTE]) ?></b>
                  </div>
                <?php endif; ?>
              </div>

              <!-- CUSTOMER -->
              <div class="p-4 rounded-2xl bg-white border border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                  <span class="material-symbols-outlined text-primary">person</span>
                  <div class="font-extrabold">Khách hàng</div>
                </div>

                <?php if ($cust): ?>
                  <div class="text-sm space-y-1">
                    <div><span class="text-slate-500">Tên:</span> <b><?= $U_NAME ? h($cust[$U_NAME] ?? '—') : '—' ?></b></div>
                    <div><span class="text-slate-500">Email:</span> <b><?= $U_EMAIL ? h($cust[$U_EMAIL] ?? '—') : '—' ?></b></div>
                    <div><span class="text-slate-500">SĐT:</span> <b><?= $U_PHONE ? h($cust[$U_PHONE] ?? '—') : '—' ?></b></div>
                    <div class="text-xs text-slate-500">
                      <?= $U_ADDR ? h($cust[$U_ADDR] ?? '') : '' ?>
                    </div>
                  </div>
                <?php else: ?>
                  <div class="text-sm text-slate-600">
                    Không lấy được thông tin khách (do thiếu bảng user hoặc thiếu khóa liên kết).
                  </div>
                <?php endif; ?>
              </div>

              <!-- ITEMS -->
              <div class="p-4 rounded-2xl bg-white border border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                  <span class="material-symbols-outlined text-primary">inventory_2</span>
                  <div class="font-extrabold">Sản phẩm trong đơn</div>
                </div>

                <?php if (!$detailTable || !$CT_ORDERID): ?>
                  <div class="text-sm text-slate-600">
                    Chưa có bảng chi tiết đơn hàng (ví dụ: <b>chi_tiet_donhang</b>).
                  </div>
                <?php else: ?>
                  <?php if (!$items): ?>
                    <div class="text-sm text-slate-600">Không có chi tiết sản phẩm.</div>
                  <?php else: ?>
                    <div class="space-y-3">
                      <?php foreach ($items as $it): ?>
                        <?php
                          $ten = $SP_NAME ? ($it['sp_ten'] ?? 'Sản phẩm') : 'Sản phẩm';
                          $qty = $CT_QTY ? (int)($it[$CT_QTY] ?? 1) : 1;
                          $price = $CT_PRICE ? (int)($it[$CT_PRICE] ?? 0) : 0;
                          $sub = $CT_SUB ? (int)($it[$CT_SUB] ?? ($qty*$price)) : ($qty*$price);
                          $anh = ($SP_IMG && !empty($it['sp_anh'])) ? '../assets/img/'.h($it['sp_anh']) : null;
                        ?>
                        <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-50 border border-gray-200">
                          <div class="size-12 rounded-xl bg-white border border-gray-200 overflow-hidden flex items-center justify-center">
                            <?php if ($anh): ?>
                              <img src="<?= $anh ?>" class="w-full h-full object-cover" alt="">
                            <?php else: ?>
                              <span class="material-symbols-outlined text-gray-400">image</span>
                            <?php endif; ?>
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="font-extrabold text-slate-800 truncate"><?= h($ten) ?></div>
                            <div class="text-xs text-slate-500">
                              SL: <b><?= $qty ?></b> • Đơn giá: <b><?= number_format($price) ?> ₫</b>
                            </div>
                          </div>
                          <div class="font-extrabold text-slate-900">
                            <?= number_format($sub) ?> ₫
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <!-- ACTIONS -->
              <div class="p-4 rounded-2xl bg-white border border-gray-200">
                <div class="flex items-center gap-2 mb-3">
                  <span class="material-symbols-outlined text-primary">manage_accounts</span>
                  <div class="font-extrabold">Xử lý đơn</div>
                </div>

                <form method="post" class="space-y-3">
                  <input type="hidden" name="action" value="capnhat">
                  <input type="hidden" name="id" value="<?= (int)$viewId ?>">

                  <div>
                    <label class="text-sm font-bold">Trạng thái</label>
                    <select name="trang_thai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
                      <?php
                        // ưu tiên status chuẩn, nhưng thêm option hiện tại nếu khác
                        $opts = $STATUS_ALL;
                        if ($stNow && !in_array($stNow, $opts, true)) array_unshift($opts, $stNow);
                      ?>
                      <?php foreach ($opts as $op): ?>
                        <option value="<?= h($op) ?>" <?= $op===$stNow ? 'selected':'' ?>><?= h($op) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="text-sm font-bold">Ghi chú (tuỳ chọn)</label>
                    <input name="ghi_chu"
                      value="<?= $DH_NOTE ? h($view[$DH_NOTE] ?? '') : '' ?>"
                      class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                      placeholder="Ví dụ: gọi khách xác nhận, hẹn giao chiều nay...">
                  </div>

                  <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
                    Lưu cập nhật
                  </button>
                </form>

                <?php if ($isAdmin): ?>
                  <form method="post" class="mt-3 space-y-2" onsubmit="return confirm('Xác nhận hủy đơn này?');">
                    <input type="hidden" name="action" value="huy">
                    <input type="hidden" name="id" value="<?= (int)$viewId ?>">

                    <div>
                      <label class="text-sm font-bold text-red-700">Lý do hủy</label>
                      <input name="ly_do"
                        class="mt-1 w-full rounded-xl border-red-200 bg-red-50 focus:ring-red-300"
                        placeholder="Ví dụ: khách không nhận, hết hàng, sai địa chỉ...">
                    </div>

                    <button class="w-full px-4 py-3 rounded-2xl bg-red-600 text-white font-extrabold hover:bg-red-700">
                      Hủy đơn
                    </button>

                    <div class="text-xs text-slate-500">
                      Chỉ <b>ADMIN</b> được hủy đơn. Khi hủy sẽ cố gắng tạo thông báo cho khách nếu có bảng <b>thong_bao</b>.
                    </div>
                  </form>
                <?php else: ?>
                  <div class="mt-3 text-xs text-slate-500">
                    Bạn là <b>NHÂN VIÊN</b>: được cập nhật trạng thái, <b>không được hủy đơn</b>.
                  </div>
                <?php endif; ?>

              </div>

            </div>
          <?php endif; ?>

        </div>

      </div>
    </div>
  </div>
</main>

</body>
</html>
