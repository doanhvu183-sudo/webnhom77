<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html class="light" lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Admin - Crocs</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@200..700" rel="stylesheet"/>

  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#137fec",
            secondary: "#5e6c84",
            "background-light": "#f8f9fa",
            "background-dark": "#0f172a",
            "surface-dark": "#1e293b",
            success: "#10b981",
            warning: "#f59e0b",
            danger: "#ef4444",
          },
          fontFamily: { display: ["Manrope","sans-serif"] },
          borderRadius: { DEFAULT:"0.375rem", xl:"1rem", "2xl":"1.5rem" },
          boxShadow: { soft: "0 4px 20px -2px rgba(0,0,0,0.05)" }
        }
      }
    }
  </script>

  <style>
    .material-symbols-outlined{
      font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
      line-height: 1;
    }
    ::-webkit-scrollbar{ width:6px; height:6px; }
    ::-webkit-scrollbar-track{ background:transparent; }
    ::-webkit-scrollbar-thumb{ background:#cbd5e1; border-radius:999px; }
    ::-webkit-scrollbar-thumb:hover{ background:#94a3b8; }
    .dark ::-webkit-scrollbar-thumb{ background:#475569; }

    /* Fix: nếu bạn lỡ có link/menu cũ thì nó sẽ không bị "dính 1 hàng" */
    a{ text-decoration:none; }
  </style>
</head>

<body class="font-display bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 h-screen overflow-hidden flex selection:bg-primary/20">
