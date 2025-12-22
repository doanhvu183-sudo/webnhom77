<?php
require_once __DIR__ . '/../giao_dien/header.php';
require_once __DIR__ . '/../cau_hinh/ket_noi.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../tai_khoan/dang_nhap.php");
    exit;
}

$user = $_SESSION['user'];
?>

<link rel="stylesheet" href="../assets/css/profile.css">

<div class="profile-container">

    <div class="profile-card">
        <h2>Hồ sơ của tôi</h2>

        <div class="profile-item">
            <span>Họ tên:</span>
            <strong><?= htmlspecialchars($user['ho_ten']) ?></strong>
        </div>

        <div class="profile-item">
            <span>Email:</span>
            <strong><?= htmlspecialchars($user['email']) ?></strong>
        </div>

        <div class="profile-item">
            <span>Số điện thoại:</span>
            <strong><?= htmlspecialchars($user['so_dien_thoai']) ?></strong>
        </div>

        <div class="profile-item">
            <span>Địa chỉ:</span>
            <strong><?= htmlspecialchars($user['dia_chi']) ?></strong>
        </div>

        <a class="btn-auth" href="doi_mat_khau.php">Đổi mật khẩu</a>
    </div>

</div>

<?php require_once __DIR__ . '/../giao_dien/footer.php'; ?>
