<?php
// trang_nguoi_dung/danh_muc.php
require_once __DIR__ . '/../giao_dien/header.php';
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../cau_hinh/ham.php';

// ====== MAP danh mục theo ?loai= ======
// Bạn đổi id theo bảng danhmuc của bạn nếu khác.
// Dữ liệu bạn gửi đang có id_danh_muc 1,2,3...
$mapLoai = [
    'nu'        => 2,
    'nam'       => 1,
    'treem'     => 8,
    'sale'      => 3,
    'hangmoi'   => 4,
    'banchay'   => 5,
    'giaydecao' => 6,
    'xuhuong'   => 7,
    'collab'    => 9,
    'classic'   => 10
];


$loai = $_GET['loai'] ?? 'nu';
$idDanhMuc = $mapLoai[$loai] ?? null;

// search trong danh mục
$q = trim($_GET['q'] ?? '');

// lọc giá
$gia = $_GET['gia'] ?? ''; 
$giaSql = "";
$params = [];

if ($gia === 'duoi500') { $giaSql = " AND gia < 500000 "; }
elseif ($gia === '500-1000') { $giaSql = " AND gia BETWEEN 500000 AND 1000000 "; }
elseif ($gia === '1000-1500') { $giaSql = " AND gia BETWEEN 1000000 AND 1500000 "; }
elseif ($gia === '1500-2000') { $giaSql = " AND gia BETWEEN 1500000 AND 2000000 "; }
elseif ($gia === 'tren2000') { $giaSql = " AND gia > 2000000 "; }

// sort
$sort = $_GET['sort'] ?? 'moi';
$orderSql = " ORDER BY id_san_pham DESC ";
if ($sort === 'gia_tang')  $orderSql = " ORDER BY gia ASC ";
if ($sort === 'gia_giam')  $orderSql = " ORDER BY gia DESC ";
if ($sort === 'ten_az')    $orderSql = " ORDER BY ten_san_pham ASC ";

// ====== Build SQL ======
$sql = "SELECT * FROM sanpham WHERE 1=1 ";
if ($idDanhMuc) {
    $sql .= " AND id_danh_muc = :id_danh_muc ";
    $params[':id_danh_muc'] = $idDanhMuc;
}
if ($q !== '') {
    $sql .= " AND ten_san_pham LIKE :q ";
    $params[':q'] = "%$q%";
}
$sql .= $giaSql . $orderSql;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sanPham = $stmt->fetchAll(PDO::FETCH_ASSOC);
$soLuong = count($sanPham);

// title theo loai
$titleMap = [
    'nu' => 'Giày dép Nữ',
    'nam' => 'Giày dép Nam',
    'treem' => 'Giày dép Trẻ Em',
    'sandals' => 'Sandals',
    'jibbitz' => 'Jibbitz™',
    'xuhuong' => 'Xu Hướng',
    'uudai' => 'Ưu Đãi',
    'blackfriday' => 'Black Friday'
];
$pageTitle = $titleMap[$loai] ?? 'Danh mục sản phẩm';
?>

<link rel="stylesheet" href="../assets/css/danh_muc.css">

<main class="dm-page">

    <!-- breadcrumb + search inside collection -->
    <div class="dm-top">
        <div class="dm-breadcrumb">
            <a href="trang_chu.php">Trang chủ</a>
            <span>›</span>
            <span><?= htmlspecialchars($pageTitle) ?></span>
        </div>

        <form class="dm-search-collection" method="get">
            <input type="hidden" name="loai" value="<?= htmlspecialchars($loai) ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Tìm kiếm sản phẩm trong bộ sưu tập này">
            <button type="submit" aria-label="search">
                <i class="fa fa-search">🔍</i>
            </button>
        </form>
    </div>

    <div class="dm-layout">

        <!-- LEFT FILTER -->
        <aside class="dm-sidebar">
            <div class="filter-box">
                <div class="filter-head">
                    <span>Giá</span>
                    <button type="button" class="filter-clear" onclick="window.location.href='danh_muc.php?loai=<?= $loai ?>'">×</button>
                </div>

                <form class="filter-form" method="get">
                    <input type="hidden" name="loai" value="<?= htmlspecialchars($loai) ?>">
                    <?php if($q!==''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                    <?php endif; ?>

                    <label class="radio-item">
                        <input type="radio" name="gia" value="duoi500" <?= $gia==='duoi500'?'checked':''; ?>>
                        <span>Dưới 500.000đ</span>
                    </label>

                    <label class="radio-item">
                        <input type="radio" name="gia" value="500-1000" <?= $gia==='500-1000'?'checked':''; ?>>
                        <span>500.000đ - 1.000.000đ</span>
                    </label>

                    <label class="radio-item">
                        <input type="radio" name="gia" value="1000-1500" <?= $gia==='1000-1500'?'checked':''; ?>>
                        <span>1.000.000đ - 1.500.000đ</span>
                    </label>

                    <label class="radio-item">
                        <input type="radio" name="gia" value="1500-2000" <?= $gia==='1500-2000'?'checked':''; ?>>
                        <span>1.500.000đ - 2.000.000đ</span>
                    </label>

                    <label class="radio-item">
                        <input type="radio" name="gia" value="tren2000" <?= $gia==='tren2000'?'checked':''; ?>>
                        <span>Trên 2.000.000đ</span>
                    </label>

                    <div class="filter-actions">
                        <button type="submit" class="btn-apply">Áp dụng</button>
                        <a class="btn-reset" href="danh_muc.php?loai=<?= $loai ?>">Xóa lọc</a>
                    </div>
                </form>
            </div>

            <!-- các filter phụ y chang crocs (chỉ UI) -->
            <div class="filter-accordion">
                <div class="acc-item">
                    <div class="acc-head">Mức Giảm Giá <span>+</span></div>
                </div>
                <div class="acc-item">
                    <div class="acc-head">Phong Cách <span>+</span></div>
                </div>
                <div class="acc-item">
                    <div class="acc-head">Kích Thước <span>+</span></div>
                </div>
                <div class="acc-item">
                    <div class="acc-head">Màu Sắc <span>+</span></div>
                </div>
                <div class="acc-item">
                    <div class="acc-head">Hình Thức Giao Hàng <span>+</span></div>
                </div>
            </div>
        </aside>

        <!-- RIGHT CONTENT -->
        <section class="dm-content">

            <div class="dm-toolbar">
                <div class="dm-count">
                    <a href="danh_muc.php?loai=<?= $loai ?>" class="dm-viewall">Xem tất cả</a>
                    <span><?= $soLuong ?> sản phẩm</span>
                </div>

                <form method="get" class="dm-sort">
                    <input type="hidden" name="loai" value="<?= htmlspecialchars($loai) ?>">
                    <?php if($q!==''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                    <?php if($gia!==''): ?><input type="hidden" name="gia" value="<?= htmlspecialchars($gia) ?>"><?php endif; ?>

                    <select name="sort" onchange="this.form.submit()">
                        <option value="moi" <?= $sort==='moi'?'selected':''; ?>>Mới nhất</option>
                        <option value="gia_tang" <?= $sort==='gia_tang'?'selected':''; ?>>Giá tăng dần</option>
                        <option value="gia_giam" <?= $sort==='gia_giam'?'selected':''; ?>>Giá giảm dần</option>
                        <option value="ten_az" <?= $sort==='ten_az'?'selected':''; ?>>Tên A-Z</option>
                    </select>
                </form>
            </div>

            <div class="dm-grid">
                <?php if(empty($sanPham)): ?>
                    <div class="dm-empty">Không có sản phẩm phù hợp.</div>
                <?php else: ?>
                    <?php foreach($sanPham as $sp): ?>
                        <a class="card" href="chi_tiet_san_pham.php?id=<?= $sp['id_san_pham'] ?>">
                            <div class="card-img">
                                <img src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>"
                                     alt="<?= htmlspecialchars($sp['ten_san_pham']) ?>">
                                <span class="badge">MỚI</span>
                            </div>

                            <div class="card-body">
                                <div class="card-title">
                                    <?= htmlspecialchars($sp['ten_san_pham']) ?>
                                </div>

                                <div class="card-price">
                                    <?= dinh_dang_gia($sp['gia']) ?>
                                </div>

                                <div class="card-colors">
                                    <span class="dot"></span>
                                    <span class="dot light"></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
