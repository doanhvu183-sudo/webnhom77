<?php
require_once __DIR__ . '/../includes/auth_core.php';
require_login();

$uSess = $_SESSION['nguoi_dung'] ?? [];
$uid   = (int)($uSess['id_nguoi_dung'] ?? ($uSess['id'] ?? 0));
if ($uid <= 0) {
  auth_logout();
  redirect(base_url('trang_nguoi_dung/dang_nhap.php'));
}

// tab hiện tại
$allowedTabs = ['profile','orders','favorite','notify','voucher','setting'];
$tab = (string)($_GET['tab'] ?? 'profile');
if (!in_array($tab, $allowedTabs, true)) $tab = 'profile';

// lấy user mới nhất từ DB
$st = $pdo->prepare("SELECT * FROM nguoidung WHERE id_nguoi_dung=? LIMIT 1");
$st->execute([$uid]);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: [];

// fallback session nếu DB lỗi (an toàn)
if (!$user) {
  $user = [
    'id_nguoi_dung' => $uid,
    'ten_dang_nhap' => (string)($uSess['ten_dang_nhap'] ?? ''),
    'email'         => (string)($uSess['email'] ?? ''),
    'ho_ten'        => (string)($uSess['ho_ten'] ?? ''),
    'so_dien_thoai' => (string)($uSess['so_dien_thoai'] ?? ''),
    'dia_chi'       => (string)($uSess['dia_chi'] ?? ''),
    'avatar'        => (string)($uSess['avatar'] ?? 'default.png'),
    'vai_tro'       => (string)($uSess['vai_tro'] ?? 'khach'),
    'gioi_tinh'     => (string)($uSess['gioi_tinh'] ?? 'khac'),
    'ngay_sinh'     => $uSess['ngay_sinh'] ?? null,
    'email_verified_at' => $uSess['email_verified_at'] ?? null,
  ];
}

$avatar = $user['avatar'] ?? 'default.png';
// cache-bust để upload avatar thấy ngay
$avatarUrl = "../assets/avatar/" . rawurlencode($avatar) . "?v=" . urlencode((string)($user['updated_at'] ?? time()));

$emailVerified = !empty($user['email_verified_at']);
$vaiTro = (string)($user['vai_tro'] ?? 'khach');

// flash updated
$updated = isset($_GET['updated']) && $_GET['updated'] == '1';

require_once __DIR__ . '/../giao_dien/header.php';
?>

<main class="max-w-[1400px] mx-auto px-6 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    <!-- SIDEBAR -->
    <aside class="lg:col-span-3 border rounded-2xl p-4 bg-white space-y-2">

      <div class="flex items-center gap-3 p-3 border-b">
        <img
          src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
          class="w-12 h-12 rounded-full object-cover border"
          alt="avatar"
          onerror="this.src='../assets/avatar/default.png';"
        >
        <div class="min-w-0">
          <p class="font-black truncate"><?= htmlspecialchars((string)($user['ho_ten'] ?? 'Tài khoản'), ENT_QUOTES, 'UTF-8') ?></p>
          <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

          <div class="mt-1 flex flex-wrap gap-2">
            <?php if ($emailVerified): ?>
              <span class="text-xs font-bold px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Đã xác thực</span>
            <?php else: ?>
              <span class="text-xs font-bold px-2 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200">Chưa xác thực</span>
            <?php endif; ?>
            <span class="text-xs font-bold px-2 py-1 rounded-full bg-gray-50 text-gray-700 border">
              <?= htmlspecialchars($vaiTro ?: 'khach', ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
        </div>
      </div>

      <a href="?tab=profile" class="tk-tab <?= $tab==='profile'?'active':'' ?>">👤 Thông tin tài khoản</a>
      <a href="?tab=orders"  class="tk-tab <?= $tab==='orders'?'active':'' ?>">📦 Đơn hàng của tôi</a>
      <a href="?tab=favorite"class="tk-tab <?= $tab==='favorite'?'active':'' ?>">❤️ Sản phẩm yêu thích</a>
      <a href="?tab=notify"  class="tk-tab <?= $tab==='notify'?'active':'' ?>">🔔 Thông báo</a>
      <a href="?tab=voucher" class="tk-tab <?= $tab==='voucher'?'active':'' ?>">🎁 Voucher cá nhân</a>
      <a href="?tab=setting" class="tk-tab <?= $tab==='setting'?'active':'' ?>">⚙️ Gợi ý sản phẩm</a>

      <div class="pt-2 border-t space-y-2">
        <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/doi_mat_khau.php'), ENT_QUOTES, 'UTF-8') ?>"
           class="block px-4 py-3 font-bold rounded-xl border hover:bg-gray-50">
          🔐 Đổi mật khẩu
        </a>

        <?php if (!$emailVerified && !empty($user['email'])): ?>
          <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/xac_thuc_email.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="block px-4 py-3 font-bold rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100">
            ✉️ Xác thực email (OTP)
          </a>
        <?php endif; ?>

        <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/dang_xuat.php'), ENT_QUOTES, 'UTF-8') ?>"
           class="block px-4 py-3 text-red-600 font-black hover:bg-gray-100 rounded-xl">
          🚪 Đăng xuất
        </a>
      </div>
    </aside>

    <!-- CONTENT -->
    <section class="lg:col-span-9 space-y-8">

      <?php if ($updated): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 font-bold">
          Cập nhật thông tin thành công.
        </div>
      <?php endif; ?>

      <?php if ($tab === 'profile'): ?>
        <div class="border rounded-2xl p-6 bg-white">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
              <h2 class="text-xl font-black mb-1">👤 Thông tin tài khoản</h2>
              <p class="text-sm text-gray-500">Cập nhật thông tin để mua hàng nhanh hơn.</p>
            </div>
            <div class="rounded-xl bg-gray-50 border px-4 py-3 text-sm text-gray-700">
              <div class="font-black mb-1">Lưu ý</div>
              Email dùng để đăng nhập/nhận OTP. Muốn đổi email sẽ cần xác thực lại.
            </div>
          </div>

          <form method="post"
                action="<?= htmlspecialchars(base_url('trang_nguoi_dung/cap_nhat_tai_khoan.php'), ENT_QUOTES, 'UTF-8') ?>"
                enctype="multipart/form-data"
                class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <div>
              <label class="block font-bold mb-1">Họ tên</label>
              <input name="ho_ten"
                     value="<?= htmlspecialchars((string)($user['ho_ten'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     class="w-full border rounded-xl px-4 py-3" required>
            </div>

            <div>
              <label class="block font-bold mb-1">Email</label>
              <input value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     class="w-full border rounded-xl px-4 py-3 bg-gray-100" disabled>
            </div>

            <div>
              <label class="block font-bold mb-1">Số điện thoại</label>
              <input name="so_dien_thoai"
                     value="<?= htmlspecialchars((string)($user['so_dien_thoai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     class="w-full border rounded-xl px-4 py-3">
            </div>

            <div>
              <label class="block font-bold mb-1">Giới tính</label>
              <?php $gt = (string)($user['gioi_tinh'] ?? 'khac'); ?>
              <select name="gioi_tinh" class="w-full border rounded-xl px-4 py-3">
                <option value="nam"  <?= $gt==='nam'?'selected':'' ?>>Nam</option>
                <option value="nu"   <?= $gt==='nu'?'selected':'' ?>>Nữ</option>
                <option value="khac" <?= $gt==='khac'?'selected':'' ?>>Khác</option>
              </select>
            </div>

            <div>
              <label class="block font-bold mb-1">Ngày sinh</label>
              <input type="date" name="ngay_sinh"
                     value="<?= htmlspecialchars((string)($user['ngay_sinh'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     class="w-full border rounded-xl px-4 py-3">
            </div>

            <div class="md:col-span-2">
              <label class="block font-bold mb-1">Địa chỉ</label>
              <input name="dia_chi"
                     value="<?= htmlspecialchars((string)($user['dia_chi'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                     class="w-full border rounded-xl px-4 py-3" required>
            </div>

            <div class="md:col-span-2">
              <label class="block font-bold mb-1">Avatar</label>
              <div class="flex items-center gap-4">
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                     class="w-14 h-14 rounded-full object-cover border"
                     onerror="this.src='../assets/avatar/default.png';" alt="">
                <input type="file" name="avatar" class="border rounded-xl px-4 py-3 w-full">
              </div>
              <p class="text-xs text-gray-500 mt-2">Chỉ nhận JPG/PNG/WEBP, tối đa 2MB.</p>
            </div>

            <div class="md:col-span-2 flex justify-end">
              <button class="bg-black text-white px-10 py-3 rounded-full font-black hover:opacity-95">
                Cập nhật
              </button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'orders'): ?>
        <?php
          $dh = ['tong'=>0,'cho_xu_ly'=>0,'dang_giao'=>0,'hoan_tat'=>0];
          try {
            $stmt = $pdo->prepare("
              SELECT
                COUNT(*) tong,
                SUM(trang_thai='CHO_XU_LY') cho_xu_ly,
                SUM(trang_thai='DANG_GIAO') dang_giao,
                SUM(trang_thai='HOAN_TAT') hoan_tat
              FROM donhang
              WHERE id_nguoi_dung = ?
            ");
            $stmt->execute([$uid]);
            $tmp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tmp) $dh = array_merge($dh, $tmp);
          } catch (Throwable $e) { /* ignore */ }
        ?>
        <div class="border rounded-2xl p-6 bg-white">
          <h2 class="text-xl font-black mb-6">📦 Đơn hàng của tôi</h2>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="tk-box">Tổng đơn<br><b><?= (int)$dh['tong'] ?></b></div>
            <div class="tk-box">Chờ xử lý<br><b><?= (int)$dh['cho_xu_ly'] ?></b></div>
            <div class="tk-box">Đang giao<br><b><?= (int)$dh['dang_giao'] ?></b></div>
            <div class="tk-box">Hoàn tất<br><b><?= (int)$dh['hoan_tat'] ?></b></div>
          </div>

          <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/don_hang.php'), ENT_QUOTES, 'UTF-8') ?>"
             class="inline-block border px-6 py-3 rounded-full font-bold hover:bg-gray-100">
            Xem chi tiết đơn hàng →
          </a>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'favorite'): ?>
        <?php
          $yeu_thich = [];
          try {
            $stmt = $pdo->prepare("
              SELECT sp.*
              FROM yeu_thich yt
              JOIN sanpham sp ON sp.id_san_pham = yt.id_san_pham
              WHERE yt.id_nguoi_dung = ?
              ORDER BY yt.ngay_tao DESC
              LIMIT 8
            ");
            $stmt->execute([$uid]);
            $yeu_thich = $stmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Throwable $e) { $yeu_thich = []; }
        ?>
        <div class="border rounded-2xl p-6 bg-white">
          <h2 class="text-xl font-black mb-6">❤️ Sản phẩm yêu thích</h2>
          <?php if (!$yeu_thich): ?>
            <p class="text-gray-500">Bạn chưa có sản phẩm yêu thích.</p>
          <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
              <?php foreach ($yeu_thich as $sp): ?>
                <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/chi_tiet_san_pham.php?id='.(int)$sp['id_san_pham']), ENT_QUOTES, 'UTF-8') ?>"
                   class="border rounded-2xl p-4 hover:shadow">
                  <img src="../assets/img/<?= htmlspecialchars((string)($sp['hinh_anh'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full aspect-square object-contain mb-2" onerror="this.style.display='none';" alt="">
                  <p class="font-bold text-sm line-clamp-2"><?= htmlspecialchars((string)($sp['ten_san_pham'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="text-red-600 font-black"><?= number_format((float)($sp['gia'] ?? 0)) ?>₫</p>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'notify'): ?>
        <div class="border rounded-2xl p-6 bg-white">
          <h2 class="text-xl font-black mb-4">🔔 Thông báo</h2>
          <p class="text-gray-500">Phần này sẵn sàng gắn DB sau.</p>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'voucher'): ?>
        <?php
          $vouchers = [];
          try {
            $stmt = $pdo->prepare("
              SELECT *
              FROM voucher_nguoidung vnd
              JOIN voucher v ON v.ma_voucher = vnd.ma_voucher
              WHERE vnd.id_nguoi_dung = ?
            ");
            $stmt->execute([$uid]);
            $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Throwable $e) { $vouchers = []; }
        ?>
        <div class="border rounded-2xl p-6 bg-white">
          <h2 class="text-xl font-black mb-6">🎁 Voucher cá nhân</h2>
          <?php if (!$vouchers): ?>
            <p class="text-gray-500">Bạn chưa có voucher.</p>
          <?php else: ?>
            <div class="space-y-4">
              <?php foreach ($vouchers as $v): ?>
                <div class="border rounded-2xl p-4 flex justify-between items-center gap-4">
                  <div>
                    <b><?= htmlspecialchars((string)($v['ma_voucher'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b><br>
                    <span class="text-sm text-gray-500">Hết hạn: <?= htmlspecialchars((string)($v['ngay_ket_thuc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/gio_hang.php'), ENT_QUOTES, 'UTF-8') ?>"
                     class="bg-black text-white px-4 py-2 rounded-full text-sm font-bold whitespace-nowrap">
                    Sử dụng
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'setting'): ?>
        <?php
          $goi_y = [];
          try {
            $stmt = $pdo->query("SELECT * FROM sanpham ORDER BY id_san_pham DESC LIMIT 6");
            $goi_y = $stmt->fetchAll(PDO::FETCH_ASSOC);
          } catch (Throwable $e) { $goi_y = []; }
        ?>
        <div class="border rounded-2xl p-6 bg-white">
          <h2 class="text-xl font-black mb-6">✨ Gợi ý dành cho bạn</h2>

          <?php if (!$goi_y): ?>
            <p class="text-gray-500">Chưa có dữ liệu gợi ý.</p>
          <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
              <?php foreach ($goi_y as $sp): ?>
                <a href="<?= htmlspecialchars(base_url('trang_nguoi_dung/chi_tiet_san_pham.php?id='.(int)$sp['id_san_pham']), ENT_QUOTES, 'UTF-8') ?>"
                   class="border rounded-2xl p-4 hover:shadow">
                  <img src="../assets/img/<?= htmlspecialchars((string)($sp['hinh_anh'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full aspect-square object-contain mb-2" onerror="this.style.display='none';" alt="">
                  <p class="font-bold text-sm line-clamp-2"><?= htmlspecialchars((string)($sp['ten_san_pham'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                  <p class="text-red-600 font-black"><?= number_format((float)($sp['gia'] ?? 0)) ?>₫</p>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </section>
  </div>
</main>

<style>
.tk-tab{
  display:block;
  width:100%;
  text-align:left;
  padding:12px 14px;
  border-radius:12px;
  font-weight:800;
  font-size:14px;
}
.tk-tab:hover{ background:#f3f4f6; }
.tk-tab.active{ background:#da0b0b; color:#fff; }

.tk-box{
  border:1px solid #eee;
  border-radius:14px;
  padding:16px;
  text-align:center;
  font-weight:800;
  background:#fff;
}
</style>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
