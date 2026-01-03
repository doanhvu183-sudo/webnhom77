<?php
// admin/phieunhap.php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

require_login_admin();
if (function_exists('requirePermission')) requirePermission('phieunhap', $pdo);

$ACTIVE = 'phieunhap';
$PAGE_TITLE = 'Phiếu nhập';

function qn(string $s): string { return '`'.str_replace('`','',$s).'`'; }

$hasPN  = tableExists($pdo,'phieunhap');
$hasCT  = tableExists($pdo,'ct_phieunhap');

$flash = null;
if (!function_exists('flash_get')) {
  // fallback
  $flash = $_SESSION['_flash'] ?? null;
  unset($_SESSION['_flash']);
} else {
  $flash = flash_get();
}

// ====== Map columns (if exist) ======
$PN = $hasPN ? getCols($pdo,'phieunhap') : [];
$CT = $hasCT ? getCols($pdo,'ct_phieunhap') : [];

$PN_ID   = $hasPN ? pickCol($PN,['id_phieu_nhap','id']) : null;
$PN_MA   = $hasPN ? pickCol($PN,['ma_phieu','ma','code']) : null;
$PN_NCC  = $hasPN ? pickCol($PN,['id_ncc','ncc_id']) : null;
$PN_TONG = $hasPN ? pickCol($PN,['tong_tien','tong_gia_tri']) : null;
$PN_STT  = $hasPN ? pickCol($PN,['trang_thai','status']) : null;
$PN_GHI  = $hasPN ? pickCol($PN,['ghi_chu','note']) : null;
$PN_NGAY = $hasPN ? pickCol($PN,['ngay_tao','created_at','ngay_nhap']) : null;

$CT_ID   = $hasCT ? pickCol($CT,['id_ct','id']) : null;
$CT_PN   = $hasCT ? pickCol($CT,['id_phieu_nhap','phieu_nhap_id']) : null;
$CT_SP   = $hasCT ? pickCol($CT,['id_san_pham','sanpham_id']) : null;
$CT_TEN  = $hasCT ? pickCol($CT,['ten_san_pham','ten']) : null;
$CT_SL   = $hasCT ? pickCol($CT,['so_luong','sl']) : null;
$CT_GIA  = $hasCT ? pickCol($CT,['don_gia','gia_nhap']) : null;
$CT_TT   = $hasCT ? pickCol($CT,['thanh_tien','thanh_toan']) : null;

$id = (int)($_GET['id'] ?? 0);
$action = $_POST['action'] ?? '';

/* ================= POST HANDLERS (before any output) ================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  // add new phieunhap (header only)
  if ($action==='pn_add') {
    if (!$hasPN || !$PN_ID) {
      flash_set(['type'=>'error','msg'=>'Chưa có bảng phieunhap (hoặc thiếu cột id).']);
      header("Location: phieunhap.php"); exit;
    }
    $ma = trim((string)($_POST['ma'] ?? ''));
    $ghi = trim((string)($_POST['ghi_chu'] ?? ''));
    $stt = trim((string)($_POST['trang_thai'] ?? 'DRAFT'));

    $fields=[]; $vals=[]; $bind=[];
    if ($PN_MA && $ma!==''){ $fields[]=$PN_MA; $vals[]=':ma'; $bind[':ma']=$ma; }
    if ($PN_GHI){ $fields[]=$PN_GHI; $vals[]=':g'; $bind[':g']=$ghi; }
    if ($PN_STT){ $fields[]=$PN_STT; $vals[]=':s'; $bind[':s']=$stt; }
    if ($PN_NGAY){ $fields[]=$PN_NGAY; $vals[]='NOW()'; }
    if ($PN_TONG){ $fields[]=$PN_TONG; $vals[]='0'; }

    $sql="INSERT INTO phieunhap(".implode(',',array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $newId=(int)$pdo->lastInsertId();

    if (function_exists('nhatky_log')) nhatky_log($pdo,'THEM_PHIEU_NHAP',"Thêm phiếu nhập #{$newId}",'phieunhap',$newId);
    flash_set(['type'=>'ok','msg'=>'Đã tạo phiếu nhập.']);
    header("Location: phieunhap.php?id=".$newId); exit;
  }

  // add line item
  if ($action==='ct_add') {
    if (!$hasCT || !$CT_PN || !$CT_SL) {
      flash_set(['type'=>'error','msg'=>'Chưa có bảng ct_phieunhap hoặc thiếu cột bắt buộc.']);
      header("Location: phieunhap.php?id=".$id); exit;
    }
    $pnId=(int)($_POST['pn_id'] ?? 0);
    $spId=(int)($_POST['id_san_pham'] ?? 0);
    $ten=trim((string)($_POST['ten_san_pham'] ?? ''));
    $sl=(int)($_POST['so_luong'] ?? 0);
    $gia=(int)($_POST['don_gia'] ?? 0);
    if ($pnId<=0 || $sl<=0) {
      flash_set(['type'=>'error','msg'=>'Thiếu phiếu nhập hoặc số lượng không hợp lệ.']);
      header("Location: phieunhap.php?id=".$pnId); exit;
    }

    $fields=[$CT_PN,$CT_SL];
    $vals=[':pn',':sl'];
    $bind=[':pn'=>$pnId,':sl'=>$sl];

    if ($CT_SP){ $fields[]=$CT_SP; $vals[]=':sp'; $bind[':sp']=$spId?:null; }
    if ($CT_TEN){ $fields[]=$CT_TEN; $vals[]=':ten'; $bind[':ten']=$ten; }
    if ($CT_GIA){ $fields[]=$CT_GIA; $vals[]=':gia'; $bind[':gia']=$gia; }
    if ($CT_TT){ $fields[]=$CT_TT; $vals[]=':tt'; $bind[':tt']=($gia*$sl); }

    $sql="INSERT INTO ct_phieunhap(".implode(',',array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    flash_set(['type'=>'ok','msg'=>'Đã thêm dòng nhập.']);
    header("Location: phieunhap.php?id=".$pnId); exit;
  }

  // delete line
  if ($action==='ct_del') {
    $ctId=(int)($_POST['ct_id'] ?? 0);
    $pnId=(int)($_POST['pn_id'] ?? 0);
    if ($hasCT && $CT_ID && $ctId>0) {
      $pdo->prepare("DELETE FROM ct_phieunhap WHERE ".qn($CT_ID)."=?")->execute([$ctId]);
      if (function_exists('nhatky_log')) nhatky_log($pdo,'XOA_CT_PHIEU_NHAP',"Xóa dòng CT #{$ctId}",'ct_phieunhap',$ctId);
      flash_set(['type'=>'ok','msg'=>'Đã xóa dòng.']);
    }
    header("Location: phieunhap.php?id=".$pnId); exit;
  }

  // cancel phieu
  if ($action==='pn_cancel') {
    $pnId=(int)($_POST['pn_id'] ?? 0);
    if ($hasPN && $PN_ID && $PN_STT && $pnId>0) {
      $pdo->prepare("UPDATE phieunhap SET ".qn($PN_STT)."='HUY' WHERE ".qn($PN_ID)."=?")->execute([$pnId]);
      if (function_exists('nhatky_log')) nhatky_log($pdo,'HUY_PHIEU_NHAP',"Hủy phiếu nhập #{$pnId}",'phieunhap',$pnId);
      flash_set(['type'=>'ok','msg'=>'Đã hủy phiếu nhập.']);
    }
    header("Location: phieunhap.php?id=".$pnId); exit;
  }
}

/* ================= LOAD LIST + DETAIL ================= */
$rows = [];
if ($hasPN && $PN_ID) {
  $sql="SELECT ".qn($PN_ID)." AS id"
    .($PN_MA?(", ".qn($PN_MA)." AS ma"):"")
    .($PN_STT?(", ".qn($PN_STT)." AS trang_thai"):"")
    .($PN_TONG?(", ".qn($PN_TONG)." AS tong"):"")
    .($PN_NGAY?(", ".qn($PN_NGAY)." AS ngay"):"")
    ." FROM phieunhap ORDER BY ".($PN_NGAY?qn($PN_NGAY):qn($PN_ID))." DESC LIMIT 30";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if ($id<=0 && $rows) $id = (int)$rows[0]['id'];
}

$pn = null;
$cts = [];
if ($id>0 && $hasPN && $PN_ID) {
  $st=$pdo->prepare("SELECT * FROM phieunhap WHERE ".qn($PN_ID)."=? LIMIT 1");
  $st->execute([$id]);
  $pn=$st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($hasCT && $CT_PN) {
    $cols=["*"];
    $sql="SELECT * FROM ct_phieunhap WHERE ".qn($CT_PN)."=? ORDER BY ".($CT_ID?qn($CT_ID):qn($CT_PN))." DESC LIMIT 200";
    $st=$pdo->prepare($sql); $st->execute([$id]);
    $cts=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
}

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhBen.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>
<div class="p-4 md:p-8">
  <div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
      <div>
        <div class="text-xl font-extrabold">Phiếu nhập</div>
        <div class="text-xs text-muted font-bold">Tạo phiếu nhập, thêm dòng, hủy phiếu</div>
      </div>
      <form method="post" class="flex items-center gap-2">
        <input type="hidden" name="action" value="pn_add">
        <input name="ma" class="px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Mã phiếu (tuỳ chọn)">
        <button class="px-4 py-2.5 rounded-xl bg-primary text-white font-extrabold">+ Tạo phiếu</button>
      </form>
    </div>

    <?php if($flash): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-4">
        <div class="text-sm font-extrabold <?= ($flash['type']??'')==='ok'?'text-green-600':'text-red-600' ?>">
          <?= h($flash['msg'] ?? '') ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if(!$hasPN): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-6">
        <div class="font-extrabold text-red-600">Bạn chưa có bảng <b>phieunhap</b> / <b>ct_phieunhap</b>.</div>
        <div class="text-sm text-muted font-bold mt-2">Dán SQL mẫu ở cuối file để tạo.</div>
      </div>
    <?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- LIST -->
      <div class="lg:col-span-7 bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="text-base font-extrabold">Danh sách (30 gần nhất)</div>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-slate-500">
                <th class="text-left py-3 pr-3">Phiếu</th>
                <th class="text-left py-3 pr-3">Trạng thái</th>
                <th class="text-right py-3 pr-0">Tổng</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach($rows as $r): $isSel=((int)$r['id']===$id); ?>
              <tr class="<?= $isSel?'bg-primary/5':'' ?>">
                <td class="py-3 pr-3">
                  <a class="font-extrabold hover:underline" href="phieunhap.php?id=<?= (int)$r['id'] ?>">
                    #<?= (int)$r['id'] ?> <?= h($r['ma'] ?? '') ?>
                  </a>
                  <div class="text-xs text-muted font-bold"><?= h($r['ngay'] ?? '') ?></div>
                </td>
                <td class="py-3 pr-3"><span class="px-3 py-1 rounded-full bg-slate-100 text-xs font-extrabold"><?= h($r['trang_thai'] ?? '') ?></span></td>
                <td class="py-3 pr-0 text-right font-extrabold"><?= isset($r['tong']) ? money_vnd((int)$r['tong']) : '—' ?></td>
              </tr>
            <?php endforeach; if(!$rows): ?>
              <tr><td colspan="3" class="py-8 text-center text-slate-500 font-bold">Chưa có phiếu nhập</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- DETAIL -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="flex items-center justify-between">
            <div class="text-base font-extrabold">Chi tiết phiếu</div>
            <div class="text-xs text-muted font-bold"><?= $pn ? ('#'.$id) : 'Chọn 1 phiếu' ?></div>
          </div>

          <?php if(!$pn): ?>
            <div class="mt-4 text-sm text-muted font-bold">Chưa chọn phiếu nhập.</div>
          <?php else: ?>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div class="p-3 rounded-2xl border border-line">
                <div class="text-xs text-muted font-bold">Trạng thái</div>
                <div class="font-extrabold"><?= h($PN_STT ? ($pn[$PN_STT] ?? '') : '') ?></div>
              </div>
              <div class="p-3 rounded-2xl border border-line">
                <div class="text-xs text-muted font-bold">Tổng</div>
                <div class="font-extrabold"><?= $PN_TONG ? money_vnd((int)($pn[$PN_TONG] ?? 0)) : '—' ?></div>
              </div>
            </div>

            <form method="post" class="mt-4 rounded-2xl border border-line p-4">
              <div class="text-sm font-extrabold">Thêm dòng nhập</div>
              <input type="hidden" name="action" value="ct_add">
              <input type="hidden" name="pn_id" value="<?= (int)$id ?>">

              <div class="mt-3 space-y-3">
                <input name="id_san_pham" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="ID sản phẩm (tuỳ chọn)">
                <input name="ten_san_pham" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Tên sản phẩm (tuỳ chọn)">
                <div class="grid grid-cols-2 gap-3">
                  <input name="so_luong" type="number" min="1" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Số lượng">
                  <input name="don_gia" type="number" min="0" class="w-full px-4 py-2.5 rounded-xl border border-line text-sm font-bold" placeholder="Đơn giá">
                </div>
                <button class="w-full px-4 py-2.5 rounded-xl bg-primary text-white font-extrabold">Thêm</button>
              </div>
            </form>

            <div class="mt-4">
              <div class="text-sm font-extrabold mb-2">Dòng nhập</div>
              <div class="space-y-2">
                <?php foreach($cts as $c):
                  $ctid = $CT_ID ? (int)($c[$CT_ID] ?? 0) : 0;
                  $ten  = $CT_TEN ? (string)($c[$CT_TEN] ?? '') : '';
                  $sl   = $CT_SL ? (int)($c[$CT_SL] ?? 0) : 0;
                  $gia  = $CT_GIA ? (int)($c[$CT_GIA] ?? 0) : 0;
                ?>
                <div class="p-3 rounded-2xl border border-line flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <div class="font-extrabold truncate"><?= h($ten ?: 'Dòng nhập') ?></div>
                    <div class="text-xs text-muted font-bold">SL: <?= $sl ?> · Giá: <?= money_vnd($gia) ?></div>
                  </div>
                  <form method="post" onsubmit="return confirm('Xóa dòng này?');">
                    <input type="hidden" name="action" value="ct_del">
                    <input type="hidden" name="pn_id" value="<?= (int)$id ?>">
                    <input type="hidden" name="ct_id" value="<?= $ctid ?>">
                    <button class="px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-extrabold">Xóa</button>
                  </form>
                </div>
                <?php endforeach; if(!$cts): ?>
                  <div class="text-sm text-muted font-bold">Chưa có dòng nhập.</div>
                <?php endif; ?>
              </div>
            </div>

            <form method="post" class="mt-5" onsubmit="return confirm('Hủy phiếu nhập này?');">
              <input type="hidden" name="action" value="pn_cancel">
              <input type="hidden" name="pn_id" value="<?= (int)$id ?>">
              <button class="w-full px-4 py-2.5 rounded-xl bg-yellow-50 text-yellow-700 font-extrabold">Hủy phiếu</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="text-sm font-extrabold">SQL mẫu (nếu bạn chưa có bảng)</div>
          <details class="mt-3">
            <summary class="cursor-pointer text-xs font-extrabold text-slate-700">Tạo bảng phieunhap + ct_phieunhap</summary>
            <pre class="mt-2 text-xs bg-slate-50 border border-line rounded-xl p-3 overflow-auto"><?=
h("CREATE TABLE IF NOT EXISTS phieunhap (
  id_phieu_nhap INT AUTO_INCREMENT PRIMARY KEY,
  ma_phieu VARCHAR(50) NULL,
  id_ncc INT NULL,
  tong_tien INT NOT NULL DEFAULT 0,
  trang_thai VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  ghi_chu VARCHAR(255) NULL,
  ngay_tao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ct_phieunhap (
  id_ct INT AUTO_INCREMENT PRIMARY KEY,
  id_phieu_nhap INT NOT NULL,
  id_san_pham INT NULL,
  ten_san_pham VARCHAR(255) NULL,
  so_luong INT NOT NULL,
  don_gia INT NOT NULL DEFAULT 0,
  thanh_tien INT NOT NULL DEFAULT 0,
  FOREIGN KEY (id_phieu_nhap) REFERENCES phieunhap(id_phieu_nhap) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")
?></pre>
          </details>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
