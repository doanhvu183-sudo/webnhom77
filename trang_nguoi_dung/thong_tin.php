<?php
session_start();
require_once "../includes/db.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    header("Location: dang_nhap.php?redirect=thong_tin.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

// Lấy thông tin user đầy đủ từ DB
$stmt = $pdo->prepare("SELECT * FROM nguoidung WHERE id_nguoi_dung = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Không tìm thấy tài khoản!");
}

// Avatar mặc định nếu không có ảnh
$avatar = $user['avatar'] ? $user['avatar'] : "default.png";

include "../giao_dien/header.php";
?>

<link rel="stylesheet" href="../assets/css/profile.css?v=<?= time() ?>">

<div class="profile-container">

    <div class="profile-card">

        <h2>Thông tin tài khoản</h2>

        <!-- AVATAR -->
        <div class="avatar-box">
            <img src="../assets/avatar/<?= htmlspecialchars($avatar) ?>" class="avatar-img" alt="avatar">

            <form action="../xu_ly/doi_avatar.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="avatar" accept="image/*" required>
                <button class="btn-avatar">Đổi ảnh đại diện</button>
            </form>
        </div>

        <!-- FORM UPDATE -->
        <form action="../xu_ly/cap_nhat_thong_tin.php" method="POST" class="info-form">

            <label>Họ tên</label>
            <input type="text" name="ho_ten" 
                   value="<?= htmlspecialchars($user['ho_ten']) ?>" required>

            <label>Email</label>
            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>

            <label>Số điện thoại</label>
            <input type="text" name="so_dien_thoai" 
                   value="<?= htmlspecialchars($user['so_dien_thoai']) ?>">

            <label>Địa chỉ</label>
            <input type="text" name="dia_chi" 
                   value="<?= htmlspecialchars($user['dia_chi']) ?>">

            <button class="btn-save">Lưu thay đổi</button>
        </form>

        <a href="doi_mat_khau.php" class="btn-change-pass">Đổi mật khẩu</a>

    </div>

</div>

<?php include "../giao_dien/footer.php"; ?>
