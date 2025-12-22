<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

session_start();
$id = $_SESSION['user']['id'];

$mk_cu  = $_POST['mk_cu'];
$mk_moi = $_POST['mk_moi'];
$mk_moi2 = $_POST['mk_moi2'];

$stmt = $pdo->prepare("SELECT * FROM NGUOIDUNG WHERE id_nguoi_dung = :id");
$stmt->execute([':id' => $id]);
$u = $stmt->fetch();

if ($u['mat_khau'] !== $mk_cu) {
    header("Location: doi_mat_khau.php?msg=Mật khẩu cũ không đúng");
    exit;
}

if ($mk_moi !== $mk_moi2) {
    header("Location: doi_mat_khau.php?msg=Mật khẩu mới không khớp");
    exit;
}

$update = $pdo->prepare("UPDATE NGUOIDUNG SET mat_khau = :mk WHERE id_nguoi_dung = :id");
$update->execute([':mk' => $mk_moi, ':id' => $id]);

header("Location: doi_mat_khau.php?msg=Đổi mật khẩu thành công!");
exit;
