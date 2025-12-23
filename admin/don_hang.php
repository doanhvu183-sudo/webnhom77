<?php
require_once '_auth.php';
require_once '../cau_hinh/ket_noi.php';

$donhang = $pdo->query("
    SELECT * FROM donhang 
    ORDER BY ngay_dat DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Danh sách đơn hàng</h2>

<table border="1" width="100%">
<tr>
    <th>Mã</th>
    <th>Khách</th>
    <th>Tổng tiền</th>
    <th>Trạng thái</th>
</tr>

<?php foreach ($donhang as $d): ?>
<tr>
    <td>#<?= $d['id_don_hang'] ?></td>
    <td><?= $d['ho_ten_nhan'] ?></td>
    <td><?= number_format($d['tong_tien']) ?> đ</td>
    <td><?= $d['trang_thai'] ?></td>
</tr>
<?php endforeach; ?>
</table>
