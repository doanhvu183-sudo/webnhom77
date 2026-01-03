<?php
// admin/includes/giaoDienDau.php
require_once __DIR__ . '/hamChung.php';

$PAGE_TITLE = $PAGE_TITLE ?? ($title ?? 'Crocs Admin');
$ACTIVE = $ACTIVE ?? 'tong_quan';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= h($PAGE_TITLE) ?></title>

  <!-- Fonts + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;600&display=swap" rel="stylesheet">

  <!-- Tailwind CDN (nếu bạn đã có file css riêng, có thể thay bằng link nội bộ) -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root{
      --primary:#0b63f6;
      --line:#e9eef6;
      --muted:#64748b;
      --bg:#f6f8fc;
    }
    html,body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;}
    .shadow-card{box-shadow:0 10px 30px rgba(15,23,42,.06);}
    .border-line{border-color:var(--line);}
    .text-muted{color:var(--muted);}
    .bg-primary{background:var(--primary);}
    .text-primary{color:var(--primary);}
  </style>
</head>

<body class="bg-[var(--bg)]">
<div class="min-h-screen flex">
