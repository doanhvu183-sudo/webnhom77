<?php
// admin/includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function adminDaDangNhap(): bool {
    return !empty($_SESSION['admin']) && !empty($_SESSION['admin']['id_admin']);
}

function yeuCauDangNhap(): void {
    if (!adminDaDangNhap()) {
        header('Location: dang_nhap.php');
        exit;
    }
}

function layVaiTroAdmin(): string {
    $v = $_SESSION['admin']['vai_tro'] ?? '';
    return strtoupper(trim((string)$v));
}

function coQuyen(array $vaiTroDuocPhep): bool {
    $role = layVaiTroAdmin();
    $allow = array_map(fn($x) => strtoupper(trim((string)$x)), $vaiTroDuocPhep);
    return in_array($role, $allow, true);
}

function yeuCauVaiTro(array $vaiTroDuocPhep): void {
    if (!coQuyen($vaiTroDuocPhep)) {
        http_response_code(403);
        echo "<h2 style='font-family:Arial'>403 - Bạn không có quyền truy cập chức năng này.</h2>";
        exit;
    }
}
