<?php
// admin/tonkho.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/includes/helpers.php';

if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$ACTIVE = 'tonkho';
$PAGE_TITLE = 'Quản lý Tồn kho';
requirePermission('tonkho');

/* ===== validate tables ===== */
$fatal = false;
if (!tableExists($pdo,'tonkho')) $fatal = true;

$TK_ID = $TK_SPID = $TK_QTY = $TK_UPD = null;
$SP_ID = $SP_NAME = $SP_IMG = $SP_COST = $SP_PRICE = null;

if(!$fatal){
  $tkCols = getCols($pdo,'tonkho');
  $TK_ID   = pickCol($tkCols, ['id_tonkho','id']);
  $TK_SPID = pickCol($tkCols, ['id_san_pham','sanpham_id','id_sp']);
  $TK_QTY  = pickCol($tkCols, ['so_luong','ton','qty','quantity']);
  $TK_UPD  = pickCol($tkCols, ['ngay_cap_nhat','updated_at']);

  if(!$TK_ID || !$TK_SPID || !$TK_QTY){
    $fatal = true;
  }
}

$spOk = tableExists($pdo,'sanpham');
if($spOk){
  $spCols = getCols($pdo,'sanpham');
  $SP_ID    = pickCol($spCols, ['id_san_pham','id']);
  $SP_NAME  = pickCol($spCols, ['ten_san_pham','ten','name']);
  $SP_IMG   = pickCol($spCols, ['hinh_anh','anh','image']);
  $SP_COST  = pickCol($spCols, ['gia_nhap','gia_von','cost']);
  $SP_PRICE = pickCol($spCols, ['gia','gia_ban','price']);
}

/* ===== helpers redirect ===== */
function go_tonkho($params=[]){
  redirectWith('tonkho.php', $params);
}

/* ================= POST actions (MUST be before render) ================= */
if(!$fatal && $_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';

  // tạo bản ghi tồn cho SP chưa có trong tonkho
  if($action==='tao_ton'){
    if(!$spOk || !$SP_ID){
      go_tonkho(['type'=>'error','msg'=>'Thiếu bảng/cột sanpham để tạo tồn.']);
    }
    // Tạo tồn = 0 cho SP chưa có
    $sql = "
      INSERT INTO tonkho($TK_SPID, $TK_QTY".($TK_UPD?(", $TK_UPD"):"").")
      SELECT sp.$SP_ID, 0".($TK_UPD?(", NOW()"):"")."
      FROM sanpham sp
      LEFT JOIN tonkho tk ON tk.$TK_SPID = sp.$SP_ID
      WHERE tk.$TK_SPID IS NULL
    ";
    $pdo->exec($sql);

    nhatky_log($pdo,'TAO_TON_KHO','Tạo bản ghi tồn kho cho các sản phẩm chưa có','tonkho',null,[]);
    go_tonkho(['type'=>'ok','msg'=>'Đã tạo bản ghi tồn (0) cho sản phẩm chưa có.']);
  }

  // cập nhật tồn
  if($action==='cap_nhat'){
    $id_tonkho = (int)($_POST['id_tonkho'] ?? 0);
    $newQty = (int)($_POST['so_luong'] ?? 0);

    if($id_tonkho<=0) go_tonkho(['type'=>'error','msg'=>'Thiếu ID tồn kho.']);

    // lấy cũ để log
    $st = $pdo->prepare("SELECT $TK_SPID, $TK_QTY FROM tonkho WHERE $TK_ID=? LIMIT 1");
    $st->execute([$id_tonkho]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if(!$row) go_tonkho(['type'=>'error','msg'=>'Bản ghi tồn kho không tồn tại.']);

    $id_sp = (int)$row[$TK_SPID];
    $oldQty = (int)$row[$TK_QTY];

    $upd = "UPDATE tonkho SET $TK_QTY=?, ".($TK_UPD?("$TK_UPD=NOW(), "):"")."$TK_ID=$TK_ID WHERE $TK_ID=?";
    // fix query gọn:
    $upd = $TK_UPD
      ? "UPDATE tonkho SET $TK_QTY=?, $TK_UPD=NOW() WHERE $TK_ID=?"
      : "UPDATE tonkho SET $TK_QTY=? WHERE $TK_ID=?";

    $pdo->prepare($upd)->execute([$newQty,$id_tonkho]);

    nhatky_log(
      $pdo,
      'CAP_NHAT_TON_KHO',
      "Cập nhật tồn SP #{$id_sp}: {$oldQty} → {$newQty}",
      'tonkho',
      $id_sp,
      ['old'=>$oldQty,'new'=>$newQty]
    );

    go_tonkho(['type'=>'ok','msg'=>'Đã cập nhật tồn kho.','xem'=>$id_tonkho]);
  }

  go_tonkho(['type'=>'error','msg'=>'Action không hợp lệ.']);
}

/* ================= filters/list ================= */
$q = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page-1)*$perPage;

$lowStock = (int)get_setting($pdo,'low_stock_threshold',5);

$rows = [];
$total = 0;
$totalPages = 1;

if(!$fatal){
  $where = " WHERE 1 ";
  $params = [];

  if($q!==''){
    $conds = [];
    // search theo id_sp / tên sp
    if (ctype_digit($q)) {
      $conds[] = "tk.$TK_SPID = ?";
      $params[] = (int)$q;
    }
    if($spOk && $SP_NAME){
      $conds[] = "sp.$SP_NAME LIKE ?";
      $params[] = "%$q%";
    }
    if($conds) $where .= " AND (".implode(" OR ",$conds).") ";
  }

  $countSql = "SELECT COUNT(*) FROM tonkho tk ".($spOk && $SP_ID ? "LEFT JOIN sanpham sp ON sp.$SP_ID = tk.$TK_SPID " : "")." $where";
  $st = $pdo->prepare($countSql);
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $totalPages = max(1,(int)ceil($total/$perPage));

  $fields = [
    "tk.$TK_ID AS id_tonkho",
    "tk.$TK_SPID AS id_san_pham",
    "tk.$TK_QTY AS so_luong",
  ];
  if($TK_UPD) $fields[] = "tk.$TK_UPD AS cap_nhat";

  if($spOk && $SP_ID){
    if($SP_NAME)  $fields[] = "sp.$SP_NAME AS ten_san_pham";
    if($SP_IMG)   $fields[] = "sp.$SP_IMG AS hinh_anh";
    if($SP_COST)  $fields[] = "sp.$SP_COST AS gia_nhap";
    if($SP_PRICE) $fields[] = "sp.$SP_PRICE AS gia_ban";
  }

  $sql = "SELECT ".implode(", ",$fields)."
          FROM tonkho tk
          ".($spOk && $SP_ID ? "LEFT JOIN sanpham sp ON sp.$SP_ID = tk.$TK_SPID" : "")."
          $where
          ORDER BY ".($TK_UPD ? "tk.$TK_UPD" : "tk.$TK_ID")." DESC
          LIMIT $perPage OFFSET $offset";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* view */
$viewId = (int)($_GET['xem'] ?? 0);
$view = null;
if(!$fatal && $viewId>0){
  $fields = ["tk.*"];
  if($spOk && $SP_ID){
    if($SP_NAME)  $fields[] = "sp.$SP_NAME AS ten_san_pham";
    if($SP_IMG)   $fields[] = "sp.$SP_IMG AS hinh_anh";
    if($SP_COST)  $fields[] = "sp.$SP_COST AS gia_nhap";
    if($SP_PRICE) $fields[] = "sp.$SP_PRICE AS gia_ban";
  }
  $sql = "SELECT ".implode(", ",$fields)."
          FROM tonkho tk
          ".($spOk && $SP_ID ? "LEFT JOIN sanpham sp ON sp.$SP_ID = tk.$TK_SPID" : "")."
          WHERE tk.$TK_ID=? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$viewId]);
  $view = $st->fetch(PDO::FETCH_ASSOC);
}

/* flash */
$type = $_GET['type'] ?? '';
$msg  = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/giaoDienDau.php';
require_once __DIR__ . '/includes/thanhben.php';
require_once __DIR__ . '/includes/thanhTren.php';
?>

<?php if($fatal): ?>
  <div class="bg-white rounded-2xl border border-line shadow-card p-6">
    <div class="text-xl font-extrabold">Thiếu cấu trúc bảng tồn kho</div>
    <div class="text-slate-600 mt-2">Cần bảng <b>tonkho</b> có các cột: <b>id_tonkho</b>, <b>id_san_pham</b>, <b>so_luong</b> (và <b>ngay_cap_nhat</b> nếu có).</div>
  </div>

<?php else: ?>

  <?php if($msg): ?>
    <div class="mb-5 bg-white rounded-2xl border border-line shadow-card p-4">
      <div class="font-bold <?= $type==='ok'?'text-green-600':($type==='error'?'text-red-600':'text-slate-700') ?>">
        <?= h($msg) ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="flex items-center justify-between mb-5">
    <div>
      <div class="text-2xl font-extrabold">Tồn kho</div>
      <div class="text-sm text-muted font-bold">Theo dõi & chỉnh sửa số lượng tồn (log vào nhatky_hoatdong)</div>
    </div>

    <form method="post" class="flex gap-2">
      <input type="hidden" name="action" value="tao_ton">
      <button class="px-4 py-2 rounded-xl bg-white border border-line shadow-card font-extrabold hover:bg-slate-50">
        Tạo tồn cho SP chưa có
      </button>
    </form>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT: LIST -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-line shadow-card p-5">
      <div class="flex items-center justify-between mb-4">
        <div class="font-extrabold">Danh sách tồn kho</div>
        <div class="text-xs text-muted font-bold">Tổng: <?= number_format($total) ?></div>
      </div>

      <form method="get" class="flex gap-2 mb-4">
        <input name="q" value="<?= h($q) ?>" class="flex-1 rounded-xl bg-slate-100 border-0 focus:ring-2 focus:ring-primary/30" placeholder="Tìm ID sản phẩm / tên sản phẩm..." />
        <button class="px-4 rounded-xl bg-primary text-white font-extrabold">Lọc</button>
      </form>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-muted">
            <tr>
              <th class="text-left py-3 pr-3">Ảnh</th>
              <th class="text-left py-3 pr-3">Sản phẩm</th>
              <th class="text-right py-3 pr-3">Tồn</th>
              <th class="text-left py-3 pr-3">Cập nhật</th>
              <th class="text-right py-3">Thao tác</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-line">
          <?php foreach($rows as $r): 
            $img = (!empty($r['hinh_anh'])) ? "../assets/img/".h($r['hinh_anh']) : "";
            $name = (string)($r['ten_san_pham'] ?? ('SP #'.$r['id_san_pham']));
            $qty  = (int)($r['so_luong'] ?? 0);
            $extra = "ID: ".$r['id_san_pham']." | Giá nhập: ".money_vnd((int)($r['gia_nhap'] ?? 0))." | Giá bán: ".money_vnd((int)($r['gia_ban'] ?? 0));
            $isLow = ($qty <= $lowStock);
          ?>
            <tr class="hover:bg-slate-50"
                data-preview-img="<?= $img ?>"
                data-preview-name="<?= h($name) ?>"
                data-preview-extra="<?= h($extra) ?>">
              <td class="py-3 pr-3">
                <div class="size-10 rounded-xl bg-slate-100 border border-line overflow-hidden grid place-items-center">
                  <?php if($img): ?>
                    <img src="<?= $img ?>" class="w-full h-full object-cover" alt="">
                  <?php else: ?>
                    <span class="material-symbols-outlined text-slate-400">photo</span>
                  <?php endif; ?>
                </div>
              </td>

              <td class="py-3 pr-3">
                <div class="font-extrabold truncate max-w-[420px]"><?= h($name) ?></div>
                <div class="text-xs text-muted font-bold">SP #<?= (int)$r['id_san_pham'] ?></div>
              </td>

              <td class="py-3 pr-3 text-right">
                <span class="px-3 py-1 rounded-full text-xs font-extrabold <?= $isLow?'bg-red-50 text-red-600':'bg-green-50 text-green-700' ?>">
                  <?= number_format($qty) ?>
                </span>
              </td>

              <td class="py-3 pr-3 text-xs text-muted font-bold">
                <?= h($r['cap_nhat'] ?? '') ?>
              </td>

              <td class="py-3 text-right">
                <a class="px-3 py-2 rounded-xl bg-slate-100 font-extrabold hover:bg-slate-200"
                   href="tonkho.php?<?= h(http_build_query(array_merge($_GET,['xem'=>(int)$r['id_tonkho']])) ) ?>">
                  Chỉnh
                </a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if(!$rows): ?>
            <tr><td colspan="5" class="py-8 text-center text-muted font-bold">Chưa có dữ liệu tồn kho.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-4">
        <div class="text-xs text-muted font-bold">Trang <?= $page ?>/<?= $totalPages ?></div>
        <div class="flex gap-2">
          <?php
            $qs = $_GET;
            $mk = function($p) use ($qs){ $qs['page']=$p; return 'tonkho.php?'.http_build_query($qs); };
          ?>
          <a class="px-3 py-2 rounded-xl border bg-white font-extrabold <?= $page<=1?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h($mk(max(1,$page-1))) ?>">Trước</a>
          <a class="px-3 py-2 rounded-xl border bg-white font-extrabold <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>"
             href="<?= h($mk(min($totalPages,$page+1))) ?>">Sau</a>
        </div>
      </div>
    </div>

    <!-- RIGHT: PREVIEW + EDIT -->
    <div class="space-y-6">

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="font-extrabold mb-3">Xem nhanh (hover)</div>
        <div class="flex gap-3">
          <div class="w-24 h-24 rounded-2xl bg-slate-100 border border-line overflow-hidden grid place-items-center">
            <img id="hoverPreviewImg" src="" class="w-full h-full object-cover" alt="">
          </div>
          <div class="min-w-0">
            <div id="hoverPreviewName" class="font-extrabold truncate">Di chuột vào dòng bên trái</div>
            <div id="hoverPreviewExtra" class="text-xs text-muted font-bold mt-1"></div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-line shadow-card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="text-lg font-extrabold"><?= $view ? 'Chỉnh tồn' : 'Chọn 1 sản phẩm' ?></div>
          <?php if($view): ?><a href="tonkho.php" class="text-primary font-extrabold">Bỏ chọn</a><?php endif; ?>
        </div>

        <?php if(!$view): ?>
          <div class="text-sm text-muted font-bold">Chọn “Chỉnh” ở danh sách để sửa tồn.</div>
        <?php else: ?>
          <?php
            $id_tk = (int)($view[$TK_ID] ?? 0);
            $id_sp = (int)($view[$TK_SPID] ?? 0);
            $qty   = (int)($view[$TK_QTY] ?? 0);
            $name  = (string)($view['ten_san_pham'] ?? ('SP #'.$id_sp));
            $img   = (!empty($view['hinh_anh'])) ? "../assets/img/".h($view['hinh_anh']) : "";
          ?>

          <div class="flex items-center gap-3 mb-4">
            <div class="size-14 rounded-2xl bg-slate-100 border border-line overflow-hidden grid place-items-center">
              <?php if($img): ?><img src="<?= $img ?>" class="w-full h-full object-cover" alt="">
              <?php else: ?><span class="material-symbols-outlined text-slate-400">photo</span><?php endif; ?>
            </div>
            <div class="min-w-0">
              <div class="font-extrabold truncate"><?= h($name) ?></div>
              <div class="text-xs text-muted font-bold">ID tồn kho: #<?= $id_tk ?> • SP #<?= $id_sp ?></div>
            </div>
          </div>

          <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="cap_nhat">
            <input type="hidden" name="id_tonkho" value="<?= $id_tk ?>">

            <div>
              <label class="text-sm font-extrabold">Số lượng tồn</label>
              <input type="number" name="so_luong" value="<?= $qty ?>"
                     class="mt-1 w-full rounded-xl bg-slate-100 border-0 focus:ring-2 focus:ring-primary/30">
              <div class="text-xs text-muted font-bold mt-2">
                Gợi ý: nếu bạn trừ tồn theo “Hoàn tất đơn”, tồn ở đây sẽ tự giảm khi đổi trạng thái.
              </div>
            </div>

            <button class="w-full px-4 py-3 rounded-2xl bg-primary text-white font-extrabold hover:opacity-90">
              Lưu tồn kho
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/giaoDienCuoi.php'; ?>
