<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user'])) {
    header("Location: dang_nhap.php?redirect=doi_mat_khau.php");
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đổi mật khẩu</title>

<style>
.page-wrap {
    max-width: 500px;
    margin: 40px auto;
    padding: 25px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 0 10px #ddd;
    font-family: Arial;
}
h2 {
    margin-bottom: 20px;
}
label {
    font-weight: bold;
    margin-top: 10px;
}
input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-top: 5px;
}
.btn-save {
    margin-top: 20px;
    width: 100%;
    padding: 12px;
    background: black;
    color: white;
    font-size: 15px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}
.btn-save:hover {
    opacity: 0.85;
}
.message {
    margin-top: 10px;
    padding: 10px;
    border-radius: 6px;
}
.success { background: #d4ffd4; color: #0a7a0a; }
.error { background: #ffe0e0; color: #b30000; }
</style>
</head>

<body>

<?php include "../giao_dien/header.php"; ?>

<div class="page-wrap">

    <h2>Đổi mật khẩu</h2>

    <?php if(isset($_GET["success"])): ?>
        <div class="message success">Đổi mật khẩu thành công!</div>
    <?php endif; ?>

    <?php if(isset($_GET["error"])): ?>
        <div class="message error"><?= htmlspecialchars($_GET["error"]) ?></div>
    <?php endif; ?>

    <form action="../xu_ly/xl_doi_mat_khau.php" method="POST">

        <label>Mật khẩu hiện tại</label>
        <input type="password" name="old_pass" required>

        <label>Mật khẩu mới</label>
        <input type="password" name="new_pass" required>

        <label>Nhập lại mật khẩu mới</label>
        <input type="password" name="confirm_pass" required>

        <button class="btn-save">Lưu thay đổi</button>
    </form>

</div>

<?php include "../giao_dien/footer.php"; ?>
</body>
</html>
