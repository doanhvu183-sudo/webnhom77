<?php
// admin/phieuxuat.php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/hamChung.php';

require_login_admin();
if (function_exists('requirePermission')) requirePermission('phieuxuat', $pdo);

$ACTIVE = 'phieuxuat';
$PAGE_TITLE = 'Phiếu xuất';

function qn(string $s): string { return '`'.str_replace('`','',$s).'`'; }

$hasPX  = tableExists($pdo,'phieuxuat');
$hasCT  = tableExists($pdo,'ct_phieuxuat');

$flash = function_exists('flash_get') ? flash_get() : ($_SESSION['_flash'] ?? null);
unset($_SESSION['_flash']);

$PX = $hasPX ? getCols($pdo,'phieuxuat') : [];
$CT = $hasCT ? getCols($pdo,'ct_phieuxuat') : [];

$PX_ID   = $hasPX ? pickCol($PX,['id_phieu_xuat','id']) : null;
$PX_MA   = $hasPX ? pickCol($PX,['ma_phieu','ma','code']) : null;
$PX_TONG = $hasPX ? pickCol($PX,['tong_tien','tong_gia_tri']) : null;
$PX_STT  = $hasPX ? pickCol($PX,['trang_thai','status']) : null;
$PX_GHI  = $hasPX ? pickCol($PX,['ghi_chu','note']) : null;
$PX_NGAY = $hasPX ? pickCol($PX,['ngay_tao','created_at','ngay_xuat']) : null;

$CT_ID   = $hasCT ? pickCol($CT,['id_ct','id']) : null;
$CT_PX   = $hasCT ? pickCol($CT,['id_phieu_xuat','phieu_xuat_id']) : null;
$CT_SP   = $hasCT ? pickCol($CT,['id_san_pham','sanpham_id']) : null;
$CT_TEN  = $hasCT ? pickCol($CT,['ten_san_pham','ten']) : null;
$CT_SL   = $hasCT ? pickCol($CT,['so_luong','sl']) : null;
$CT_GIA  = $hasCT ? pickCol($CT,['don_gia','gia_xuat']) : null;
$CT_TT   = $hasCT ? pickCol($CT,['thanh_tien','thanh_toan']) : null;

$id = (int)($_GET['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($action==='px_add') {
    if (!$hasPX || !$PX_ID) {
      flash_set(['type'=>'error','msg'=>'Chưa có bảng phieuxuat (hoặc thiếu cột id).']);
      header("Location: phieuxuat.php"); exit;
    }
    $ma = trim((string)($_POST['ma'] ?? ''));
    $ghi = trim((string)($_POST['ghi_chu'] ?? ''));
    $stt = trim((string)($_POST['trang_thai'] ?? 'DRAFT'));

    $fields=[]; $vals=[]; $bind=[];
    if ($PX_MA && $ma!==''){ $fields[]=$PX_MA; $vals[]=':ma'; $bind[':ma']=$ma; }
    if ($PX_GHI){ $fields[]=$PX_GHI; $vals[]=':g'; $bind[':g']=$ghi; }
    if ($PX_STT){ $fields[]=$PX_STT; $vals[]=':s'; $bind[':s']=$stt; }
    if ($PX_NGAY){ $fields[]=$PX_NGAY; $vals[]='NOW()'; }
    if ($PX_TONG){ $fields[]=$PX_TONG; $vals[]='0'; }

    $sql="INSERT INTO phieuxuat(".implode(',',array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);
    $newId=(int)$pdo->lastInsertId();

    if (function_exists('nhatky_log')) nhatky_log($pdo,'THEM_PHIEU_XUAT',"Thêm phiếu xuất #{$newId}",'phieuxuat',$newId);
    flash_set(['type'=>'ok','msg'=>'Đã tạo phiếu xuất.']);
    header("Location: phieuxuat.php?id=".$newId); exit;
  }

  if ($action==='ct_add') {
    if (!$hasCT || !$CT_PX || !$CT_SL) {
      flash_set(['type'=>'error','msg'=>'Chưa có bảng ct_phieuxuat hoặc thiếu cột bắt buộc.']);
      header("Location: phieuxuat.php?id=".$id); exit;
    }
    $pxId=(int)($_POST['px_id'] ?? 0);
    $spId=(int)($_POST['id_san_pham'] ?? 0);
    $ten=trim((string)($_POST['ten_san_pham'] ?? ''));
    $sl=(int)($_POST['so_luong'] ?? 0);
    $gia=(int)($_POST['don_gia'] ?? 0);
    if ($pxId<=0 || $sl<=0) {
      flash_set(['type'=>'error','msg'=>'Thiếu phiếu xuất hoặc số lượng không hợp lệ.']);
      header("Location: phieuxuat.php?id=".$pxId); exit;
    }

    $fields=[$CT_PX,$CT_SL];
    $vals=[':px',':sl'];
    $bind=[':px'=>$pxId,':sl'=>$sl];

    if ($CT_SP){ $fields[]=$CT_SP; $vals[]=':sp'; $bind[':sp']=$spId?:null; }
    if ($CT_TEN){ $fields[]=$CT_TEN; $vals[]=':ten'; $bind[':ten']=$ten; }
    if ($CT_GIA){ $fields[]=$CT_GIA; $vals[]=':gia'; $bind[':gia']=$gia; }
    if ($CT_TT){ $fields[]=$CT_TT; $vals[]=':tt'; $bind[':tt']=($gia*$sl); }

    $sql="INSERT INTO ct_phieuxuat(".implode(',',array_map('qn',$fields)).") VALUES(".implode(',',$vals).")";
    $pdo->prepare($sql)->execute($bind);

    flash_set(['type'=>'ok','msg'=>'Đã thêm dòng xuất.']);
    header("Location: phieuxuat.php?id=".$pxId); exit;
  }

  if ($action==='ct_del') {
    $ctId=(int)($_POST['ct_id'] ?? 0);
    $pxId=(int)($_POST['px_id'] ?? 0);
    if ($hasCT && $CT_ID && $ctId>0) {
      $pdo->prepare("DELETE FROM ct_phieuxuat WHERE ".qn($CT_ID)."=?")->execute([$ctId]);
      if (function_exists('nhatky_log')) nhatky_log($pdo,'XOA_CT_PHIEU_XUAT',"Xóa dòng CT #{$ctId}",'ct_phieuxuat',$ctId);
      flash_set(['type'=>'ok','msg'=>'Đã xóa dòng.']);
    }
    header("Location: phieuxuat.php?id=".$pxId); exit;
  }

  if ($action==='px_cancel') {
    $pxId=(int)($_POST['px_id'] ?? 0);
    if ($hasPX && $PX_ID && $PX_STT && $pxId>0) {
      $pdo->prepare("UPDATE phieuxuat SET ".qn($PX_STT)."='HUY' WHERE ".qn($PX_ID)."=?")->execute([$pxId]);
      if (function_exists('nhatky_log')) nhatky_log($pdo,'HUY_PHIEU_XUAT',"Hủy phiếu xuất #{$pxId}",'phieuxuat',$pxId);
      flash_set(['type'=>'ok','msg'=>'Đã hủy phiếu xuất.']);
    }
    header("Location: phieuxuat.php?id=".$pxId); exit;
  }
}

$rows = [];
if ($hasPX && $PX_ID) {
  $sql="SELECT ".qn($PX_ID)." AS id"
    .($PX_MA?(", ".qn($PX_MA)." AS ma"):"")
    .($PX_STT?(", ".qn($PX_STT)." AS trang_thai"):"")
    .($PX_TONG?(", ".qn($PX_TONG)." AS tong"):"")
    .($PX_NGAY?(", ".qn($PX_NGAY)." AS ngay"):"")
    ." FROM phieuxuat ORDER BY ".($PX_NGAY?qn($PX_NGAY):qn($PX_ID))." DESC LIMIT 30";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if ($id<=0 && $rows) $id = (int)$rows[0]['id'];
}

$px = null; $cts=[];
if ($id>0 && $hasPX && $PX_ID) {
  $st=$pdo->prepare("SELECT * FROM phieuxuat WHERE ".qn($PX_ID)."=? LIMIT 1");
  $st->execute([$id]);
  $px=$st->fetch(PDO::FETCH_ASSOC) ?: null;

  if ($hasCT && $CT_PX) {
    $st=$pdo->prepare("SELECT * FROM ct_phieuxuat WHERE ".qn($CT_PX)."=? ORDER BY ".($CT_ID?qn($CT_ID):qn($CT_PX))." DESC LIMIT 200");
    $st->execute([$id]);
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
        <div class="text-xl font-extrabold">Phiếu xuất</div>
        <div class="text-xs text-muted font-bold">Tạo phiếu xuất, thêm dòng, hủy phiếu</div>
      </div>
      <form method="post" class="flex items-center gap-2">
        <input type="hidden" name="action" value="px_add">
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

    <?php if(!$hasPX): ?>
      <div class="bg-white rounded-2xl border border-line shadow-card p-6">
        <div class="font-extrabold text-red-600">Bạn chưa có bảng <b>phieuxuat</b> / <b>ct_phieuxuat</b>.</div>
      </div>
    <?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
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
                  <a class="font-extrabold hover:underline" href="phieuxuat.php?id=<?= (int)$r['id'] ?>">
                    #<?= (int)$r['id'] ?> <?= h($r['ma'] ?? '') ?>
                  </a>
                  <div class="text-xs text-muted font-bold"><?= h($r['ngay'] ?? '') ?></div>
                </td>
                <td class="py-3 pr-3"><span class="px-3 py-1 rounded-full bg-slate-100 text-xs font-extrabold"><?= h($r['trang_thai'] ?? '') ?></span></td>
                <td class="py-3 pr-0 text-right font-extrabold"><?= isset($r['tong']) ? money_vnd((int)$r['tong']) : '—' ?></td>
              </tr>
            <?php endforeach; if(!$rows): ?>
              <tr><td colspan="3" class="py-8 text-center text-slate-500 font-bold">Chưa có phiếu xuất</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="lg:col-span-5 space-y-6">
        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="flex items-center justify-between">
            <div class="text-base font-extrabold">Chi tiết phiếu</div>
            <div class="text-xs text-muted font-bold"><?= $px ? ('#'.$id) : 'Chọn 1 phiếu' ?></div>
          </div>

          <?php if(!$px): ?>
            <div class="mt-4 text-sm text-muted font-bold">Chưa chọn phiếu xuất.</div>
          <?php else: ?>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div class="p-3 rounded-2xl border border-line">
                <div class="text-xs text-muted font-bold">Trạng thái</div>
                <div class="font-extrabold"><?= h($PX_STT ? ($px[$PX_STT] ?? '') : '') ?></div>
              </div>
              <div class="p-3 rounded-2xl border border-line">
                <div class="text-xs text-muted font-bold">Tổng</div>
                <div class="font-extrabold"><?= $PX_TONG ? money_vnd((int)($px[$PX_TONG] ?? 0)) : '—' ?></div>
              </div>
            </div>

            <form method="post" class="mt-4 rounded-2xl border border-line p-4">
              <div class="text-sm font-extrabold">Thêm dòng xuất</div>
              <input type="hidden" name="action" value="ct_add">
              <input type="hidden" name="px_id" value="<?= (int)$id ?>">

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
              <div class="text-sm font-extrabold mb-2">Dòng xuất</div>
              <div class="space-y-2">
                <?php foreach($cts as $c):
                  $ctid = $CT_ID ? (int)($c[$CT_ID] ?? 0) : 0;
                  $ten  = $CT_TEN ? (string)($c[$CT_TEN] ?? '') : '';
                  $sl   = $CT_SL ? (int)($c[$CT_SL] ?? 0) : 0;
                  $gia  = $CT_GIA ? (int)($c[$CT_GIA] ?? 0) : 0;
                ?>
                <div class="p-3 rounded-2xl border border-line flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <div class="font-extrabold truncate"><?= h($ten ?: 'Dòng xuất') ?></div>
                    <div class="text-xs text-muted font-bold">SL: <?= $sl ?> · Giá: <?= money_vnd($gia) ?></div>
                  </div>
                  <form method="post" onsubmit="return confirm('Xóa dòng này?');">
                    <input type="hidden" name="action" value="ct_del">
                    <input type="hidden" name="px_id" value="<?= (int)$id ?>">
                    <input type="hidden" name="ct_id" value="<?= $ctid ?>">
                    <button class="px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-extrabold">Xóa</button>
                  </form>
                </div>
                <?php endforeach; if(!$cts): ?>
                  <div class="text-sm text-muted font-bold">Chưa có dòng xuất.</div>
                <?php endif; ?>
              </div>
            </div>

            <form method="post" class="mt-5" onsubmit="return confirm('Hủy phiếu xuất này?');">
              <input type="hidden" name="action" value="px_cancel">
              <input type="hidden" name="px_id" value="<?= (int)$id ?>">
              <button class="w-full px-4 py-2.5 rounded-xl bg-yellow-50 text-yellow-700 font-extrabold">Hủy phiếu</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="bg-white rounded-2xl border border-line shadow-card p-5">
          <div class="text-sm font-extrabold">SQL mẫu (nếu bạn chưa có bảng)</div>
          <details class="mt-3">
            <summary class="cursor-pointer text-xs font-extrabold text-slate-700">Tạo bảng phieuxuat + ct_phieuxuat</summary>
            <pre class="mt-2 text-xs bg-slate-50 border border-line rounded-xl p-3 overflow-auto"><?=
h("CREATE TABLE IF NOT EXISTS phieuxuat (
  id_phieu_xuat INT AUTO_INCREMENT PRIMARY KEY,
  ma_phieu VARCHAR(50) NULL,
  tong_tien INT NOT NULL DEFAULT 0,
  trang_thai VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  ghi_chu VARCHAR(255) NULL,
  ngay_tao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ct_phieuxuat (
  id_ct INT AUTO_INCREMENT PRIMARY KEY,
  id_phieu_xuat INT NOT NULL,
  id_san_pham INT NULL,
  ten_san_pham VARCHAR(255) NULL,
  so_luong INT NOT NULL,
  don_gia INT NOT NULL DEFAULT 0,
  thanh_tien INT NOT NULL DEFAULT 0,
  FOREIGN KEY (id_phieu_xuat) REFERENCES phieuxuat(id_phieu_xuat) ON DELETE CASCADE
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
