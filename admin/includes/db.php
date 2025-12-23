<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ĐÁNH DẤU FILE */
define('DB_LOADED', true);

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=webnhom7;charset=utf8",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối DB: " . $e->getMessage());
}
