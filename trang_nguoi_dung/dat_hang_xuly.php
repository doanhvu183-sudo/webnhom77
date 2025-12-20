<?php
// dat_hang_xuly.php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['nguoi_dung'])) {
    header("Location: dang_nhap.php");
    exit;
}

$user = $_SESSION['nguoi_dung'];
$userId = $user['id_nguoi_dung'] ?? ($user['id'] ?? 0);

$cart   = $_SESSION['cart'] ?? [];
$chonSP = $_POST['cart_keys'] ?? [];
if (!is_array($chonSP)) $chonSP = [];

if (empty($cart) || empty($chonSP)) {
    die('Không có sản phẩm để đặt hàng');
}

/* ================== TÍNH TIỀN (ĐỒNG BỘ) ================== */
$tong_tien = 0;
$items = [];

foreach ($chonSP as $key) {
    if (!isset($cart[$key])) continue;

    $sp = $cart[$key];
    $qty = max(1, (int)($sp['qty'] ?? $sp['so_luong_mua'] ?? 1));
    $don_gia = (int)($sp['don_gia'] ?? $sp['gia'] ?? 0);
    $thanh_tien = $don_gia * $qty;

    $tong_tien += $thanh_tien;

    $items[] = [
        'key'         => $key,
        'id_san_pham'  => (int)($sp['id'] ?? 0),
        'ten_san_pham' => (string)($sp['ten'] ?? ''),
        'size'        => (string)($sp['size'] ?? ''),
        'so_luong'     => $qty,
        'don_gia'      => $don_gia,
        'thanh_tien'   => $thanh_tien
    ];
}

if (empty($items)) {
    die('Không có sản phẩm hợp lệ để đặt hàng');
}

/* ================== VOUCHER (SESSION) ================== */
$tien_giam = 0;
$ma_voucher = null;

if (!empty($_SESSION['voucher'])) {
    $vc = $_SESSION['voucher'];
    $ma_voucher = $vc['ma_voucher'] ?? null;

    if (($vc['loai'] ?? '') === 'TIEN') {
        $tien_giam = (int)($vc['gia_tri'] ?? 0);
    } elseif (($vc['loai'] ?? '') === 'PHAN_TRAM') {
        $tien_giam = (int)floor($tong_tien * ((int)($vc['gia_tri'] ?? 0)) / 100);
        if (!empty($vc['toi_da'])) {
            $tien_giam = min($tien_giam, (int)$vc['toi_da']);
        }
    }
}

$tong_thanh_toan = max(0, $tong_tien - $tien_giam);

/* ================== TẠO MÃ ĐƠN (DÙNG ĐỂ LẤY ID) ================== */
$ma_don_hang = 'DH' . date('YmdHis') . random_int(100, 999);

try {
    $pdo->beginTransaction();

    /* ================== TẠO ĐƠN HÀNG ================== */
    // Chỉ dùng các cột bạn đang có/đã dùng trong các file trước đó
    $stmt = $pdo->prepare("
        INSERT INTO donhang
        (id_nguoi_dung, ma_don_hang, ho_ten_nhan, so_dien_thoai_nhan, dia_chi_nhan,
         tong_tien, tong_thanh_toan, phuong_thuc_thanh_toan, trang_thai, ma_voucher, ngay_dat)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'CHO_XU_LY', ?, NOW())
    ");

    $stmt->execute([
        $userId,
        $ma_don_hang,
        $_POST['ho_ten'] ?? '',
        $_POST['so_dien_thoai'] ?? '',
        $_POST['dia_chi'] ?? '',
        $tong_tien,
        $tong_thanh_toan,
        $_POST['phuong_thuc'] ?? 'COD',
        $ma_voucher
    ]);

    // 1) thử lastInsertId
    $id_don_hang = (int)$pdo->lastInsertId();

    // 2) nếu = 0 thì lấy theo ma_don_hang (cực chắc)
    if ($id_don_hang <= 0) {
        $q = $pdo->prepare("SELECT id_don_hang FROM donhang WHERE ma_don_hang = ? LIMIT 1");
        $q->execute([$ma_don_hang]);
        $id_don_hang = (int)$q->fetchColumn();
    }

    if ($id_don_hang <= 0) {
        throw new Exception('Không tạo được đơn hàng (không lấy được id_don_hang)');
    }

    /* ================== CHI TIẾT ĐƠN ================== */
    $stmtCT = $pdo->prepare("
        INSERT INTO chitiet_donhang
        (id_don_hang, id_san_pham, ten_san_pham, size, so_luong, don_gia, thanh_tien)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $it) {
        $stmtCT->execute([
            $id_don_hang,
            $it['id_san_pham'],
            $it['ten_san_pham'],
            $it['size'],
            $it['so_luong'],
            $it['don_gia'],
            $it['thanh_tien']
        ]);

        unset($_SESSION['cart'][$it['key']]);
    }

    if (empty($_SESSION['cart'])) unset($_SESSION['cart']);
    unset($_SESSION['voucher']);

    $pdo->commit();

    header("Location: hoan_tat.php?id=" . $id_don_hang);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Lỗi đặt hàng: " . $e->getMessage());
}
