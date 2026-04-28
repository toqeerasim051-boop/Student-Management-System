<?php
// Load local config (credentials). Falls back to defaults for first-time setup.
$_localConfig = __DIR__ . '/config.local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
} else {
    // Fallback defaults — copy config.example.php to config.local.php
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'sms_db');
    define('SITE_NAME', 'EduManage SMS');
    define('SITE_URL', 'http://localhost/sms-project');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#ef4444;">⚠️ Database Connection Failed</h2>
        <p>Please check your database settings in <code>config/db.php</code></p>
        <p>Error: ' . htmlspecialchars($conn->connect_error) . '</p>
        <p>Make sure you have run <code>setup.sql</code> first.</p>
    </div>');
}

$conn->set_charset('utf8mb4');

// Helper: log action
function logAction($conn, $userId, $role, $action, $old = '', $new = '') {
    $stmt = $conn->prepare("INSERT INTO logs (user_id, user_role, action, old_value, new_value) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issss", $userId, $role, $action, $old, $new);
    $stmt->execute();
    $stmt->close();
}

// Helper: sanitize output
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper: redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Pagination helper
function paginate($conn, $sql, $countSql, $params, $types, $page = 1, $perPage = 10) {
    $offset = ($page - 1) * $perPage;
    
    $countStmt = $conn->prepare($countSql);
    if ($params) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $stmt = $conn->prepare($sql . " LIMIT ? OFFSET ?");
    $allParams = $params ? array_merge($params, [$perPage, $offset]) : [$perPage, $offset];
    $allTypes = $types . 'ii';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'data' => $data,
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'current' => $page
    ];
}
