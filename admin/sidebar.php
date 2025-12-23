<div class="sidebar">
    <a href="index.php">Tổng quan</a>
    <a href="san_pham.php">Sản phẩm</a>
    <a href="ton_kho.php">Tồn kho</a>
    <a href="don_hang.php">Đơn hàng</a>
    <a href="nguoi_dung.php">Người dùng</a>

    <?php if ($_SESSION['admin']['vai_tro'] === 'ADMIN'): ?>
        <a href="nhan_vien.php">Nhân viên</a>
    <?php endif; ?>
</div>
