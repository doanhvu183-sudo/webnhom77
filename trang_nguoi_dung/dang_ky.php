<?php
session_start();
require_once "../includes/db.php";

// Trang trước (nếu đăng ký xong quay lại)
$redirect = $_GET["redirect"] ?? "../index.php";

$thong_bao = "";
$thanh_cong = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ho_ten = trim($_POST["ho_ten"]);
    $email = trim($_POST["email"]);
    $username = trim($_POST["ten_dang_nhap"]);
    $pass = trim($_POST["mat_khau"]);
    $sdt  = trim($_POST["so_dien_thoai"]);
    $dia_chi = trim($_POST["dia_chi"]);
    $gioi_tinh = $_POST["gioi_tinh"] ?? "";
    $ngay_sinh = $_POST["ngay_sinh"] ?? "";

    // Kiểm tra email đã tồn tại
    $stmt = $pdo->prepare("SELECT id_nguoi_dung FROM nguoidung WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $thong_bao = "Email đã được sử dụng!";
    } else {
        // Hash mật khẩu
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);

        // Thêm vào database
        $ins = $pdo->prepare("
            INSERT INTO nguoidung 
            (ho_ten, email, ten_dang_nhap, mat_khau, so_dien_thoai, dia_chi, gioi_tinh, ngay_sinh)
            VALUES (?,?,?,?,?,?,?,?)
        ");

        $ins->execute([$ho_ten, $email, $username, $pass_hash, $sdt, $dia_chi, $gioi_tinh, $ngay_sinh]);

        $thanh_cong = true;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng ký</title>
<link rel="stylesheet" href="../assets/css/auth.css?v=<?= time() ?>">
</head>
<body>

<?php include "../giao_dien/header.php"; ?>

<div class="auth-wrapper">
    <div class="auth-container single">

        <div class="auth-panel">
            <h2>Đăng ký tài khoản</h2>

            <?php if ($thong_bao): ?>
                <div class="auth-error"><?= $thong_bao ?></div>
            <?php endif; ?>

            <?php if ($thanh_cong): ?>
                <div class="auth-success">
                    Tạo tài khoản thành công! <br>
                    <a href="dang_nhap.php?redirect=<?= urlencode($redirect) ?>">Đăng nhập ngay</a>
                </div>
            <?php else: ?>

            <form method="post">

                <div class="auth-input">
                    <label>Họ tên</label>
                    <input type="text" name="ho_ten" required>
                </div>

                <div class="auth-input">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="auth-input">
                    <label>Username</label>
                    <input type="text" name="ten_dang_nhap" required>
                </div>

                <div class="auth-input">
                    <label>Mật khẩu</label>
                    <input type="password" name="mat_khau" required>
                </div>

                <div class="auth-input">
                    <label>Số điện thoại</label>
                    <input type="text" name="so_dien_thoai" required>
                </div>

                <div class="auth-input">
                    <label>Địa chỉ</label>
                    <input type="text" name="dia_chi" required>
                </div>

                <div class="auth-input">
                    <label>Giới tính</label>
                    <select name="gioi_tinh">
                        <option value="">Không chọn</option>
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                    </select>
                </div>

                <div class="auth-input">
                    <label>Ngày sinh</label>
                    <input type="date" name="ngay_sinh">
                </div>

                <button class="auth-btn">Đăng ký</button>

                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            </form>

            <p class="switch-link">
                Đã có tài khoản?
                <a href="dang_nhap.php?redirect=<?= urlencode($redirect) ?>">Đăng nhập</a>
            </p>

            <?php endif; ?>

        </div>

    </div>
</div>

<?php include "../giao_dien/footer.php"; ?>

</body>
</html>
