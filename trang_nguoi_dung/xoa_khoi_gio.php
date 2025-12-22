<?php
require_once __DIR__ . '/../cau_hinh/ket_noi.php';
require_once __DIR__ . '/../cau_hinh/ham.php';

init_gio_hang();
$id = (int)($_GET['id'] ?? 0);

/**
 * Remove an item from the shopping cart stored in session.
 * This provides a local definition in case the function is not defined in cau_hinh/ham.php.
 */
if (!function_exists('xoa_khoi_gio')) {
	function xoa_khoi_gio(int $id): void {
		if ($id <= 0) {
			return;
		}

		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		// Expecting cart in $_SESSION['gio_hang'] as [id => qty]
		if (!isset($_SESSION['gio_hang']) || !is_array($_SESSION['gio_hang'])) {
			return;
		}

		if (isset($_SESSION['gio_hang'][$id])) {
			unset($_SESSION['gio_hang'][$id]);
		}
	}
}

xoa_khoi_gio($id);

header("Location: gio_hang.php");
exit;
