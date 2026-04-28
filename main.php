<?php
session_start();
require_once 'config/db.php';

$totalStudents  = (int)$conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$totalTeachers  = (int)$conn->query("SELECT COUNT(*) FROM teachers")->fetch_row()[0];
$totalClasses   = (int)$conn->query("SELECT COUNT(*) FROM classes")->fetch_row()[0];
$totalSubjects  = (int)$conn->query("SELECT COUNT(*) FROM subjects")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>EduManage SMS — Smart School Management</title>
<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- Plus Jakarta Sans (same as dashboard) -->
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

/* ════ RESET ════ */


*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
    font-family:'Plus Jakarta Sans',sans-serif;
    background:var(--bg);color:var(--text);
    overflow-x:hidden;min-height:100vh;
    transition:background .3s,color .3s;
    width:100%;max-width:100vw;

}
.mypara{
    display:flex;
    justify-content:center;
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
.g1{width:500px;height:500px;background:rgba(59,130,246,.14);top:-180px;left:-120px;animation-delay:0s}
.g2{width:420px;height:420px;background:rgba(139,92,246,.1);top:35%;right:-120px;animation-delay:-5s}
.g3{width:360px;height:360px;background:rgba(16,185,129,.07);bottom:-80px;left:28%;animation-delay:-10s}
[data-theme="light"] .g1{background:rgba(59,130,246,.08)}
[data-theme="light"] .g2{background:rgba(139,92,246,.06)}
[data-theme="light"] .g3{background:rgba(16,185,129,.05)}
@keyframes gd{0%,100%{transform:translate(0,0)}33%{transform:translate(35px,-25px)}66%{transform:translate(-22px,30px)}}

/* ════ NAVBAR ════ */
.navbar{
    position:fixed;top:0;left:0;right:0;z-index:200;
    height:64px;
    background:rgba(15,23,42,.88);
    backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 32px;
    transition:background .3s,border-color .3s;
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
.nb-right{display:flex;align-items:center;gap:10px}
.nb-links{display:flex;align-items:center;gap:24px}
.nb-links a{color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:color .2s;white-space:nowrap}
.nb-links a:hover{color:var(--primary)}

/* Theme toggle button */
.theme-btn{
    width:36px;height:36px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:var(--rs);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    color:var(--muted);font-size:15px;
    transition:all .2s;flex-shrink:0;
}
.theme-btn:hover{color:var(--primary);border-color:var(--primary)}

.nb-cta{
    background:linear-gradient(135deg,var(--primary),var(--purple));
    color:#fff;padding:8px 18px;border-radius:var(--rs);
    font-size:13px;font-weight:600;text-decoration:none;
    display:flex;align-items:center;gap:6px;
    transition:opacity .2s;white-space:nowrap;
    box-shadow:0 4px 14px rgba(59,130,246,.3);
}
.nb-cta:hover{opacity:.88;color:#fff}

/* Mobile menu toggle */
.mob-menu-btn{
    display:none;width:36px;height:36px;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:var(--rs);align-items:center;justify-content:center;
    color:var(--muted);font-size:16px;cursor:pointer;
}

/* ════ HERO ════ */
.hero{
    position:relative;z-index:1;
    min-height:100vh;
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    text-align:center;
     padding: 96px 16px 0px;
    width:100%;
}

.hero-badge{
    display:inline-flex;align-items:center;gap:8px;
    background:var(--purple-l);border:1px solid rgba(139,92,246,.35);
    border-radius:30px;padding:7px 18px;
    font-size:12px;font-weight:600;color:var(--purple);
    margin-bottom:26px;letter-spacing:.3px;
    animation:fadeUp .5s ease both;
}
.badge-dot{
    width:7px;height:7px;border-radius:50%;
    background:var(--purple);
    animation:blink 2s ease-in-out infinite;
}
@keyframes blink{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.65)}}

.hero-h1{
    font-size:clamp(34px,6vw,76px);font-weight:800;
    line-height:1.06;letter-spacing:-1.5px;
    color:var(--text);margin-bottom:20px;
    animation:fadeUp .5s .08s ease both;
    width:100%;
}
.hero-h1 .g-text{
    background:linear-gradient(135deg,var(--primary) 0%,var(--purple) 55%,var(--cyan) 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    display:block;
}
.hero-h1 .u-word{position:relative;display:inline-block}
.hero-h1 .u-word::after{
    content:'';position:absolute;bottom:4px;left:0;width:100%;height:4px;
    background:linear-gradient(90deg,var(--warning),transparent);border-radius:2px;
}

.hero-sub{
    font-size:clamp(14px,1.8vw,17px);color:var(--muted);
    max-width:540px;line-height:1.8;margin-bottom:40px;
    animation:fadeUp .5s .14s ease both;
    width:100%;
}

/* ════ STATS BAR ════ */
.stats-bar{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--r);
    overflow:hidden;
    margin-bottom:48px;
    animation:fadeUp .5s .2s ease both;
    box-shadow:var(--shadow);
    width:100%;max-width:720px;
    transition:background .3s,border-color .3s;
}
.s-item{
    padding:20px 16px;
    text-align:center;
    border-right:1px solid var(--border);
    position:relative;
    transition:background .2s;
}
.s-item:last-child{border-right:none}
.s-item:hover{background:var(--surface2)}

/* FA icon circle above number */
.s-icon-wrap{
    width:40px;height:40px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 10px;font-size:17px;
    transition:transform .3s;
}
.s-item:hover .s-icon-wrap{transform:scale(1.1)}

.s-num{
    font-size:clamp(22px,3vw,30px);font-weight:800;
    color:var(--text);display:block;
    letter-spacing:-1px;line-height:1;margin-bottom:4px;
}
.s-suffix{font-size:18px;font-weight:800}
.s-lbl{font-size:10px;color:var(--dim);text-transform:uppercase;letter-spacing:.8px;font-weight:600}

/* ════ LOGIN CTA BUTTON ════ */
.login-cta-wrap{
    width:100%;max-width:320px;
    animation:fadeUp .5s .26s ease both;
}
.login-cta-btn{
    width:100%;padding:16px 24px;
    background:linear-gradient(135deg,var(--primary),var(--purple));
    border:none;border-radius:var(--r);
    color:#fff;font-family:'Plus Jakarta Sans',sans-serif;
    font-size:16px;font-weight:700;cursor:pointer;
    display:flex;align-items:center;justify-content:center;gap:12px;
    transition:all .2s;letter-spacing:.5px;
    text-decoration:none;
    box-shadow:0 8px 24px rgba(59,130,246,.4);
}
.login-cta-btn:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(59,130,246,.5);color:#fff}
.login-cta-btn:active{transform:translateY(0)}
.login-cta-btn i{font-size:18px}

/* ════ SECTION STYLES ════ */
.content-section{position:relative;z-index:1;width:100%}
.sec{padding: 88px 32px 40px;max-width:1200px;margin:0 auto}
.sec-badge{
    display:inline-flex;align-items:center;gap:6px;
    background:var(--primary-l);border:1px solid rgba(59,130,246,.3);
    border-radius:20px;padding:5px 14px;
    font-size:11px;font-weight:700;color:var(--primary);
    margin-bottom:12px;letter-spacing:.4px;text-transform:uppercase;
}
.sec-h2{
    font-size:clamp(26px,3.5vw,42px);font-weight:800;
    letter-spacing:-.8px;line-height:1.1;color:var(--text);margin-bottom:10px;
}
.sec-p{font-size:15px;color:var(--muted);max-width:480px;line-height:1.75;margin-bottom:44px}
.sec-center{text-align:center;max-width:520px;margin:0 auto 44px}
.sec-center .sec-p{margin:0 auto 0}

.sec-divider{
    position:relative;z-index:1;
    height:1px;background:var(--border);
    margin:0;
}

/* ════ FEATURE CARDS ════ */
.feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.fcard{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--r);padding:24px;
    transition:all .3s;position:relative;overflow:hidden;
}
.fcard::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;transition:opacity .3s}
.fcard:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.3)}
.fcard:hover::before{opacity:1}
.fcard.c-blue::before{background:linear-gradient(90deg,var(--primary),var(--cyan))}
.fcard.c-green::before{background:linear-gradient(90deg,var(--success),var(--cyan))}
.fcard.c-amber::before{background:linear-gradient(90deg,var(--warning),var(--success))}
.fcard.c-purple::before{background:linear-gradient(90deg,var(--purple),var(--primary))}
.fcard.c-cyan::before{background:linear-gradient(90deg,var(--cyan),var(--primary))}
.fcard.c-red::before{background:linear-gradient(90deg,var(--danger),var(--purple))}
.fico{width:46px;height:46px;border-radius:var(--rs);display:flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:14px}
.fico.blue{background:var(--primary-l);color:var(--primary)}
.fico.green{background:var(--success-l);color:var(--success)}
.fico.amber{background:var(--warning-l);color:var(--warning)}
.fico.purple{background:var(--purple-l);color:var(--purple)}
.fico.cyan{background:var(--cyan-l);color:var(--cyan)}
.fico.red{background:var(--danger-l);color:var(--danger)}
.fcard h3{font-size:15px;font-weight:700;color:var(--text);margin-bottom:7px;letter-spacing:-.2px}
.fcard p{font-size:13px;color:var(--muted);line-height:1.7}

/* ════ ROLE CARDS ════ */
.roles-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.rcard{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:28px;text-align:center;transition:all .3s}
.rcard:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.3)}
.rcard-ico{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px}
.rcard h3{font-size:18px;font-weight:800;color:var(--text);margin-bottom:6px;letter-spacing:-.3px}
.rcard>.rp{font-size:13px;color:var(--muted);margin-bottom:16px;line-height:1.6}
.plist{text-align:left;display:flex;flex-direction:column;gap:7px}
.pi{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.pi i{font-size:10px;flex-shrink:0}

/* ════ STORY CARDS ════ */
.story-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.scard{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:26px;transition:transform .3s}
.scard:hover{transform:translateY(-4px)}
.stars{color:var(--warning);font-size:13px;margin-bottom:12px;letter-spacing:3px}
.stxt{font-size:13px;color:var(--muted);line-height:1.85;margin-bottom:18px;font-style:italic}
.stxt::before{content:'"';font-size:34px;color:var(--primary);line-height:0;vertical-align:-13px;margin-right:2px;font-style:normal;font-weight:800}
.sauth{display:flex;align-items:center;gap:11px;border-top:1px solid var(--border);padding-top:14px}
.savatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;color:#fff;flex-shrink:0}
.sname{font-weight:700;font-size:13px;color:var(--text)}
.srole{font-size:11px;color:var(--dim);margin-top:2px}


/* ════ FOOTER ════ */
footer {
    position: relative;
    z-index: 1;

    width: 100%;
    margin: 0;              /* remove extra outer space */
    padding: 20px 40px;     /* control inner spacing */

    background: var(--surface);
    border-top: 1px solid var(--border);

    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;

    transition: background .3s, border-color .3s;
}
.foot-brand{display:flex;align-items:center;gap:9px;text-decoration:none}
.foot-ico{width:30px;height:30px;background:linear-gradient(135deg,var(--primary),var(--purple));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;color:#fff}
.foot-name{font-size:15px;font-weight:800;color:var(--text)}
.foot-name span{color:var(--primary)}
.foot-copy{font-size:12px;color:var(--dim)}
.foot-links{display:flex;gap:20px;flex-wrap:wrap}
.foot-links a{font-size:12px;color:var(--muted);text-decoration:none;font-weight:500;transition:color .2s}
.foot-links a:hover{color:var(--primary)}

/* ════ ANIMATIONS ════ */
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.reveal{opacity:0;transform:translateY(26px);transition:opacity .6s ease,transform .6s ease}
.reveal.vis{opacity:1;transform:translateY(0)}
.d1{transition-delay:.07s}.d2{transition-delay:.14s}.d3{transition-delay:.21s}

/* ════════════════════════════════════════════
   MEDIA QUERIES — MOBILE FIRST
════════════════════════════════════════════ */

/* Large desktop */
@media(min-width:1280px){
    .sec{padding:96px 60px}
}

/* Tablet & Small Desktop */
@media(max-width:1024px){
    .nb-links{display:none}
    .feat-grid{grid-template-columns:repeat(2,1fr)}
    .roles-grid{grid-template-columns:repeat(2,1fr)}
    .story-grid{grid-template-columns:repeat(2,1fr)}
}

/* Tablet Portrait */
@media(max-width:768px){
    .navbar{padding:0 16px;height:60px}
    .nb-name{font-size:14px}
    .nb-cta span{display:none}
    .hero{padding:80px 16px 48px}
    .hero-h1{letter-spacing:-.8px}
    .stats-bar{
        grid-template-columns:repeat(2,1fr);
    }
    .s-item{border-right:none;border-bottom:1px solid var(--border)}
    .s-item:nth-child(odd){border-right:1px solid var(--border)}
    .s-item:nth-last-child(-n+2){border-bottom:none}
    .sec{padding:60px 20px}
    .feat-grid{grid-template-columns:1fr}
    .roles-grid{grid-template-columns:1fr}
    .story-grid{grid-template-columns:1fr}
    footer{flex-direction:column;text-align:center;padding:24px 20px}
    .foot-links{justify-content:center}
}

/* Mobile */
@media(max-width:480px){
    .navbar{padding:0 14px;height:58px}
    .nb-name{font-size:13px}
    .nb-cta{padding:7px 12px;font-size:12px}
    .hero{padding:76px 14px 44px}
    .hero-badge{font-size:11px;padding:6px 14px}
    .stats-bar{
        grid-template-columns:repeat(2,1fr);
        max-width:100%;
    }
    .s-icon-wrap{width:34px;height:34px;font-size:14px}
    .s-num{font-size:22px}
    .sec{padding:48px 16px}
    .sec-h2{letter-spacing:-.5px}
    footer{padding:20px 16px}
    .foot-links{gap:14px}
    .login-cta-wrap{max-width:280px}
    .login-cta-btn{padding:14px 20px;font-size:14px}
}

/* Very small phones */
@media(max-width:360px){
    .hero-h1{font-size:28px;letter-spacing:-.5px}
    .stats-bar{grid-template-columns:1fr}
    .s-item{border-right:none !important}
    .s-item:not(:last-child){border-bottom:1px solid var(--border)}
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
    <a href="#" class="nb-logo">
        <div class="nb-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="nb-name">Edu<span>Manage</span></div>
    </a>
    <div class="nb-right">
        <div class="nb-links">
            <a href="#features">Features</a>
            <a href="#roles">Roles</a>
            <a href="#stories">Stories</a>
        </div>
        <!-- Theme Toggle -->
        <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme" id="themeBtn">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <a href="auth/login.php" class="nb-cta">
            <i class="fas fa-sign-in-alt"></i>
            <span>Login</span>
        </a>
    </div>
</nav>

<!-- ════ HERO ════ -->
<section class="hero" id="home">

    <div class="hero-badge">
        <div class="badge-dot"></div>
        Pakistan's Smart School Management Platform
    </div>

    <h1 class="hero-h1">
        Manage Your School<br>
        <span class="g-text">Smarter, Faster &</span>
        <span class="u-word">Efficiently</span>
    </h1>

    <p class="hero-sub">
        A complete digital solution for schools — track students, teachers,
        attendance and grades in one secure platform. Built for Pakistani schools.
    </p>

    <!-- ════ LIVE STATS — COUNT-UP ════ -->
    <div class="stats-bar">
        <div class="s-item">
            <div class="s-icon-wrap" style="background:var(--primary-l);color:var(--primary)">
                <i class="fas fa-user-graduate"></i>
            </div>
            <span class="s-num">
                <span class="cnt" data-to="<?= $totalStudents ?>" data-suffix="+">0</span><span class="s-suffix">+</span>
            </span>
            <div class="s-lbl">Students Enrolled</div>
        </div>
        <div class="s-item">
            <div class="s-icon-wrap" style="background:var(--success-l);color:var(--success)">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <span class="s-num">
                <span class="cnt" data-to="<?= $totalTeachers ?>">0</span><span class="s-suffix">+</span>
            </span>
            <div class="s-lbl">Active Teachers</div>
        </div>
        <div class="s-item">
            <div class="s-icon-wrap" style="background:var(--warning-l);color:var(--warning)">
                <i class="fas fa-school"></i>
            </div>
            <span class="s-num">
                <span class="cnt" data-to="<?= $totalClasses ?>">0</span><span class="s-suffix">+</span>
            </span>
            <div class="s-lbl">Classes Running</div>
        </div>
        <div class="s-item">
            <div class="s-icon-wrap" style="background:var(--purple-l);color:var(--purple)">
                <i class="fas fa-book-open"></i>
            </div>
            <span class="s-num">
                <span class="cnt" data-to="<?= $totalSubjects ?>">0</span><span class="s-suffix">+</span>
            </span>
            <div class="s-lbl">Subjects Offered</div>
        </div>
    </div>

    <!-- ════ LOGIN CTA BUTTON ════ -->
    
<!-- ════ FEATURES ════ -->
<section class="content-section" id="features">
    <div class="sec">
        <div class="reveal">
            <div class="sec-badge"><i class="fas fa-bolt"></i> Core Features</div>
            <h2 class="sec-h2">Everything Your School Needs</h2>
            <div class="mypara"><p class="sec-p">Built for Pakistani schools — powerful to manage, simple for everyone to use.</p>
       </div>
         </div>
        <div class="feat-grid">
            <div class="fcard c-blue reveal d1">
                <div class="fico blue"><i class="fas fa-user-graduate"></i></div>
                <h3>Student Management</h3>
                <p>Add, edit, enroll students into classes. Track full profiles, contacts, roll numbers and academic history all in one place.</p>
            </div>
            <div class="fcard c-green reveal d2">
                <div class="fico green"><i class="fas fa-calendar-check"></i></div>
                <h3>Attendance Tracking</h3>
                <p>Teachers mark daily attendance per subject per class. Instant subject-wise percentage reports with one-click CSV export.</p>
            </div>
            <div class="fcard c-amber reveal d3">
                <div class="fico amber"><i class="fas fa-star"></i></div>
                <h3>Grade Management</h3>
                <p>Enter marks per subject, per term. Auto-calculates A+/A/B/C grades. Students view full report cards instantly.</p>
            </div>
            <div class="fcard c-purple reveal d1">
                <div class="fico purple"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3>Teacher Assignments</h3>
                <p>Assign teachers to specific classes and subjects. Each teacher sees only their own students — zero data overlap.</p>
            </div>
            <div class="fcard c-cyan reveal d2">
                <div class="fico cyan"><i class="fas fa-file-csv"></i></div>
                <h3>CSV Export Everywhere</h3>
                <p>Export any table — students, attendance, grades — to CSV with one click. Perfect for offline records and parent reports.</p>
            </div>
            <div class="fcard c-red reveal d3">
                <div class="fico red"><i class="fas fa-shield-alt"></i></div>
                <h3>Role-Based Security</h3>
                <p>Admin, Teacher, Student each see only what they need. Prepared statements and session checks protect every page.</p>
            </div>
        </div>
    </div>
</section>

<div class="sec-divider"></div>

<!-- ════ ROLES ════ -->
<section class="content-section" id="roles">
    <div class="sec">
        <div class="reveal sec-center">
            <div class="sec-badge"><i class="fas fa-users"></i> User Roles</div>
            <h2 class="sec-h2">Three Roles, Clear Purpose</h2>
            <p class="sec-p">Every user sees exactly what they need. Nothing more, nothing less.</p>
        </div>
        <div class="roles-grid">
            <div class="rcard reveal d1">
                <div class="rcard-ico" style="background:var(--danger-l);color:var(--danger)"><i class="fas fa-crown"></i></div>
                <h3>Admin</h3>
                <p class="rp">Full system control — manage everything from one dashboard.</p>
                <div class="plist">
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Add/Edit/Delete Students & Teachers</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Create Classes & Subjects</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Assign Teachers to Classes</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> View All Attendance & Grades</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Activity Logs & Announcements</div>
                </div>
            </div>
            <div class="rcard reveal d2">
                <div class="rcard-ico" style="background:var(--success-l);color:var(--success)"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3>Teacher</h3>
                <p class="rp">Sees only their own assigned classes and students.</p>
                <div class="plist">
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> View Only Assigned Classes</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Mark Attendance Per Subject</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> Enter & Update Grades</div>
                    <div class="pi"><i class="fas fa-times" style="color:var(--danger)"></i> Cannot See Other Teachers' Data</div>
                    <div class="pi"><i class="fas fa-times" style="color:var(--danger)"></i> Cannot Change System Settings</div>
                </div>
            </div>
            <div class="rcard reveal d3">
                <div class="rcard-ico" style="background:var(--primary-l);color:var(--primary)"><i class="fas fa-user-graduate"></i></div>
                <h3>Student</h3>
                <p class="rp">Read-only access to their own data only.</p>
                <div class="plist">
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> View Personal Profile</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> View Own Attendance %</div>
                    <div class="pi"><i class="fas fa-check" style="color:var(--success)"></i> View Own Grades & Reports</div>
                    <div class="pi"><i class="fas fa-times" style="color:var(--danger)"></i> Cannot Edit Any Data</div>
                    <div class="pi"><i class="fas fa-times" style="color:var(--danger)"></i> Cannot View Other Students</div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="sec-divider"></div>

<!-- ════ SUCCESS STORIES ════ -->
<section class="content-section" id="stories">
    <div class="sec">
        <div class="reveal sec-center" style="max-width:520px;margin:0 auto 44px">
            <div class="sec-badge" style="background:var(--purple-l);border-color:rgba(139,92,246,.3);color:var(--purple)">
                <i class="fas fa-heart"></i> Success Stories
            </div>
            <h2 class="sec-h2">What Schools Are Saying</h2>
            <p class="sec-p" style="margin:0 auto">Real feedback from principals, teachers and students using EduManage.</p>
        </div>
        <div class="story-grid">
            <div class="scard reveal d1">
                <div class="stars">★★★★★</div>
                <p class="stxt">EduManage transformed how we run our school. Attendance used to take 20 minutes per class — teachers are now done in under 2 minutes.</p>
                <div class="sauth">
                    <div class="savatar" style="background:linear-gradient(135deg,var(--warning),var(--danger))">Z</div>
                    <div><div class="sname">Zulfiqar Ahmed</div><div class="srole">Principal, Lahore Grammar School</div></div>
                </div>
            </div>
            <div class="scard reveal d2">
                <div class="stars">★★★★★</div>
                <p class="stxt">As a teacher I love that I only see my own students. No confusion, no mistakes. Grade entry is clean and CSV export saves hours every week.</p>
                <div class="sauth">
                    <div class="savatar" style="background:linear-gradient(135deg,var(--success),var(--cyan))">S</div>
                    <div><div class="sname">Sara Imran</div><div class="srole">Mathematics Teacher, Class 9–10</div></div>
                </div>
            </div>
            <div class="scard reveal d3">
                <div class="stars">★★★★★</div>
                <p class="stxt">I check my attendance and grades from my phone anytime. My parents feel connected seeing my progress. Best system our school has ever used.</p>
                <div class="sauth">
                    <div class="savatar" style="background:linear-gradient(135deg,var(--primary),var(--purple))">A</div>
                    <div><div class="sname">Ahmed Raza</div><div class="srole">Student, Class 9-A</div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════ FOOTER ════ -->
<footer>
    <a href="#" class="foot-brand">
        <div class="foot-ico"><i class="fas fa-graduation-cap"></i></div>
        <div class="foot-name">Edu<span>Manage</span> SMS</div>
    </a>
    <div class="foot-copy">© <?= date('Y') ?> EduManage. Built for Pakistani Schools with ❤️</div>
    <div class="foot-links">
        <a href="#features">Features</a>
        <a href="#roles">Roles</a>
        <a href="#stories">Stories</a>
       <a href="auth/login.php">Login</a>
    </div>
</footer>

<script>
/* ── THEME TOGGLE ── */
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    const current = html.getAttribute('data-theme');
    if (current === 'dark') {
        html.setAttribute('data-theme', 'light');
        icon.classList.replace('fa-moon', 'fa-sun');
        localStorage.setItem('sms-landing-theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        icon.classList.replace('fa-sun', 'fa-moon');
        localStorage.setItem('sms-landing-theme', 'dark');
    }
}
// Restore saved theme
(function() {
    const saved = localStorage.getItem('sms-landing-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (saved === 'light' && icon) {
        icon.classList.replace('fa-moon', 'fa-sun');
    }
})();

/* ── COUNT-UP ANIMATION ── */
function runCountUp(el) {
    const target = parseInt(el.getAttribute('data-to')) || 0;
    if (!target) return;
    const duration = 1800;
    const steps    = 60;
    const interval = duration / steps;
    let step = 0;
    const ease = t => 1 - Math.pow(1 - t, 3);
    const timer = setInterval(() => {
        step++;
        const progress = ease(step / steps);
        const current  = Math.round(progress * target);
        el.textContent = current.toLocaleString();
        if (step >= steps) {
            el.textContent = target.toLocaleString();
            clearInterval(timer);
        }
    }, interval);
}

// Trigger count-up when stats bar enters viewport
const statsBar = document.querySelector('.stats-bar');
let countStarted = false;
const countObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting && !countStarted) {
            countStarted = true;
            document.querySelectorAll('.cnt[data-to]').forEach(runCountUp);
        }
    });
}, {threshold: 0.4});
if (statsBar) countObs.observe(statsBar);

/* ── SCROLL REVEAL ── */
const revObs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('vis'); });
}, {threshold: 0.08});
document.querySelectorAll('.reveal').forEach(el => revObs.observe(el));
</script>
</body>
</html>