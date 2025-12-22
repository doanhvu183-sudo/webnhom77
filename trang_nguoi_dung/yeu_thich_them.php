<?php
session_start();
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

$isAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1')
  || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function jsonOut($ok, $msg) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// user
$id_nguoi_dung = $_SESSION['nguoi_dung']['id_nguoi_dung'] ?? ($_SESSION['nguoi_dung']['id'] ?? 0);
if (!$id_nguoi_dung) {
  if ($isAjax) jsonOut(false, 'Bạn cần đăng nhập để thêm yêu thích.');
  header('Location: dang_nhap.php');
  exit;
}

// product id
$id_sp = (int)($_GET['id'] ?? 0);
if ($id_sp <= 0) {
  if ($isAjax) jsonOut(false, 'Sản phẩm không hợp lệ.');
  header('Location: index.php');
  exit;
}

// optional: check product exists
$stmt = $pdo->prepare("SELECT 1 FROM sanpham WHERE id_san_pham=? LIMIT 1");
$stmt->execute([$id_sp]);
if (!$stmt->fetchColumn()) {
  if ($isAjax) jsonOut(false, 'Không tìm thấy sản phẩm.');
  header('Location: index.php');
  exit;
}

// check exists
$stmt = $pdo->prepare("SELECT 1 FROM yeu_thich WHERE id_nguoi_dung=? AND id_san_pham=? LIMIT 1");
$stmt->execute([$id_nguoi_dung, $id_sp]);

if ($stmt->fetchColumn()) {
  if ($isAjax) jsonOut(true, 'Sản phẩm đã có trong yêu thích.');
  $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Sản phẩm đã có trong yêu thích.'];
  header("Location: chi_tiet_san_pham.php?id=" . $id_sp);
  exit;
}

// insert (đúng cột ngay_tao)
$stmt = $pdo->prepare("INSERT INTO yeu_thich (id_nguoi_dung, id_san_pham, ngay_tao) VALUES (?, ?, NOW())");
$stmt->execute([$id_nguoi_dung, $id_sp]);

if ($isAjax) jsonOut(true, 'Đã thêm vào yêu thích.');
$_SESSION['flash'] = ['type' => 'success', 'msg' => 'Đã thêm vào yêu thích.'];
header("Location: chi_tiet_san_pham.php?id=" . $id_sp);
exit;
