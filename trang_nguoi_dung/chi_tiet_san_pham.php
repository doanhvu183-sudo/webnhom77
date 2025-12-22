<?php
session_start();
require_once "../includes/db.php";

// ===== Đọc sản phẩm =====
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM sanpham WHERE id_san_pham = ?");
$stmt->execute([$id]);
$sp = $stmt->fetch();

if (!$sp) die("Sản phẩm không tồn tại");

// Hàm format tiền
function vnd($n) { return number_format($n, 0, ',', '.') . " đ"; }
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($sp['ten_san_pham']) ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/chi_tiet_san_pham.css?v=<?=time()?>">
</head>
<body>

<?php include "../giao_dien/header.php"; ?>

<div class="product-container">

    <!-- LEFT IMG -->
    <div class="left-box">
        <div class="main-img-box">
            <img id="mainImg" src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>" alt="">
        </div>

        <div class="thumb-row">
            <img class="thumb active" onclick="changeImg(this)"
                 src="../assets/img/<?= htmlspecialchars($sp['hinh_anh']) ?>">
        </div>
    </div>

    <!-- RIGHT -->
    <div class="right-box">

        <div class="breadcrumb">
            <a href="trang_chu.php">Trang chủ</a> /
            <span><?= htmlspecialchars($sp['ten_san_pham']) ?></span>
        </div>

        <h1 class="pd-title"><?= htmlspecialchars($sp['ten_san_pham']) ?></h1>
        <div class="pd-price"><?= vnd($sp['gia']) ?></div>

        <!-- SIZE -->
        <div class="pd-block">
            <div class="pd-label">Kích thước</div>
            <div class="size-row">
                <?php
                $sizes = ["US W5","US W6","US W7","US W8","US W9","US W10"];
                foreach ($sizes as $i => $s) {
                    echo '<button type="button" class="size-btn '.($i==0?'active':'').'">'.$s.'</button>';
                }
                ?>
            </div>
        </div>

        <!-- STOCK -->
        <div class="stock-line">
            <span class="dot green"></span>
            Còn hàng (<?= (int)$sp['so_luong'] ?>)
        </div>

        <!-- FORM ADD / BUY -->
        <form id="buyForm" class="action-box" method="post">
            <input type="hidden" name="id_san_pham" value="<?= (int)$sp['id_san_pham'] ?>">
            <input type="hidden" name="action" id="actionInput">

            <div class="qty-box">
                <button type="button" class="qty-btn" data-action="minus">-</button>
                <input name="so_luong" id="qtyInput" type="number" min="1" value="1">
                <button type="button" class="qty-btn" data-action="plus">+</button>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-add" onclick="submitForm('add')">Thêm vào giỏ</button>
                <button type="button" class="btn btn-buy" onclick="submitForm('buy')">Mua ngay</button>
            </div>
        </form>

        <!-- MÔ TẢ -->
        <div class="pd-desc">
            <h3>Mô tả sản phẩm</h3>
            <p><?= nl2br(htmlspecialchars($sp['mo_ta'])) ?></p>
        </div>

    </div>
</div>

<?php include "../giao_dien/footer.php"; ?>

<script>
// đổi ảnh
function changeImg(el){
    document.getElementById("mainImg").src = el.src;
    document.querySelectorAll(".thumb").forEach(t=>t.classList.remove("active"));
    el.classList.add("active");
}

// qty + -
const qtyInput = document.getElementById("qtyInput");
document.querySelectorAll(".qty-btn").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        let v = parseInt(qtyInput.value);
        if(btn.dataset.action === "minus") v = Math.max(1, v-1);
        else v++;
        qtyInput.value = v;
    });
});

// chọn size UI
document.querySelectorAll(".size-btn").forEach(b=>{
    b.addEventListener("click", ()=>{
        document.querySelectorAll(".size-btn").forEach(x=>x.classList.remove("active"));
        b.classList.add("active");
    });
});

// xử lý form
function submitForm(type){
    document.getElementById("actionInput").value = type;

    let form = document.getElementById("buyForm");

    fetch("them_vao_gio.php", {
        method: "POST",
        body: new FormData(form)
    })
    .then(res => res.text())
    .then(data => {
        if (type === "buy") {
            window.location.href = "checkout.php";
        } else {
            alert("Đã thêm vào giỏ!");
            location.reload();
        }
    });
}
</script>

</body>
</html>
