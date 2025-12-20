<?php
session_start();
session_destroy();
header("Location: trang_chu.php");
