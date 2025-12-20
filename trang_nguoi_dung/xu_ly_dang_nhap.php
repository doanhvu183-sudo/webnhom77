<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$login    = trim($_POST['login'] ?? '');
$matKhau  = $_POST['mat_khau'] ?? '';

if ($login === '' || $matKhau === '') {
    die("<script>alert('Vui lòng nhập đầy đủ thông tin');history.back();</script>");
}

/* TÌM NGƯỜI DÙNG */
$sql = "
    SELECT *
    FROM nguoidung
    WHERE email = :login OR ten_dang_nhap = :login
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['login' => $login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* KIỂM TRA */
if (!$user || !password_verify($matKhau, $user['mat_khau'])) {
    die("<script>alert('Sai thông tin đăng nhập');history.back();</script>");
}

/* LƯU SESSION – THỐNG NHẤT TOÀN SITE */
$_SESSION['nguoi_dung'] = [
    'id'            => $user['id_nguoi_dung'],
    'ten_dang_nhap' => $user['ten_dang_nhap'],
    'ho_ten'        => $user['ho_ten'],
    'email'         => $user['email'],
    'sdt'           => $user['sdt'] ?? '',
    'dia_chi'       => $user['dia_chi'] ?? '',
    'vai_tro'       => $user['vai_tro']
];

/* QUAY LẠI TRANG TRƯỚC KHI LOGIN */
if (!empty($_SESSION['redirect_after_login'])) {
    $url = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header("Location: $url");
} else {
    header("Location: trang_chu.php");
}
exit;
