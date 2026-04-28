<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . getBaseUrl() . "/auth/login.php");
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        $base = getBaseUrl();
        $r = $_SESSION['role'];
        header("Location: $base/$r/dashboard.php");
        exit;
    }
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Find the base path (folder containing index.php)
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
    // Walk up to find project root
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $projectPath = str_replace($docRoot, '', $scriptDir);
    // Find sms-project root
    if (preg_match('#(/[^/]*sms[^/]*)#i', $projectPath, $m)) {
        return $protocol . '://' . $host . $m[1];
    }
    return $protocol . '://' . $host . '/sms-project';
}

function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'entity_id' => $_SESSION['entity_id'] ?? null,
    ];
}

// ── CSRF Protection ──────────────────────────────────────────
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
            <h2 style="color:#ef4444;">⛔ Invalid Request</h2>
            <p>Security token mismatch. Please <a href="javascript:history.back()">go back</a> and try again.</p>
        </div>');
    }
}

// Flash messages
function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash() {
    $f = getFlash();
    if (!$f) return '';
    $icons = ['success' => 'check-circle', 'error' => 'times-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'];
    $icon = $icons[$f['type']] ?? 'info-circle';
    return '<div class="alert alert-' . h($f['type']) . '"><i class="fas fa-' . $icon . '"></i> ' . h($f['msg']) . '</div>';
}
