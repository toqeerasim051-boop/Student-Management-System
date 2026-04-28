<?php
session_start();

// Clear POST data on normal page load (not POST request)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST = array();
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: ../$role/dashboard.php");
    exit;
}
require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $stmt = $conn->prepare("SELECT id,name,email,password,role,status FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        
        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            
            if ($user['role'] === 'student') {
                $s = $conn->prepare("SELECT id FROM students WHERE user_id=?");
                $s->bind_param("i",$user['id']); $s->execute();
                $_SESSION['entity_id'] = $s->get_result()->fetch_assoc()['id'] ?? null; $s->close();
            } elseif ($user['role'] === 'teacher') {
                $s = $conn->prepare("SELECT id FROM teachers WHERE user_id=?");
                $s->bind_param("i",$user['id']); $s->execute();
                $_SESSION['entity_id'] = $s->get_result()->fetch_assoc()['id'] ?? null; $s->close();
            }
            
            if (function_exists('logAction')) {
                logAction($conn, $user['id'], $user['role'], 'Login', '', 'Logged in');
            }
            
            header("Location: ../{$user['role']}/dashboard.php"); 
            exit;
        } else { 
            $error = 'Invalid email or password. Please try again.';
        }
    } else { 
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login | EduManage SMS</title>
<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ════════════════════════════════════════════
   EXACT DASHBOARD CSS VARIABLES
════════════════════════════════════════════ */
:root {
    --bg:       #0f172a;
    --surface:  #1e293b;
    --surface2: #263348;
    --border:   #334155;
    --text:     #e2e8f0;
    --muted:    #94a3b8;
    --dim:      #64748b;
    --primary:  #3b82f6;
    --primary-d:#2563eb;
    --primary-l:rgba(59,130,246,.15);
    --purple:   #8b5cf6;
    --purple-l: rgba(139,92,246,.15);
    --success:  #10b981;
    --success-l:rgba(16,185,129,.15);
    --warning:  #f59e0b;
    --warning-l:rgba(245,158,11,.15);
    --danger:   #ef4444;
    --danger-l: rgba(239,68,68,.15);
    --cyan:     #06b6d4;
    --cyan-l:   rgba(6,182,212,.15);
    --shadow:   0 4px 24px rgba(0,0,0,.35);
    --r:        12px;
    --rs:       8px;
}
[data-theme="light"] {
    --bg:       #f1f5f9;
    --surface:  #ffffff;
    --surface2: #f8fafc;
    --border:   #e2e8f0;
    --text:     #0f172a;
    --muted:    #475569;
    --dim:      #94a3b8;
    --primary-l:rgba(59,130,246,.1);
    --purple-l: rgba(139,92,246,.1);
    --success-l:rgba(16,185,129,.1);
    --warning-l:rgba(245,158,11,.1);
    --danger-l: rgba(239,68,68,.1);
    --cyan-l:   rgba(6,182,212,.1);
    --shadow:   0 4px 24px rgba(0,0,0,.1);
}

/* Disable autofill background colors */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px var(--surface2) inset !important;
    -webkit-text-fill-color: var(--text) !important;
    transition: background-color 5000s ease-in-out 0s;
}

/* ════ RESET ════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family:'Plus Jakarta Sans',sans-serif;
    background:var(--bg);color:var(--text);
    min-height:100vh;
    transition:background .3s,color .3s;
}

/* ════ BACKGROUND ════ */
.bg-wrap{position:fixed;inset:0;z-index:0;overflow:hidden;background:var(--bg);transition:background .3s}
.bg-grid{
    position:absolute;inset:0;
    background-image:
        linear-gradient(var(--border) 1px,transparent 1px),
        linear-gradient(90deg,var(--border) 1px,transparent 1px);
    background-size:64px 64px;
    opacity:.18;transition:opacity .3s;
}
[data-theme="light"] .bg-grid{opacity:.35}
.glow{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;animation:gd 14s ease-in-out infinite}
.g1{width:500px;height:500px;background:rgba(59,130,246,.14);top:-180px;left:-120px;}
.g2{width:420px;height:420px;background:rgba(139,92,246,.1);top:35%;right:-120px;animation-delay:-5s}
.g3{width:360px;height:360px;background:rgba(16,185,129,.07);bottom:-80px;left:28%;animation-delay:-10s}
[data-theme="light"] .g1{background:rgba(59,130,246,.08)}
[data-theme="light"] .g2{background:rgba(139,92,246,.06)}
[data-theme="light"] .g3{background:rgba(16,185,129,.05)}
@keyframes gd{0%,100%{transform:translate(0,0)}33%{transform:translate(35px,-25px)}66%{transform:translate(-22px,30px)}}

/* ════ NAVBAR (minimal for login) ════ */
.navbar{
    position:fixed;top:0;left:0;right:0;z-index:200;
    height:64px;
    background:rgba(15,23,42,.88);
    backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 32px;
}
[data-theme="light"] .navbar{background:rgba(255,255,255,.9)}
.nb-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.nb-icon{
    width:36px;height:36px;
    background:linear-gradient(135deg,var(--primary),var(--purple));
    border-radius:9px;display:flex;align-items:center;justify-content:center;
    font-size:16px;color:#fff;flex-shrink:0;
}
.nb-name{font-size:16px;font-weight:800;color:var(--text);letter-spacing:-.3px}
.nb-name span{color:var(--primary)}
.nb-right{display:flex;align-items:center;gap:12px}
.theme-btn{
    width:36px;height:36px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:var(--rs);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    color:var(--muted);font-size:15px;
    transition:all .2s;flex-shrink:0;
}
.theme-btn:hover{color:var(--primary);border-color:var(--primary)}

.back-link{
    display:flex;align-items:center;gap:6px;
    color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;
    padding:8px 14px;border-radius:var(--rs);background:var(--surface2);
    border:1px solid var(--border);transition:all .2s;
}
.back-link:hover{color:var(--primary);border-color:var(--primary);background:var(--primary-l)}

/* ════ LOGIN SECTION - CENTERED & LARGER ════ */
.login-section{
    position:relative;z-index:1;
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    padding:100px 24px 60px;
}
.login-wrap{
    width:100%;max-width:520px;
    animation:fadeUp .5s ease both;
}
.login-card{
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r);
    overflow:hidden;
    box-shadow:var(--shadow);
    transition:background .3s,border-color .3s;
}
.lc-top{
    padding:34px 32px 28px;
    background:linear-gradient(135deg,rgba(59,130,246,.2),rgba(139,92,246,.15));
    border-bottom:1px solid var(--border);
    text-align:center;
}
.lc-avatar{
    width:72px;height:72px;
    background:linear-gradient(135deg,var(--primary),var(--purple));
    border-radius:20px;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
    font-size:28px;color:#fff;
    box-shadow:0 8px 22px rgba(59,130,246,.35);
}
.lc-top h3{font-size:24px;font-weight:800;color:var(--text);margin-bottom:6px;letter-spacing:-.5px}
.lc-top p{font-size:14px;color:var(--muted);margin-bottom:12px}
.role-pills{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:8px}
.rpill{
    display:flex;align-items:center;gap:6px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:30px;padding:6px 14px;
    font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;
}
.rp-a{color:var(--danger)}.rp-t{color:var(--success)}.rp-s{color:var(--primary)}

.lc-body{padding:32px 36px}

.alert-err{
    display:flex;align-items:center;gap:8px;
    padding:14px 16px;border-radius:var(--rs);
    font-size:13px;font-weight:500;margin-bottom:20px;
    background:var(--danger-l);color:var(--danger);
    border:1px solid var(--danger);
    animation:shake .35s ease;
}
@keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

.fgrp{margin-bottom:20px}
.flbl{display:block;font-size:12px;font-weight:700;color:var(--muted);
    margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.fwrap{position:relative}
.fic{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--dim);font-size:15px}
.finp{
    width:100%;padding:14px 15px 14px 44px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:var(--rs);color:var(--text);
    font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;
    outline:none;transition:all .2s;
}
.finp:focus{border-color:var(--primary);background:rgba(59,130,246,.07);box-shadow:0 0 0 3px var(--primary-l)}
.finp::placeholder{color:var(--dim)}

.eye-t{
    position:absolute;right:15px;top:50%;transform:translateY(-50%);
    background:none;border:none;color:var(--dim);cursor:pointer;font-size:15px;
    transition:color .2s;
    padding:8px;
}
.eye-t:hover{color:var(--muted)}

.lbtn{
    width:100%;padding:14px;margin-top:8px;
    background:linear-gradient(135deg,var(--primary),var(--purple));
    border:none;border-radius:var(--rs);
    color:#fff;font-family:'Plus Jakarta Sans',sans-serif;
    font-size:15px;font-weight:700;cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:10px;
    transition:all .2s;letter-spacing:.3px;
    box-shadow:0 4px 16px rgba(59,130,246,.3);
}
.lbtn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(59,130,246,.45)}
.lbtn:active{transform:translateY(0)}

.help-links{
    display:flex;justify-content:center;gap:20px;margin-top:24px;
    padding-top:20px;border-top:1px solid var(--border);
}
.help-links a{
    font-size:12px;color:var(--dim);text-decoration:none;
    transition:color .2s;display:flex;align-items:center;gap:5px;
}
.help-links a:hover{color:var(--primary)}

/* ════ ANIMATIONS ════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(25px)}to{opacity:1;transform:translateY(0)}}

/* ════ RESPONSIVE ════ */
@media(max-width:640px){
    .navbar{padding:0 20px}
    .nb-name{font-size:14px}
    .back-link span{display:none}
    .login-section{padding:80px 16px 40px}
    .login-wrap{max-width:100%}
    .lc-top{padding:26px 20px 20px}
    .lc-avatar{width:56px;height:56px;font-size:22px}
    .lc-top h3{font-size:20px}
    .lc-body{padding:24px 20px}
    .finp{padding:12px 12px 12px 40px;font-size:14px}
    .fic{left:12px;font-size:13px}
    .eye-t{right:12px}
}

@media(max-width:380px){
    .role-pills{gap:6px}
    .rpill{font-size:9px;padding:4px 10px}
    .help-links{gap:12px;flex-wrap:wrap}
}
</style>
</head>
<body>

<!-- Background -->
<div class="bg-wrap">
    <div class="bg-grid"></div>
    <div class="glow g1"></div>
    <div class="glow g2"></div>
    <div class="glow g3"></div>
</div>

<!-- Navbar -->
<nav class="navbar">
    <a href="../main.php" class="nb-logo">
        <div class="nb-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="nb-name">Edu<span>Manage</span></div>
    </a>
    <div class="nb-right">
        <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme" id="themeBtn">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <a href="../main.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Home</span>
        </a>
    </div>
</nav>

<!-- Login Section -->
<section class="login-section">
    <div class="login-wrap">
        <div class="login-card">
            <div class="lc-top">
                <div class="lc-avatar"><i class="fas fa-graduation-cap"></i></div>
                <h3>Welcome Back 👋</h3>
                <p>Sign in to access your school portal</p>
            
            </div>
            <div class="lc-body">
                <?php if ($error): ?>
                <div class="alert-err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" autocomplete="off" id="loginForm">
                    <div class="fgrp">
                        <label class="flbl">Email Address</label>
                        <div class="fwrap">
                            <i class="fas fa-envelope fic"></i>
                            <input type="email" name="email" class="finp" id="emailFld"
                                   placeholder="your@sms.com" required
                                   value="" 
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="fgrp">
                        <label class="flbl">Password</label>
                        <div class="fwrap">
                            <i class="fas fa-lock fic"></i>
                            <input type="password" name="password" class="finp" id="passFld"
                                   placeholder="Enter your password" required
                                   autocomplete="new-password">
                            <button type="button" class="eye-t" onclick="togglePass()">
                                <i class="fas fa-eye" id="eyeIco"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="lbtn">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
                <div class="help-links">
                    <a href="#"><i class="fas fa-laptop"></i> Forgot Password?</a>
                    <a href="#" id="helpContact"><i class="fas fa-headset"></i> Help & Support</a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
/* ── THEME TOGGLE ── */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const current = html.getAttribute('data-theme');
    if (current === 'dark') {
        html.setAttribute('data-theme', 'light');
        icon.classList.replace('fa-moon', 'fa-sun');
        localStorage.setItem('sms-login-theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        icon.classList.replace('fa-sun', 'fa-moon');
        localStorage.setItem('sms-login-theme', 'dark');
    }
}

// Restore saved theme
(function() {
    const saved = localStorage.getItem('sms-login-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (saved === 'light' && icon) {
        icon.classList.replace('fa-moon', 'fa-sun');
    }
})();

/* ── SHOW / HIDE PASSWORD ── */
function togglePass() {
    const f = document.getElementById('passFld');
    const i = document.getElementById('eyeIco');
    if (f && i) {
        f.type = f.type === 'password' ? 'text' : 'password';
        i.classList.toggle('fa-eye');
        i.classList.toggle('fa-eye-slash');
    }
}

/* ── CLEAR FORM FIELDS ON PAGE LOAD ── */
function clearFormFields() {
    const emailField = document.getElementById('emailFld');
    const passField = document.getElementById('passFld');
    
    if (emailField) {
        emailField.value = '';
    }
    if (passField) {
        passField.value = '';
    }
}

// Clear on page load
window.addEventListener('load', clearFormFields);

// Clear when page is restored from cache (back/forward buttons)
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        clearFormFields();
    }
});

// Clear on DOM ready
document.addEventListener('DOMContentLoaded', clearFormFields);

// Help link alert
document.getElementById('helpContact')?.addEventListener('click', function(e) {
    e.preventDefault();
    alert('📧 Contact support at: support@edumanage.com\n📞 Or call: +92 315 0640664');
});
</script>
</body>
</html>