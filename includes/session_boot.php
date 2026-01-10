<?php
// includes/session_boot.php
if (!headers_sent()) { @ob_start(); }

$config = require __DIR__ . '/../cau_hinh/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (!isset($_SESSION['_inited'])) {
  session_regenerate_id(true);
  $_SESSION['_inited'] = 1;
}
