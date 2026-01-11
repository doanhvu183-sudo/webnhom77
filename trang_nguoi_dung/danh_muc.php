<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../cau_hinh/ham.php';
require_once __DIR__ . '/../giao_dien/header.php';

/**
 * DANH MỤC (UI hiện đại + CSS trong file)
 * - Đồng bộ ẩn/hiện:
 *   + danhmuc.hien_thi = 1
 *   + sanpham.hien_thi = 1 AND sanpham.trang_thai = 1
 * - Lọc giá theo GIÁ HIỆU LỰC: COALESCE(NULLIF(gia_khuyen_mai,0), gia)
 * - Tương thích link cũ: ?loai=nu/nam/treem/hangmoi/banchay...
 */

// ===== Helper nhỏ (tránh lỗi thiếu h()) =====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Nhận tham số =====
$loaiRaw = trim((string)($_GET['loai'] ?? 'nu'));
$q       = trim((string)($_GET['q'] ?? ''));
$gia     = (string)($_GET['gia'] ?? '');
$sort    = (string)($_GET['sort'] ?? 'moi');

// ===== Alias map mềm (không phụ thuộc ID) =====
$slugCandidates = [];
$loaiLower = strtolower($loaiRaw);

// nếu truyền số thì hiểu là id_danh_muc
$idDanhMuc = null;
if (ctype_digit($loaiLower)) {
  $idDanhMuc = (int)$loaiLower;
} else {
  $alias = [
    'nu'        => ['nu','dep-nu','women','female'],
    'nam'       => ['nam','dep-nam','men','male'],
    'treem'     => ['treem','tre-em','tre_em','kid','kids'],
    'uudai'     => ['uudai','uu-dai','sale'],
    'sale'      => ['sale','uudai','uu-dai'],
    'hangmoi'   => ['hangmoi','hang-moi','new'],
    'banchay'   => ['banchay','ban-chay','best-seller','bestseller'],
    'giaydecao' => ['giaydecao','giay-de-cao','platform'],
    'xuhuong'   => ['xuhuong','xu-huong','trend'],
    'collab'    => ['collab'],
    'classic'   => ['classic'],
  ];
  $slugCandidates = $alias[$loaiLower] ?? [$loaiLower];
}

// ===== Tìm danh mục từ DB =====
$dm = null;
try {
  if ($idDanhMuc !== null) {
    $st = $pdo->prepare("SELECT id_danh_muc, ten_danh_muc, slug, hien_thi FROM danhmuc WHERE id_danh_muc=? LIMIT 1");
    $st->execute([$idDanhMuc]);
    $dm = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($dm && (int)$dm['hien_thi'] !== 1) $dm = null;
  } else {
    $placeholders = implode(',', array_fill(0, count($slugCandidates), '?'));
    $sqlDm = "SELECT id_danh_muc, ten_danh_muc, slug, hien_thi
              FROM danhmuc
              WHERE hien_thi = 1 AND slug IN ($placeholders)
              LIMIT 1";
    $st = $pdo->prepare($sqlDm);
    $st->execute($slugCandidates);
    $dm = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($dm) $idDanhMuc = (int)$dm['id_danh_muc'];
  }
} catch (Throwable $e) {
  $dm = null;
}

// ===== Lọc giá theo GIÁ HIỆU LỰC =====
$priceExpr = "COALESCE(NULLIF(s.gia_khuyen_mai,0), s.gia)";
$giaSql = '';

if ($gia === 'duoi500')        $giaSql = " AND $priceExpr < 500000 ";
elseif ($gia === '500-1000')   $giaSql = " AND $priceExpr BETWEEN 500000 AND 1000000 ";
elseif ($gia === '1000-1500')  $giaSql = " AND $priceExpr BETWEEN 1000000 AND 1500000 ";
elseif ($gia === '1500-2000')  $giaSql = " AND $priceExpr BETWEEN 1500000 AND 2000000 ";
elseif ($gia === 'tren2000')   $giaSql = " AND $priceExpr > 2000000 ";

// ===== Sắp xếp =====
$orderSql = " ORDER BY s.id_san_pham DESC ";
if ($sort === 'moi')      $orderSql = " ORDER BY s.ngay_tao DESC, s.id_san_pham DESC ";
if ($sort === 'gia_tang') $orderSql = " ORDER BY $priceExpr ASC, s.id_san_pham DESC ";
if ($sort === 'gia_giam') $orderSql = " ORDER BY $priceExpr DESC, s.id_san_pham DESC ";
if ($sort === 'ten_az')   $orderSql = " ORDER BY s.ten_san_pham ASC, s.id_san_pham DESC ";

// ===== SQL lấy sản phẩm =====
$sql = "
  SELECT s.*
  FROM sanpham s
  JOIN danhmuc dm ON dm.id_danh_muc = s.id_danh_muc
  WHERE dm.hien_thi = 1
    AND s.hien_thi = 1
    AND s.trang_thai = 1
";

$params = [];
if ($idDanhMuc !== null) {
  $sql .= " AND s.id_danh_muc = :id_danh_muc ";
  $params[':id_danh_muc'] = $idDanhMuc;
}
if ($q !== '') {
  $sql .= " AND (s.ten_san_pham LIKE :q OR s.mo_ta LIKE :q) ";
  $params[':q'] = "%$q%";
}

$sql .= $giaSql . $orderSql;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sanPham = $stmt->fetchAll(PDO::FETCH_ASSOC);
$soLuong = count($sanPham);

// ===== Tiêu đề =====
$titleMap = [
  'nu'        => 'Giày dép Nữ',
  'nam'       => 'Giày dép Nam',
  'treem'     => 'Giày dép Trẻ Em',
  'uudai'     => 'Ưu đãi',
  'sale'      => 'SALE',
  'hangmoi'   => 'Hàng mới',
  'banchay'   => 'Bán chạy',
  'giaydecao' => 'Giày đế cao',
  'xuhuong'   => 'Xu hướng',
  'collab'    => 'Collab',
  'classic'   => 'Classic'
];
$pageTitle = $dm['ten_danh_muc'] ?? ($titleMap[$loaiLower] ?? 'Danh mục sản phẩm');

// ===== URL base để giữ filter =====
function build_url($base, $arr){
  return $base . '?' . http_build_query($arr);
}
$baseParams = ['loai' => $loaiRaw];
if ($q !== '') $baseParams['q'] = $q;
?>
<style>
/* ===================== LAYOUT ===================== */
.dm-wrap{ max-width:1200px; margin:0 auto; padding:22px 16px 40px; }
.dm-top{
  display:flex; align-items:center; justify-content:space-between; gap:14px;
  margin-bottom:18px;
}
.dm-breadcrumb{ font-size:13px; color:#6b7280; display:flex; gap:8px; align-items:center; }
.dm-breadcrumb a{ color:#6b7280; text-decoration:none; }
.dm-breadcrumb a:hover{ color:#111827; }
.dm-title{ margin:8px 0 0; font-size:26px; font-weight:900; letter-spacing:.5px; }

.dm-search{
  flex:1;
  display:flex; justify-content:center;
}
.dm-search form{
  width:min(680px, 100%);
  display:flex; align-items:center;
  border:1px solid #e5e7eb;
  border-radius:999px;
  padding:10px 14px;
  gap:10px;
  background:#fff;
}
.dm-search input{
  border:0; outline:none; width:100%;
  font-size:14px;
}
.dm-search button{
  border:0; background:transparent; cursor:pointer;
  font-size:16px; padding:4px 8px;
}

.dm-layout{ display:grid; grid-template-columns: 290px 1fr; gap:22px; }
@media (max-width: 980px){
  .dm-layout{ grid-template-columns: 1fr; }
}

/* ===================== SIDEBAR ===================== */
.sidebar{
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:#fff;
  overflow:hidden;
  height:fit-content;
  position:sticky;
  top:90px;
}
@media (max-width: 980px){
  .sidebar{ position:relative; top:auto; }
}

.sb-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 14px;
  border-bottom:1px solid #eef2f7;
}
.sb-head .t{ font-weight:900; }
.sb-clear{
  width:34px; height:34px; border-radius:999px;
  border:1px solid #e5e7eb; background:#fff; cursor:pointer;
}
.sb-clear:hover{ background:#f3f4f6; }

.sb-body{ padding:14px; }
.sb-group{ margin-bottom:14px; }
.sb-group-title{ font-weight:900; margin-bottom:10px; }
.radio{
  display:flex; align-items:center; gap:10px;
  padding:10px 10px;
  border-radius:12px;
  cursor:pointer;
}
.radio:hover{ background:#f8fafc; }
.radio input{ width:16px; height:16px; }

.sb-actions{ display:flex; gap:10px; margin-top:10px; }
.btn{
  display:inline-flex; align-items:center; justify-content:center;
  padding:10px 12px; border-radius:999px;
  font-weight:900; font-size:13px;
  border:1px solid #e5e7eb;
  text-decoration:none;
  cursor:pointer;
}
.btn-primary{ background:#111827; color:#fff; border-color:#111827; }
.btn-primary:hover{ background:#000; }
.btn-ghost{ background:#fff; color:#111827; }
.btn-ghost:hover{ background:#f3f4f6; }

/* ===================== TOOLBAR ===================== */
.toolbar{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px; margin-bottom:14px;
}
.count{
  font-size:14px; color:#111827;
  display:flex; gap:10px; align-items:center;
}
.count a{ color:#2563eb; text-decoration:none; font-weight:800; }
.count a:hover{ text-decoration:underline; }

.sort select{
  border:1px solid #e5e7eb;
  background:#fff;
  border-radius:12px;
  padding:10px 12px;
  font-weight:800;
  outline:none;
}

/* ===================== GRID + CARD ===================== */
.grid{
  display:grid;
  grid-template-columns: repeat(4, minmax(0,1fr));
  gap:18px;
}
@media (max-width: 1200px){ .grid{ grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px){ .grid{ grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px){ .grid{ grid-template-columns: repeat(1, 1fr); } }

.card{
  display:block;
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:#fff;
  overflow:hidden;
  transition: box-shadow .15s ease, transform .15s ease;
  text-decoration:none;
}
.card:hover{ box-shadow:0 12px 28px rgba(0,0,0,.10); transform: translateY(-2px); }

.card-img{
  position:relative;
  width:100%;
  aspect-ratio: 1 / 1; /* khung vuông như mẫu */
  background:#f6f7f8;
  overflow:hidden;
}
/* FIX ẢNH: fill kín viền */
.card-img img{
  position:absolute; inset:0;
  width:100%; height:100%;
  object-fit: cover;       /* quan trọng: vừa viền */
  object-position:center;
  display:block;
}

.badge{
  position:absolute;
  top:10px; right:10px;
  background:#fff;
  border:1px solid #e5e7eb;
  padding:4px 8px;
  font-size:12px;
  font-weight:900;
  border-radius:999px;
  color:#2563eb;
}

.card-body{ padding:12px 14px 14px; }
.card-title{
  font-weight:800;
  font-size:14px;
  line-height:1.35;
  color:#111827;
  min-height:38px;
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
}
.price{
  margin-top:8px;
  font-weight:900;
  color:#111827;
  display:flex;
  gap:8px;
  align-items:baseline;
}
.price .old{
  font-size:12px;
  color:#9ca3af;
  text-decoration:line-through;
  font-weight:800;
}

.empty{
  border:1px dashed #cbd5e1;
  background:#fff;
  border-radius:16px;
  padding:22px;
  color:#64748b;
}

/* ===================== FIX khoảng trắng footer ===================== */
.spacer{ height:6px; }
</style>

<main class="dm-wrap">
  <div class="dm-top">
    <div>
      <div class="dm-breadcrumb">
        <a href="trang_chu.php">Trang chủ</a>
        <span>›</span>
        <span><?= h($pageTitle) ?></span>
      </div>
      <div class="dm-title"><?= h($pageTitle) ?></div>
    </div>

    <div class="dm-search">
      <form method="get">
        <input type="hidden" name="loai" value="<?= h($loaiRaw) ?>">
        <?php if ($gia !== ''): ?><input type="hidden" name="gia" value="<?= h($gia) ?>"><?php endif; ?>
        <?php if ($sort !== ''): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>
        <input name="q" value="<?= h($q) ?>" placeholder="Tìm kiếm sản phẩm trong bộ sưu tập này">
        <button type="submit" aria-label="search">🔍</button>
      </form>
    </div>
  </div>

  <div class="dm-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sb-head">
        <div class="t">Bộ lọc</div>
        <button class="sb-clear" type="button"
          onclick="location.href='<?= h(build_url('danh_muc.php', ['loai'=>$loaiRaw])) ?>'">×</button>
      </div>

      <div class="sb-body">
        <form method="get">
          <input type="hidden" name="loai" value="<?= h($loaiRaw) ?>">
          <?php if($q!==''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
          <?php if($sort!==''): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>

          <div class="sb-group">
            <div class="sb-group-title">Giá</div>

            <label class="radio">
              <input type="radio" name="gia" value="duoi500" <?= $gia==='duoi500'?'checked':'' ?>>
              <span>Dưới 500.000đ</span>
            </label>
            <label class="radio">
              <input type="radio" name="gia" value="500-1000" <?= $gia==='500-1000'?'checked':'' ?>>
              <span>500.000đ - 1.000.000đ</span>
            </label>
            <label class="radio">
              <input type="radio" name="gia" value="1000-1500" <?= $gia==='1000-1500'?'checked':'' ?>>
              <span>1.000.000đ - 1.500.000đ</span>
            </label>
            <label class="radio">
              <input type="radio" name="gia" value="1500-2000" <?= $gia==='1500-2000'?'checked':'' ?>>
              <span>1.500.000đ - 2.000.000đ</span>
            </label>
            <label class="radio">
              <input type="radio" name="gia" value="tren2000" <?= $gia==='tren2000'?'checked':'' ?>>
              <span>Trên 2.000.000đ</span>
            </label>

            <div class="sb-actions">
              <button class="btn btn-primary" type="submit">Áp dụng</button>
              <a class="btn btn-ghost"
                 href="<?= h(build_url('danh_muc.php', ['loai'=>$loaiRaw] + ($q!==''?['q'=>$q]:[]) + ($sort!==''?['sort'=>$sort]:[]))) ?>">
                Xóa lọc
              </a>
            </div>
          </div>

          <div class="sb-group" style="opacity:.65">
            <div class="sb-group-title">Mức Giảm Giá</div>
            <div style="font-size:13px;color:#64748b;">(Có thể bổ sung sau)</div>
          </div>
          <div class="sb-group" style="opacity:.65">
            <div class="sb-group-title">Phong Cách</div>
            <div style="font-size:13px;color:#64748b;">(Có thể bổ sung sau)</div>
          </div>
          <div class="sb-group" style="opacity:.65">
            <div class="sb-group-title">Kích Thước</div>
            <div style="font-size:13px;color:#64748b;">(Có thể bổ sung sau)</div>
          </div>
          <div class="sb-group" style="opacity:.65">
            <div class="sb-group-title">Màu Sắc</div>
            <div style="font-size:13px;color:#64748b;">(Có thể bổ sung sau)</div>
          </div>
          <div class="sb-group" style="opacity:.65">
            <div class="sb-group-title">Hình Thức Giao Hàng</div>
            <div style="font-size:13px;color:#64748b;">(Có thể bổ sung sau)</div>
          </div>

        </form>
      </div>
    </aside>

    <!-- CONTENT -->
    <section>
      <div class="toolbar">
        <div class="count">
          <a href="<?= h(build_url('danh_muc.php', ['loai'=>$loaiRaw])) ?>">Xem tất cả</a>
          <span><b><?= (int)$soLuong ?></b> sản phẩm</span>
        </div>

        <form method="get" class="sort">
          <input type="hidden" name="loai" value="<?= h($loaiRaw) ?>">
          <?php if($q!==''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
          <?php if($gia!==''): ?><input type="hidden" name="gia" value="<?= h($gia) ?>"><?php endif; ?>

          <select name="sort" onchange="this.form.submit()">
            <option value="moi" <?= $sort==='moi'?'selected':'' ?>>Sắp xếp: Mới nhất</option>
            <option value="gia_tang" <?= $sort==='gia_tang'?'selected':'' ?>>Giá tăng dần</option>
            <option value="gia_giam" <?= $sort==='gia_giam'?'selected':'' ?>>Giá giảm dần</option>
            <option value="ten_az" <?= $sort==='ten_az'?'selected':'' ?>>Tên A–Z</option>
          </select>
        </form>
      </div>

      <?php if(empty($sanPham)): ?>
        <div class="empty">Không có sản phẩm phù hợp.</div>
      <?php else: ?>
        <div class="grid">
          <?php foreach($sanPham as $sp): ?>
            <?php
              $gia_goc = (int)($sp['gia'] ?? 0);
              $gia_km  = (int)($sp['gia_khuyen_mai'] ?? 0);
              $co_km   = ($gia_km > 0 && $gia_km < $gia_goc);
              $badge   = $co_km ? 'SALE' : 'MỚI';
              $gia_hien_thi = $co_km ? $gia_km : $gia_goc;
              $img = trim((string)($sp['hinh_anh'] ?? ''));
              if ($img === '') $img = 'no-image.png';
            ?>
            <a class="card" href="chi_tiet_san_pham.php?id=<?= (int)$sp['id_san_pham'] ?>">
              <div class="card-img">
                <img src="../assets/img/<?= h($img) ?>" alt="<?= h($sp['ten_san_pham'] ?? '') ?>">
                <span class="badge"><?= h($badge) ?></span>
              </div>

              <div class="card-body">
                <div class="card-title"><?= h($sp['ten_san_pham'] ?? '') ?></div>
                <div class="price">
                  <span><?= function_exists('dinh_dang_gia') ? dinh_dang_gia($gia_hien_thi) : number_format($gia_hien_thi).'₫' ?></span>
                  <?php if($co_km): ?>
                    <span class="old"><?= function_exists('dinh_dang_gia') ? dinh_dang_gia($gia_goc) : number_format($gia_goc).'₫' ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="spacer"></div>
    </section>

  </div>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
