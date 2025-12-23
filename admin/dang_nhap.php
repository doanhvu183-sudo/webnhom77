<?php
session_start();
require_once '../cau_hinh/ket_noi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT * FROM admin 
        WHERE email = ? AND trang_thai = 1 
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = [
            'id' => $admin['id_admin'],
            'ho_ten' => $admin['ho_ten'],
            'email' => $admin['email'],
            'vai_tro' => $admin['vai_tro'],
            'avatar' => $admin['avatar']
        ];
        header("Location: index.php");
        exit;
    } else {
        $error = 'Sai email hoặc mật khẩu';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Admin Login</title></head>
<body>
<form method="post">
    <h2>Đăng nhập Admin</h2>
    <input name="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Mật khẩu" required>
    <button>Đăng nhập</button>
    <p style="color:red"><?= $error ?></p>
</form>
</body>
</html>
