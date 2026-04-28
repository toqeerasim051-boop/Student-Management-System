<?php
session_start();
require_once '../config/db.php';
if (isset($_SESSION['user_id'])) {
    logAction($conn, $_SESSION['user_id'], $_SESSION['role'] ?? '', 'Logout', '', 'Logged out');
}
session_destroy();
header("Location: ../auth/login.php");
exit;
