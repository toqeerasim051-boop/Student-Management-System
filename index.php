<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
} else {
    header("Location:main.php");
}
exit;
