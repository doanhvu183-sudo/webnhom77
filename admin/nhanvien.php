<?php
// admin/nhanvien.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================= AUTH ================= */
if (empty($_SESSION['admin']) || (!isset($_SESSION['admin']['id']) && !isset($_SESSION['admin']['id_admin']))) {
  header("Location: dang_nhap.php"); exit;
}
$me = $_SESSION['admin'];
$myId = (int)($me['id'] ?? $me['id_admin'] ?? 0);
$vaiTro = strtoupper(trim($me['vai_tro'] ?? 'ADMIN'));
$isAdmin = ($vaiTro === 'ADMIN');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getCols(PDO $pdo, $table){
  $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?");
  $st->execute([$table]);
  return $st->fetchAll(PDO::FETCH_COLUMN);
}
function pickCol(array $cols, array $cands){
  foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; }
  return null;
}
function redirectWith($params=[]){
  header("Location: nhanvien.php".($params?('?'.http_build_query($params)):''));
  exit;
}

/* ================= Detect admin table columns ================= */
$cols = getCols($pdo,'admin');

$A_ID     = pickCol($cols, ['id_admin','id']);
$A_EMAIL  = pickCol($cols, ['email']);
$A_USER   = pickCol($cols, ['username','tai_khoan','ten_dang_nhap']);
$A_PASS   = pickCol($cols, ['password','mat_khau','pass_hash']);
$A_NAME   = pickCol($cols, ['ho_ten','ten','full_name']);
$A_ROLE   = pickCol($cols, ['vai_tro','role']);
$A_AVATAR = pickCol($cols, ['avatar']);
$A_STATUS = pickCol($cols, ['trang_thai','is_active','active','status']);
$A_CREATE = pickCol($cols, ['ngay_tao','created_at']);

if(!$A_ID || !$A_EMAIL || !$A_PASS){
  die("Bảng <b>admin</b> thiếu cột bắt buộc (id/email/password).");
}

/* ================= POST actions (ADMIN only) ================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!$isAdmin) redirectWith(['type'=>'error','msg'=>'Nhân viên không có quyền thao tác mục Nhân viên.']);

  $action = $_POST['action'] ?? '';
  $id = (int)($_POST['id'] ?? 0);

  if ($action==='them') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['ho_ten'] ?? '');
    $role = strtoupper(trim($_POST['vai_tro'] ?? 'NHAN_VIEN'));
    $pass = (string)($_POST['mat_khau'] ?? '');

    if ($email==='' || $pass==='') redirectWith(['type'=>'error','msg'=>'Email và mật khẩu không được trống.']);

    // check trùng email
    $st = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE $A_EMAIL=?");
    $st->execute([$email]);
    if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Email đã tồn tại trong admin.']);

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $fields=[]; $vals=[]; $bind=[];

    $fields[]=$A_EMAIL; $vals[]=':e'; $bind[':e']=$email;
    $fields[]=$A_PASS;  $vals[]=':p'; $bind[':p']=$hash;

    if ($A_USER){
      $fields[]=$A_USER; $vals[]=':u'; $bind[':u']=$username;
    }
    if ($A_NAME){
      $fields[]=$A_NAME; $vals[]=':n'; $bind[':n']=$name;
    }
    if ($A_ROLE){
      $fields[]=$A_ROLE; $vals[]=':r'; $bind[':r']=($role==='ADMIN'?'ADMIN':'NHAN_VIEN');
    }
    if ($A_STATUS){
      $fields[]=$A_STATUS; $vals[]=':s'; $bind[':s']=1;
    }
    if ($A_CREATE){
      $fields[]=$A_CREATE; $vals[]='NOW()';
    }

    $sql="INSERT INTO admin(".implode(',',$fields).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    redirectWith(['type'=>'ok','msg'=>'Đã thêm nhân viên.']);
  }

  if ($action==='sua') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    if ($id===$myId) {
      // cho sửa tên/email ok, nhưng không cho tự hạ quyền/làm khoá tài khoản
    }

    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['ho_ten'] ?? '');
    $role = strtoupper(trim($_POST['vai_tro'] ?? 'NHAN_VIEN'));
    $status = trim($_POST['trang_thai'] ?? '1');

    if ($email==='') redirectWith(['type'=>'error','msg'=>'Email không được trống.']);

    // trùng email (trừ chính nó)
    $st = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE $A_EMAIL=? AND $A_ID<>?");
    $st->execute([$email,$id]);
    if ((int)$st->fetchColumn() > 0) redirectWith(['type'=>'error','msg'=>'Email bị trùng với tài khoản khác.']);

    $set=[]; $bind=[':id'=>$id];

    $set[]="$A_EMAIL=:e"; $bind[':e']=$email;
    if ($A_USER){ $set[]="$A_USER=:u"; $bind[':u']=$username; }
    if ($A_NAME){ $set[]="$A_NAME=:n"; $bind[':n']=$name; }

    if ($A_ROLE){
      // nếu sửa chính mình thì giữ nguyên vai trò hiện tại
      if ($id===$myId){
        // bỏ qua
      } else {
        $set[]="$A_ROLE=:r"; $bind[':r']=($role==='ADMIN'?'ADMIN':'NHAN_VIEN');
      }
    }

    if ($A_STATUS){
      if ($id===$myId){
        // không tự khoá mình
      } else {
        $set[]="$A_STATUS=:s"; $bind[':s']=(is_numeric($status)?(int)$status:1);
      }
    }

    $sql="UPDATE admin SET ".implode(', ',$set)." WHERE $A_ID=:id";
    $pdo->prepare($sql)->execute($bind);

    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật nhân viên.','xem'=>$id]);
  }

  if ($action==='doimk') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    $new = (string)($_POST['mat_khau_moi'] ?? '');
    if ($new==='') redirectWith(['type'=>'error','msg'=>'Mật khẩu mới không được trống.']);
    $hash = password_hash($new, PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE admin SET $A_PASS=? WHERE $A_ID=?")->execute([$hash,$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã đổi mật khẩu.','xem'=>$id]);
  }

  if ($action==='xoa') {
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    if ($id===$myId) redirectWith(['type'=>'error','msg'=>'Không thể xoá chính bạn.']);
    $pdo->prepare("DELETE FROM admin WHERE $A_ID=?")->execute([$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã xoá nhân viên.']);
  }

  if ($action==='khoa_mo') {
    if (!$A_STATUS) redirectWith(['type'=>'error','msg'=>'Bảng admin không có cột trạng_thái.']);
    if ($id<=0) redirectWith(['type'=>'error','msg'=>'Thiếu ID.']);
    if ($id===$myId) redirectWith(['type'=>'error','msg'=>'Không thể khoá/mở chính bạn.']);

    $st=$pdo->prepare("SELECT $A_STATUS FROM admin WHERE $A_ID=? LIMIT 1");
    $st->execute([$id]);
    $cur=$st->fetchColumn();
    $new = is_numeric($cur) ? ((int)$cur ? 0 : 1) : ((string)$cur==='1'?'0':'1');
    $pdo->prepare("UPDATE admin SET $A_STATUS=? WHERE $A_ID=?")->execute([$new,$id]);
    redirectWith(['type'=>'ok','msg'=>'Đã cập nhật trạng thái tài khoản.','xem'=>$id]);
  }

  redirectWith(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= List / filters ================= */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$where=" WHERE 1 ";
$params=[];
if ($q!==''){
  $conds=[];
  $conds[]="a.$A_EMAIL LIKE ?"; $params[]="%$q%";
  if ($A_NAME){ $conds[]="a.$A_NAME LIKE ?"; $params[]="%$q%"; }
  if ($A_USER){ $conds[]="a.$A_USER LIKE ?"; $params[]="%$q%"; }
  $where.=" AND (".implode(" OR ",$conds).") ";
}

$orderBy = $A_CREATE ? "a.$A_CREATE" : "a.$A_ID";

/* total */
$st=$pdo->prepare("SELECT COUNT(*) FROM admin a $where");
$st->execute($params);
$total=(int)$st->fetchColumn();
$totalPages=max(1,(int)ceil($total/$perPage));

/* list */
$fields=["a.$A_ID AS id","a.$A_EMAIL AS email"];
if ($A_USER)   $fields[]="a.$A_USER AS username";
if ($A_NAME)   $fields[]="a.$A_NAME AS ho_ten";
if ($A_ROLE)   $fields[]="a.$A_ROLE AS vai_tro";
if ($A_AVATAR) $fields[]="a.$A_AVATAR AS avatar";
if ($A_STATUS) $fields[]="a.$A_STATUS AS trang_thai";
if ($A_CREATE) $fields[]="a.$A_CREATE AS ngay_tao";

$sql="SELECT ".implode(", ",$fields)." FROM admin a $where ORDER BY $orderBy DESC LIMIT $perPage OFFSET $offset";
$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

/* view */
$viewId=(int)($_GET['xem'] ?? 0);
$view=null;
if($viewId>0){
  $st=$pdo->prepare("SELECT * FROM admin WHERE $A_ID=? LIMIT 1");
  $st->execute([$viewId]);
  $view=$st->fetch(PDO::FETCH_ASSOC);
}

/* stats */
$statTotal=$total;
$statAdmin=0; $statStaff=0; $statActive=0;
if ($A_ROLE){
  $st=$pdo->query("SELECT $A_ROLE AS r, COUNT(*) c FROM admin GROUP BY $A_ROLE");
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){
    $role=strtoupper((string)$r['r']);
    if($role==='ADMIN') $statAdmin=(int)$r['c']; else $statStaff += (int)$r['c'];
  }
} else {
  $statAdmin=$total;
}
if ($A_STATUS){
  $statActive=(int)$pdo->query("SELECT COUNT(*) FROM admin WHERE $A_STATUS=1")->fetchColumn();
}

/* flash */
$type=$_GET['type'] ?? '';
$msg =$_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin - Nhân viên</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  theme:{extend:{
    colors:{primary:"#137fec","background-light":"#f8f9fa",success:"#10b981",warning:"#f59e0b",danger:"#ef4444"},
    fontFamily:{display:["Manrope","sans-serif"]},
    boxShadow:{soft:"0 4px 20px -2px rgba(0,0,0,.05)"},
    borderRadius:{'2xl':"1.5rem"}
  }}
}
</script>
<style>
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
</style>
</head>

<body class="font-display bg-background-light text-slate-800 h-screen overflow-hidden flex">

<!-- SIDEBAR -->
<aside class="w-20 lg:w-64 bg-white border-r border-gray-200 hidden md:flex flex-col h-full flex-shrink-0">
  <div class="h-16 flex items-center justify-center lg:justify-start lg:px-6 border-b border-gray-100">
    <div class="size-8 rounded bg-primary flex items-center justify-center text-white font-bold text-xl">C</div>
    <span class="ml-3 font-bold text-lg hidden lg:block text-slate-900">Crocs Admin</span>
  </div>

  <nav class="flex-1 overflow-y-auto py-6 px-3 flex flex-col gap-2">
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="index.php">
      <span class="material-symbols-outlined group-hover:text-primary">grid_view</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Tổng quan</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="sanpham.php">
      <span class="material-symbols-outlined group-hover:text-primary">inventory_2</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Sản phẩm</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="donhang.php">
      <span class="material-symbols-outlined group-hover:text-primary">shopping_bag</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Đơn hàng</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="khachhang.php">
      <span class="material-symbols-outlined group-hover:text-primary">groups</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Khách hàng</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="voucher.php">
      <span class="material-symbols-outlined group-hover:text-primary">sell</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Voucher</span>
    </a>
    <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="baocao.php">
      <span class="material-symbols-outlined group-hover:text-primary">bar_chart</span>
      <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Báo cáo</span>
    </a>

    <a class="flex items-center gap-3 px-3 py-3 rounded-xl bg-primary text-white shadow-soft" href="nhanvien.php">
      <span class="material-symbols-outlined">badge</span>
      <span class="text-sm font-bold hidden lg:block">Nhân viên</span>
    </a>

    <div class="mt-auto pt-6 border-t border-gray-100">
      <a class="flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-gray-100 text-slate-600 group" href="dang_xuat.php">
        <span class="material-symbols-outlined group-hover:text-primary">logout</span>
        <span class="text-sm font-medium hidden lg:block group-hover:text-slate-900">Đăng xuất</span>
      </a>
    </div>
  </nav>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col h-full overflow-hidden">

  <!-- TOPBAR -->
  <header class="bg-white/80 backdrop-blur-md border-b border-gray-200 h-16 flex items-center justify-between px-6 sticky top-0 z-20">
    <h2 class="text-xl font-bold hidden sm:block">Quản lý Nhân viên</h2>

    <div class="flex items-center gap-3">
      <form class="hidden sm:block relative" method="get" action="nhanvien.php">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-gray-400 text-[20px]">search</span>
        <input name="q" value="<?= h($q) ?>"
          class="pl-10 pr-4 py-2 bg-gray-100 border-none rounded-lg text-sm w-80 focus:ring-2 focus:ring-primary/50"
          placeholder="Tìm email / username / họ tên..." />
      </form>

      <div class="text-xs px-3 py-1 rounded-full bg-gray-100 text-slate-600 font-bold">
        <?= $isAdmin ? 'ADMIN' : 'NHÂN VIÊN' ?>
      </div>
    </div>
  </header>

  <div class="flex-1 overflow-y-auto p-4 md:p-8">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

      <?php if($msg): ?>
      <div class="p-4 rounded-2xl border shadow-soft bg-white <?= $type==='ok'?'border-green-200':($type==='error'?'border-red-200':'border-gray-200') ?>">
        <div class="flex gap-2 items-start">
          <span class="material-symbols-outlined <?= $type==='ok'?'text-green-600':($type==='error'?'text-red-600':'text-slate-600') ?>">
            <?= $type==='ok'?'check_circle':($type==='error'?'error':'info') ?>
          </span>
          <div class="text-sm font-semibold"><?= h($msg) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-blue-50 rounded-xl text-primary w-fit"><span class="material-symbols-outlined">groups</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Tổng tài khoản</div>
          <div class="text-2xl font-extrabold"><?= number_format($statTotal) ?></div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-green-50 rounded-xl text-green-700 w-fit"><span class="material-symbols-outlined">verified</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Đang hoạt động</div>
          <div class="text-2xl font-extrabold"><?= number_format($statActive) ?></div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-purple-50 rounded-xl text-purple-700 w-fit"><span class="material-symbols-outlined">admin_panel_settings</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Admin</div>
          <div class="text-2xl font-extrabold"><?= number_format($statAdmin) ?></div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="p-3 bg-orange-50 rounded-xl text-orange-700 w-fit"><span class="material-symbols-outlined">badge</span></div>
          <div class="mt-3 text-slate-500 text-sm font-medium">Nhân viên</div>
          <div class="text-2xl font-extrabold"><?= number_format($statStaff) ?></div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LIST -->
        <div class="lg:col-span-2 bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-extrabold">Danh sách nhân viên</div>
            <div class="text-xs text-slate-500">Bảng: <b>admin</b></div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-slate-500 border-b">
                  <th class="py-3 pr-3">Tài khoản</th>
                  <th class="py-3 pr-3">Họ tên</th>
                  <th class="py-3 pr-3">Vai trò</th>
                  <th class="py-3 pr-3">Trạng thái</th>
                  <th class="py-3 text-right">Chi tiết</th>
                </tr>
              </thead>
              <tbody>
              <?php if(!$rows): ?>
                <tr><td colspan="10" class="py-8 text-center text-slate-500">Không có dữ liệu.</td></tr>
              <?php endif; ?>

              <?php foreach($rows as $r): ?>
                <?php
                  $id=(int)$r['id'];
                  $role = strtoupper((string)($r['vai_tro'] ?? 'ADMIN'));
                  $active = true;
                  if ($A_STATUS && isset($r['trang_thai'])){
                    $active = is_numeric($r['trang_thai']) ? ((int)$r['trang_thai']===1) : ((string)$r['trang_thai']!=='0');
                  }
                ?>
                <tr class="border-b last:border-0 hover:bg-gray-50 <?= ($viewId===$id)?'bg-blue-50/40':'' ?>">
                  <td class="py-3 pr-3">
                    <div class="font-extrabold text-slate-900"><?= h($r['email']) ?></div>
                    <div class="text-xs text-slate-500">
                      ID: <?= $id ?>
                      <?php if(!empty($r['username'])): ?> • <?= h($r['username']) ?><?php endif; ?>
                    </div>
                  </td>

                  <td class="py-3 pr-3 font-bold text-slate-700"><?= h($r['ho_ten'] ?? '—') ?></td>

                  <td class="py-3 pr-3">
                    <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $role==='ADMIN'?'bg-purple-50 text-purple-700':'bg-blue-50 text-primary' ?>">
                      <?= $role==='ADMIN'?'ADMIN':'NHÂN VIÊN' ?>
                    </span>
                  </td>

                  <td class="py-3 pr-3">
                    <span class="inline-flex px-2 py-1 rounded-full text-[11px] font-extrabold <?= $active?'bg-green-50 text-green-700':'bg-slate-100 text-slate-700' ?>">
                      <?= $active?'Hoạt động':'Đã khóa' ?>
                    </span>
                    <?php if($id===$myId): ?>
                      <div class="text-[10px] text-slate-400 font-bold mt-1">(Tài khoản bạn)</div>
                    <?php endif; ?>
                  </td>

                  <td class="py-3 text-right">
                    <a class="px-3 py-2 rounded-xl bg-blue-50 text-primary font-extrabold hover:bg-blue-100 text-xs"
                       href="nhanvien.php?<?= h(http_build_query(array_merge($_GET,['xem'=>$id]))) ?>">
                      Xem
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <div class="flex items-center justify-between mt-4">
            <div class="text-xs text-slate-500">Trang <?= $page ?>/<?= $totalPages ?> • Tổng <?= number_format($total) ?></div>
            <div class="flex gap-2">
              <?php
              $qs=$_GET;
              $mk=function($p) use($qs){ $qs['page']=$p; return 'nhanvien.php?'.http_build_query($qs); };
              ?>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
              <a class="px-3 py-2 rounded-xl border bg-white text-sm font-bold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
                 href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
            </div>
          </div>
        </div>

        <!-- FORM -->
        <div class="bg-white p-4 md:p-6 rounded-2xl shadow-soft border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <div>
              <div class="text-lg font-extrabold"><?= $view ? 'Cập nhật nhân viên' : 'Thêm nhân viên' ?></div>
              <div class="text-xs text-slate-500">Chỉ ADMIN được thao tác</div>
            </div>
            <?php if($view): ?>
              <a class="text-sm font-extrabold text-primary hover:underline" href="nhanvien.php">Bỏ chọn</a>
            <?php endif; ?>
          </div>

          <?php if(!$isAdmin): ?>
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-sm text-slate-600">
              Bạn là <b>NHÂN VIÊN</b> nên không có quyền quản lý nhân sự.
            </div>
          <?php else: ?>

            <!-- ADD / EDIT -->
            <form method="post" class="space-y-3">
              <input type="hidden" name="action" value="<?= $view?'sua':'them' ?>">
              <?php if($view): ?><input type="hidden" name="id" value="<?= (int)$viewId ?>"><?php endif; ?>

              <div>
                <label class="text-sm font-bold">Email</label>
                <input name="email" required
                  value="<?= $view ? h($view[$A_EMAIL] ?? '') : '' ?>"
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
              </div>

              <?php if($A_USER): ?>
              <div>
                <label class="text-sm font-bold">Username</label>
                <input name="username"
                  value="<?= $view ? h($view[$A_USER] ?? '') : '' ?>"
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
              </div>
              <?php endif; ?>

              <?php if($A_NAME): ?>
              <div>
                <label class="text-sm font-bold">Họ tên</label>
                <input name="ho_ten"
                  value="<?= $view ? h($view[$A_NAME] ?? '') : '' ?>"
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50">
              </div>
              <?php endif; ?>

              <?php if($A_ROLE): ?>
              <div>
                <label class="text-sm font-bold">Vai trò</label>
                <?php $curRole = $view ? strtoupper((string)($view[$A_ROLE] ?? 'NHAN_VIEN')) : 'NHAN_VIEN'; ?>
                <select name="vai_tro" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50" <?= ($viewId===$myId)?'disabled':'' ?>>
                  <option value="NHAN_VIEN" <?= $curRole==='NHAN_VIEN'?'selected':'' ?>>NHÂN VIÊN</option>
                  <option value="ADMIN" <?= $curRole==='ADMIN'?'selected':'' ?>>ADMIN</option>
                </select>
                <?php if($viewId===$myId): ?>
                  <div class="text-[11px] text-slate-500 mt-1">Không cho tự đổi vai trò chính bạn.</div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <?php if($A_STATUS): ?>
              <div>
                <label class="text-sm font-bold">Trạng thái</label>
                <?php $curSt = $view ? (string)($view[$A_STATUS] ?? '1') : '1'; ?>
                <select name="trang_thai" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50" <?= ($viewId===$myId)?'disabled':'' ?>>
                  <option value="1" <?= $curSt==='1'?'selected':'' ?>>Hoạt động</option>
                  <option value="0" <?= $curSt==='0'?'selected':'' ?>>Khóa</option>
                </select>
                <?php if($viewId===$myId): ?>
                  <div class="text-[11px] text-slate-500 mt-1">Không cho tự khóa chính bạn.</div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <?php if(!$view): ?>
              <div>
                <label class="text-sm font-bold">Mật khẩu</label>
                <input type="password" name="mat_khau" required
                  class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                  placeholder="VD: 123456">
              </div>
              <?php endif; ?>

              <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
                <?= $view ? 'Lưu cập nhật' : 'Thêm nhân viên' ?>
              </button>
            </form>

            <?php if($view): ?>
              <!-- CHANGE PASS -->
              <form method="post" class="mt-4 space-y-2">
                <input type="hidden" name="action" value="doimk">
                <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                <label class="text-sm font-bold">Đổi mật khẩu</label>
                <input type="password" name="mat_khau_moi" class="mt-1 w-full rounded-xl border-gray-200 bg-gray-50"
                       placeholder="Nhập mật khẩu mới">
                <button class="w-full px-4 py-3 rounded-2xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200">
                  Cập nhật mật khẩu
                </button>
              </form>

              <div class="grid grid-cols-2 gap-2 mt-4">
                <?php if($A_STATUS && $viewId !== $myId): ?>
                  <form method="post" onsubmit="return confirm('Khóa/Mở tài khoản này?');">
                    <input type="hidden" name="action" value="khoa_mo">
                    <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                    <button class="w-full px-4 py-3 rounded-2xl bg-slate-100 text-slate-700 font-extrabold hover:bg-slate-200">
                      Khóa / Mở
                    </button>
                  </form>
                <?php else: ?>
                  <button class="w-full px-4 py-3 rounded-2xl bg-slate-100 text-slate-400 font-extrabold cursor-not-allowed">
                    Khóa / Mở
                  </button>
                <?php endif; ?>

                <?php if($viewId !== $myId): ?>
                  <form method="post" onsubmit="return confirm('Xóa nhân viên này?');">
                    <input type="hidden" name="action" value="xoa">
                    <input type="hidden" name="id" value="<?= (int)$viewId ?>">
                    <button class="w-full px-4 py-3 rounded-2xl bg-red-600 text-white font-extrabold hover:bg-red-700">
                      Xóa
                    </button>
                  </form>
                <?php else: ?>
                  <button class="w-full px-4 py-3 rounded-2xl bg-red-100 text-red-300 font-extrabold cursor-not-allowed">
                    Xóa
                  </button>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          <?php endif; ?>
        </div>

      </div>

      <div class="bg-white p-5 rounded-2xl shadow-soft border border-gray-100 text-xs text-slate-500">
        <b>Ghi chú phân quyền</b>: Vai trò lấy từ cột <b><?= h($A_ROLE ?: 'vai_tro') ?></b>. Chỉ <b>ADMIN</b> được thêm/sửa/xóa nhân viên.
      </div>

    </div>
  </div>
</main>
</body>
</html>
