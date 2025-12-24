<?php
// admin/includes/auth.php
require_once __DIR__ . '/_init.php';

if (empty($AUTH_OK)) {
  header("Location: dang_nhap.php");
  exit;
}
