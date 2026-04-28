<?php
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(dirname(__FILE__)));
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Detect base URL from script path
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    if (preg_match('#(/[^/]*sms[^/]*)#i', $script, $m)) {
        define('BASE_URL', $protocol . '://' . $host . $m[1]);
    } else {
        define('BASE_URL', $protocol . '://' . $host . '/sms-project');
    }
}
$user = getCurrentUser();
$role = $user['role'];

// Sidebar menu per role
$menus = [
    'admin' => [
        ['icon' => 'tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/admin/dashboard.php'],
        ['icon' => 'user-graduate', 'label' => 'Students', 'url' => BASE_URL . '/admin/students.php'],
        ['icon' => 'chalkboard-teacher', 'label' => 'Teachers', 'url' => BASE_URL . '/admin/teachers.php'],
        ['icon' => 'school', 'label' => 'Classes', 'url' => BASE_URL . '/admin/classes.php'],
        ['icon' => 'book', 'label' => 'Subjects', 'url' => BASE_URL . '/admin/subjects.php'],
        ['icon' => 'calendar-check', 'label' => 'Attendance', 'url' => BASE_URL . '/admin/attendance.php'],
        ['icon' => 'star', 'label' => 'Grades', 'url' => BASE_URL . '/admin/grades.php'],
        ['icon' => 'bullhorn', 'label' => 'Announcements', 'url' => BASE_URL . '/admin/announcements.php'],
        ['icon' => 'history', 'label' => 'Activity Logs', 'url' => BASE_URL . '/admin/logs.php'],
    ],
    'teacher' => [
        ['icon' => 'tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/teacher/dashboard.php'],
        ['icon' => 'calendar-check', 'label' => 'Mark Attendance', 'url' => BASE_URL . '/teacher/attendance.php'],
        ['icon' => 'star', 'label' => 'Enter Grades', 'url' => BASE_URL . '/teacher/grades.php'],
        ['icon' => 'users', 'label' => 'My Students', 'url' => BASE_URL . '/teacher/students.php'],
    ],
    'student' => [
        ['icon' => 'tachometer-alt', 'label' => 'Dashboard', 'url' => BASE_URL . '/student/dashboard.php'],
        ['icon' => 'user', 'label' => 'My Profile', 'url' => BASE_URL . '/student/profile.php'],
        ['icon' => 'calendar-check', 'label' => 'Attendance', 'url' => BASE_URL . '/student/attendance.php'],
        ['icon' => 'star', 'label' => 'My Grades', 'url' => BASE_URL . '/student/grades.php'],
    ],
];

$currentMenu = $menus[$role] ?? [];
$currentUrl = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Dashboard') ?> — EduManage SMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="logo-text">
            <span class="logo-name">EduManage</span>
            <span class="logo-sub">SMS v1.0</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        <div class="user-info">
            <span class="user-name"><?= h($user['name']) ?></span>
            <span class="role-badge role-<?= $role ?>"><?= ucfirst($role) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($currentMenu as $item): 
            $isActive = strpos($currentUrl, $item['url']) !== false;
        ?>
        <a href="<?= $item['url'] ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
            <i class="fas fa-<?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <a href="<?= BASE_URL ?>/auth/logout.php" class="sidebar-logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Navbar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title-area">
                <h1 class="page-heading"><?= h($pageTitle ?? 'Dashboard') ?></h1>
                <?php if (isset($breadcrumb)): ?>
                <div class="breadcrumb">
                    <a href="<?= BASE_URL ?>/<?= $role ?>/dashboard.php">Home</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?= h($breadcrumb) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="topbar-right">
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle theme">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <div class="topbar-user">
                <div class="topbar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <div class="topbar-user-info">
                    <span><?= h($user['name']) ?></span>
                    <small><?= ucfirst($role) ?></small>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="content-area">
