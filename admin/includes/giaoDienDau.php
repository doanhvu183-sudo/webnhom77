<?php
// admin/includes/giaoDienDau.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../cau_hinh/ket_noi.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['admin'])) { header("Location: dang_nhap.php"); exit; }
[$me,$myId,$vaiTro,$isAdmin] = auth_me();

$PAGE_TITLE = $PAGE_TITLE ?? 'Crocs Admin';
$shopName = get_setting($pdo, 'shop_name', 'Crocs Admin');
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= h($PAGE_TITLE) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config={theme:{extend:{
      colors:{
        primary:"#137fec",
        bg:"#f6f7fb",
        line:"#e5e7eb",
        muted:"#64748b",
      },
      fontFamily:{display:["Manrope","sans-serif"]},
      boxShadow:{card:"0 10px 30px rgba(15,23,42,.06)"},
      borderRadius:{'2xl':"1.25rem"}
    }}}
  </script>
  <style>
    ::-webkit-scrollbar{width:8px;height:8px}
    ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}
  </style>
</head>

<body class="font-display bg-bg text-slate-900">
<div class="h-screen w-full overflow-hidden flex">
