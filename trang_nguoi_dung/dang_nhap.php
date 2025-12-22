<?php
session_start();
require_once "../includes/db.php";

// Nếu có redirect từ trang trước
$redirect = $_GET["redirect"] ?? "../index.php";

$thong_bao = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"]);
    $pass  = trim($_POST["mat_khau"]);

    // Lấy user theo email hoặc username
    $stmt = $pdo->prepare("
        SELECT * FROM nguoidung 
        WHERE email = ? OR ten_dang_nhap = ? 
        LIMIT 1
    ");
    $stmt->execute([$login, $login]);
    $u = $stmt->fetch();

    if (!$u) {
        $thong_bao = "Tài khoản không tồn tại!";
    } elseif (!password_verify($pass, $u["mat_khau"])) {
        $thong_bao = "Sai mật khẩu!";
    } else {
        // Lưu SESSION đầy đủ (KHÔNG BAO GIỜ lỗi checkout hay lỗi thông tin)
        $_SESSION["user"] = [
            "id"             => $u["id_nguoi_dung"],
            "ten_dang_nhap"  => $u["ten_dang_nhap"],
            "ho_ten"         => $u["ho_ten"],
            "email"          => $u["email"],
            "so_dien_thoai"  => $u["so_dien_thoai"],
            "dia_chi"        => $u["dia_chi"]
        ];

        // Điều hướng về trang trước hoặc trang chủ
        header("Location: " . $redirect);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập</title>
<link rel="stylesheet" href="../assets/css/auth.css?v=<?= time() ?>">
</head>
<body>

<?php include "../giao_dien/header.php"; ?>

<div class="auth-wrapper">
    <div class="auth-container single">

        <div class="auth-panel">
            <h2>Đăng nhập</h2>

            <?php if ($thong_bao): ?>
                <div class="auth-error"><?= $thong_bao ?></div>
            <?php endif; ?>

            <form method="post">

                <div class="auth-input">
                    <label>Email hoặc Username</label>
                    <input type="text" name="login" required>
                </div>

                <div class="auth-input">
                    <label>Mật khẩu</label>
                    <input type="password" name="mat_khau" required>
                </div>

                <button class="auth-btn">Đăng nhập</button>

                <!-- Gửi lại trang trước -->
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            </form>

            <p class="switch-link">
                Chưa có tài khoản?
                <a href="dang_ky.php?redirect=<?= urlencode($redirect) ?>">Đăng ký</a>
            </p>

        </div>

    </div>
</div>

<?php include "../giao_dien/footer.php"; ?>

</body>
</html>
