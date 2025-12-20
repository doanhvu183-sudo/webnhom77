<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "webnhom7";

$conn = mysqli_connect($host, $user, $pass, $db);
mysqli_set_charset($conn, "utf8");

if (!$conn) {
    die("Lỗi kết nối CSDL: " . mysqli_connect_error());
}
