<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

/* ================== FLASH ================== */
function _flash_set($type, $msg) {
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function _flash_get() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ================== BẮT BUỘC LOGIN ================== */
if (!isset($_SESSION['nguoi_dung'])) {
  $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'trang_chu.php';
  header('Location: dang_nhap.php');
  exit;
}

/* ================== CHUẨN HÓA ID (ĐỒNG BỘ) ================== */
$id_nguoi_dung = (int)($_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? 0));
if ($id_nguoi_dung <= 0) {
  session_destroy();
  header('Location: dang_nhap.php');
  exit;
}

/* ================== HELPER: CHECK COLUMN EXISTS ================== */
function _col_exists($pdo, $table, $col) {
  try {
    $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $q->execute([$col]);
    return (bool)$q->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}

/* ================== MAP TRẠNG THÁI ================== */
$mapTrangThai = [
  // CODE
  'CHO_XAC_NHAN_EMAIL' => 'Chờ xác nhận email',
  'CHO_XU_LY'     => 'Chờ xử lý',
  'DANG_XU_LY'    => 'Đang xử lý',
  'DANG_GIAO'     => 'Đang giao',
  'HOAN_TAT'      => 'Hoàn tất',
  'DA_HUY'        => 'Đã hủy',
  'HUY'           => 'Đã hủy',
  'YEU_CAU_HUY'   => 'Chờ duyệt hủy',

  // TIẾNG VIỆT (nếu DB lưu dạng này)
  'Chờ xác nhận email' => 'Chờ xác nhận email',
  'Chờ duyệt'     => 'Chờ xử lý',
  'Chờ xử lý'     => 'Chờ xử lý',
  'Đang xử lý'    => 'Đang xử lý',
  'Đang giao'     => 'Đang giao',
  'Hoàn tất'      => 'Hoàn tất',
  'Đã hủy'        => 'Đã hủy',
  'Chờ duyệt hủy' => 'Chờ duyệt hủy',
];

function status_to_label(array $map, ?string $raw): string {
  $raw = trim((string)$raw);
  if ($raw === '') return 'Chờ xử lý';
  if (isset($map[$raw])) return $map[$raw];
  $up = strtoupper($raw);
  if (isset($map[$up])) return $map[$up];
  return $raw;
}

function status_badge_class(string $label): string {
  $label = trim($label);
  $lower = function_exists('mb_strtolower')
    ? mb_strtolower($label, 'UTF-8')
    : strtolower($label);

  if ($lower === 'hoàn tất') return 'bg-green-50 text-green-700 border-green-200';
  if ($lower === 'đang giao') return 'bg-blue-50 text-blue-700 border-blue-200';
  if ($lower === 'đang xử lý') return 'bg-yellow-50 text-yellow-700 border-yellow-200';
  if ($lower === 'chờ duyệt hủy') return 'bg-orange-50 text-orange-700 border-orange-200';
  if ($lower === 'đã hủy') return 'bg-red-50 text-red-700 border-red-200';
  if ($lower === 'chờ xác nhận email') return 'bg-purple-50 text-purple-700 border-purple-200';
  return 'bg-gray-50 text-gray-700 border-gray-200';
}

/* ================== XỬ LÝ: GỬI YÊU CẦU HỦY ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_cancel') {
  $id_don_hang = (int)($_POST['id_don_hang'] ?? 0);
  if ($id_don_hang <= 0) {
    _flash_set('error', 'Đơn hàng không hợp lệ.');
    header('Location: don_hang.php');
    exit;
  }

  try {
    $stmt = $pdo->prepare("
      SELECT id_don_hang, trang_thai
      FROM donhang
      WHERE id_don_hang = ? AND id_nguoi_dung = ?
      LIMIT 1
    ");
    $stmt->execute([$id_don_hang, $id_nguoi_dung]);
    $dh = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dh) {
      _flash_set('error', 'Không tìm thấy đơn hàng.');
      header('Location: don_hang.php');
      exit;
    }

    $rawStatus = trim((string)($dh['trang_thai'] ?? ''));

    // Cho phép yêu cầu hủy khi đơn còn sớm (bạn có thể thêm/bớt tùy nghiệp vụ)
    $allowStatuses = [
      'CHO_XAC_NHAN_EMAIL',
      'CHO_XU_LY', 'DANG_XU_LY',
      'Chờ xác nhận email',
      'Chờ duyệt', 'Chờ xử lý', 'Đang xử lý'
    ];

    if ($rawStatus === 'YEU_CAU_HUY' || $rawStatus === 'Chờ duyệt hủy') {
      _flash_set('success', 'Đơn này đã được gửi yêu cầu hủy trước đó.');
      header('Location: don_hang.php');
      exit;
    }

    if (!in_array($rawStatus, $allowStatuses, true)) {
      _flash_set('error', 'Đơn này không thể yêu cầu hủy ở trạng thái hiện tại.');
      header('Location: don_hang.php');
      exit;
    }

    $pdo->beginTransaction();

    $sql = "UPDATE donhang SET trang_thai = ? WHERE id_don_hang = ? AND id_nguoi_dung = ?";
    if (_col_exists($pdo, 'donhang', 'ngay_cap_nhat')) {
      $sql = "UPDATE donhang SET trang_thai = ?, ngay_cap_nhat = NOW() WHERE id_don_hang = ? AND id_nguoi_dung = ?";
    }

    $u = $pdo->prepare($sql);
    $u->execute(['YEU_CAU_HUY', $id_don_hang, $id_nguoi_dung]);

    $pdo->commit();

    _flash_set('success', 'Đã gửi yêu cầu hủy. Vui lòng chờ Admin duyệt.');
    header('Location: don_hang.php');
    exit;

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    _flash_set('error', 'Không gửi được yêu cầu hủy. Nếu cột trang_thai là ENUM, hãy bổ sung giá trị YEU_CAU_HUY hoặc đổi sang VARCHAR.');
    header('Location: don_hang.php');
    exit;
  }
}

/* ================== LẤY ĐƠN HÀNG ================== */
$stmt = $pdo->prepare("
  SELECT id_don_hang, ma_don_hang, tong_tien, tong_thanh_toan, trang_thai, ngay_dat, ma_voucher
  FROM donhang
  WHERE id_nguoi_dung = ?
  ORDER BY ngay_dat DESC
");
$stmt->execute([$id_nguoi_dung]);
$donhangs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================== LẤY ẢNH ĐẠI DIỆN ĐƠN ================== */
$stmtImg = $pdo->prepare("
  SELECT sp.hinh_anh
  FROM chitiet_donhang ct
  JOIN sanpham sp ON sp.id_san_pham = ct.id_san_pham
  WHERE ct.id_don_hang = ?
  LIMIT 1
");

$flash = _flash_get();

require_once __DIR__ . '/../giao_dien/header.php';
?>

<main class="max-w-[1200px] mx-auto px-4 py-10">
  <h1 class="text-3xl font-black mb-8">Đơn hàng của tôi</h1>

  <?php if ($flash): ?>
    <div class="mb-6 border rounded-xl p-4 bg-white">
      <div class="font-bold <?= $flash['type']==='success' ? 'text-green-700' : 'text-red-700' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($donhangs)): ?>
    <div class="border rounded-xl p-8 text-center text-gray-500 bg-white">
      Bạn chưa có đơn hàng nào.
    </div>
  <?php else: ?>
    <div class="space-y-6">
      <?php foreach ($donhangs as $dh): ?>
        <?php
          $stmtImg->execute([(int)$dh['id_don_hang']]);
          $img = $stmtImg->fetchColumn();

          $rawStatus = trim((string)($dh['trang_thai'] ?? ''));
          $labelStatus = status_to_label($mapTrangThai, $rawStatus);
          $badgeClass  = status_badge_class($labelStatus);

          $tong = (int)(($dh['tong_thanh_toan'] ?? 0) ?: ($dh['tong_tien'] ?? 0));

          // Điều kiện nút yêu cầu hủy
          $canRequestCancel = in_array($rawStatus, ['CHO_XAC_NHAN_EMAIL','CHO_XU_LY','DANG_XU_LY','Chờ xác nhận email','Chờ duyệt','Chờ xử lý','Đang xử lý'], true);
          $isCancelPending  = ($rawStatus === 'YEU_CAU_HUY' || $rawStatus === 'Chờ duyệt hủy');
        ?>

        <div class="border rounded-xl p-6 bg-white flex flex-col md:flex-row gap-6 items-center">

          <img
            src="../assets/img/<?= htmlspecialchars($img ?: 'no-image.png') ?>"
            class="w-24 h-24 object-contain border rounded"
            alt="order"
          >

          <div class="flex-1 w-full">
            <div class="flex justify-between items-center gap-3">
              <div class="font-bold text-lg">
                Mã đơn #<?= htmlspecialchars($dh['ma_don_hang'] ?? (int)$dh['id_don_hang']) ?>
              </div>
              <span class="px-3 py-1 rounded-full border text-sm font-bold <?= $badgeClass ?>">
                <?= htmlspecialchars($labelStatus) ?>
              </span>
            </div>

            <div class="text-sm text-gray-500 mt-2">
              Ngày đặt: <?= !empty($dh['ngay_dat']) ? date('d/m/Y H:i', strtotime($dh['ngay_dat'])) : '-' ?>
              <?php if (!empty($dh['ma_voucher'])): ?>
                • Voucher: <b><?= htmlspecialchars($dh['ma_voucher']) ?></b>
              <?php endif; ?>
            </div>
          </div>

          <div class="text-right">
            <div class="font-black text-xl text-primary mb-2">
              <?= number_format($tong) ?>₫
            </div>

            <div class="flex flex-col gap-2 items-end">

              <!-- NÚT CHÍNH: nếu chưa xác nhận email thì chuyển sang OTP, còn lại thì xem chi tiết -->
              <a
                href="<?= ($rawStatus === 'CHO_XAC_NHAN_EMAIL')
                    ? ('xac_nhan_dat_hang.php?don='.(int)$dh['id_don_hang'])
                    : ('hoan_tat_don_hang.php?don='.(int)$dh['id_don_hang']) ?>"
                class="px-4 py-2 border rounded-full text-sm font-bold hover:bg-gray-100"
              >
                <?= ($rawStatus === 'CHO_XAC_NHAN_EMAIL') ? 'Xác nhận email' : 'Xem chi tiết' ?>
              </a>

              <!-- NÚT GỬI LẠI OTP: chỉ hiện khi đang CHO_XAC_NHAN_EMAIL -->
              <?php if ($rawStatus === 'CHO_XAC_NHAN_EMAIL'): ?>
                <form method="post" action="gui_lai_otp_don.php" class="inline-block">
                  <input type="hidden" name="don" value="<?= (int)$dh['id_don_hang'] ?>">
                  <button
                    type="submit"
                    class="px-4 py-2 border rounded-full text-sm font-bold hover:bg-gray-100"
                    onclick="return confirm('Gửi lại OTP xác nhận email cho đơn này?')"
                  >
                    Gửi lại OTP
                  </button>
                </form>
              <?php endif; ?>

              <!-- YÊU CẦU HỦY -->
              <?php if ($isCancelPending): ?>
                <button
                  type="button"
                  disabled
                  class="px-4 py-2 border rounded-full text-sm font-bold opacity-60 cursor-not-allowed"
                >
                  Đang chờ duyệt hủy
                </button>
              <?php elseif ($canRequestCancel): ?>
                <form method="post" class="inline-block">
                  <input type="hidden" name="action" value="request_cancel">
                  <input type="hidden" name="id_don_hang" value="<?= (int)$dh['id_don_hang'] ?>">
                  <button
                    type="submit"
                    class="px-4 py-2 border rounded-full text-sm font-bold hover:bg-gray-100"
                    onclick="return confirm('Bạn muốn gửi yêu cầu hủy đơn này? Đơn sẽ chờ Admin duyệt.')"
                  >
                    Yêu cầu hủy
                  </button>
                </form>
              <?php endif; ?>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
